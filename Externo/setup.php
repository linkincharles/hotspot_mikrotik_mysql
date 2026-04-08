<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$configFile = __DIR__ . '/config.ini';
$setupComplete = file_exists($configFile);

if ($setupComplete && !isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php?action=login');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
    $db_port = trim($_POST['db_port'] ?? '3306');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = trim($_POST['db_name'] ?? 'hotspot');
    $hotspot_name = trim($_POST['hotspot_name'] ?? 'Charles WiFi');
    $hotspot_logo = trim($_POST['hotspot_logo'] ?? 'logo.png');
    $hotspot_bg_image = trim($_POST['hotspot_bg_image'] ?? 'bg.jpg');
    $hotspot_primary_color = trim($_POST['hotspot_primary_color'] ?? '#667eea');
    $hotspot_secondary_color = trim($_POST['hotspot_secondary_color'] ?? '#764ba2');
    $external_login_url = trim($_POST['external_login_url'] ?? 'https://hotspot.redeslinkin.com.br/login.php');
    $admin_user = trim($_POST['admin_user'] ?? 'admin');
    $admin_pass = '';

    if (!empty($_POST['admin_pass'])) {
        $admin_pass = password_hash(trim($_POST['admin_pass']), PASSWORD_DEFAULT);
    } elseif ($setupComplete) {
        $oldConfig = parse_ini_file($configFile, true);
        $admin_pass = $oldConfig['admin']['admin_pass'] ?? '';
    }

    try {
        $mysqli = new mysqli($db_host, $db_user, $db_pass, '', intval($db_port));

        if ($mysqli->connect_error) {
            $error = 'Erro ao conectar ao banco: ' . $mysqli->connect_error;
        } else {
            $mysqli->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $mysqli->select_db($db_name);

            if ($mysqli->error) {
                $error = "Erro ao selecionar o banco '$db_name'. Crie-o manualmente no painel da hospedagem.";
            } else {
                $sql = file_get_contents(__DIR__ . '/database.sql');
                $queries = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($queries as $query) {
                    if (!empty($query) && !preg_match('/^(CREATE DATABASE|USE)/i', $query)) {
                        $mysqli->query($query);
                    }
                }

                $iniContent = "; ============================================\n";
                $iniContent .= "; Configuração do Hotspot\n";
                $iniContent .= "; Gerado em: " . date('d/m/Y H:i:s') . "\n";
                $iniContent .= "; ============================================\n\n";
                $iniContent .= "[database]\n";
                $iniContent .= "db_host = \"$db_host\"\n";
                $iniContent .= "db_port = \"$db_port\"\n";
                $iniContent .= "db_user = \"$db_user\"\n";
                $iniContent .= "db_pass = \"$db_pass\"\n";
                $iniContent .= "db_name = \"$db_name\"\n\n";
                $iniContent .= "[hotspot]\n";
                $iniContent .= "hotspot_name = \"$hotspot_name\"\n";
                $iniContent .= "hotspot_logo = \"$hotspot_logo\"\n";
                $iniContent .= "hotspot_bg_image = \"$hotspot_bg_image\"\n";
                $iniContent .= "hotspot_primary_color = \"$hotspot_primary_color\"\n";
                $iniContent .= "hotspot_secondary_color = \"$hotspot_secondary_color\"\n";
                $iniContent .= "external_login_url = \"$external_login_url\"\n\n";
                $iniContent .= "[admin]\n";
                $iniContent .= "admin_user = \"$admin_user\"\n";
                $iniContent .= "admin_pass = \"$admin_pass\"\n";

                file_put_contents($configFile, $iniContent);

                $loginHtmlFile = __DIR__ . '/../Mikrotik/login.html';
                if (file_exists($loginHtmlFile)) {
                    $loginHtml = file_get_contents($loginHtmlFile);
                    $loginHtml = preg_replace('/<h1>.*?<\/h1>/', '<h1>' . htmlspecialchars($hotspot_name) . '</h1>', $loginHtml, 1);
                    $loginHtml = preg_replace('/action="[^"]*login\.php"/', 'action="' . htmlspecialchars($external_login_url) . '"', $loginHtml, 1);
                    file_put_contents($loginHtmlFile, $loginHtml);
                }

                $success = 'Configuração salva com sucesso!';
                $setupComplete = true;
            }
            $mysqli->close();
        }
    } catch (Exception $e) {
        $error = 'Erro: ' . $e->getMessage();
    }
}

