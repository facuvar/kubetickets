<?php
// Configuración inicial de la base de datos

$host = 'localhost';
$dbname = 'sistema_tickets_kube';
$username = 'root';
$password = '';

try {
    // Conectar a MySQL
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear la base de datos si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Tabla de usuarios
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'agente', 'cliente') NOT NULL,
            company VARCHAR(255),
            phone VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Tabla de tickets actualizada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(50) UNIQUE NOT NULL,
            subject VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            status ENUM('abierto', 'proceso', 'cerrado') DEFAULT 'abierto',
            priority ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
            cliente_id INT NOT NULL,
            agente_id INT NULL,
            category VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at TIMESTAMP NULL,
            FOREIGN KEY (cliente_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (agente_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    // Tabla de respuestas/mensajes de tickets
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            is_internal BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Tabla de adjuntos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NULL,
            message_id INT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            uploaded_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Tabla de configuración del sistema
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(100) UNIQUE NOT NULL,
            config_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Insertar usuarios demo
    $users = [
        ['Admin Principal', 'admin@kubeagency.co', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'KubeAgency'],
        ['Facundo Admin', 'facundo@kubeagency.co', password_hash('facundo123', PASSWORD_DEFAULT), 'admin', 'KubeAgency'],
        ['Agente Soporte', 'agente@kubeagency.co', password_hash('agente123', PASSWORD_DEFAULT), 'agente', 'KubeAgency'],
        ['Cliente Demo', 'cliente@kubeagency.co', password_hash('cliente123', PASSWORD_DEFAULT), 'cliente', 'Empresa Demo']
    ];

    foreach ($users as $user) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (name, email, password, role, company) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($user);
    }

    // Insertar tickets demo
    $tickets = [
        ['KUBE-001', 'Problema con el servidor', 'El servidor no responde desde esta mañana', 'abierto', 'alta', 4, 3],
        ['KUBE-002', 'Error en aplicación web', 'La aplicación muestra error 500', 'proceso', 'media', 4, 3],
        ['KUBE-003', 'Solicitud de nueva funcionalidad', 'Necesitamos agregar un módulo de reportes', 'cerrado', 'baja', 4, 3]
    ];

    foreach ($tickets as $ticket) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO tickets (ticket_number, subject, description, status, priority, cliente_id, agente_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($ticket);
    }

    // Insertar configuración predeterminada
    $config = [
        ['company_name', 'KubeAgency'],
        ['admin_email', 'info@kubeagency.co'],
        ['ticket_prefix', 'KUBE'],
        ['auto_assign', '1'],
        ['email_notifications', '1'],
        ['default_priority', 'media'],
        ['max_file_size', '10'],
        ['allowed_extensions', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip'],
        ['theme_color', '#1e3a8a']
    ];

    foreach ($config as $conf) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_config (config_key, config_value) VALUES (?, ?)");
        $stmt->execute($conf);
    }

    echo "Base de datos configurada correctamente.\n";

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?> 
// Configuración inicial de la base de datos

$host = 'localhost';
$dbname = 'sistema_tickets_kube';
$username = 'root';
$password = '';

try {
    // Conectar a MySQL
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear la base de datos si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Tabla de usuarios
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'agente', 'cliente') NOT NULL,
            company VARCHAR(255),
            phone VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Tabla de tickets actualizada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(50) UNIQUE NOT NULL,
            subject VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            status ENUM('abierto', 'proceso', 'cerrado') DEFAULT 'abierto',
            priority ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
            cliente_id INT NOT NULL,
            agente_id INT NULL,
            category VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at TIMESTAMP NULL,
            FOREIGN KEY (cliente_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (agente_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    // Tabla de respuestas/mensajes de tickets
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            is_internal BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Tabla de adjuntos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NULL,
            message_id INT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            uploaded_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Tabla de configuración del sistema
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(100) UNIQUE NOT NULL,
            config_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Insertar usuarios demo
    $users = [
        ['Admin Principal', 'admin@kubeagency.co', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'KubeAgency'],
        ['Facundo Admin', 'facundo@kubeagency.co', password_hash('facundo123', PASSWORD_DEFAULT), 'admin', 'KubeAgency'],
        ['Agente Soporte', 'agente@kubeagency.co', password_hash('agente123', PASSWORD_DEFAULT), 'agente', 'KubeAgency'],
        ['Cliente Demo', 'cliente@kubeagency.co', password_hash('cliente123', PASSWORD_DEFAULT), 'cliente', 'Empresa Demo']
    ];

    foreach ($users as $user) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (name, email, password, role, company) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($user);
    }

    // Insertar tickets demo
    $tickets = [
        ['KUBE-001', 'Problema con el servidor', 'El servidor no responde desde esta mañana', 'abierto', 'alta', 4, 3],
        ['KUBE-002', 'Error en aplicación web', 'La aplicación muestra error 500', 'proceso', 'media', 4, 3],
        ['KUBE-003', 'Solicitud de nueva funcionalidad', 'Necesitamos agregar un módulo de reportes', 'cerrado', 'baja', 4, 3]
    ];

    foreach ($tickets as $ticket) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO tickets (ticket_number, subject, description, status, priority, cliente_id, agente_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($ticket);
    }

    // Insertar configuración predeterminada
    $config = [
        ['company_name', 'KubeAgency'],
        ['admin_email', 'info@kubeagency.co'],
        ['ticket_prefix', 'KUBE'],
        ['auto_assign', '1'],
        ['email_notifications', '1'],
        ['default_priority', 'media'],
        ['max_file_size', '10'],
        ['allowed_extensions', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip'],
        ['theme_color', '#1e3a8a']
    ];

    foreach ($config as $conf) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_config (config_key, config_value) VALUES (?, ?)");
        $stmt->execute($conf);
    }

    echo "Base de datos configurada correctamente.\n";

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?> 