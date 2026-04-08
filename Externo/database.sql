-- ============================================
-- Banco de dados: Hotspot Charles WiFi
-- ============================================

CREATE DATABASE IF NOT EXISTS `hotspot` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hotspot`;

-- Tabela de dados dos usuários
CREATE TABLE IF NOT EXISTS `dados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cpf` varchar(20) NOT NULL,
  `nome` varchar(60) NOT NULL,
  `sobrenome` varchar(60) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `link_orig` varchar(500) DEFAULT NULL,
  `mac` varchar(20) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('ativo','bloqueado','expirado') DEFAULT 'ativo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cpf` (`cpf`),
  KEY `email` (`email`),
  KEY `mac` (`mac`),
  KEY `data_cadastro` (`data_cadastro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de sessões dos usuários
CREATE TABLE IF NOT EXISTS `sessoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `mac` varchar(20) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `data_inicio` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_fim` datetime DEFAULT NULL,
  `bytes_in` bigint(20) DEFAULT 0,
  `bytes_out` bigint(20) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `mac` (`mac`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configurações do admin
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de estatísticas diárias
CREATE TABLE IF NOT EXISTS `estatisticas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data` date NOT NULL,
  `novos_cadastros` int(11) DEFAULT 0,
  `sessoes_ativas` int(11) DEFAULT 0,
  `total_bytes_in` bigint(20) DEFAULT 0,
  `total_bytes_out` bigint(20) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data` (`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O admin deve ser criado via setup.php (nunca via SQL dump)
