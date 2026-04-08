<?php
define('HOTSPOT_ACCESS', true);
require_once __DIR__ . '/config.php';

$userId = $_GET['user_id'] ?? 0;
$action = $_GET['action'] ?? 'dashboard';

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: admin.php?action=login');
    exit;
}

// ==========================================
// FUNÇÕES AUXILIARES
// ==========================================

// Função getConfigValue() agora está em config.php

// ==========================================
// FUNÇÕES MIKROTIK API
// ==========================================
function mikrotikApiConnect() {
    $mkIp = getConfigValue('mikrotik_ip', 'hotspot');
    $mkPass = getConfigValue('mikrotik_api_pass', 'hotspot');
    $mkUser = getConfigValue('mikrotik_api_user', 'hotspot') ?: 'admin';
    
    $len = strlen($mkPass);
    $masked = ($len > 4) ? substr($mkPass, 0, 2) . '***' . substr($mkPass, -2) : '****';
    $debugInfo = "IP: '$mkIp', User: '$mkUser', Senha: '$masked' (Len: $len)";

    if (!$mkIp || !$mkPass) return ['error' => "IP ou Senha não configurados. ($debugInfo)"];

    $socket = @fsockopen($mkIp, 8728, $errno, $errstr, 5);
    if (!$socket) return ['error' => "Erro de conexão: $errstr ($errno)."];
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
        $byte = fread($socket, 1);
        if ($byte === false || strlen($byte) === 0) return false;
        $byte = ord($byte);
        $len = 0;
        if (($byte & 0x80) == 0) $len = $byte;
        elseif (($byte & 0xC0) == 0x80) { 
            $b2 = fread($socket, 1);
            if ($b2 === false) return false;
            $len = (($byte & 0x3F) << 8) + ord($b2); 
        }
        elseif (($byte & 0xE0) == 0xC0) { 
            $b2 = fread($socket, 1); $b3 = fread($socket, 1);
            if ($b2 === false || $b3 === false) return false;
            $len = (($byte & 0x1F) << 16) + (ord($b2) << 8) + ord($b3); 
        }
        elseif (($byte & 0xF0) == 0xE0) { 
            $b2 = fread($socket, 1); $b3 = fread($socket, 1); $b4 = fread($socket, 1);
            if ($b2 === false || $b3 === false || $b4 === false) return false;
            $len = (($byte & 0x0F) << 24) + (ord($b2) << 16) + (ord($b3) << 8) + ord($b4); 
        }
        elseif (($byte & 0xF8) == 0xF0) { 
            $b1 = fread($socket, 1); $b2 = fread($socket, 1); $b3 = fread($socket, 1); $b4 = fread($socket, 1);
            if ($b1 === false || $b2 === false || $b3 === false || $b4 === false) return false;
            $len = (ord($b1) << 24) + (ord($b2) << 16) + (ord($b3) << 8) + ord($b4); 
        }
        
        if ($len == 0) return ''; 
        
        $word = fread($socket, $len);
        return $word === false ? false : $word;
    };
    
    $mkPassUtf8 = mb_convert_encoding($mkPass, 'UTF-8', 'UTF-8');
    $sendWord('/login');
    $sendWord('=name=' . $mkUser);
    $sendWord('=password=' . $mkPassUtf8);
    $sendWord('');
    
    $loggedIn = false;
    while (true) {
        $w = $readWord();
        if ($w === false || $w === '') break;
        if ($w === '!done') { $loggedIn = true; }
        if ($w === '!trap' || $w === '!fatal') { 
            fclose($socket); 
            return ['error' => "Senha incorreta ou sem permissão. ($debugInfo)"]; 
        }
    }
    
    fclose($socket);
    return $loggedIn ? true : ['error' => "Falha no login. ($debugInfo)"];
}

function mikrotikApiConnectDirect($mkIp, $mkUser, $mkPass) {
    $socket = @fsockopen($mkIp, 8728, $errno, $errstr, 5);
    if (!$socket) return ['error' => "Erro de conexão: $errstr ($errno)."];
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
        $byte = fread($socket, 1);
        if ($byte === false || strlen($byte) === 0) return false;
        $byte = ord($byte);
        $len = 0;
        if (($byte & 0x80) == 0) $len = $byte;
        elseif (($byte & 0xC0) == 0x80) { 
            $b2 = fread($socket, 1);
            if ($b2 === false) return false;
            $len = (($byte & 0x3F) << 8) + ord($b2); 
        }
        elseif (($byte & 0xE0) == 0xC0) { 
            $b2 = fread($socket, 1); $b3 = fread($socket, 1);
            if ($b2 === false || $b3 === false) return false;
            $len = (($byte & 0x1F) << 16) + (ord($b2) << 8) + ord($b3); 
        }
        elseif (($byte & 0xF0) == 0xE0) { 
            $b2 = fread($socket, 1); $b3 = fread($socket, 1); $b4 = fread($socket, 1);
            if ($b2 === false || $b3 === false || $b4 === false) return false;
            $len = (($byte & 0x0F) << 24) + (ord($b2) << 16) + (ord($b3) << 8) + ord($b4); 
        }
        
        if ($len == 0) return ''; 
        $word = fread($socket, $len);
        return $word === false ? false : $word;
    };
    
    $mkPassUtf8 = mb_convert_encoding($mkPass, 'UTF-8', 'UTF-8');
    $sendWord('/login');
    $sendWord('=name=' . $mkUser);
    $sendWord('=password=' . $mkPassUtf8);
    $sendWord('');

    $loggedIn = false;
    while (true) {
        $w = $readWord();
        if ($w === false || $w === '') break;
        if ($w === '!done') { $loggedIn = true; break; }
        if ($w === '!trap' || $w === '!fatal') { break; }
    }
    
    fclose($socket);
    
    if ($loggedIn) {
        return true;
    }
    return ['error' => "Senha incorreta ou sem permissão de API."];
}

