<?php
/**
 * Conexão com banco de dados (compatibilidade com código legado)
 * Usa o sistema de configuração centralizado
 */

define('HOTSPOT_ACCESS', true);
require_once __DIR__ . '/config.php';

$MySQLi = getDbConnection();

if (!$MySQLi) {
    error_log("Falha na conexão com o banco de dados.");
}
