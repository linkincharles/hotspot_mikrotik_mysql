<?php
// Habilita a exibição de erros para facilitar a depuração (remover ou comentar em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclui os arquivos necessários
include "db.php";
include "validacpf.php";

// Define o fuso horário
date_default_timezone_set('America/Sao_Paulo');

// VERIFICA SE A REQUISIÇÃO É DO TIPO POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Pega as variáveis do Mikrotik Hotspot (se existirem)
    $mac = isset($_POST['mac']) ? $_POST['mac'] : '';
    $ip = isset($_POST['ip']) ? $_POST['ip'] : '';
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $linklogin = isset($_POST['link-login']) ? $_POST['link-login'] : '';
    $linkorig = isset($_POST['link-orig']) ? $_POST['link-orig'] : '';
    $error = isset($_POST['error']) ? $_POST['error'] : '';
    $chapid = isset($_POST['chap-id']) ? $_POST['chap-id'] : '';
    $chapchallenge = isset($_POST['chap-challenge']) ? $_POST['chap-challenge'] : '';
    $linkloginonly = isset($_POST['link-login-only']) ? $_POST['link-login-only'] : '';
    $linkorigesc = isset($_POST['link-orig-esc']) ? $_POST['link-orig-esc'] : '';
    $macesc = isset($_POST['mac-esc']) ? $_POST['mac-esc'] : '';

    $valida = 0;
    $erros = []; // Array para guardar as mensagens de erro

    // Validações
    if (empty($_POST['inputCpf']) || validaCPF($_POST['inputCpf']) == false) {
        $erros[] = "CPF inválido.";
        $valida++;
    }
    if (empty($_POST['inputNome']) || strlen($_POST['inputNome']) < 3) {
        $erros[] = "Nome inválido.";
        $valida++;
    }
    if (empty($_POST['inputEmail']) || filter_var($_POST['inputEmail'], FILTER_VALIDATE_EMAIL) == false) {
        $erros[] = "Email inválido.";
        $valida++;
    }
    if (empty($_POST['inputSobrenome']) || strlen($_POST['inputSobrenome']) < 3) {
        $erros[] = "Sobrenome inválida.";
        $valida++;
    }
    if (empty($_POST['inputTelefone']) || strlen($_POST['inputTelefone']) < 14) { // Assumindo formato com máscara (xx) xxxxx-xxxx
        $erros[] = "Telefone inválido.";
        $valida++;
    }

    // Se não houver erros de validação, insere no banco
    if ($valida == 0) {
        // Verifica se a conexão com o banco de dados foi bem-sucedida
        if (isset($MySQLi) && $MySQLi->ping()) {
            
            // Usar prepared statements para segurança contra SQL Injection
            $stmt = $MySQLi->prepare("INSERT INTO dados (cpf, nome, email, sobrenome, telefone, link_orig, mac, ip, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Verifique se os nomes das colunas estão corretos na sua tabela 'dados'
            $cpf = $_POST['inputCpf'];
            $nome = $_POST['inputNome'];
            $email = $_POST['inputEmail'];
            $sobrenome = $_POST['inputSobrenome'];
            $telefone = $_POST['inputTelefone'];
            $data = date('Y-m-d H:i:s');
            
            $stmt->bind_param("sssssssss", $cpf, $nome, $email, $sobrenome, $telefone, $linkorig, $mac, $ip, $data);

            if ($stmt->execute()) {
                // Redireciona para a página de login do Hotspot
                echo "<script> window.location.href = '" . $linkloginonly . "?dst=" . $linkorigesc . "&username=T-" . $macesc . "'; </script>";
                exit(); // Encerra o script após o redirecionamento
            } else {
                echo "Erro ao inserir os dados: " . $stmt->error;
            }
            $stmt->close();

        } else {
            echo "Erro: Falha na conexão com o banco de dados. Verifique o arquivo db.php.";
        }

    } else {
        // Se houver erros, exibe-os
        echo "<h1>Por favor, corrija os seguintes erros:</h1>";
        foreach ($erros as $erro) {
            echo "<p style='color:red;'>- " . $erro . "</p>";
        }
    }
    
    // Fecha a conexão somente se ela foi aberta
    if (isset($MySQLi)) {
        $MySQLi->close();
    }
} else {
    // Se a página for acessada via GET, você pode exibir uma mensagem ou o formulário HTML
    // Como seu HTML está misturado, o ideal seria separá-lo.
    // Por enquanto, podemos apenas exibir uma mensagem de que o formulário deve ser enviado.
    // echo "Esta página deve ser acessada através do formulário de hotspot.";
}

?>
<html lang="pt-br">
<head>
    <meta charset="utf-t">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="css/bootstrap.css" rel="stylesheet">
    <link href="css/signin.css" rel="stylesheet">
</head>
<body class="text-center">
    <div class="container">
        </div>
</body>
</html>