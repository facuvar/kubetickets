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

$action = $_GET['action'] ?? '';
$message = '';
$error = '';

if ($action === 'cleanup_orphaned_db') {
    // Limpiar registros de archivos que no existen físicamente
    $stmt = $pdo->query("SELECT id, filename FROM ticket_attachments");
    $attachments = $stmt->fetchAll();
    
    $removed_count = 0;
    foreach ($attachments as $attachment) {
        $file_path = __DIR__ . '/uploads/tickets/' . $attachment['filename'];
        if (!file_exists($file_path)) {
            $delete_stmt = $pdo->prepare("DELETE FROM ticket_attachments WHERE id = ?");
            $delete_stmt->execute([$attachment['id']]);
            $removed_count++;
        }
    }
    
    $message = "Se eliminaron $removed_count registros huérfanos de la base de datos.";
    
} elseif ($action === 'cleanup_orphaned_files') {
    // Limpiar archivos que no tienen registro en base de datos
    $upload_dir = __DIR__ . '/uploads/tickets/';
    $files = scandir($upload_dir);
    
    // Obtener todos los filenames de la BD
    $stmt = $pdo->query("SELECT filename FROM ticket_attachments");
    $db_filenames = array_column($stmt->fetchAll(), 'filename');
    
    $removed_count = 0;
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && $file !== '.gitkeep') {
            if (!in_array($file, $db_filenames)) {
                $file_path = $upload_dir . $file;
                if (is_file($file_path)) {
                    unlink($file_path);
                    $removed_count++;
                }
            }
        }
    }
    
    $message = "Se eliminaron $removed_count archivos huérfanos del sistema de archivos.";
}

// Obtener estadísticas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ticket_attachments");
$total_db = $stmt->fetch()['total'];

$upload_dir = __DIR__ . '/uploads/tickets/';
$files = scandir($upload_dir);
$total_files = count(array_filter($files, function($file) use ($upload_dir) {
    return $file !== '.' && $file !== '..' && $file !== '.gitkeep' && is_file($upload_dir . $file);
}));

// Verificar inconsistencias
$stmt = $pdo->query("SELECT id, filename FROM ticket_attachments");
$attachments = $stmt->fetchAll();

$missing_files = 0;
foreach ($attachments as $attachment) {
    $file_path = $upload_dir . $attachment['filename'];
    if (!file_exists($file_path)) {
        $missing_files++;
    }
}

$stmt = $pdo->query("SELECT filename FROM ticket_attachments");
$db_filenames = array_column($stmt->fetchAll(), 'filename');

$orphaned_files = 0;
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..' && $file !== '.gitkeep') {
        if (!in_array($file, $db_filenames)) {
            $orphaned_files++;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento de Uploads - KubeAgency</title>
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
            max-width: 800px;
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

        .btn {
            background: #4fd1c7;
            color: #1a1d29;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .btn:hover {
            background: #38b2ac;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #374151;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #4fd1c7;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .status-ok { color: #10b981; }
        .status-error { color: #ef4444; }
        .status-warning { color: #f59e0b; }

        .message {
            background: #10b981;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error {
            background: #ef4444;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($message): ?>
            <div class="message">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3><i class="fas fa-tools"></i> Mantenimiento de Archivos de Uploads</h3>
            
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_db; ?></div>
                    <div class="stat-label">Registros en BD</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_files; ?></div>
                    <div class="stat-label">Archivos en servidor</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number <?php echo $missing_files > 0 ? 'status-error' : 'status-ok'; ?>"><?php echo $missing_files; ?></div>
                    <div class="stat-label">Archivos faltantes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number <?php echo $orphaned_files > 0 ? 'status-warning' : 'status-ok'; ?>"><?php echo $orphaned_files; ?></div>
                    <div class="stat-label">Archivos huérfanos</div>
                </div>
            </div>

            <p>Este panel te permite mantener la consistencia entre los archivos almacenados y los registros de la base de datos.</p>
        </div>

        <div class="card">
            <h3><i class="fas fa-database"></i> Acciones de Mantenimiento</h3>
            
            <p><strong>Limpiar registros huérfanos:</strong> Elimina de la base de datos los registros de archivos que ya no existen físicamente en el servidor.</p>
            <a href="cleanup-uploads.php?action=cleanup_orphaned_db" 
               class="btn btn-danger"
               onclick="return confirm('¿Estás seguro de que quieres eliminar los registros huérfanos de la base de datos?')">
                <i class="fas fa-database"></i> Limpiar BD (<?php echo $missing_files; ?> registros)
            </a>
            
            <p><strong>Limpiar archivos huérfanos:</strong> Elimina del servidor los archivos que no tienen registro en la base de datos.</p>
            <a href="cleanup-uploads.php?action=cleanup_orphaned_files" 
               class="btn btn-danger"
               onclick="return confirm('¿Estás seguro de que quieres eliminar los archivos huérfanos del servidor?')">
                <i class="fas fa-hdd"></i> Limpiar Archivos (<?php echo $orphaned_files; ?> archivos)
            </a>
        </div>

        <div class="card">
            <h3><i class="fas fa-info-circle"></i> Información del Sistema</h3>
            <ul>
                <li><strong>Directorio de uploads:</strong> <?php echo $upload_dir; ?></li>
                <li><strong>Directorio escribible:</strong> 
                    <span class="<?php echo is_writable($upload_dir) ? 'status-ok' : 'status-error'; ?>">
                        <?php echo is_writable($upload_dir) ? 'Sí' : 'No'; ?>
                    </span>
                </li>
                <li><strong>Espacio disponible:</strong> <?php echo number_format(disk_free_space($upload_dir) / 1024 / 1024 / 1024, 2); ?> GB</li>
            </ul>
        </div>

        <div class="card">
            <a href="debug-uploads.php" class="btn">
                <i class="fas fa-search"></i> Diagnóstico Detallado
            </a>
            <a href="tickets.php" class="btn">
                <i class="fas fa-arrow-left"></i> Volver a Tickets
            </a>
        </div>
    </div>
</body>
</html> 