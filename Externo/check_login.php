<?php
define('HOTSPOT_ACCESS', true);
require_once __DIR__ . '/config.php';

$host = $_SERVER['HTTP_HOST'] ?? 'redeslinkin.com.br';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . $host . '/hotspot';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acesso inválido.");
}

$cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
$mikrotik_url = $_POST['mikrotik_login_url'] ?? '';
$dst = $_POST['dst'] ?? '';
$lgpd = $_POST['lgpd_consent'] ?? '';

if (empty($lgpd) && empty($_POST['cpf'])) {
    header("Location: " . $baseUrl . "/login_page.php?error=CPF+inválido");
    exit;
}

if (empty($lgpd)) {
    header("Location: " . $baseUrl . "/login_page.php?error=Você+precisa+aceitar+a+Política+de+Privacidade");
    exit;
}

if (empty($cpf) || strlen($cpf) < 11) {
    header("Location: " . $baseUrl . "/login_page.php?error=CPF+inválido");
    exit;
}

$conn = getDbConnection();
if (!$conn) die("Erro de conexão.");

$stmt = $conn->prepare("SELECT id, status, mac, ip FROM dados WHERE cpf = ?");
$stmt->bind_param("s", $cpf);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    if ($row['status'] === 'bloqueado') {
        echo "<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding:50px;'><h3>Acesso Bloqueado</h3><p>Contate o administrador.</p></body></html>";
        exit;
    }

    $updateStmt = $conn->prepare("UPDATE dados SET last_login = NOW() WHERE cpf = ?");
    $updateStmt->bind_param("s", $cpf);
    $updateStmt->execute();
    $updateStmt->close();

    $usuarioId = $row['id'];
    $mac = $row['mac'] ?? '';
    $ip = $row['ip'] ?? '';
    $logStmt = $conn->prepare("INSERT INTO logs_conexao (usuario_id, cpf, mac, ip, data_conexao) VALUES (?, ?, ?, ?, NOW())");
    $logStmt->bind_param("isss", $usuarioId, $cpf, $mac, $ip);
    $logStmt->execute();
    $logStmt->close();

    // Autentica no MikroTik usando o CPF como usuário e senha
    $login_url = $mikrotik_url . "?username=" . urlencode($cpf) . "&password=" . urlencode($cpf);
    if ($dst) $login_url .= "&dst=" . urlencode($dst);

    header("Location: " . $login_url);
    exit;
} else {
    header("Location: " . $baseUrl . "/login_page.php?error=CPF+não+encontrado");
    exit;
}
?>
