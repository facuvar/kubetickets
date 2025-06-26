<?php
// Script de deploy para Railway - Configuración automática de base de datos
require_once 'config_railway.php';

echo "🚀 Iniciando configuración para Railway...\n\n";

try {
    // Obtener configuración de base de datos
    $db_config = getRailwayDbConfig();
    
    echo "📊 Configuración de base de datos:\n";
    echo "- Host: {$db_config['host']}\n";
    echo "- Puerto: {$db_config['port']}\n";
    echo "- Base de datos: {$db_config['dbname']}\n";
    echo "- Usuario: {$db_config['username']}\n\n";
    
    // Conectar a la base de datos
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión a base de datos exitosa\n\n";
    
    // Verificar si las tablas ya existen
    $stmt = $pdo->query("SHOW TABLES");
    $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($existing_tables) > 0) {
        echo "📋 Tablas existentes encontradas:\n";
        foreach ($existing_tables as $table) {
            echo "- $table\n";
        }
        echo "\n⚠️ Base de datos ya configurada. Saltando migración.\n";
    } else {
        echo "🔧 Base de datos vacía. Ejecutando migración...\n\n";
        
        // Ejecutar script de migración
        include 'database/migrate.php';
        
        echo "\n✅ Migración completada\n";
    }
    
    // Verificar URL base
    $base_url = getRailwayBaseUrl();
    echo "\n🌐 URL base configurada: $base_url\n";
    
    // Verificar SendGrid
    $sendgrid_config = getRailwaySendGridConfig();
    echo "\n📧 SendGrid configurado:\n";
    echo "- Email: {$sendgrid_config['from_email']}\n";
    echo "- API Key: " . (strlen($sendgrid_config['api_key']) > 10 ? '✅ Configurada' : '❌ No configurada') . "\n";
    
    echo "\n🎉 ¡Configuración de Railway completada exitosamente!\n";
    echo "🔗 Tu aplicación estará disponible en: $base_url\n";
    
} catch(PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    echo "\n🔍 Verifica las variables de entorno de Railway:\n";
    echo "- MYSQLHOST\n";
    echo "- MYSQLPORT\n";
    echo "- MYSQLDATABASE\n";
    echo "- MYSQLUSER\n";
    echo "- MYSQLPASSWORD\n";
    exit(1);
} catch(Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
    exit(1);
}
?> 