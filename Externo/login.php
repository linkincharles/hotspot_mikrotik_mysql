<?php
define('HOTSPOT_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/validacpf.php';

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Acesso direto não permitido.";
    exit;
}

$mac = $_POST['mac'] ?? '';
$ip = $_POST['ip'] ?? '';
$linkloginonly = $_POST['link-login-only'] ?? '';
$linkorigesc = $_POST['link-orig-esc'] ?? '';
$macesc = $_POST['mac-esc'] ?? '';
$link_orig_val = $_POST['link-orig'] ?? '';

$cpf = trim($_POST['inputCpf'] ?? '');
$nome = trim($_POST['inputNome'] ?? '');
$sobrenome = trim($_POST['inputSobrenome'] ?? '');
$email = trim($_POST['inputEmail'] ?? '');
$telefone = trim($_POST['inputTelefone'] ?? '');
$lgpd = $_POST['lgpd_consent'] ?? '';

// Debug
file_put_contents('/tmp/debug_login.txt', print_r($_POST, true));

$erros = [];
if (empty($lgpd)) {
    $erros[] = "Você precisa aceitar a Política de Privacidade.";
}
if (!validaCPF($cpf)) $erros[] = "CPF inválido.";
if (strlen($nome) < 3 || strlen($nome) > 60 || !preg_match('/^[a-zA-ZÀ-ÿ\s]+$/', $nome)) $erros[] = "Nome inválido.";
if (strlen($sobrenome) < 3 || strlen($sobrenome) > 60 || !preg_match('/^[a-zA-ZÀ-ÿ\s]+$/', $sobrenome)) $erros[] = "Sobrenome inválido.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $erros[] = "Email inválido.";
if (strlen(preg_replace('/[^0-9]/', '', $telefone)) < 10 || strlen($telefone) > 20) $erros[] = "Telefone inválido.";

