<?php
// Script para importar backup de localhost hacia Railway
// Este script se ejecuta en Railway después de exportar los datos locales

require_once '../config.php';

echo "🚀 Importando backup hacia Railway...\n\n";

try {
    // Verificar que estamos en Railway
    $config = Config::getInstance();
    if (!$config->isRailway()) {
        throw new Exception("Este script solo debe ejecutarse en Railway");
    }
    
    echo "✅ Entorno detectado: Railway\n";
    echo "🗄️ Conectando a base de datos Railway...\n";
    
    // Conectar a Railway
    $pdo = $config->getDbConnection();
    echo "✅ Conexión exitosa a Railway MySQL\n\n";
    
    // Buscar el archivo de backup más reciente
    $backup_files = glob('../database/backup_local_to_railway_*.sql');
    if (empty($backup_files)) {
        throw new Exception("No se encontró ningún archivo de backup. Ejecuta primero export_local.php en localhost.");
    }
    
    // Ordenar por fecha (más reciente primero)
    usort($backup_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $backup_file = $backup_files[0];
    echo "📁 Archivo de backup encontrado: " . basename($backup_file) . "\n";
    echo "📊 Tamaño: " . round(filesize($backup_file) / 1024, 2) . " KB\n";
    echo "🕒 Fecha: " . date('Y-m-d H:i:s', filemtime($backup_file)) . "\n\n";
    
    // Leer el archivo SQL
    $sql_content = file_get_contents($backup_file);
    if ($sql_content === false) {
        throw new Exception("No se pudo leer el archivo de backup");
    }
    
    echo "🔄 Procesando archivo SQL...\n";
    
    // Dividir en declaraciones SQL individuales
    $statements = explode(';', $sql_content);
    $executed = 0;
    $errors = 0;
    
    // Separar CREATE TABLE de ALTER TABLE para evitar problemas con FK
    $create_statements = [];
    $insert_statements = [];
    $other_statements = [];
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        if (stripos($statement, 'CREATE TABLE') === 0) {
            // Remover constraint de foreign key del CREATE TABLE
            $statement = preg_replace('/,\s*CONSTRAINT[^,]*FOREIGN KEY[^,]*REFERENCES[^,]*ON DELETE[^,]*ON UPDATE[^,]*/', '', $statement);
            $statement = preg_replace('/,\s*CONSTRAINT[^,]*FOREIGN KEY[^,]*REFERENCES[^,]*ON DELETE[^,]*/', '', $statement);
            $statement = preg_replace('/,\s*CONSTRAINT[^,]*FOREIGN KEY[^,]*REFERENCES[^,]*/', '', $statement);
            $create_statements[] = $statement;
        } elseif (stripos($statement, 'INSERT INTO') === 0) {
            $insert_statements[] = $statement;
        } else {
            $other_statements[] = $statement;
        }
    }
    
    echo "📋 Organizando declaraciones:\n";
    echo "- CREATE TABLE: " . count($create_statements) . "\n";
    echo "- INSERT: " . count($insert_statements) . "\n";
    echo "- Otras: " . count($other_statements) . "\n\n";
    
    // Deshabilitar autocommit para transacción
    $pdo->beginTransaction();
    
    // Ejecutar en orden: DROP, SET, CREATE, INSERT
    $all_statements = array_merge($other_statements, $create_statements, $insert_statements);
    
    foreach ($all_statements as $statement) {
        try {
            $pdo->exec($statement);
            $executed++;
            
            // Mostrar progreso cada 5 declaraciones
            if ($executed % 5 === 0) {
                echo "⏳ Procesadas: $executed declaraciones\n";
            }
            
        } catch (PDOException $e) {
            $errors++;
            $error_msg = $e->getMessage();
            
            // Ignorar algunos errores conocidos
            if (strpos($error_msg, 'already exists') !== false ||
                strpos($error_msg, 'Failed to open the referenced table') !== false ||
                strpos($error_msg, 'Duplicate entry') !== false) {
                echo "⚠️  Info: Saltando declaración $executed (ya existe o FK temporal)\n";
                continue;
            }
            
            echo "⚠️  Warning en declaración $executed: " . $error_msg . "\n";
            
            // Si hay muchos errores críticos, abortar
            if ($errors > 15) {
                throw new Exception("Demasiados errores en la importación. Abortando.");
            }
        }
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    echo "\n✅ Importación completada!\n";
    echo "📈 Declaraciones ejecutadas: $executed\n";
    echo "⚠️  Errores/warnings: $errors\n\n";
    
    // Verificar tablas importadas
    echo "🔍 Verificando tablas importadas:\n";
    $tables_stmt = $pdo->query("SHOW TABLES");
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $total_records = 0;
    foreach ($tables as $table) {
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $count_stmt->fetchColumn();
        $total_records += $count;
        echo "- $table: $count registros\n";
    }
    
    echo "\n📊 Resumen final:\n";
    echo "- Tablas: " . count($tables) . "\n";
    echo "- Total registros: $total_records\n";
    echo "- Base de datos: " . $config->getDbConfig()['dbname'] . "\n";
    echo "- Host: " . $config->getDbConfig()['host'] . "\n\n";
    
    echo "🎉 ¡Base de datos Railway configurada exitosamente!\n";
    echo "🔗 Tu aplicación ya tiene todos los datos de localhost\n";
    
    // Verificar usuario admin
    $admin_stmt = $pdo->prepare("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
    $admin_stmt->execute();
    $admin = $admin_stmt->fetch();
    
    if ($admin) {
        echo "\n👤 Usuario admin encontrado: " . $admin['email'] . "\n";
        echo "🔑 Puedes hacer login con las mismas credenciales de localhost\n";
    }
    
} catch(PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    exit(1);
} catch(Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error general: " . $e->getMessage() . "\n";
    exit(1);
}
?> 