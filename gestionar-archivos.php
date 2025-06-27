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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_file') {
        $file_id = $_POST['file_id'] ?? '';
        
        if (!empty($file_id)) {
            try {
                // Obtener información del archivo antes de eliminarlo
                $stmt = $pdo->prepare("SELECT filename FROM ticket_attachments WHERE id = ?");
                $stmt->execute([$file_id]);
                $file_data = $stmt->fetch();
                
                if ($file_data) {
                    // Eliminar archivo físico
                    $file_path = __DIR__ . '/uploads/tickets/' . $file_data['filename'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                    
                    // Eliminar registro de base de datos
                    $stmt = $pdo->prepare("DELETE FROM ticket_attachments WHERE id = ?");
                    $stmt->execute([$file_id]);
                    
                    $message = "Archivo eliminado correctamente.";
                } else {
                    $error = "Archivo no encontrado.";
                }
            } catch (Exception $e) {
                $error = "Error al eliminar archivo: " . $e->getMessage();
            }
        }
    } 
    elseif ($action === 'cleanup_orphaned_db') {
        // Limpiar registros huérfanos
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
    }
    elseif ($action === 'cleanup_orphaned_files') {
        // Limpiar archivos huérfanos
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
}

// Obtener archivos de la base de datos con información adicional
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

// Calcular estadísticas
$total_db = count($db_files);
$total_files = count($fs_files);

// Verificar inconsistencias
$missing_files = 0;
foreach ($db_files as $file) {
    $file_path = $upload_dir . $file['filename'];
    if (!file_exists($file_path)) {
        $missing_files++;
    }
}

$db_filenames = array_column($db_files, 'filename');
$orphaned_files = 0;
foreach ($fs_files as $file) {
    if (!in_array($file['filename'], $db_filenames)) {
        $orphaned_files++;
    }
}

