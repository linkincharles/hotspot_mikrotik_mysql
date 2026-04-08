<?php
define('HOTSPOT_ACCESS', true);
require_once __DIR__ . '/config.php';

$link_login_only = $_GET['link_login_only'] ?? '';
$link_orig_esc = $_GET['link_orig_esc'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hotspot</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .login-box {
            background: white; border-radius: 16px; padding: 30px; max-width: 400px; width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2); text-align: center;
        }
        .login-box i { font-size: 48px; color: #667eea; margin-bottom: 15px; }
        .login-box h2 { font-size: 20px; color: #333; margin-bottom: 10px; }
        .login-box p { color: #666; font-size: 14px; margin-bottom: 20px; }
        .input-wrapper { position: relative; margin-bottom: 15px; }
        .input-wrapper input {
            width: 100%; padding: 14px 14px 14px 44px; border: 2px solid #e1e5e9; border-radius: 10px;
            font-size: 14px; font-family: 'Inter', sans-serif;
        }
        .input-wrapper input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .input-wrapper i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #999; }
        .btn {
            display: block; width: 100%; padding: 14px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif;
        }
        .btn:hover { opacity: 0.9; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .lgpd { text-align: left; font-size: 11px; color: #666; margin: 15px 0; padding: 10px; background: #f9fafb; border-radius: 8px; }
        .lgpd label { display: flex; align-items: flex-start; gap: 8px; cursor: pointer; }
        .lgpd input { margin-top: 3px; }
        .lgpd a { color: #667eea; text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-box">
        <i class="fas fa-wifi"></i>
        <h2>Acessar Internet</h2>
        <p>Digite seu CPF para se conectar</p>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>
        
        <form action="check_login.php" method="POST">
            <input type="hidden" name="mikrotik_login_url" value="<?= htmlspecialchars($link_login_only, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="dst" value="<?= htmlspecialchars($link_orig_esc, ENT_QUOTES, 'UTF-8') ?>">
            <div class="input-wrapper">
                <input type="text" name="cpf" placeholder="000.000.000-00" required autofocus>
                <i class="fas fa-id-card"></i>
            </div>
            <div class="lgpd">
                <label>
                    <input type="checkbox" name="lgpd_consent" required>
                    <span>Autorizo o uso dos meus dados para fins de autenticação e conexão à internet. Concordo com a <a href="#" onclick="alert('Política de Privacidade:\\n\\nOs dados fornecidos (CPF, nome, email, telefone) são utilizados exclusivamente para controle de acesso à internet via Hotspot.\\n\\nNão compartilhamos seus dados com terceiros.\\n\\nOs dados são armazenados de forma segura e podem ser excluídos mediante solicitação.'); return false;">Política de Privacidade</a> e estou ciente de que posso solicitar a exclusão dos meus dados a qualquer momento.');</span>
                </label>
            </div>
            <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Conectar</button>
        </form>
    </div>
</body>
</html>
