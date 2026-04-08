<?php
$ip = '177.10.197.140';
$port = 8728;

echo "Conectando em $ip:$port ...<br>";
$socket = @fsockopen($ip, $port, $errno, $errstr, 5);

if (!$socket) {
    die("Falha na conexão: $errstr ($errno)");
}

echo "Conectado com sucesso!<br>";
stream_set_timeout($socket, 5);

// Função de envio
function sendWord($socket, $word) {
    $len = strlen($word);
    if ($len < 0x80) fwrite($socket, chr($len));
    elseif ($len < 0x4000) fwrite($socket, chr(($len >> 8) | 0x80) . chr($len & 0xFF));
    else fwrite($socket, chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
    fwrite($socket, $word);
}

// Envia /login
echo "Enviando /login...<br>";
sendWord($socket, '/login');
sendWord($socket, '');

// Tenta ler a resposta crua
echo "Lendo resposta...<br>";
$response = fread($socket, 1024);

if ($response === false || $response === '') {
    echo "<b style='color:red'>ERRO: O MikroTik fechou a conexão imediatamente ou não enviou dados.</b><br>";
    echo "Isso geralmente significa que o IP do seu servidor está bloqueado no 'Available From' do serviço API ou há um firewall dropando os pacotes de resposta.";
} else {
    echo "Resposta recebida (" . strlen($response) . " bytes):<br>";
    echo "<pre>" . bin2hex($response) . "</pre>";
    echo "ASCII: " . htmlspecialchars($response) . "<br>";
}

fclose($socket);
?>