if (empty($erros)) {
    $conn = getDbConnection();
    if ($conn) {
        $stmt = $conn->prepare("SELECT id FROM dados WHERE cpf = ?");
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        $stmt->bind_param("s", $cpfLimpo);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $erros[] = "CPF já cadastrado. Faça o login.";
        } else {
            $stmt = $conn->prepare("INSERT INTO dados (cpf, nome, sobrenome, email, telefone, link_orig, mac, ip, data_cadastro, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'ativo')");
            $stmt->bind_param("ssssssss", $cpfLimpo, $nome, $sobrenome, $email, $telefone, $link_orig_val, $mac, $ip);

            if ($stmt->execute()) {
                // Tenta criar no MikroTik e pega o resultado detalhado
                $nomeCompleto = $nome . ' ' . $sobrenome;
                $mkResult = criarUsuarioMikrotik($cpfLimpo, $nomeCompleto);
                if ($mkResult === true) {
                    $mkStatus = "✅ Criado com sucesso";
                } else {
                    $mkStatus = "❌ Falha: $mkResult";
                }

                // Enviar notificação WhatsApp
                $whatsappResult = enviarNotificacaoWhatsApp($nomeCompleto, $cpfLimpo, $email, $telefone);
                
                // Enviar mensagem para o cliente
                $whatsappClientResult = enviarMensagemCliente($telefone, $nomeCompleto, $cpfLimpo, $email);

                $stmt->close();
                $conn->close();

                if ($linkloginonly) {
                    $host = $_SERVER['HTTP_HOST'] ?? 'redeslinkin.com.br';
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $loginPage = $scheme . '://' . $host . '/hotspot/login_page.php?link_login_only=' . urlencode($linkloginonly) . '&link_orig_esc=' . urlencode($linkorigesc);
                    $safeUrl = htmlspecialchars($loginPage, ENT_QUOTES, 'UTF-8');

                    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Sucesso</title></head><body style='font-family:sans-serif;text-align:center;padding:50px;background:#f3f4f6;'>";
                    echo "<h3>Cadastro realizado!</h3>";
                    echo "<p>Usuário e Senha: <b>$cpfLimpo</b></p>";
                    echo "<p style='font-size:12px;color:#666;'>Status MikroTik: $mkStatus</p>";
                    echo "<script>setTimeout(function(){ window.location.href = '$safeUrl'; }, 5000);</script>";
                    echo "<a href='$safeUrl' style='background:#667eea;color:#fff;padding:15px 30px;text-decoration:none;border-radius:8px;display:inline-block;margin-top:20px;font-weight:bold;'>IR PARA O LOGIN</a>";
                    echo "</body></html>";
                } else {
                    echo "Cadastro realizado! Status: $mkStatus";
                }
                exit;
            } else {
                $erros[] = "Erro ao salvar: " . $stmt->error;
            }
        }
        $stmt->close();
        $conn->close();
    } else {
        $erros[] = "Erro de conexão com o banco.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 20px; background: #f3f4f6; }
        .error-box { background: white; padding: 20px; border-radius: 10px; max-width: 400px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .error-box h2 { color: #dc3545; }
        .error-box ul { text-align: left; list-style: none; padding: 0; }
        .error-box li { color: #dc3545; padding: 5px 0; border-bottom: 1px solid #eee; }
        .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="error-box">
        <h2>Corrija os seguintes erros:</h2>
        <ul>
            <?php foreach ($erros as $erro): ?>
                <li><?= htmlspecialchars($erro) ?></li>
            <?php endforeach; ?>
        </ul>
        <a href="javascript:history.back()" class="btn">Voltar</a>
    </div>
</body>
</html>

<?php
function criarUsuarioMikrotik($cpf, $nome = '') {
    $mkIp = getConfigValue('mikrotik_ip', 'hotspot');
    $mkPass = getConfigValue('mikrotik_api_pass', 'hotspot');
    $mkUser = getConfigValue('mikrotik_api_user', 'hotspot') ?: 'admin';

    // Debug info
    $debugLog = "MK Debug - IP: $mkIp, User: $mkUser, Pass len: " . strlen($mkPass) . "\n";
    @file_put_contents(__DIR__ . '/debug_mk.txt', $debugLog, FILE_APPEND);

    if (!$mkIp || !$mkPass) {
        return "IP ou Senha não configurados. IP: $mkIp, User: $mkUser";
    }

    $socket = @fsockopen($mkIp, 8728, $errno, $errstr, 5);
    if (!$socket) return "Erro de conexão: $errstr ($errno)";
    stream_set_timeout($socket, 5);

    $sendWord = function($word) use ($socket) {
        $len = strlen($word);
        if ($len < 0x80) fwrite($socket, chr($len));
        elseif ($len < 0x4000) fwrite($socket, chr(($len >> 8) | 0x80) . chr($len & 0xFF));
        elseif ($len < 0x200000) fwrite($socket, chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        else fwrite($socket, chr(($len >> 24) | 0xE0) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        fwrite($socket, $word);
    };

    $readWord = function() use ($socket) {
        $byte = fgetc($socket);
        if ($byte === false) return false;
        $byte = ord($byte);
        $len = 0;
        if (($byte & 0x80) == 0) $len = $byte;
        elseif (($byte & 0xC0) == 0x80) $len = (($byte & 0x3F) << 8) + ord(fgetc($socket));
        elseif (($byte & 0xE0) == 0xC0) $len = (($byte & 0x1F) << 16) + (ord(fgetc($socket)) << 8) + ord(fgetc($socket));
        elseif (($byte & 0xF0) == 0xE0) $len = (($byte & 0x0F) << 24) + (ord(fgetc($socket)) << 16) + (ord(fgetc($socket)) << 8) + ord(fgetc($socket));
        elseif (($byte & 0xF8) == 0xF0) { fgetc($socket); $len = (ord(fgetc($socket)) << 24) + (ord(fgetc($socket)) << 16) + (ord(fgetc($socket)) << 8) + ord(fgetc($socket)); }
        if ($len == 0) return '';
        $word = '';
        for ($i = 0; $i < $len; $i++) $word .= fgetc($socket);
        return $word;
    };

    // 1. Login Challenge
    $sendWord('/login');
    $sendWord('');

    $challenge = '';
    while (true) {
        $w = $readWord();
        if ($w === false) return "Erro de leitura (Conexão fechada)";
        if ($w === '!fatal' || $w === '!trap') return "Erro no Router (Trap/Fatal)";
        if ($w === '!done') break;
        if (strpos($w, '=ret=') === 0) $challenge = substr($w, 5);
    }
    
    // RouterOS v7: método direto com =password=
    $mkPassUtf8 = mb_convert_encoding($mkPass, 'UTF-8', 'UTF-8');
    $sendWord('/login');
    $sendWord('=name=' . $mkUser);
    $sendWord('=password=' . $mkPassUtf8);
    $sendWord('');

    $loggedIn = false;
    while (true) {
        $w = $readWord();
        if ($w === false) return "Erro de leitura após login";
        if ($w === '!fatal' || $w === '!trap') return "Login falhou (Verifique Usuário/Senha)";
        if ($w === '!done') { $loggedIn = true; break; }
    }
    if (!$loggedIn) return "Login falhou";

    // 3. Criar Usuário
    $sendWord('/ip/hotspot/user/add');
    $sendWord('=name=' . $cpf);
    $sendWord('=password=' . $cpf);
    $sendWord('=profile=default');
    if (!empty($nome)) {
        $sendWord('=comment=' . $nome);
    }
    $sendWord('');

    while (true) {
        $w = $readWord();
        if ($w === false) return "Erro de leitura ao criar usuário";
        if ($w === '!trap') return "Erro ao criar (Verifique se o perfil 'default' existe)";
        if ($w === '!fatal') return "Erro Fatal ao criar";
        if ($w === '!done') break;
    }
    
    fclose($socket);
    return true;
}

function enviarNotificacaoWhatsApp($nome, $cpf, $email, $telefone) {
    $apiUrl = getConfigValue('evolution_api_url', 'whatsapp');
    $instanceName = getConfigValue('evolution_instance_name', 'whatsapp');
    $apiKey = getConfigValue('evolution_api_key', 'whatsapp');
    $enabled = getConfigValue('evolution_notify_enabled', 'whatsapp');
    $msgType = getConfigValue('evolution_notify_type', 'whatsapp');
    $template = getConfigValue('evolution_notify_template', 'whatsapp');
    $numbers = getConfigValue('evolution_notify_numbers', 'whatsapp');
    $mediaUrl = getConfigValue('evolution_notify_media_url', 'whatsapp');

    $debugLog = "Notify - enabled: $enabled, apiUrl: $apiUrl, numbers: $numbers\n";
    @file_put_contents(__DIR__ . '/debug_whatsapp.txt', $debugLog, FILE_APPEND);

    if ($enabled !== 'true' || empty($apiUrl) || empty($instanceName) || empty($apiKey) || empty($numbers)) {
        return "Notificações desativadas ou configuração incompleta";
    }

    // Substituir variáveis no template
    $mensagem = str_replace(['{nome}', '{cpf}', '{email}', '{telefone}'], [$nome, $cpf, $email, $telefone], $template);

    // Separar números e adicionar DDI 55
    $numeros = array_map('trim', explode(',', $numbers));
    $results = [];

    foreach ($numeros as $numero) {
        if (empty($numero)) continue;

        $numero = preg_replace('/[^0-9]/', '', $numero);
        if (strlen($numero) >= 10 && strlen($numero) <= 11) {
            $numero = '55' . $numero;
        }
        if ($msgType === 'image' && !empty($mediaUrl)) {
            $payload = [
                'number' => $numero,
                'mediaUrl' => $mediaUrl,
                'caption' => $mensagem
            ];
            $endpoint = $apiUrl . '/message/sendImage/' . $instanceName;
        } else {
            $payload = [
                'number' => $numero,
                'text' => $mensagem
            ];
            $endpoint = $apiUrl . '/message/sendText/' . $instanceName;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $results[] = "Número $numero: HTTP $httpCode";
    }

    return implode(', ', $results);
}

function enviarMensagemCliente($telefone, $nome, $cpf, $email) {
    $configFile = __DIR__ . '/config.ini';
    $debugLog = "=== Nova tentativa ===\n";
    
    if (!file_exists($configFile)) {
        $debugLog .= "Arquivo config.ini não encontrado!\n";
        @file_put_contents(__DIR__ . '/debug_whatsapp.txt', $debugLog, FILE_APPEND);
        return "Configuração não encontrada";
    }
    
    // Ler diretamente do arquivo para debug
    $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $whatsappConfig = [];
    $currentSection = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^\[(.*)\]$/', $line, $matches)) {
            $currentSection = $matches[1];
            continue;
        }
        if ($currentSection === 'whatsapp' && strpos($line, 'evolution_client_enabled') !== false) {
            $debugLog .= "Linha cliente_enabled: $line\n";
        }
    }
    
    $apiUrl = getConfigValue('evolution_api_url', 'whatsapp');
    $instanceName = getConfigValue('evolution_instance_name', 'whatsapp');
    $apiKey = getConfigValue('evolution_api_key', 'whatsapp');
    $enabled = getConfigValue('evolution_client_enabled', 'whatsapp');
    $msgType = getConfigValue('evolution_client_type', 'whatsapp');
    $template = getConfigValue('evolution_client_template', 'whatsapp');
    $mediaUrl = getConfigValue('evolution_client_media_url', 'whatsapp');

    $debugLog .= "Cliente - enabled: '$enabled' (type: " . gettype($enabled) . "), apiUrl: $apiUrl, instance: $instanceName, telefone: $telefone\n";
    $debugLog .= "All whatsapp config: apiUrl=$apiUrl, instance=$instanceName, key=" . substr($apiKey ?? '', 0, 5) . "..., enabled=$enabled, type=$msgType\n";
    @file_put_contents(__DIR__ . '/debug_whatsapp.txt', $debugLog, FILE_APPEND);

    if ($enabled !== 'true' || empty($apiUrl) || empty($instanceName) || empty($apiKey) || empty($telefone)) {
        @file_put_contents(__DIR__ . '/debug_whatsapp.txt', "Erro: enabled='$enabled' (expected 'true'), telefone empty=" . empty($telefone) . "\n", FILE_APPEND);
        return "Mensagem para cliente desativada ou incompleta";
    }

    // Limpar telefone
    $numero = preg_replace('/[^0-9]/', '', $telefone);
    
    // Adicionar DDI 55 (Brasil) se não tiver
    if (strlen($numero) >= 10 && strlen($numero) <= 11) {
        $numero = '55' . $numero;
    }
    
    if (strlen($numero) < 12) {
        return "Telefone inválido";
    }

    // Substituir variáveis no template
    $mensagem = str_replace(['{nome}', '{cpf}', '{email}', '{telefone}'], [$nome, $cpf, $email, $telefone], $template);

    if ($msgType === 'image' && !empty($mediaUrl)) {
        $payload = [
            'number' => $numero,
            'mediaUrl' => $mediaUrl,
            'caption' => $mensagem
        ];
        $endpoint = $apiUrl . '/message/sendImage/' . $instanceName;
    } else {
        $payload = [
            'number' => $numero,
            'text' => $mensagem
        ];
        $endpoint = $apiUrl . '/message/sendText/' . $instanceName;
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    @file_put_contents(__DIR__ . '/debug_whatsapp.txt', "Response: HTTP $httpCode, Error: $curlError, Body: $response\n", FILE_APPEND);

    return "Cliente $numero: HTTP $httpCode";
}
?>
