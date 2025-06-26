-- Backup de base de datos local para Railway
-- Generado: 2025-06-26 19:07:59
-- Base de datos origen: sistema_tickets_kube

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Estructura de tabla `system_config`
DROP TABLE IF EXISTS `system_config`;
CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(50) NOT NULL,
  `config_value` text DEFAULT NULL,
  `description` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Datos de tabla `system_config`
INSERT INTO `system_config` (`id`, `config_key`, `config_value`, `description`, `created_at`, `updated_at`) VALUES
('1', 'company_name', 'KubeAgency', 'Nombre de la empresa', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('2', 'company_email', 'info@kubeagency.co', 'Email principal de notificaciones', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('3', 'tickets_per_page', '20', 'Número de tickets por página', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('4', 'max_file_size', '100', 'Tamaño máximo de archivos en bytes (10MB)', '2025-06-25 17:56:53', '2025-06-25 18:57:04'),
('5', 'allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,gif,txt,zip,rar', 'Tipos de archivo permitidos', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('6', 'auto_assign_tickets', '0', 'Asignación automática de tickets (0=no, 1=sí)', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('7', 'email_notifications', '1', 'Enviar notificaciones por email (0=no, 1=sí)', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('8', 'ticket_auto_close_days', '30', 'Días para cerrar tickets automáticamente (0=nunca)', '2025-06-25 17:56:53', '2025-06-25 17:56:53');

-- Estructura de tabla `ticket_attachments`
DROP TABLE IF EXISTS `ticket_attachments`;
CREATE TABLE `ticket_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) DEFAULT NULL,
  `message_id` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `message_id` (`message_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `ticket_attachments_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_attachments_ibfk_2` FOREIGN KEY (`message_id`) REFERENCES `ticket_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_attachments_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Datos de tabla `ticket_attachments`
INSERT INTO `ticket_attachments` (`id`, `ticket_id`, `message_id`, `filename`, `original_filename`, `file_path`, `file_size`, `file_type`, `uploaded_by`, `created_at`) VALUES
('1', '5', NULL, '685d5e156f155_image-scanning.png', 'image-scanning.png', '', '2464125', 'png', '3', '2025-06-26 11:49:57'),
('2', '6', NULL, '685d5e8e31349_cafe-martinez.jpg', 'cafe-martinez.jpg', '', '283864', 'jpg', '3', '2025-06-26 11:51:58'),
('3', '7', NULL, '685d5eed3de32_cafe-martinez.jpg', 'cafe-martinez.jpg', '', '283864', 'jpg', '3', '2025-06-26 11:53:33'),
('4', '8', NULL, '685d5f866ccf3_cafe-martinez.jpg', 'cafe-martinez.jpg', '', '283864', 'jpg', '3', '2025-06-26 11:56:06');

-- Estructura de tabla `ticket_messages`
DROP TABLE IF EXISTS `ticket_messages`;
CREATE TABLE `ticket_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ticket_messages_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Datos de tabla `ticket_messages`
INSERT INTO `ticket_messages` (`id`, `ticket_id`, `user_id`, `message`, `is_internal`, `created_at`) VALUES
('1', '4', '1', 'Estamos trabajando en eso, en breve estara solucionado', '0', '2025-06-26 08:31:41');

-- Estructura de tabla `tickets`
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `status` enum('abierto','proceso','cerrado') DEFAULT 'abierto',
  `priority` enum('baja','media','alta','critica') DEFAULT 'media',
  `cliente_id` int(11) NOT NULL,
  `agente_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `ticket_number` varchar(50) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `cliente_id` (`cliente_id`),
  KEY `agente_id` (`agente_id`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`agente_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Datos de tabla `tickets`
INSERT INTO `tickets` (`id`, `title`, `description`, `status`, `priority`, `cliente_id`, `agente_id`, `created_at`, `updated_at`, `closed_at`, `ticket_number`, `subject`, `category`) VALUES
('1', 'No andan los mails', 'No funciona algo', 'abierto', 'alta', '3', NULL, '2025-06-25 17:59:41', '2025-06-25 18:09:43', NULL, 'KUBE-001', 'No andan los mails', NULL),
('2', '', 'es un problema que viene desde ayer', 'abierto', 'alta', '3', NULL, '2025-06-25 19:01:42', '2025-06-25 19:01:42', NULL, 'KUBE-002', 'no me anda el bot', 'tecnico'),
('3', '', 'no andan las cosas de la maica', 'abierto', 'media', '3', NULL, '2025-06-25 21:19:16', '2025-06-25 21:19:16', NULL, 'KUBE-003', 'no me anda el bot', 'cuenta'),
('4', '', 'no andan las cosas de la maica', 'abierto', 'media', '3', '2', '2025-06-26 08:23:28', '2025-06-26 08:31:41', NULL, 'KUBE-004', 'no me anda el bot', 'cuenta'),
('5', '', 'el bot se vuelve loco yputea a l gente', 'abierto', 'media', '3', NULL, '2025-06-26 11:49:57', '2025-06-26 11:49:57', NULL, 'KUBE-005', 'no me anda el botija', 'cuenta'),
('6', '', 'el bot insulta y les dice Pollo a todos los clientes', 'abierto', 'alta', '3', NULL, '2025-06-26 11:51:58', '2025-06-26 11:51:58', NULL, 'KUBE-006', 'no me anda el bot', 'facturacion'),
('7', '', 'le dice pollo a todos', 'abierto', 'alta', '3', NULL, '2025-06-26 11:53:33', '2025-06-26 11:53:33', NULL, 'KUBE-007', 'no me anda el bot', 'cuenta'),
('8', '', 'pollea y pollea', 'cerrado', 'alta', '3', NULL, '2025-06-26 11:56:06', '2025-06-26 12:22:11', '2025-06-26 12:22:11', 'KUBE-008', 'no me anda el robot', 'funcionalidad');

-- Estructura de tabla `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','agente','cliente') NOT NULL DEFAULT 'cliente',
  `company` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('activo','inactivo') DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Datos de tabla `users`
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `company`, `phone`, `status`, `created_at`, `updated_at`) VALUES
('1', 'Administrador KubeAgency', 'admin@kubeagency.co', '$2y$10$LQ3y9fZMJDw56/40KnFiLOGSjyT50z5x0WbIooK9c2VVvdxMJMpGK', 'admin', 'KubeAgency', NULL, 'activo', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('2', 'Agente de Soporte', 'agente@kubeagency.co', '$2y$10$CXyYwariV.r0mTXpI/pej.1DS3YbPHYjFK2ogeHLL.lcI7L6ZXy4K', 'agente', 'KubeAgency', NULL, 'activo', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('3', 'Cliente Demo', 'cliente@kubeagency.co', '$2y$10$0SPEx1wI8IItNaEUFjjsaulK8TCTMHY3eiXZ/EdzOqoFxHRGTvM2O', 'cliente', 'Empresa Demo', NULL, 'activo', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('4', 'Facundo', 'facundo@kubeagency.co', '$2y$10$2SUY8p5J54q1U1RHRXAmKOg0qMhoa/F6A5/2QgP60qmOPMDWlLFEe', 'admin', 'KubeAgency', NULL, 'activo', '2025-06-25 17:56:53', '2025-06-25 17:56:53'),
('5', 'facundo', 'facundo@maberik.com', '$2y$10$5tG2hMbRnfJx4m3gK1HLN.obkIH.k2ES9GWhyGcEVu0y1eNQzNiuG', 'cliente', 'maberik.com', NULL, 'activo', '2025-06-25 17:57:59', '2025-06-25 17:57:59'),
('10', 'Pepe LePosh', 'vargues@gmail.com', '$2y$10$vkktBB0pqmzLVkGINhf2metqo4H69YAmqAt7mweajyLh5IkAcB9iW', 'cliente', 'Polleria Pepe', NULL, 'activo', '2025-06-26 12:47:00', '2025-06-26 12:47:00');

SET FOREIGN_KEY_CHECKS = 1;

-- Backup completado: 2025-06-26 19:07:59
-- Total de registros exportados: 27
