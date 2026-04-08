<?php
/**
 * Arquivo de configuração principal
 * Carrega as variáveis de ambiente do arquivo .env ou config.ini
 */

// Previne acesso direto
if (!defined('HOTSPOT_ACCESS')) {
    define('HOTSPOT_ACCESS', true);
}

// Ambiente (mudar para 'production' em produção)
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development');
}

// Força HTTPS em produção
if (APP_ENV === 'production' && empty($_SERVER['HTTPS'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Caminho base
define('BASE_PATH', dirname(__FILE__));

// Carrega configuração
$configFile = BASE_PATH . '/config.ini';

if (!file_exists($configFile)) {
    // Primeira configuração - criar arquivo básico e redirecionar
    $defaultConfig = <<<'INI'
; ============================================
; Configuração do Hotspot
; ============================================

[database]
db_host = "127.0.0.1"
db_port = "3306"
db_user = "root"
db_pass = ""
db_name = "hotspot"

[hotspot]
hotspot_name = "WiFi Hotspot"
hotspot_logo = "logo.png"
hotspot_bg_image = "bg.jpg"
hotspot_primary_color = "#667eea"
hotspot_secondary_color = "#764ba2"
external_login_url = "https://seudominio.com/login.php"
mikrotik_ip = ""
mikrotik_api_port = "8728"
mikrotik_api_user = "admin"
mikrotik_api_pass = ""

[admin]
admin_user = "admin"
admin_pass = ""
INI;
    file_put_contents($configFile, $defaultConfig);
    header('Location: admin.php?action=settings');
    exit;
}

$config = parse_ini_file($configFile, true);

// Armazena globalmente para uso em funções
$GLOBALS['hotspot_config'] = $config;

// Configurações do banco de dados
$cfgDb = $config['database'] ?? [];
define('DB_HOST', $cfgDb['db_host'] ?? '127.0.0.1');
define('DB_USER', $cfgDb['db_user'] ?? 'root');
define('DB_PASS', $cfgDb['db_pass'] ?? '');
define('DB_NAME', $cfgDb['db_name'] ?? 'hotspot');
define('DB_PORT', $cfgDb['db_port'] ?? '3306');

// Configurações do hotspot
$cfgHs = $config['hotspot'] ?? [];
define('HOTSPOT_NAME', $cfgHs['hotspot_name'] ?? 'Charles WiFi');
define('HOTSPOT_ADMIN_USER', $cfgHs['admin_user'] ?? 'admin');
define('HOTSPOT_ADMIN_PASS', $cfgHs['admin_pass'] ?? '');
define('HOTSPOT_LOGO', $cfgHs['hotspot_logo'] ?? 'logo.png');
define('HOTSPOT_BG_IMAGE', $cfgHs['hotspot_bg_image'] ?? 'bg.jpg');
define('HOTSPOT_PRIMARY_COLOR', $cfgHs['hotspot_primary_color'] ?? '#667eea');
define('HOTSPOT_SECONDARY_COLOR', $cfgHs['hotspot_secondary_color'] ?? '#764ba2');
define('HOTSPOT_EXTERNAL_LOGIN_URL', $cfgHs['external_login_url'] ?? 'https://hotspot.redeslinkin.com.br/login.php');

// Segurança
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600);

// Ambiente (mudar para 'production' em produção)
//define('APP_ENV', 'development');

// Inicia sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Função para conexão segura com MySQLi
 */
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        error_log("Erro de conexão: " . $conn->connect_error);
        return null;
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Gera token CSRF
 */
function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verifica token CSRF
 */
function verifyCsrfToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitiza input
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica se o usuário está logado no admin
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Redireciona para login se não autenticado
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: admin.php?action=login');
        exit;
    }
}

function getConfigValue($key, $section = 'hotspot') {
    $configFile = __DIR__ . '/config.ini';
    if (!file_exists($configFile)) return '';
    
    $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $currentSection = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^\[(.*)\]$/', $line, $matches)) {
            $currentSection = $matches[1];
            continue;
        }
        if ($currentSection !== $section) continue;
        if (isset($line[0]) && ($line[0] === ';' || $line[0] === '#')) continue;
        
        if (strpos($line, $key . ' =') !== false) {
            $value = trim(substr($line, strpos($line, '=') + 1));
            
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || 
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            $value = stripslashes($value);
            
            return trim($value);
        }
    }
    return '';
}