$currentConfig = [];
if ($setupComplete) {
    $rawConfig = parse_ini_file($configFile, true);
    if (is_array($rawConfig)) {
        foreach ($rawConfig as $section => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $val) {
                    $currentConfig[$key] = $val;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração - Hotspot</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .setup-container { background: white; border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.25); max-width: 600px; width: 100%; overflow: hidden; }
        .setup-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .setup-header i { font-size: 48px; margin-bottom: 15px; opacity: 0.9; }
        .setup-header h1 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .setup-header p { font-size: 14px; opacity: 0.85; }
        .setup-body { padding: 30px; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #667eea; display: flex; align-items: center; gap: 8px; }
        .section-title i { color: #667eea; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e1e5e9; border-radius: 10px; font-size: 14px; font-family: 'Inter', sans-serif; }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .color-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .color-input { display: flex; align-items: center; gap: 10px; }
        .color-input input[type="color"] { width: 50px; height: 40px; border: none; border-radius: 8px; cursor: pointer; padding: 2px; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 28px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; width: 100%; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3); }
        .btn-secondary { background: #6c757d; color: white; margin-top: 10px; text-decoration: none; text-align: center; display: inline-flex; }
        .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-badge.configured { background: #d4edda; color: #155724; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        @media (max-width: 576px) { .form-row, .color-inputs { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <i class="fas fa-cog"></i>
            <h1>Configuração do Hotspot</h1>
            <p>Configure seu sistema de hotspot</p>
            <span class="status-badge <?= $setupComplete ? 'configured' : 'pending' ?>">
                <i class="fas fa-<?= $setupComplete ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= $setupComplete ? 'Configurado' : 'Pendente' ?>
            </span>
        </div>
        <div class="setup-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save">
                <div class="section">
                    <div class="section-title"><i class="fas fa-database"></i> Banco de Dados</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Host</label>
                            <input type="text" name="db_host" value="<?= htmlspecialchars($currentConfig['db_host'] ?? '127.0.0.1') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Porta</label>
                            <input type="text" name="db_port" value="<?= htmlspecialchars($currentConfig['db_port'] ?? '3306') ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Usuário</label>
                            <input type="text" name="db_user" value="<?= htmlspecialchars($currentConfig['db_user'] ?? 'root') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Senha</label>
                            <input type="password" name="db_pass" value="<?= htmlspecialchars($currentConfig['db_pass'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nome do Banco</label>
                        <input type="text" name="db_name" value="<?= htmlspecialchars($currentConfig['db_name'] ?? 'hotspot') ?>" required>
                    </div>
                </div>
                <div class="section">
                    <div class="section-title"><i class="fas fa-wifi"></i> Configurações do Hotspot</div>
                    <div class="form-group">
                        <label>Nome da Rede</label>
                        <input type="text" name="hotspot_name" value="<?= htmlspecialchars($currentConfig['hotspot_name'] ?? 'Charles WiFi') ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Logo (arquivo)</label>
                            <input type="text" name="hotspot_logo" value="<?= htmlspecialchars($currentConfig['hotspot_logo'] ?? 'logo.png') ?>">
                        </div>
                        <div class="form-group">
                            <label>Imagem de Fundo</label>
                            <input type="text" name="hotspot_bg_image" value="<?= htmlspecialchars($currentConfig['hotspot_bg_image'] ?? 'bg.jpg') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>URL Externa de Login</label>
                        <input type="url" name="external_login_url" value="<?= htmlspecialchars($currentConfig['external_login_url'] ?? 'https://hotspot.redeslinkin.com.br/login.php') ?>" required>
                    </div>
                    <div class="color-inputs">
                        <div class="form-group">
                            <label>Cor Primária</label>
                            <div class="color-input">
                                <input type="color" name="hotspot_primary_color" value="<?= htmlspecialchars($currentConfig['hotspot_primary_color'] ?? '#667eea') ?>">
                                <span>Primária</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Cor Secundária</label>
                            <div class="color-input">
                                <input type="color" name="hotspot_secondary_color" value="<?= htmlspecialchars($currentConfig['hotspot_secondary_color'] ?? '#764ba2') ?>">
                                <span>Secundária</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="section">
                    <div class="section-title"><i class="fas fa-user-shield"></i> Administrador</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Usuário Admin</label>
                            <input type="text" name="admin_user" value="<?= htmlspecialchars($currentConfig['admin_user'] ?? 'admin') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Senha Admin <?= $setupComplete ? '(deixe vazio para manter)' : '' ?></label>
                            <input type="password" name="admin_pass" <?= $setupComplete ? '' : 'required' ?>>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Configuração</button>
            </form>
            <?php if ($setupComplete): ?>
                <a href="admin.php" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Acessar Painel Admin</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