function toggleMikrotikUser($username, $disable) {
    $mkIp = getConfigValue('mikrotik_ip', 'hotspot');
    $mkPass = getConfigValue('mikrotik_api_pass', 'hotspot');
    $mkUser = getConfigValue('mikrotik_api_user', 'hotspot') ?: 'admin';
    
    if (!$mkIp || !$mkPass) return ['error' => 'MikroTik não configurado'];
    
    $socket = @fsockopen($mkIp, 8728, $errno, $errstr, 5);
    if (!$socket) return ['error' => "Erro conexão: $errstr"];
    stream_set_timeout($socket, 10);

    $sendWord = function($word) use ($socket) {
        $len = strlen($word);
        if ($len < 0x80) fwrite($socket, chr($len));
        elseif ($len < 0x4000) fwrite($socket, chr(($len >> 8) | 0x80) . chr($len & 0xFF));
        elseif ($len < 0x200000) fwrite($socket, chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        else fwrite($socket, chr(($len >> 24) | 0xE0) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        fwrite($socket, $word);
    };

    $readWord = function() use ($socket) {
        $byte = fread($socket, 1);
        if ($byte === false || strlen($byte) === 0) return false;
        $byte = ord($byte);
        $len = 0;
        if (($byte & 0x80) == 0) $len = $byte;
        elseif (($byte & 0xC0) == 0x80) { $b2 = fread($socket, 1); $len = (($byte & 0x3F) << 8) + ord($b2); }
        elseif (($byte & 0xE0) == 0xC0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); $len = (($byte & 0x1F) << 16) + (ord($b2) << 8) + ord($b3); }
        elseif (($byte & 0xF0) == 0xE0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); $b4 = fread($socket, 1); $len = (($byte & 0x0F) << 24) + (ord($b2) << 16) + (ord($b3) << 8) + ord($b4); }
        if ($len == 0) return '';
        $word = fread($socket, $len);
        return $word === false ? false : $word;
    };

    $readAll = function() use ($socket, $readWord) {
        $data = '';
        while (true) {
            $w = $readWord();
            if ($w === false || $w === '') break;
            $data .= $w . "\n";
        }
        return $data;
    };
    
    $mkPassUtf8 = mb_convert_encoding($mkPass, 'UTF-8', 'UTF-8');
    $sendWord('/login');
    $sendWord('=name=' . $mkUser);
    $sendWord('=password=' . $mkPassUtf8);
    $sendWord('');

    $loggedIn = false;
    while (true) {
        $w = $readWord();
        if ($w === false || $w === '') break;
        if ($w === '!done') { $loggedIn = true; break; }
        if ($w === '!trap' || $w === '!fatal') { break; }
    }
    
    if (!$loggedIn) { fclose($socket); return ['error' => 'Falha no login']; }

    // Primeiro busca o usuário para obter o .id
    $sendWord('/ip/hotspot/user/print');
    $sendWord('');

    // Ler todas as palavras
    $words = [];
    while (true) {
        $byte = fread($socket, 1);
        if ($byte === false || $byte === '') break;
        $byte = ord($byte);
        $len = 0;
        if (($byte & 0x80) == 0) $len = $byte;
        elseif (($byte & 0xC0) == 0x80) { $b2 = fread($socket, 1); if (!$b2) break; $len = (($byte & 0x3F) << 8) + ord($b2); }
        elseif (($byte & 0xE0) == 0xC0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); if (!$b2 || !$b3) break; $len = (($byte & 0x1F) << 16) + (ord($b2) << 8) + ord($b3); }
        elseif (($byte & 0xF0) == 0xE0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); $b4 = fread($socket, 1); if (!$b2 || !$b3 || !$b4) break; $len = (($byte & 0x0F) << 24) + (ord($b2) << 16) + (ord($b3) << 8) + ord($b4); }
        if ($len == 0) { $words[] = ''; continue; }
        $word = fread($socket, $len);
        if ($word === false) break;
        $words[] = $word;
        if ($word === '!done') break;
    }
    
    $usersData = implode("\n", $words);
    
    // Procura o usuário nos resultados
    if (strpos($usersData, '=name=' . $username) === false) {
        fclose($socket);
        return ['error' => 'Usuário não encontrado. Dados: ' . substr($usersData, 0, 500)];
    }

    // Parse mais robusto - cada usuário é separado por !re
    $blocks = explode('!re', $usersData);
    $userId = '';
    
    foreach ($blocks as $block) {
        if (strpos($block, '=name=' . $username) !== false) {
            preg_match('/\.id=(\S+)/', $block, $m);
            $userId = $m[1] ?? '';
            break;
        }
    }
    
    if (!$userId) {
        fclose($socket);
        return ['error' => 'ID do usuário não encontrado'];
    }

    if ($disable) {
        $sendWord('/ip/hotspot/user/disable');
        $sendWord('=.id=' . $userId);
    } else {
        $sendWord('/ip/hotspot/user/enable');
        $sendWord('=.id=' . $userId);
    }
    $sendWord('');

    // Ler resposta
    $respWords = [];
    while (true) {
        $byte = fread($socket, 1);
        if ($byte === false || $byte === '') break;
        $byte = ord($byte);
        $len = 0;
        if (($byte & 0x80) == 0) $len = $byte;
        elseif (($byte & 0xC0) == 0x80) { $b2 = fread($socket, 1); if (!$b2) break; $len = (($byte & 0x3F) << 8) + ord($b2); }
        elseif (($byte & 0xE0) == 0xC0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); if (!$b2 || !$b3) break; $len = (($byte & 0x1F) << 16) + (ord($b2) << 8) + ord($b3); }
        elseif (($byte & 0xF0) == 0xE0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); $b4 = fread($socket, 1); if (!$b2 || !$b3 || !$b4) break; $len = (($byte & 0x0F) << 24) + (ord($b2) << 16) + (ord($b3) << 8) + ord($b4); }
        if ($len == 0) { $respWords[] = ''; continue; }
        $word = fread($socket, $len);
        if ($word === false) break;
        $respWords[] = $word;
        if ($word === '!done' || $word === '!trap') break;
    }
    
    $resp = implode("\n", $respWords);
    fclose($socket);

    if (strpos($resp, '!done') !== false) {
        return true;
    }
    return ['error' => 'Erro: ' . trim($resp) . ' | userId: ' . $userId];
}

function getOnlineUsers() {
    $mkIp = getConfigValue('mikrotik_ip', 'hotspot');
    $mkPass = getConfigValue('mikrotik_api_pass', 'hotspot');
    $mkUser = getConfigValue('mikrotik_api_user', 'hotspot') ?: 'admin';
    
    $debugLog = "getOnlineUsers: IP=$mkIp, User=$mkUser\n";
    @file_put_contents(__DIR__ . '/debug_online.txt', $debugLog, FILE_APPEND);
    
    if (!$mkIp || !$mkPass) return [];
    
    $socket = @fsockopen($mkIp, 8728, $errno, $errstr, 3);
    if (!$socket) {
        @file_put_contents(__DIR__ . '/debug_online.txt', "Socket failed: $errstr\n", FILE_APPEND);
        return [];
    }
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
        $byte = fread($socket, 1);
        if ($byte === false || strlen($byte) === 0) return false;
        $byte = ord($byte);
        $len = 0;
        if (($byte & 0x80) == 0) $len = $byte;
        elseif (($byte & 0xC0) == 0x80) { $b2 = fread($socket, 1); $len = (($byte & 0x3F) << 8) + ord($b2); }
        elseif (($byte & 0xE0) == 0xC0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); $len = (($byte & 0x1F) << 16) + (ord($b2) << 8) + ord($b3); }
        elseif (($byte & 0xF0) == 0xE0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); $b4 = fread($socket, 1); $len = (($byte & 0x0F) << 24) + (ord($b2) << 16) + (ord($b3) << 8) + ord($b4); }
        if ($len == 0) return '';
        $word = fread($socket, $len);
        return $word === false ? false : $word;
    };
    
    $mkPassUtf8 = mb_convert_encoding($mkPass, 'UTF-8', 'UTF-8');
    $sendWord('/login');
    $sendWord('=name=' . $mkUser);
    $sendWord('=password=' . $mkPassUtf8);
    $sendWord('');

    $loginOk = false;
    $loginResp = [];
    while (true) {
        $w = $readWord();
        if ($w === false || $w === '') break;
        $loginResp[] = $w;
        if ($w === '!done') { $loginOk = true; break; }
        if ($w === '!trap' || $w === '!fatal') break;
    }
    
    @file_put_contents(__DIR__ . '/debug_online.txt', "Login response: " . print_r($loginResp, true) . "\n", FILE_APPEND);
    
    if (!$loginOk) { fclose($socket); return []; }
    
    $sendWord('/ip/hotspot/active/print');
    $sendWord('.proplist=user,address,mac-address,session-time-left');
    $sendWord('');

    $users = [];
    $allWords = [];
    $firstWord = true;
    while (true) {
        $w = $readWord();
        if ($w === false) break;
        $allWords[] = $w;
        if ($firstWord) {
            @file_put_contents(__DIR__ . '/debug_online.txt', "First response word: '$w'\n", FILE_APPEND);
            $firstWord = false;
        }
        if ($w === '!done') break;
        
        if (strpos($w, '=user=') !== false) {
            @file_put_contents(__DIR__ . '/debug_online.txt', "Found user word: $w\n", FILE_APPEND);
            preg_match('/=user=([^=]+)/', $w, $um);
            preg_match('/=address=([^=]+)/', $w, $am);
            preg_match('/=mac-address=([^=]+)/', $w, $mm);
            preg_match('/=session-time-left=([^=]+)/', $w, $tm);
            
            $users[] = [
                'user' => $um[1] ?? '',
                'ip' => $am[1] ?? '',
                'mac' => $mm[1] ?? '',
                'time' => $tm[1] ?? ''
            ];
        }
    }
    
    @file_put_contents(__DIR__ . '/debug_online.txt', "All words: " . print_r($allWords, true) . "\n", FILE_APPEND);
    @file_put_contents(__DIR__ . '/debug_online.txt', "Users found: " . count($users) . "\n", FILE_APPEND);
    
    fclose($socket);
    return $users;
}

