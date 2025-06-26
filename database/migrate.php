<?php
$host = 'localhost';
$dbname = 'sistema_tickets_kube';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Iniciando migración de base de datos...\n";

    // Verificar si existe la columna title en tickets
    $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'title'");
    if ($stmt->rowCount() > 0) {
        // Migrar de title a subject
        $pdo->exec("ALTER TABLE tickets ADD COLUMN ticket_number VARCHAR(50) UNIQUE");
        $pdo->exec("ALTER TABLE tickets ADD COLUMN subject VARCHAR(255)");
        $pdo->exec("ALTER TABLE tickets ADD COLUMN category VARCHAR(100)");
        $pdo->exec("UPDATE tickets SET subject = title WHERE subject IS NULL");
        $pdo->exec("UPDATE tickets SET ticket_number = CONCAT('KUBE-', LPAD(id, 3, '0')) WHERE ticket_number IS NULL");
        echo "✓ Columnas migradas\n";
    }

    // Verificar si existe la columna status con valores antiguos
    $stmt = $pdo->query("SHOW COLUMNS FROM tickets WHERE Field = 'status'");
    $column = $stmt->fetch();
    if ($column && strpos($column['Type'], 'en_proceso') !== false) {
        // Cambiar enum de status
        $pdo->exec("ALTER TABLE tickets MODIFY COLUMN status ENUM('abierto', 'proceso', 'cerrado') DEFAULT 'abierto'");
        $pdo->exec("UPDATE tickets SET status = 'proceso' WHERE status = 'en_proceso'");
        echo "✓ Estados actualizados\n";
    }

    // Verificar si existe la columna priority con valores antiguos
    $stmt = $pdo->query("SHOW COLUMNS FROM tickets WHERE Field = 'priority'");
    $column = $stmt->fetch();
    if ($column && strpos($column['Type'], 'urgente') !== false) {
        // Cambiar enum de priority
        $pdo->exec("ALTER TABLE tickets MODIFY COLUMN priority ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media'");
        $pdo->exec("UPDATE tickets SET priority = 'critica' WHERE priority = 'urgente'");
        echo "✓ Prioridades actualizadas\n";
    }

    // Crear tabla de mensajes si no existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'ticket_messages'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE ticket_messages (
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
        echo "✓ Tabla ticket_messages creada\n";
    }

    // Crear tabla de adjuntos si no existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachments'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE ticket_attachments (
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
        echo "✓ Tabla ticket_attachments creada\n";
    }

    // Verificar si system_config existe y tiene la estructura correcta
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_config'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE system_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) UNIQUE NOT NULL,
                config_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
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
        echo "✓ Tabla system_config creada y configurada\n";
    }

    // Agregar columna closed_at si no existe
    $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'closed_at'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN closed_at TIMESTAMP NULL");
        echo "✓ Columna closed_at agregada\n";
    }

    echo "Migración completada exitosamente!\n";

} catch(PDOException $e) {
    die("Error en migración: " . $e->getMessage() . "\n");
}
?> 
$host = 'localhost';
$dbname = 'sistema_tickets_kube';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Iniciando migración de base de datos...\n";

    // Verificar si existe la columna title en tickets
    $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'title'");
    if ($stmt->rowCount() > 0) {
        // Migrar de title a subject
        $pdo->exec("ALTER TABLE tickets ADD COLUMN ticket_number VARCHAR(50) UNIQUE");
        $pdo->exec("ALTER TABLE tickets ADD COLUMN subject VARCHAR(255)");
        $pdo->exec("ALTER TABLE tickets ADD COLUMN category VARCHAR(100)");
        $pdo->exec("UPDATE tickets SET subject = title WHERE subject IS NULL");
        $pdo->exec("UPDATE tickets SET ticket_number = CONCAT('KUBE-', LPAD(id, 3, '0')) WHERE ticket_number IS NULL");
        echo "✓ Columnas migradas\n";
    }

    // Verificar si existe la columna status con valores antiguos
    $stmt = $pdo->query("SHOW COLUMNS FROM tickets WHERE Field = 'status'");
    $column = $stmt->fetch();
    if ($column && strpos($column['Type'], 'en_proceso') !== false) {
        // Cambiar enum de status
        $pdo->exec("ALTER TABLE tickets MODIFY COLUMN status ENUM('abierto', 'proceso', 'cerrado') DEFAULT 'abierto'");
        $pdo->exec("UPDATE tickets SET status = 'proceso' WHERE status = 'en_proceso'");
        echo "✓ Estados actualizados\n";
    }

    // Verificar si existe la columna priority con valores antiguos
    $stmt = $pdo->query("SHOW COLUMNS FROM tickets WHERE Field = 'priority'");
    $column = $stmt->fetch();
    if ($column && strpos($column['Type'], 'urgente') !== false) {
        // Cambiar enum de priority
        $pdo->exec("ALTER TABLE tickets MODIFY COLUMN priority ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media'");
        $pdo->exec("UPDATE tickets SET priority = 'critica' WHERE priority = 'urgente'");
        echo "✓ Prioridades actualizadas\n";
    }

    // Crear tabla de mensajes si no existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'ticket_messages'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE ticket_messages (
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
        echo "✓ Tabla ticket_messages creada\n";
    }

    // Crear tabla de adjuntos si no existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachments'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE ticket_attachments (
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
        echo "✓ Tabla ticket_attachments creada\n";
    }

    // Verificar si system_config existe y tiene la estructura correcta
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_config'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE system_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) UNIQUE NOT NULL,
                config_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
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
        echo "✓ Tabla system_config creada y configurada\n";
    }

    // Agregar columna closed_at si no existe
    $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'closed_at'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN closed_at TIMESTAMP NULL");
        echo "✓ Columna closed_at agregada\n";
    }

    echo "Migración completada exitosamente!\n";

} catch(PDOException $e) {
    die("Error en migración: " . $e->getMessage() . "\n");
}
?> 