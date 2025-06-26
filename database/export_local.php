<?php
// Script para exportar base de datos local a Railway
// Ejecutar desde localhost para generar dump SQL

echo "🔄 Exportando base de datos local...\n\n";

// Configuración localhost
$localhost_config = [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'sistema_tickets_kube',
    'username' => 'root',
    'password' => ''
];

try {
    // Conectar a localhost
    $dsn = "mysql:host={$localhost_config['host']};port={$localhost_config['port']};dbname={$localhost_config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $localhost_config['username'], $localhost_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conectado a base de datos local\n\n";
    
    // Obtener lista de tablas
    $tables_stmt = $pdo->query("SHOW TABLES");
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Tablas encontradas: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    echo "\n";
    
    // Generar nombre de archivo con timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "database/backup_local_to_railway_{$timestamp}.sql";
    
    // Abrir archivo para escribir
    $file = fopen($filename, 'w');
    if (!$file) {
        throw new Exception("No se pudo crear el archivo $filename");
    }
    
    // Escribir header
    fwrite($file, "-- Backup de base de datos local para Railway\n");
    fwrite($file, "-- Generado: " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- Base de datos origen: {$localhost_config['dbname']}\n\n");
    
    fwrite($file, "SET FOREIGN_KEY_CHECKS = 0;\n");
    fwrite($file, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
    fwrite($file, "SET time_zone = \"+00:00\";\n\n");
    
    $total_records = 0;
    
    // Exportar cada tabla
    foreach ($tables as $table) {
        echo "📦 Exportando tabla: $table...";
        
        // Obtener estructura de la tabla
        $create_stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $create_row = $create_stmt->fetch(PDO::FETCH_ASSOC);
        
        fwrite($file, "-- Estructura de tabla `$table`\n");
        fwrite($file, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($file, $create_row['Create Table'] . ";\n\n");
        
        // Obtener datos de la tabla
        $data_stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            fwrite($file, "-- Datos de tabla `$table`\n");
            
            // Obtener nombres de columnas
            $columns = array_keys($rows[0]);
            $columns_str = '`' . implode('`, `', $columns) . '`';
            
            // Escribir datos en lotes de 50 registros
            $batch_size = 50;
            for ($i = 0; $i < count($rows); $i += $batch_size) {
                $batch = array_slice($rows, $i, $batch_size);
                
                fwrite($file, "INSERT INTO `$table` ($columns_str) VALUES\n");
                
                $values = [];
                foreach ($batch as $row) {
                    $row_values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $row_values[] = 'NULL';
                        } else {
                            $row_values[] = $pdo->quote($value);
                        }
                    }
                    $values[] = '(' . implode(', ', $row_values) . ')';
                }
                
                fwrite($file, implode(",\n", $values) . ";\n\n");
            }
            
            $total_records += count($rows);
            echo " ✅ " . count($rows) . " registros\n";
        } else {
            echo " ⚪ Sin datos\n";
        }
    }
    
    fwrite($file, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fwrite($file, "\n-- Backup completado: " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- Total de registros exportados: $total_records\n");
    
    fclose($file);
    
    echo "\n🎉 ¡Exportación completada!\n";
    echo "📁 Archivo generado: $filename\n";
    echo "📊 Total de registros: $total_records\n";
    echo "💾 Tamaño del archivo: " . round(filesize($filename) / 1024, 2) . " KB\n\n";
    
    echo "🚀 Próximo paso:\n";
    echo "1. Subir este archivo a GitHub\n";
    echo "2. Ejecutar el script import_to_railway.php en Railway\n";
    
} catch(PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    exit(1);
} catch(Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
    exit(1);
}
?> 