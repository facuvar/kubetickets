<?php
session_start();

// Verificar acceso (solo admin)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

try {
    $config = Config::getInstance();
    $pdo = $config->getDbConnection();
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener archivos de la base de datos
$stmt = $pdo->query("
    SELECT ta.*, t.ticket_number, u.name as uploaded_by_name
    FROM ticket_attachments ta
    LEFT JOIN tickets t ON ta.ticket_id = t.id
    LEFT JOIN users u ON ta.uploaded_by = u.id
    ORDER BY ta.created_at DESC
");
$db_files = $stmt->fetchAll();

// Obtener archivos del sistema de archivos
$upload_dir = __DIR__ . '/uploads/tickets/';
$fs_files = [];
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && $file !== '.gitkeep') {
            $file_path = $upload_dir . $file;
            if (is_file($file_path)) {
                $fs_files[] = [
                    'filename' => $file,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path),
                    'path' => $file_path
                ];
            }
        }
    }
}

// Buscar archivo específico
$search_file = $_GET['search'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Uploads - KubeAgency</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1d29;
            color: #e2e8f0;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: #2d3748;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #4a5568;
        }

        .card h3 {
            color: #4fd1c7;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #4a5568;
            font-size: 13px;
        }

        th {
            background: #374151;
            color: #4fd1c7;
        }

        .btn {
            background: #4fd1c7;
            color: #1a1d29;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            font-weight: 500;
        }

        .btn:hover {
            background: #38b2ac;
        }

        .search-box {
            padding: 8px 12px;
            background: #374151;
            border: 1px solid #4a5568;
            border-radius: 6px;
            color: #e2e8f0;
            margin-right: 10px;
        }

        .status-ok { color: #10b981; }
        .status-error { color: #ef4444; }
        .status-warning { color: #f59e0b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h3><i class="fas fa-cog"></i> Diagnóstico de Archivos de Uploads</h3>
            
            <form method="GET" style="margin-bottom: 20px;">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_file); ?>" 
                       placeholder="Buscar archivo específico..." class="search-box">
                <button type="submit" class="btn">Buscar</button>
                <a href="debug-uploads.php" class="btn">Limpiar</a>
            </form>

            <p><strong>Directorio de uploads:</strong> <?php echo $upload_dir; ?></p>
            <p><strong>¿Directorio existe?</strong> 
                <span class="<?php echo is_dir($upload_dir) ? 'status-ok' : 'status-error'; ?>">
                    <?php echo is_dir($upload_dir) ? 'Sí' : 'No'; ?>
                </span>
            </p>
            <p><strong>¿Directorio escribible?</strong> 
                <span class="<?php echo is_writable($upload_dir) ? 'status-ok' : 'status-error'; ?>">
                    <?php echo is_writable($upload_dir) ? 'Sí' : 'No'; ?>
                </span>
            </p>
        </div>

        <?php if ($search_file): ?>
        <div class="card">
            <h3><i class="fas fa-search"></i> Resultado de búsqueda: "<?php echo htmlspecialchars($search_file); ?>"</h3>
            
            <?php
            $found_in_fs = false;
            $found_in_db = false;
            
            // Buscar en sistema de archivos
            foreach ($fs_files as $file) {
                if (strpos($file['filename'], $search_file) !== false) {
                    $found_in_fs = true;
                    echo "<p class='status-ok'><i class='fas fa-check'></i> Archivo encontrado en sistema de archivos:</p>";
                    echo "<ul>";
                    echo "<li><strong>Nombre:</strong> " . htmlspecialchars($file['filename']) . "</li>";
                    echo "<li><strong>Tamaño:</strong> " . number_format($file['size'] / 1024, 2) . " KB</li>";
                    echo "<li><strong>Modificado:</strong> " . date('d/m/Y H:i:s', $file['modified']) . "</li>";
                    echo "<li><strong>URL directa:</strong> <a href='uploads/tickets/" . urlencode($file['filename']) . "' target='_blank'>Ver archivo</a></li>";
                    echo "<li><strong>URL con script:</strong> <a href='serve-file.php?file=" . urlencode($file['filename']) . "' target='_blank'>Ver archivo (script)</a></li>";
                    echo "</ul>";
                    break;
                }
            }
            
            // Buscar en base de datos
            foreach ($db_files as $file) {
                if (strpos($file['filename'], $search_file) !== false) {
                    $found_in_db = true;
                    echo "<p class='status-ok'><i class='fas fa-check'></i> Archivo encontrado en base de datos:</p>";
                    echo "<ul>";
                    echo "<li><strong>Ticket:</strong> " . htmlspecialchars($file['ticket_number'] ?? 'N/A') . "</li>";
                    echo "<li><strong>Nombre original:</strong> " . htmlspecialchars($file['original_filename']) . "</li>";
                    echo "<li><strong>Subido por:</strong> " . htmlspecialchars($file['uploaded_by_name']) . "</li>";
                    echo "<li><strong>Fecha:</strong> " . date('d/m/Y H:i:s', strtotime($file['created_at'])) . "</li>";
                    echo "</ul>";
                    break;
                }
            }
            
            if (!$found_in_fs && !$found_in_db) {
                echo "<p class='status-error'><i class='fas fa-times'></i> Archivo no encontrado ni en sistema de archivos ni en base de datos.</p>";
            } elseif (!$found_in_fs) {
                echo "<p class='status-warning'><i class='fas fa-exclamation-triangle'></i> Archivo existe en base de datos pero no en sistema de archivos.</p>";
            } elseif (!$found_in_db) {
                echo "<p class='status-warning'><i class='fas fa-exclamation-triangle'></i> Archivo existe en sistema de archivos pero no en base de datos.</p>";
            }
            ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3><i class="fas fa-hdd"></i> Archivos en Sistema de Archivos (<?php echo count($fs_files); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre del Archivo</th>
                        <th>Tamaño</th>
                        <th>Última Modificación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fs_files as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['filename']); ?></td>
                        <td><?php echo number_format($file['size'] / 1024, 2); ?> KB</td>
                        <td><?php echo date('d/m/Y H:i:s', $file['modified']); ?></td>
                        <td>
                            <a href="uploads/tickets/<?php echo urlencode($file['filename']); ?>" target="_blank" class="btn">Ver</a>
                            <a href="serve-file.php?file=<?php echo urlencode($file['filename']); ?>" target="_blank" class="btn">Script</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3><i class="fas fa-database"></i> Archivos en Base de Datos (<?php echo count($db_files); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Nombre Original</th>
                        <th>Archivo en Servidor</th>
                        <th>Tamaño</th>
                        <th>Subido por</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($db_files as $file): ?>
                    <?php
                    $file_exists = file_exists($upload_dir . $file['filename']);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['ticket_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($file['original_filename']); ?></td>
                        <td><?php echo htmlspecialchars($file['filename']); ?></td>
                        <td><?php echo number_format($file['file_size'] / 1024, 2); ?> KB</td>
                        <td><?php echo htmlspecialchars($file['uploaded_by_name']); ?></td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($file['created_at'])); ?></td>
                        <td class="<?php echo $file_exists ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $file_exists ? 'Existe' : 'Faltante'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <p><a href="tickets.php" class="btn">Volver a Tickets</a></p>
        </div>
    </div>
</body>
</html> 