// ==========================================
// LÓGICA DO ADMIN
// ==========================================

if ($action === 'test_mikrotik') {
    requireAdmin();
    $result = mikrotikApiConnect();
    if ($result === true) {
        echo json_encode(['status' => 'success', 'msg' => 'Conexão estabelecida com sucesso!']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $result['error']]);
    }
    exit;
}
    
if ($action === 'view_logs' && $userId) {
    requireAdmin();
    $conn = getDbConnection();
    $userStmt = $conn->prepare("SELECT id, cpf, nome, sobrenome FROM dados WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userData = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
    
    $cpf = $userData['cpf'];
    $mkLogs = [];
    
    $mkIp = getConfigValue('mikrotik_ip', 'hotspot');
    $mkPass = getConfigValue('mikrotik_api_pass', 'hotspot');
    $mkUser = getConfigValue('mikrotik_api_user', 'hotspot') ?: 'admin';
    
    if ($mkIp && $mkPass) {
        $socket = @fsockopen($mkIp, 8728, $errno, $errstr, 3);
        if ($socket) {
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
                $byte = fread($socket, 1);
                if ($byte === false || strlen($byte) === 0) return false;
                $byte = ord($byte);
                $len = 0;
                if (($byte & 0x80) == 0) $len = $byte;
                elseif (($byte & 0xC0) == 0x80) { $b2 = fread($socket, 1); $len = (($byte & 0x3F) << 8) + ord($b2); }
                elseif (($byte & 0xE0) == 0xC0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); $len = (($byte & 0x1F) << 16) + (ord($b2) << 8) + ord($b3); }
                elseif (($byte & 0xF0) == 0xE0) { $b2 = fread($socket, 1); $b3 = fread($socket, 1); $b4 = fread($socket, 1); $len = (($byte & 0x0F) << 24) + (ord($b2) << 16) + (ord($b3) << 8) + ord($b4); }
                if ($len == 0) return '';
                $word = fread($socket, $len);
                return $word === false ? false : $word;
            };
            
            $readAll = function() use ($socket, $readWord) {
                $data = [];
                while (true) {
                    $w = $readWord();
                    if ($w === false || $w === '') break;
                    $data[] = $w;
                }
                return $data;
            };
            
            $mkPassUtf8 = mb_convert_encoding($mkPass, 'UTF-8', 'UTF-8');
            $sendWord('/login');
            $sendWord('=name=' . $mkUser);
            $sendWord('=password=' . $mkPassUtf8);
            $sendWord('');
            
            $loginOk = false;
            foreach ($readAll() as $w) {
                if ($w === '!done') $loginOk = true;
            }
            
            if ($loginOk) {
                $sendWord('/log/print');
                $sendWord('?message=' . $cpf);
                $sendWord('');
                
                foreach ($readAll() as $w) {
                    if (strpos($w, '=time=') !== false && strpos($w, 'login') !== false) {
                        preg_match('/=time=(\S+)/', $w, $tMatch);
                        preg_match('/=message=(.+)$/', $w, $mMatch);
                        if ($tMatch[1] && $mMatch[1]) {
                            $mkLogs[] = ['time' => $tMatch[1], 'message' => $mMatch[1]];
                        }
                    }
                }
            }
            fclose($socket);
        }
    }
    
    $logStmt = $conn->prepare("SELECT * FROM logs_conexao WHERE usuario_id = ? ORDER BY data_conexao DESC LIMIT 50");
    $logStmt->bind_param("i", $userId);
    $logStmt->execute();
    $logsResult = $logStmt->get_result();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Logs de Conexão</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 20px; }
            .container { max-width: 900px; margin: 0 auto; }
            .card { background: white; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.07); overflow: hidden; margin-bottom: 20px; }
            .card-header { padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
            .card-header h3 { font-size: 18px; color: #333; }
            .card-body { padding: 20px 25px; }
            .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; font-family: 'Inter', sans-serif; }
            .btn-primary { background: #667eea; color: white; }
            .btn-secondary { background: #6c757d; color: white; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
            th { background: #f9fafb; color: #666; font-weight: 600; }
            .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
            .badge-success { background: #d1fae5; color: #065f46; }
            code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Logs de Conexão - <?= htmlspecialchars($userData['nome'] . ' ' . $userData['sobrenome']) ?></h3>
                    <a href="admin.php?action=users" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
                <div class="card-body">
                    <h4 style="margin-bottom:15px;color:#333;">Conexões do Portal</h4>
                    <table>
                        <thead><tr><th>#</th><th>Data/Hora</th><th>MAC</th><th>IP</th></tr></thead>
                        <tbody>
                            <?php if ($logsResult->num_rows > 0): ?>
                                <?php while ($log = $logsResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $log['id'] ?></td>
                                    <td><?= date('d/m/Y H:i:s', strtotime($log['data_conexao'])) ?></td>
                                    <td><code><?= htmlspecialchars($log['mac']) ?></code></td>
                                    <td><code><?= htmlspecialchars($log['ip']) ?></code></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center;color:#999;">Nenhuma conexão registrada.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <?php if (!empty($mkLogs)): ?>
                    <h4 style="margin:30px 0 15px;color:#333;">Histórico do MikroTik</h4>
                    <table>
                        <thead><tr><th>Data/Hora</th><th>Mensagem</th></tr></thead>
                        <tbody>
                            <?php foreach ($mkLogs as $mkLog): ?>
                            <tr>
                                <td><?= htmlspecialchars($mkLog['time']) ?></td>
                                <td><?= htmlspecialchars($mkLog['message']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
            $lastAttempt = $_SESSION['login_lock_time'] ?? 0;
            if (time() - $lastAttempt < 900) {
                $loginError = "Muitas tentativas. Aguarde 15 minutos.";
            } else {
                unset($_SESSION['login_attempts']);
                unset($_SESSION['login_lock_time']);
            }
        }
        
        if (!isset($loginError)) {
            $username = sanitize($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Verificar no config.ini
            $configUser = getConfigValue('admin_user', 'admin');
            $configPass = getConfigValue('admin_pass', 'admin');
            
            if ($username === $configUser && !empty($configPass) && password_verify($password, $configPass)) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = 1;
                $_SESSION['admin_user'] = $username;
                unset($_SESSION['login_attempts']);
                unset($_SESSION['login_lock_time']);
                
                header('Location: admin.php');
                exit;
            }
            
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= 5) $_SESSION['login_lock_time'] = time();
            $loginError = "Usuário ou senha incorretos.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Admin</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Inter', sans-serif; background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
            .login-box h2 { text-align: center; margin-bottom: 20px; color: #333; }
            .form-group { margin-bottom: 15px; }
            .form-group label { display: block; margin-bottom: 5px; color: #555; }
            .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
            .btn { width: 100%; padding: 12px; border: none; border-radius: 8px; background: #667eea; color: white; font-weight: 600; cursor: pointer; font-size: 15px; }
            .alert { padding: 10px; background: #fee2e2; color: #b91c1c; border-radius: 8px; margin-bottom: 15px; font-size: 13px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2><i class="fas fa-user-shield"></i> Admin</h2>
            <?php if (isset($loginError)): ?><div class="alert"><?= $loginError ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="form-group"><label>Usuário</label><input type="text" name="username" required autofocus></div>
                <div class="form-group"><label>Senha</label><input type="password" name="password" required></div>
                <button type="submit" class="btn">Entrar</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Queries que podem executar antes do login (para actions específicas)

// Buscar usuários online apenas quando necessário
$onlineUsers = [];
$statsLast7Days = [];
$connectionsByHour = [];
$totalUsers = $activeUsers = $blockedUsers = $todayUsers = 0;

if ($action === 'dashboard' || $action === 'online') {
    $conn = getDbConnection();
    if ($conn) {
        $totalUsers = $conn->query("SELECT COUNT(*) as total FROM dados")->fetch_assoc()['total'];
        $activeUsers = $conn->query("SELECT COUNT(*) as total FROM dados WHERE status = 'ativo'")->fetch_assoc()['total'];
        $blockedUsers = $conn->query("SELECT COUNT(*) as total FROM dados WHERE status = 'bloqueado'")->fetch_assoc()['total'];
        $todayUsers = $conn->query("SELECT COUNT(*) as total FROM dados WHERE DATE(data_cadastro) = CURDATE()")->fetch_assoc()['total'];
        
        if ($action === 'dashboard') {
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $count = $conn->query("SELECT COUNT(*) as total FROM dados WHERE DATE(data_cadastro) = '$date'")->fetch_assoc()['total'];
                $statsLast7Days[] = ['date' => date('d/m', strtotime($date)), 'count' => $count];
            }
            for ($h = 0; $h < 24; $h++) {
                $count = $conn->query("SELECT COUNT(*) as total FROM logs_conexao WHERE DATE(data_conexao) = CURDATE() AND HOUR(data_conexao) = $h")->fetch_assoc()['total'];
                $connectionsByHour[] = $count;
            }
        }
        
        if ($action === 'online') {
            $onlineUsers = getOnlineUsers();
        }
    }
}

if ($action !== 'dashboard' && $action !== 'online') {
    requireAdmin();
}

$conn = getDbConnection();
if (!$conn) die("Erro de conexão com o banco de dados.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) die("Token CSRF inválido.");
    
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'delete_user' && isset($_POST['user_id'])) {
        $stmt = $conn->prepare("DELETE FROM dados WHERE id = ?");
        $stmt->bind_param("i", $_POST['user_id']);
        $stmt->execute();
        $stmt->close();
        $success = "Usuário removido com sucesso.";
    }
    
    if ($postAction === 'save_settings') {
        $configFile = __DIR__ . '/config.ini';
        $rawConfig = file_exists($configFile) ? parse_ini_file($configFile, true, INI_SCANNER_RAW) : [];
        $currentConfig = [];
        
        if ($rawConfig) {
            foreach ($rawConfig as $sec => $vals) {
                if (is_array($vals)) {
                    foreach ($vals as $k => $v) {
                        $v = trim($v);
                        // Remove aspas se existirem
                        if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || 
                            (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) {
                            $v = substr($v, 1, -1);
                        }
                        $currentConfig[$k] = stripslashes($v);
                    }
                }
            }
        }
        
        $newConfig = [];
        $newConfig['db_host'] = trim($_POST['db_host'] ?? $currentConfig['db_host'] ?? '127.0.0.1');
        $newConfig['db_port'] = trim($_POST['db_port'] ?? $currentConfig['db_port'] ?? '3306');
        $newConfig['db_user'] = trim($_POST['db_user'] ?? $currentConfig['db_user'] ?? 'root');
        
        if (!empty($_POST['db_pass'])) $newConfig['db_pass'] = $_POST['db_pass'];
        else $newConfig['db_pass'] = $currentConfig['db_pass'] ?? '';
        
        $newConfig['db_name'] = trim($_POST['db_name'] ?? $currentConfig['db_name'] ?? 'hotspot');
        $newConfig['hotspot_name'] = trim($_POST['hotspot_name'] ?? $currentConfig['hotspot_name'] ?? 'Charles WiFi');
        $newConfig['hotspot_logo'] = trim($_POST['hotspot_logo'] ?? $currentConfig['hotspot_logo'] ?? 'logo.png');
        $newConfig['hotspot_bg_image'] = trim($_POST['hotspot_bg_image'] ?? $currentConfig['hotspot_bg_image'] ?? 'bg.jpg');
        $newConfig['hotspot_primary_color'] = trim($_POST['hotspot_primary_color'] ?? $currentConfig['hotspot_primary_color'] ?? '#667eea');
        $newConfig['hotspot_secondary_color'] = trim($_POST['hotspot_secondary_color'] ?? $currentConfig['hotspot_secondary_color'] ?? '#764ba2');
        $newConfig['external_login_url'] = trim($_POST['external_login_url'] ?? $currentConfig['external_login_url'] ?? 'https://hotspot.redeslinkin.com.br/login.php');
        $newConfig['admin_user'] = trim($_POST['admin_user'] ?? $currentConfig['admin_user'] ?? 'admin');
        
        if (!empty($_POST['admin_pass'])) $newConfig['admin_pass'] = password_hash(trim($_POST['admin_pass']), PASSWORD_DEFAULT);
        else $newConfig['admin_pass'] = $currentConfig['admin_pass'] ?? '';
        
        $newConfig['mikrotik_ip'] = trim($_POST['mikrotik_ip'] ?? $currentConfig['mikrotik_ip'] ?? '');
        $newConfig['mikrotik_api_port'] = trim($_POST['mikrotik_api_port'] ?? $currentConfig['mikrotik_api_port'] ?? '8728');
        $newConfig['mikrotik_api_user'] = trim($_POST['mikrotik_api_user'] ?? $currentConfig['mikrotik_api_user'] ?? 'admin');
        
        $mkPassInput = trim($_POST['mikrotik_api_pass']);
        // Remove aspas se o usuário colou com aspas
        if ((substr($mkPassInput, 0, 1) === '"' && substr($mkPassInput, -1) === '"') || 
            (substr($mkPassInput, 0, 1) === "'" && substr($mkPassInput, -1) === "'")) {
            $mkPassInput = substr($mkPassInput, 1, -1);
        }
        
        if (!empty($mkPassInput)) $newConfig['mikrotik_api_pass'] = $mkPassInput;
        else $newConfig['mikrotik_api_pass'] = $currentConfig['mikrotik_api_pass'] ?? '';

        // Evolution API - WhatsApp
        $newConfig['evolution_api_url'] = trim($_POST['evolution_api_url'] ?? $currentConfig['evolution_api_url'] ?? '');
        $newConfig['evolution_instance_name'] = trim($_POST['evolution_instance_name'] ?? $currentConfig['evolution_instance_name'] ?? '');
        $newConfig['evolution_api_key'] = trim($_POST['evolution_api_key'] ?? $currentConfig['evolution_api_key'] ?? '');
        $newConfig['evolution_notify_enabled'] = isset($_POST['evolution_notify_enabled']) ? 'true' : 'false';
        $newConfig['evolution_notify_type'] = trim($_POST['evolution_notify_type'] ?? $currentConfig['evolution_notify_type'] ?? 'text');
        $newConfig['evolution_notify_template'] = trim($_POST['evolution_notify_template'] ?? $currentConfig['evolution_notify_template'] ?? 'Novo cadastro: {nome} ({cpf}) - Email: {email}');
        $newConfig['evolution_notify_numbers'] = trim($_POST['evolution_notify_numbers'] ?? $currentConfig['evolution_notify_numbers'] ?? '');
        $newConfig['evolution_notify_media_url'] = trim($_POST['evolution_notify_media_url'] ?? $currentConfig['evolution_notify_media_url'] ?? '');
        
        // WhatsApp - Mensagem para o cliente
        $newConfig['evolution_client_enabled'] = isset($_POST['evolution_client_enabled']) ? 'true' : ($currentConfig['evolution_client_enabled'] ?? 'false');
        $newConfig['evolution_client_type'] = trim($_POST['evolution_client_type'] ?? $currentConfig['evolution_client_type'] ?? 'text');
        $newConfig['evolution_client_template'] = trim($_POST['evolution_client_template'] ?? $currentConfig['evolution_client_template'] ?? 'Olá {nome}! Bem-vindo ao nosso WiFi. Seu login: {cpf}');
        $newConfig['evolution_client_media_url'] = trim($_POST['evolution_client_media_url'] ?? $currentConfig['evolution_client_media_url'] ?? '');

        $esc = function($v) { return '"' . addcslashes($v, '"\\') . '"'; };

        $iniContent = "; ============================================\n; Configuração do Hotspot\n; Gerado em: " . date('d/m/Y H:i:s') . "\n; ============================================\n\n";
        $iniContent .= "[database]\n";
        $iniContent .= "db_host = " . $esc($newConfig['db_host']) . "\n";
        $iniContent .= "db_port = " . $esc($newConfig['db_port']) . "\n";
        $iniContent .= "db_user = " . $esc($newConfig['db_user']) . "\n";
        $iniContent .= "db_pass = " . $esc($newConfig['db_pass']) . "\n";
        $iniContent .= "db_name = " . $esc($newConfig['db_name']) . "\n\n";
        $iniContent .= "[hotspot]\n";
        $iniContent .= "hotspot_name = " . $esc($newConfig['hotspot_name']) . "\n";
        $iniContent .= "hotspot_logo = " . $esc($newConfig['hotspot_logo']) . "\n";
        $iniContent .= "hotspot_bg_image = " . $esc($newConfig['hotspot_bg_image']) . "\n";
        $iniContent .= "hotspot_primary_color = " . $esc($newConfig['hotspot_primary_color']) . "\n";
        $iniContent .= "hotspot_secondary_color = " . $esc($newConfig['hotspot_secondary_color']) . "\n";
        $iniContent .= "external_login_url = " . $esc($newConfig['external_login_url']) . "\n";
        $iniContent .= "mikrotik_ip = " . $esc($newConfig['mikrotik_ip']) . "\n";
        $iniContent .= "mikrotik_api_port = " . $esc($newConfig['mikrotik_api_port']) . "\n";
        $iniContent .= "mikrotik_api_user = " . $esc($newConfig['mikrotik_api_user']) . "\n";
        $iniContent .= "mikrotik_api_pass = " . $esc($newConfig['mikrotik_api_pass']) . "\n\n";
        $iniContent .= "[admin]\n";
        $iniContent .= "admin_user = " . $esc($newConfig['admin_user']) . "\n";
        $iniContent .= "admin_pass = " . $esc($newConfig['admin_pass']) . "\n\n";
        $iniContent .= "[whatsapp]\n";
        $iniContent .= "evolution_api_url = " . $esc($newConfig['evolution_api_url']) . "\n";
        $iniContent .= "evolution_instance_name = " . $esc($newConfig['evolution_instance_name']) . "\n";
        $iniContent .= "evolution_api_key = " . $esc($newConfig['evolution_api_key']) . "\n";
        $iniContent .= "evolution_notify_enabled = " . $esc($newConfig['evolution_notify_enabled']) . "\n";
        $iniContent .= "evolution_notify_type = " . $esc($newConfig['evolution_notify_type']) . "\n";
        $iniContent .= "evolution_notify_template = " . $esc($newConfig['evolution_notify_template']) . "\n";
        $iniContent .= "evolution_notify_numbers = " . $esc($newConfig['evolution_notify_numbers']) . "\n";
        $iniContent .= "evolution_notify_media_url = " . $esc($newConfig['evolution_notify_media_url']) . "\n";
        $iniContent .= "evolution_client_enabled = " . $esc($newConfig['evolution_client_enabled']) . "\n";
        $iniContent .= "evolution_client_type = " . $esc($newConfig['evolution_client_type']) . "\n";
        $iniContent .= "evolution_client_template = " . $esc($newConfig['evolution_client_template']) . "\n";
        $iniContent .= "evolution_client_media_url = " . $esc($newConfig['evolution_client_media_url']) . "\n";
        
        if (file_put_contents($configFile, $iniContent)) {
            $loginHtmlFile = __DIR__ . '/../Mikrotik/login.html';
            if (file_exists($loginHtmlFile)) {
                $loginHtml = file_get_contents($loginHtmlFile);
                $loginHtml = preg_replace('/<h1>.*?<\/h1>/', '<h1>' . htmlspecialchars($newConfig['hotspot_name']) . '</h1>', $loginHtml, 1);
                $loginHtml = preg_replace('/action="[^"]*login\.php"/', 'action="' . htmlspecialchars($newConfig['external_login_url']) . '"', $loginHtml, 1);
                file_put_contents($loginHtmlFile, $loginHtml);
            }
            $success = "Configurações salvas com sucesso!";
        } else {
            $error = "Erro ao salvar o arquivo de configuração.";
        }
    }
}

$totalUsers = $conn->query("SELECT COUNT(*) as total FROM dados")->fetch_assoc()['total'];
$activeUsers = $conn->query("SELECT COUNT(*) as total FROM dados WHERE status = 'ativo'")->fetch_assoc()['total'];
$blockedUsers = $conn->query("SELECT COUNT(*) as total FROM dados WHERE status = 'bloqueado'")->fetch_assoc()['total'];
$todayUsers = $conn->query("SELECT COUNT(*) as total FROM dados WHERE DATE(data_cadastro) = CURDATE()")->fetch_assoc()['total'];

// Estatísticas - últimos 7 dias
$statsLast7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = $conn->query("SELECT COUNT(*) as total FROM dados WHERE DATE(data_cadastro) = '$date'")->fetch_assoc()['total'];
    $statsLast7Days[] = ['date' => date('d/m', strtotime($date)), 'count' => $count];
}

// Conexões por hora - hoje
$connectionsByHour = [];
for ($h = 0; $h < 24; $h++) {
    $hourStart = date('Y-m-d H:00:00');
    $hourEnd = date('Y-m-d H:59:59');
    $count = $conn->query("SELECT COUNT(*) as total FROM logs_conexao WHERE DATE(data_conexao) = CURDATE() AND HOUR(data_conexao) = $h")->fetch_assoc()['total'];
    $connectionsByHour[] = $count;
}

// Usuários online
$onlineUsers = getOnlineUsers();

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$totalPages = ceil($totalUsers / $perPage);

$search = sanitize($_GET['search'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$searchQuery = '';
$params = [];
$types = '';

if ($search) {
    $searchQuery = " WHERE nome LIKE CONCAT('%', ?, '%') OR cpf LIKE CONCAT('%', ?, '%') OR email LIKE CONCAT('%', ?, '%') OR mac LIKE CONCAT('%', ?, '%')";
    $params = [$search, $search, $search, $search];
    $types = 'ssss';
}

if ($dateFrom && $dateTo) {
    $dateFromSql = date('Y-m-d', strtotime($dateFrom));
    $dateToSql = date('Y-m-d', strtotime($dateTo));
    
    if ($searchQuery) {
        $searchQuery .= " AND DATE(data_cadastro) BETWEEN ? AND ?";
    } else {
        $searchQuery = " WHERE DATE(data_cadastro) BETWEEN ? AND ?";
    }
    $params[] = $dateFromSql;
    $params[] = $dateToSql;
    $types .= 'ss';
}

$stmt = $conn->prepare("SELECT * FROM dados$searchQuery ORDER BY data_cadastro DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$usersResult = $stmt->get_result();

$configFile = __DIR__ . '/config.ini';
$currentConfig = [];
if (file_exists($configFile)) {
    $rawConfig = parse_ini_file($configFile, true, INI_SCANNER_RAW);
    if ($rawConfig !== false) {
        foreach ($rawConfig as $section => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $val) {
                    $val = trim($val);
                    if ((substr($val, 0, 1) === '"' && substr($val, -1) === '"') || 
                        (substr($val, 0, 1) === "'" && substr($val, -1) === "'")) {
                        $val = substr($val, 1, -1);
                    }
                    $currentConfig[$key] = stripslashes($val);
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
    <title>Painel Admin - <?= HOTSPOT_NAME ?></title>
    <?php if ($action === 'online' || $action === 'dashboard'): ?>
    <meta http-equiv="refresh" content="30">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; color: #333; }
        .sidebar { width: 250px; background: #1e293b; color: white; height: 100vh; position: fixed; left: 0; top: 0; padding: 20px 0; }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid #334155; margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 20px; font-weight: 700; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 20px; color: #94a3b8; text-decoration: none; transition: 0.3s; }
        .nav-item:hover, .nav-item.active { background: #334155; color: white; }
        .nav-item i { width: 20px; text-align: center; }
        .main { margin-left: 250px; padding: 30px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .topbar h1 { font-size: 24px; font-weight: 700; color: #1e293b; }
        .topbar-user { display: flex; align-items: center; gap: 15px; }
        .topbar-user span { font-size: 14px; color: #64748b; }
        .btn-logout { color: #ef4444; text-decoration: none; font-size: 14px; font-weight: 500; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 14px; color: #64748b; margin-bottom: 5px; }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #1e293b; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; }
        .card-header { padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { font-size: 18px; font-weight: 600; }
        .card-body { padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #64748b; font-size: 13px; text-transform: uppercase; }
        td { font-size: 14px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: none; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; transition: 0.2s; }
        .btn-sm { padding: 6px 10px; font-size: 12px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .search-box { display: flex; gap: 10px; }
        .search-box input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
        .pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
        .pagination a { padding: 8px 12px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #333; }
        .pagination a.active { background: #667eea; color: white; border-color: #667eea; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .settings-section { background: #f8fafc; padding: 20px; border-radius: 8px; }
        .settings-section-header { font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; }
        .setting-item { margin-bottom: 15px; }
        .setting-item label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 5px; }
        .setting-item input { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; }
        .setting-hint { font-size: 12px; color: #64748b; margin-top: 4px; }
        .online-users { background: #ecfdf5; border: 1px solid #a7f3d0; }
        .online-users td { color: #065f46; }
        .chart-container { display: flex; align-items: flex-end; height: 150px; gap: 8px; padding: 20px 0; }
        .chart-bar { flex: 1; background: #667eea; border-radius: 4px 4px 0 0; min-height: 5px; position: relative; }
        .chart-bar:hover { background: #5568d3; }
        .chart-bar span { position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 10px; color: #666; white-space: nowrap; }
        .chart-bar .value { position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 10px; font-weight: bold; }
        .date-filter { display: flex; gap: 10px; align-items: center; margin-bottom: 15px; }
        .date-filter input { padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; }
        .date-filter .btn { padding: 8px 15px; }
        @media (max-width: 768px) { .sidebar { width: 100%; height: auto; position: relative; } .main { margin-left: 0; } .settings-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2><i class="fas fa-wifi"></i> <?= HOTSPOT_NAME ?></h2></div>
        <nav>
            <a href="admin.php" class="nav-item <?= $action === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="admin.php?action=users" class="nav-item <?= $action === 'users' ? 'active' : '' ?>"><i class="fas fa-users"></i> Usuários</a>
            <a href="admin.php?action=online" class="nav-item <?= $action === 'online' ? 'active' : '' ?>"><i class="fas fa-signal"></i> Online</a>
            <a href="admin.php?action=settings" class="nav-item <?= $action === 'settings' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Configurações</a>
        </nav>
    </div>

    <div class="main">
        <div class="topbar">
            <h1><i class="fas fa-<?= $action === 'dashboard' ? 'chart-pie' : ($action === 'users' ? 'users' : ($action === 'online' ? 'signal' : 'cog')) ?>"></i> <?= $action === 'dashboard' ? 'Dashboard' : ($action === 'users' ? 'Usuários' : ($action === 'online' ? 'Usuários Online' : 'Configurações')) ?></h1>
            <div class="topbar-user">
                <span><i class="fas fa-user"></i> <?= sanitize($_SESSION['admin_user']) ?></span>
                <a href="admin.php?action=logout" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>

        <?php if (isset($success)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
        <?php if (isset($error)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div><?php endif; ?>

        <?php if ($action === 'dashboard'): ?>
            <div class="stats-grid">
                <div class="stat-card"><h3>Total de Usuários</h3><div class="value"><?= $totalUsers ?></div></div>
                <div class="stat-card"><h3>Ativos</h3><div class="value" style="color: #10b981;"><?= $activeUsers ?></div></div>
                <div class="stat-card"><h3>Online Agora</h3><div class="value" style="color: #667eea;"><?= count($onlineUsers) ?></div></div>
                <div class="stat-card"><h3>Cadastros Hoje</h3><div class="value"><?= $todayUsers ?></div></div>
            </div>
            
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header"><h3>Cadastros últimos 7 dias</h3></div>
                <div class="card-body">
                    <div class="chart-container">
                        <?php 
                        $maxCount = max(array_column($statsLast7Days, 'count'), 1);
                        foreach ($statsLast7Days as $stat): 
                            $height = ($stat['count'] / $maxCount) * 100;
                        ?>
                        <div class="chart-bar" style="height: <?= $height ?>%">
                            <?php if ($stat['count'] > 0): ?><span class="value"><?= $stat['count'] ?></span><?php endif; ?>
                            <span><?= $stat['date'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header"><h3>Conexões por hora (Hoje)</h3></div>
                <div class="card-body">
                    <div class="chart-container">
                        <?php 
                        $maxConn = max($connectionsByHour) ?: 1;
                        for ($h = 0; $h < 24; $h++): 
                            $height = ($connectionsByHour[$h] / $maxConn) * 100;
                        ?>
                        <div class="chart-bar" style="height: <?= $height ?>%">
                            <?php if ($connectionsByHour[$h] > 0): ?><span class="value"><?= $connectionsByHour[$h] ?></span><?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div style="text-align:center;font-size:12px;color:#666;margin-top:10px;">0h &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;6h &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;12h &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;18h &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;23h</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h3>Últimos Cadastros</h3><a href="admin.php?action=users" class="btn btn-primary btn-sm">Ver Todos</a></div>
                <div class="card-body">
                    <table>
                        <thead><tr><th>Nome</th><th>CPF</th><th>Data</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php 
                            $recent = $conn->query("SELECT * FROM dados ORDER BY data_cadastro DESC LIMIT 5");
                            while ($user = $recent->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?= sanitize($user['nome'] . ' ' . $user['sobrenome']) ?></td>
                                <td><?= sanitize($user['cpf']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($user['data_cadastro'])) ?></td>
                                <td><span class="badge <?= $user['status'] === 'ativo' ? 'badge-success' : 'badge-danger' ?>"><?= $user['status'] ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3>Exportar Dados</h3></div>
                <div class="card-body">
                    <form method="POST"><input type="hidden" name="action" value="export_csv"><input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>"><button type="submit" class="btn btn-success"><i class="fas fa-file-csv"></i> Baixar CSV Completo</button></form>
                </div>
            </div>

        <?php elseif ($action === 'users'): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Todos os Usuários</h3>
                    <div class="search-box">
                        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
                            <input type="hidden" name="action" value="users">
                            <input type="text" name="search" placeholder="Buscar..." value="<?= $search ?>">
                            <input type="date" name="date_from" value="<?= $dateFrom ?>" title="De">
                            <input type="date" name="date_to" value="<?= $dateTo ?>" title="Até">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </form>
                        <form method="POST"><input type="hidden" name="action" value="export_csv"><input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>"><button type="submit" class="btn btn-success"><i class="fas fa-file-csv"></i> Exportar</button></form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>#</th><th>CPF</th><th>Nome</th><th>Email</th><th>Telefone</th><th>MAC</th><th>Data</th><th>Conexão</th><th>Status</th><th>Ações</th></tr></thead>
                            <tbody>
                                <?php while ($user = $usersResult->fetch_assoc()):
                                    $isOnline = false;
                                    if (!empty($user['last_login'])) { $lastLogin = strtotime($user['last_login']); $isOnline = (time() - $lastLogin) < 300; }
                                ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= sanitize($user['cpf']) ?></td>
                                    <td><?= sanitize($user['nome'] . ' ' . $user['sobrenome']) ?></td>
                                    <td><?= sanitize($user['email']) ?></td>
                                    <td><?= sanitize($user['telefone']) ?></td>
                                    <td><code><?= sanitize($user['mac']) ?></code></td>
                                    <td><?= date('d/m/Y H:i', strtotime($user['data_cadastro'])) ?></td>
                                    <td>
                                        <?php if ($isOnline): ?>
                                            <span class="badge badge-success">🟢 Online</span><br><small style="color:#999"><?= date('H:i', strtotime($user['last_login'])) ?></small>
                                        <?php else: ?>
                                            <span class="badge badge-danger">🔴 Offline</span>
                                            <?php if (!empty($user['last_login'])): ?><br><small style="color:#999">Último: <?= date('d/m H:i', strtotime($user['last_login'])) ?></small><?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= $user['status'] === 'ativo' ? 'badge-success' : 'badge-danger' ?>"><?= $user['status'] ?></span></td>
                                    <td class="actions">
                                        <a href="admin.php?action=view_logs&user_id=<?= $user['id'] ?>" class="btn btn-sm btn-primary" title="Ver Logs"><i class="fas fa-history"></i></a>
                                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>"><button type="submit" class="btn btn-sm <?= $user['status'] === 'ativo' ? 'btn-warning' : 'btn-success' ?>"><i class="fas fa-<?= $user['status'] === 'ativo' ? 'ban' : 'check' ?>"></i></button></form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remover este usuário?');"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="?action=users&page=<?= $i ?>&search=<?= urlencode($search) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'online'): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Usuários Conectados Agora</h3>
                    <button class="btn btn-secondary btn-sm" onclick="location.reload()"><i class="fas fa-sync"></i> Atualizar</button>
                </div>
                <div class="card-body">
                    <?php if (empty($onlineUsers)): ?>
                        <p style="text-align:center;color:#999;padding:30px;">Nenhum usuário online no momento.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Usuário</th><th>Nome</th><th>IP</th><th>MAC</th><th>Tempo Restante</th></tr></thead>
                            <tbody>
                                <?php 
                                $conn = getDbConnection();
                                foreach ($onlineUsers as $u): 
                                    $nomeDisplay = $u['user'];
                                    if ($conn) {
                                        $stmt = $conn->prepare("SELECT nome, sobrenome FROM dados WHERE cpf = ?");
                                        $stmt->bind_param("s", $u['user']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($row = $result->fetch_assoc()) {
                                            $nomeDisplay = $row['nome'] . ' ' . $row['sobrenome'];
                                        }
                                        $stmt->close();
                                    }
                                ?>
                                <tr>
                                    <td><?= sanitize($u['user']) ?></td>
                                    <td><?= sanitize($nomeDisplay) ?></td>
                                    <td><code><?= sanitize($u['ip']) ?></code></td>
                                    <td><code><?= sanitize($u['mac']) ?></code></td>
                                    <td><?= sanitize($u['time']) ?></td>
                                </tr>
                                <?php endforeach; $conn->close(); ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'settings'): ?>
            <div class="card">
                <div class="card-header"><h3>Configurações do Sistema</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <div class="settings-grid">
                            <div class="settings-section">
                                <div class="settings-section-header"><i class="fas fa-wifi"></i> Hotspot</div>
                                <div class="setting-item"><label>Nome da Rede</label><input type="text" name="hotspot_name" value="<?= htmlspecialchars($currentConfig['hotspot_name'] ?? 'Charles WiFi') ?>"></div>
                                <div class="setting-item"><label>Logo (arquivo)</label><input type="text" name="hotspot_logo" value="<?= htmlspecialchars($currentConfig['hotspot_logo'] ?? 'logo.png') ?>"></div>
                                <div class="setting-item"><label>URL Externa de Login</label><input type="url" name="external_login_url" value="<?= htmlspecialchars($currentConfig['external_login_url'] ?? 'https://hotspot.redeslinkin.com.br/login.php') ?>" required></div>
                            </div>
                            <div class="settings-section">
                                <div class="settings-section-header"><i class="fas fa-server"></i> Conexão MikroTik</div>
                                <div class="setting-item"><label>IP do Router</label><input type="text" name="mikrotik_ip" value="<?= htmlspecialchars($currentConfig['mikrotik_ip'] ?? '') ?>"><div class="setting-hint">IP para acesso à API (Porta 8728)</div></div>
                                <div class="setting-item"><label>Usuário API</label><input type="text" name="mikrotik_api_user" value="<?= htmlspecialchars($currentConfig['mikrotik_api_user'] ?? 'admin') ?>"></div>
                                <div class="setting-item">
                                    <label>Senha API</label>
                                    <div style="display:flex;gap:10px;">
                                        <input type="password" id="mk_pass_input" name="mikrotik_api_pass" value="<?= htmlspecialchars($currentConfig['mikrotik_api_pass'] ?? '') ?>">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="testMkConnection()">Testar</button>
                                    </div>
                                    <div class="setting-hint" id="mk_test_result">Clique em Testar para validar.</div>
                                </div>
                            </div>
                            <div class="settings-section">
                                <div class="settings-section-header"><i class="fab fa-whatsapp"></i> WhatsApp (Evolution API)</div>
                                <div class="setting-item">
                                    <label>Ativar Notificações</label>
                                    <input type="checkbox" name="evolution_notify_enabled" <?= ($currentConfig['evolution_notify_enabled'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                                    <div class="setting-hint">Envia notificação quando alguém se cadastrar</div>
                                </div>
                                <div class="setting-item"><label>URL da API</label><input type="url" name="evolution_api_url" value="<?= htmlspecialchars($currentConfig['evolution_api_url'] ?? '') ?>" placeholder="https://api.suaempresa.com.br"></div>
                                <div class="setting-item"><label>Nome da Instância</label><input type="text" name="evolution_instance_name" value="<?= htmlspecialchars($currentConfig['evolution_instance_name'] ?? '') ?>" placeholder="minha-instancia"></div>
                                <div class="setting-item"><label>API Key</label><input type="password" name="evolution_api_key" value="<?= htmlspecialchars($currentConfig['evolution_api_key'] ?? '') ?>"></div>
                                <div class="setting-item">
                                    <label>Tipo de Mensagem</label>
                                    <select name="evolution_notify_type">
                                        <option value="text" <?= ($currentConfig['evolution_notify_type'] ?? 'text') === 'text' ? 'selected' : '' ?>>Texto</option>
                                        <option value="image" <?= ($currentConfig['evolution_notify_type'] ?? 'text') === 'image' ? 'selected' : '' ?>>Imagem</option>
                                    </select>
                                </div>
                                <div class="setting-item"><label>URL da Imagem</label><input type="url" name="evolution_notify_media_url" value="<?= htmlspecialchars($currentConfig['evolution_notify_media_url'] ?? '') ?>" placeholder="https://exemplo.com/logo.png"></div>
                                <div class="setting-item">
                                    <label>Modelo da Mensagem</label>
                                    <textarea name="evolution_notify_template" rows="3"><?= htmlspecialchars($currentConfig['evolution_notify_template'] ?? 'Novo cadastro: {nome} ({cpf}) - Email: {email}') ?></textarea>
                                    <div class="setting-hint">Variáveis: {nome}, {cpf}, {email}, {telefone}</div>
                                </div>
                                <div class="setting-item"><label>Números para Notificar</label><input type="text" name="evolution_notify_numbers" value="<?= htmlspecialchars($currentConfig['evolution_notify_numbers'] ?? '') ?>" placeholder="5511999999999,5511888888888">
                                <div class="setting-hint">DDD + número, separados por vírgula</div>
                                </div>
                            </div>
                            <div class="settings-section">
                                <div class="settings-section-header"><i class="fab fa-whatsapp"></i> WhatsApp - Mensagem para Cliente</div>
                                <div class="setting-item">
                                    <label>Ativar Mensagem de Boas-Vindas</label>
                                    <input type="checkbox" name="evolution_client_enabled" <?= ($currentConfig['evolution_client_enabled'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                                    <div class="setting-hint">Envia mensagem automática para o cliente</div>
                                </div>
                                <div class="setting-item">
                                    <label>Tipo de Mensagem</label>
                                    <select name="evolution_client_type">
                                        <option value="text" <?= ($currentConfig['evolution_client_type'] ?? 'text') === 'text' ? 'selected' : '' ?>>Texto</option>
                                        <option value="image" <?= ($currentConfig['evolution_client_type'] ?? 'text') === 'image' ? 'selected' : '' ?>>Imagem</option>
                                    </select>
                                </div>
                                <div class="setting-item"><label>URL da Imagem</label><input type="url" name="evolution_client_media_url" value="<?= htmlspecialchars($currentConfig['evolution_client_media_url'] ?? '') ?>" placeholder="https://exemplo.com/promocao.png"></div>
                                <div class="setting-item">
                                    <label>Mensagem de Boas-Vindas</label>
                                    <textarea name="evolution_client_template" rows="3"><?= htmlspecialchars($currentConfig['evolution_client_template'] ?? 'Olá {nome}! Bem-vindo ao nosso WiFi. Seu login: {cpf}') ?></textarea>
                                    <div class="setting-hint">Variáveis: {nome}, {cpf}, {email}, {telefone}</div>
                                </div>
                            </div>
                            <div class="settings-section">
                                <div class="settings-section-header"><i class="fas fa-database"></i> Banco de Dados</div>
                                <div class="setting-item"><label>Host</label><input type="text" name="db_host" value="<?= htmlspecialchars($currentConfig['db_host'] ?? '127.0.0.1') ?>"></div>
                                <div class="setting-item"><label>Usuário</label><input type="text" name="db_user" value="<?= htmlspecialchars($currentConfig['db_user'] ?? 'root') ?>"></div>
                                <div class="setting-item">
                                    <label>Senha</label>
                                    <input type="password" name="db_pass" value="">
                                    <div class="setting-hint">Deixe vazio para manter a senha atual</div>
                                </div>
                                <div class="setting-item"><label>Nome do Banco</label><input type="text" name="db_name" value="<?= htmlspecialchars($currentConfig['db_name'] ?? 'hotspot') ?>"></div>
                            </div>
                            <div class="settings-section">
                                <div class="settings-section-header"><i class="fas fa-user-shield"></i> Administrador</div>
                                <div class="setting-item"><label>Usuário Admin</label><input type="text" name="admin_user" value="<?= htmlspecialchars($currentConfig['admin_user'] ?? 'admin') ?>"></div>
                                <div class="setting-item">
                                    <label>Nova Senha Admin</label>
                                    <input type="password" name="admin_pass" placeholder="Deixe vazio para manter">
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 20px;"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Configurações</button></div>
                    </form>
                </div>
            </div>
            <script>
                function testMkConnection() {
                    const resultDiv = document.getElementById('mk_test_result');
                    resultDiv.innerHTML = 'Testando...';
                    resultDiv.style.color = '#666';
                    
                    fetch('admin.php?action=test_mikrotik')
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                resultDiv.innerHTML = '✅ ' + data.msg;
                                resultDiv.style.color = 'green';
                            } else {
                                resultDiv.innerHTML = '❌ ' + data.msg;
                                resultDiv.style.color = 'red';
                            }
                        })
                        .catch(err => {
                            resultDiv.innerHTML = '❌ Erro ao testar.';
                            resultDiv.style.color = 'red';
                        });
                }
                
            </script>
        <?php endif; ?>
    </div>
</body>
</html>