// Calcular tamaño total
$total_size = 0;
foreach ($fs_files as $file) {
    $total_size += $file['size'];
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Archivos Adjuntos - KubeAgency</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1d29;
            color: #e2e8f0;
            min-height: 100vh;
            font-size: 13px;
            line-height: 1.4;
        }

        /* Fondo animado */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a1d29 0%, #232840 50%, #1a1d29 100%);
            z-index: -2;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(circle at 20% 80%, rgba(79, 209, 199, 0.1) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(79, 209, 199, 0.08) 0%, transparent 50%);
            z-index: -1;
        }

        .header {
            background: linear-gradient(135deg, #2d3748 0%, #374151 100%);
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #4a5568;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h2 {
            color: #f7fafc;
            font-size: 1.25rem;
            font-weight: 500;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header a {
            color: white;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .header a:hover {
            color: #4fd1c7;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .card {
            background: rgba(45, 55, 72, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(74, 85, 104, 0.6);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card h3 {
            color: #4fd1c7;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: rgba(55, 65, 81, 0.8);
            padding: 1.25rem;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(74, 85, 104, 0.6);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #4fd1c7;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .btn {
            background: #4fd1c7;
            color: #1a1d29;
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .btn:hover {
            background: #38b2ac;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(79, 209, 199, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: #cbd5e0;
            border: 1px solid #4a5568;
        }

        .btn-secondary:hover {
            background: #4a5568;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }

        .btn-small {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid rgba(74, 85, 104, 0.6);
            margin-top: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(45, 55, 72, 0.8);
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(74, 85, 104, 0.6);
            font-size: 0.8rem;
        }

        th {
            background: rgba(55, 65, 81, 0.9);
            color: #4fd1c7;
            font-weight: 600;
        }

        tr:hover td {
            background: rgba(55, 65, 81, 0.6);
        }

        .file-preview {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #4a5568;
        }

        .file-icon {
            width: 40px;
            height: 40px;
            background: #374151;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #4fd1c7;
        }

        .status-ok { color: #10b981; }
        .status-error { color: #ef4444; }
        .status-warning { color: #f59e0b; }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            color: #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .action-card {
            background: rgba(55, 65, 81, 0.8);
            padding: 1.25rem;
            border-radius: 10px;
            border: 1px solid rgba(74, 85, 104, 0.6);
        }

        .action-card h4 {
            color: #4fd1c7;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .action-card p {
            opacity: 0.9;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
        <h2><i class="fas fa-headset"></i> KUBE Soporte - Gestión de Archivos</h2>
        <div>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="card">
            <h3><i class="fas fa-chart-bar"></i> Resumen de Archivos Adjuntos</h3>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_db; ?></div>
                    <div class="stat-label">Registros en Base de Datos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_files; ?></div>
                    <div class="stat-label">Archivos en Servidor</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number <?php echo $missing_files > 0 ? 'status-error' : 'status-ok'; ?>"><?php echo $missing_files; ?></div>
                    <div class="stat-label">Archivos Faltantes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number <?php echo $orphaned_files > 0 ? 'status-warning' : 'status-ok'; ?>"><?php echo $orphaned_files; ?></div>
                    <div class="stat-label">Archivos Huérfanos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($total_size / 1024 / 1024, 1); ?> MB</div>
                    <div class="stat-label">Espacio Utilizado</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format(disk_free_space($upload_dir) / 1024 / 1024 / 1024, 1); ?> GB</div>
                    <div class="stat-label">Espacio Disponible</div>
                </div>
            </div>
        </div>

        <!-- Acciones de Limpieza -->
        <div class="card">
            <h3><i class="fas fa-broom"></i> Acciones de Mantenimiento</h3>
            
            <div class="actions-grid">
                <div class="action-card">
                    <h4><i class="fas fa-database"></i> Limpiar Registros Huérfanos</h4>
                    <p>Elimina de la base de datos los registros de archivos que ya no existen físicamente en el servidor.</p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="cleanup_orphaned_db">
                        <button type="submit" class="btn btn-danger"
                                onclick="return confirm('¿Estás seguro de que quieres eliminar <?php echo $missing_files; ?> registros huérfanos de la base de datos?')"
                                <?php echo $missing_files === 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-database"></i> Limpiar BD (<?php echo $missing_files; ?> registros)
                        </button>
                    </form>
                </div>
                
                <div class="action-card">
                    <h4><i class="fas fa-hdd"></i> Limpiar Archivos Huérfanos</h4>
                    <p>Elimina del servidor los archivos que no tienen registro en la base de datos y no pertenecen a ningún ticket.</p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="cleanup_orphaned_files">
                        <button type="submit" class="btn btn-danger"
                                onclick="return confirm('¿Estás seguro de que quieres eliminar <?php echo $orphaned_files; ?> archivos huérfanos del servidor?')"
                                <?php echo $orphaned_files === 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-hdd"></i> Limpiar Archivos (<?php echo $orphaned_files; ?> archivos)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista de Archivos -->
        <div class="card">
            <h3><i class="fas fa-file-alt"></i> Todos los Archivos Adjuntos (<?php echo count($db_files); ?>)</h3>
            
            <?php if (count($db_files) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Vista Previa</th>
                                <th>Archivo Original</th>
                                <th>Ticket</th>
                                <th>Tamaño</th>
                                <th>Subido por</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db_files as $file): ?>
                            <?php
                            $file_exists = file_exists($upload_dir . $file['filename']);
                            $is_image = in_array(strtolower($file['file_type']), ['jpg', 'jpeg', 'png', 'gif']);
                            ?>
                            <tr>
                                <td>
                                    <?php if ($file_exists && $is_image): ?>
                                        <img src="serve-file.php?file=<?php echo urlencode($file['filename']); ?>" 
                                             alt="Vista previa" class="file-preview">
                                    <?php else: ?>
                                        <div class="file-icon">
                                            <i class="fas fa-file"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($file['original_filename']); ?></strong><br>
                                    <small style="opacity: 0.7;"><?php echo htmlspecialchars($file['filename']); ?></small>
                                </td>
                                <td>
                                    <?php if ($file['ticket_number']): ?>
                                        <a href="ticket-detalle.php?id=<?php echo $file['ticket_id']; ?>" 
                                           style="color: #4fd1c7; text-decoration: none;">
                                            <?php echo htmlspecialchars($file['ticket_number']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="opacity: 0.5;">Sin ticket</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($file['file_size'] / 1024, 1); ?> KB</td>
                                <td><?php echo htmlspecialchars($file['uploaded_by_name']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($file['created_at'])); ?></td>
                                <td>
                                    <span class="<?php echo $file_exists ? 'status-ok' : 'status-error'; ?>">
                                        <i class="fas fa-<?php echo $file_exists ? 'check-circle' : 'times-circle'; ?>"></i>
                                        <?php echo $file_exists ? 'Existe' : 'Faltante'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($file_exists): ?>
                                        <a href="serve-file.php?file=<?php echo urlencode($file['filename']); ?>" 
                                           target="_blank" class="btn btn-secondary btn-small">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_file">
                                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small"
                                                onclick="return confirm('¿Estás seguro de que quieres eliminar este archivo?')"
                                                title="Eliminar archivo">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; opacity: 0.6;">
                    <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h4>No hay archivos adjuntos</h4>
                    <p>Los archivos aparecerán aquí cuando se suban a los tickets.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navegación -->
        <div class="card">
            <a href="debug-uploads.php" class="btn btn-secondary">
                <i class="fas fa-search"></i> Diagnóstico Detallado
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="tickets.php" class="btn btn-secondary">
                <i class="fas fa-ticket-alt"></i> Gestionar Tickets
            </a>
        </div>
    </div>
</body>
</html> 