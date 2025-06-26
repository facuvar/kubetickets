<?php
// Script completo para Railway: Migración + Importación de datos
require_once '../config.php';

echo "🚀 Setup completo de Railway (Migración + Datos)...\n\n";

try {
    // Verificar que estamos en Railway
    $config = Config::getInstance();
    if (!$config->isRailway()) {
        throw new Exception("Este script solo debe ejecutarse en Railway");
    }
    
    echo "✅ Entorno detectado: Railway\n";
    
    // Conectar a Railway
    $pdo = $config->getDbConnection();
    echo "✅ Conexión exitosa a Railway MySQL\n\n";
    
    // PASO 1: Crear tablas (migración)
    echo "🔧 PASO 1: Creando estructura de base de datos...\n";
    
    // Verificar si las tablas ya existen
    $tables_check = $pdo->query("SHOW TABLES");
    $existing_tables = $tables_check->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($existing_tables) > 0) {
        echo "📋 Tablas existentes: " . implode(', ', $existing_tables) . "\n";
    } else {
        echo "📋 Base de datos vacía, ejecutando migración...\n";
    }
    
    // Ejecutar migración usando la conexión correcta
    echo "🔧 Creando tablas necesarias...\n";
    
    // Crear tabla users si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'agente', 'cliente') DEFAULT 'cliente',
            company VARCHAR(100) NULL,
            phone VARCHAR(20) NULL,
            status ENUM('activo', 'inactivo') DEFAULT 'activo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Crear tabla tickets si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            status ENUM('abierto', 'proceso', 'cerrado') DEFAULT 'abierto',
            priority ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
            cliente_id INT NOT NULL,
            agente_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at TIMESTAMP NULL,
            ticket_number VARCHAR(50) UNIQUE NULL,
            subject VARCHAR(255) NULL,
            category VARCHAR(100) NULL,
            FOREIGN KEY (cliente_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (agente_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    // Crear tabla ticket_messages si no existe
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
    
    // Crear tabla ticket_attachments si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NULL,
            message_id INT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            uploaded_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Crear tabla system_config si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(50) UNIQUE NOT NULL,
            config_value TEXT NULL,
            description VARCHAR(200) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Insertar configuración inicial si no existe
    $default_config = [
        ['company_name', 'KubeAgency', 'Nombre de la empresa'],
        ['company_email', 'info@kubeagency.co', 'Email principal de notificaciones'],
        ['tickets_per_page', '20', 'Número de tickets por página'],
        ['max_file_size', '10485760', 'Tamaño máximo de archivos en bytes (10MB)'],
        ['allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,gif,txt,zip,rar', 'Tipos de archivo permitidos'],
        ['auto_assign_tickets', '0', 'Asignación automática de tickets (0=no, 1=sí)'],
        ['email_notifications', '1', 'Enviar notificaciones por email (0=no, 1=sí)'],
        ['ticket_auto_close_days', '30', 'Días para cerrar tickets automáticamente (0=nunca)']
    ];
    
    $config_stmt = $pdo->prepare("INSERT IGNORE INTO system_config (config_key, config_value, description) VALUES (?, ?, ?)");
    foreach ($default_config as $config_item) {
        $config_stmt->execute($config_item);
    }
    
    echo "✅ Migración completada\n\n";
    
    // PASO 2: Verificar si ya hay datos
    echo "🔧 PASO 2: Verificando datos existentes...\n";
    
    $admin_check = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@kubeagency.co'");
    $admin_exists = $admin_check->fetchColumn() > 0;
    
    if ($admin_exists) {
        echo "⚠️  Los datos ya fueron importados previamente.\n";
        echo "🔍 Saltando importación...\n\n";
    } else {
        echo "📥 Base de datos vacía, importando datos...\n\n";
        
        // PASO 3: Importar usuarios
        echo "👥 Importando usuarios...\n";
        $users_data = [
            [1, 'Administrador KubeAgency', 'admin@kubeagency.co', '$2y$10$LQ3y9fZMJDw56/40KnFiLOGSjyT50z5x0WbIooK9c2VVvdxMJMpGK', 'admin', 'KubeAgency'],
            [2, 'Agente de Soporte', 'agente@kubeagency.co', '$2y$10$CXyYwariV.r0mTXpI/pej.1DS3YbPHYjFK2ogeHLL.lcI7L6ZXy4K', 'agente', 'KubeAgency'],
            [3, 'Cliente Demo', 'cliente@kubeagency.co', '$2y$10$0SPEx1wI8IItNaEUFjjsaulK8TCTMHY3eiXZ/EdzOqoFxHRGTvM2O', 'cliente', 'Empresa Demo'],
            [4, 'Facundo', 'facundo@kubeagency.co', '$2y$10$2SUY8p5J54q1U1RHRXAmKOg0qMhoa/F6A5/2QgP60qmOPMDWlLFEe', 'admin', 'KubeAgency'],
            [5, 'facundo', 'facundo@maberik.com', '$2y$10$5tG2hMbRnfJx4m3gK1HLN.obkIH.k2ES9GWhyGcEVu0y1eNQzNiuG', 'cliente', 'maberik.com'],
            [10, 'Pepe LePosh', 'vargues@gmail.com', '$2y$10$vkktBB0pqmzLVkGINhf2metqo4H69YAmqAt7mweajyLh5IkAcB9iW', 'cliente', 'Polleria Pepe']
        ];
        
        $user_stmt = $pdo->prepare("INSERT IGNORE INTO users (id, name, email, password, role, company, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'activo', NOW(), NOW())");
        
        foreach ($users_data as $user) {
            $user_stmt->execute($user);
        }
        echo "✅ " . count($users_data) . " usuarios importados\n\n";
        
        // PASO 4: Importar tickets
        echo "🎫 Importando tickets...\n";
        $tickets_data = [
            [1, 'No andan los mails', 'No funciona algo', 'abierto', 'alta', 3, null, 'KUBE-001', 'No andan los mails'],
            [2, 'Bot problemas', 'es un problema que viene desde ayer', 'abierto', 'alta', 3, null, 'KUBE-002', 'no me anda el bot'],
            [3, 'Problemas varios', 'no andan las cosas de la maica', 'abierto', 'media', 3, null, 'KUBE-003', 'no me anda el bot'],
            [4, 'Issue crítico', 'no andan las cosas de la maica', 'abierto', 'media', 3, 2, 'KUBE-004', 'no me anda el bot'],
            [5, 'Bot agresivo', 'el bot se vuelve loco yputea a l gente', 'abierto', 'media', 3, null, 'KUBE-005', 'no me anda el botija'],
            [6, 'Bot insulta', 'el bot insulta y les dice Pollo a todos los clientes', 'abierto', 'alta', 3, null, 'KUBE-006', 'no me anda el bot'],
            [7, 'Problema pollo', 'le dice pollo a todos', 'abierto', 'alta', 3, null, 'KUBE-007', 'no me anda el bot'],
            [8, 'Robot issues', 'pollea y pollea', 'cerrado', 'alta', 3, null, 'KUBE-008', 'no me anda el robot']
        ];
        
        $ticket_stmt = $pdo->prepare("INSERT IGNORE INTO tickets (id, title, description, status, priority, cliente_id, agente_id, ticket_number, subject, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        
        foreach ($tickets_data as $ticket) {
            $ticket_stmt->execute($ticket);
        }
        echo "✅ " . count($tickets_data) . " tickets importados\n\n";
        
        // PASO 5: Importar mensaje de ticket
        echo "💬 Importando mensajes...\n";
        $message_stmt = $pdo->prepare("INSERT IGNORE INTO ticket_messages (id, ticket_id, user_id, message, is_internal, created_at) VALUES (1, 4, 1, 'Estamos trabajando en eso, en breve estara solucionado', 0, NOW())");
        $message_stmt->execute();
        echo "✅ 1 mensaje importado\n\n";
        
        // PASO 6: Actualizar configuración
        echo "⚙️ Actualizando configuración del sistema...\n";
        $config_updates = [
            ['company_name', 'KubeAgency'],
            ['company_email', 'info@kubeagency.co'],
            ['email_notifications', '1'],
            ['max_file_size', '10485760'],
            ['allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,gif,txt,zip,rar']
        ];
        
        $config_stmt = $pdo->prepare("UPDATE system_config SET config_value = ? WHERE config_key = ?");
        foreach ($config_updates as $config_item) {
            $config_stmt->execute([$config_item[1], $config_item[0]]);
        }
        echo "✅ Configuración actualizada\n\n";
    }
    
    // PASO 7: Verificar resultado final
    echo "🔍 VERIFICACIÓN FINAL:\n";
    
    $tables = ['users', 'tickets', 'ticket_messages', 'ticket_attachments', 'system_config'];
    $total_records = 0;
    
    foreach ($tables as $table) {
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $count_stmt->fetchColumn();
        $total_records += $count;
        echo "- $table: $count registros\n";
    }
    
    echo "\n📊 RESUMEN COMPLETO:\n";
    echo "- Total de tablas: " . count($tables) . "\n";
    echo "- Total de registros: $total_records\n";
    echo "- Base de datos: " . $config->getDbConfig()['dbname'] . "\n";
    echo "- Host: " . $config->getDbConfig()['host'] . "\n\n";
    
    // Verificar usuario admin
    $admin_stmt = $pdo->prepare("SELECT name, email FROM users WHERE role = 'admin' ORDER BY id");
    $admin_stmt->execute();
    $admins = $admin_stmt->fetchAll();
    
    echo "👤 USUARIOS ADMIN DISPONIBLES:\n";
    foreach ($admins as $admin) {
        echo "- {$admin['name']} ({$admin['email']})\n";
    }
    
    echo "\n🎉 ¡RAILWAY CONFIGURADO EXITOSAMENTE!\n";
    echo "🔗 Tu aplicación está lista con todos los datos de localhost\n";
    echo "🔑 Login principal: admin@kubeagency.co / admin123\n";
    echo "🌐 Puedes acceder al sistema principal desde la URL raíz\n";
    
} catch(PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    exit(1);
} catch(Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
    exit(1);
}
?> 