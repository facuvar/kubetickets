<?php
session_start();

// Verificar acceso
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$config = Config::getInstance();
$pdo = $config->getDbConnection();

$debug_info = [];
$upload_result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_info['POST_data'] = $_POST;
    $debug_info['FILES_data'] = $_FILES;
    $debug_info['upload_max_filesize'] = ini_get('upload_max_filesize');
    $debug_info['post_max_size'] = ini_get('post_max_size');
    $debug_info['max_execution_time'] = ini_get('max_execution_time');
    $debug_info['memory_limit'] = ini_get('memory_limit');
    
    // Verificar si se enviaron archivos
    if (isset($_FILES['test_file']) && !empty($_FILES['test_file']['name'])) {
        $file = $_FILES['test_file'];
        
        $debug_info['file_info'] = [
            'name' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error' => $file['error'],
            'error_message' => ''
        ];
        
        // Interpretar código de error
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                $debug_info['file_info']['error_message'] = 'Sin errores';
                break;
            case UPLOAD_ERR_INI_SIZE:
                $debug_info['file_info']['error_message'] = 'El archivo es demasiado grande (upload_max_filesize)';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $debug_info['file_info']['error_message'] = 'El archivo es demasiado grande (MAX_FILE_SIZE del form)';
                break;
            case UPLOAD_ERR_PARTIAL:
                $debug_info['file_info']['error_message'] = 'El archivo fue subido parcialmente';
                break;
            case UPLOAD_ERR_NO_FILE:
                $debug_info['file_info']['error_message'] = 'No se subió ningún archivo';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $debug_info['file_info']['error_message'] = 'Directorio temporal no encontrado';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $debug_info['file_info']['error_message'] = 'No se pudo escribir el archivo al disco';
                break;
            case UPLOAD_ERR_EXTENSION:
                $debug_info['file_info']['error_message'] = 'Una extensión de PHP bloqueó la subida';
                break;
        }
        
        // Intentar mover el archivo
        if ($file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/tickets/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $unique_filename = uniqid() . '_test_' . $file['name'];
            $file_path = $upload_dir . $unique_filename;
            
            $debug_info['upload_attempt'] = [
                'upload_dir' => $upload_dir,
                'unique_filename' => $unique_filename,
                'file_path' => $file_path,
                'dir_exists' => is_dir($upload_dir),
                'dir_writable' => is_writable($upload_dir),
                'tmp_file_exists' => file_exists($file['tmp_name']),
                'tmp_file_readable' => is_readable($file['tmp_name'])
            ];
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $upload_result = "✅ Archivo subido exitosamente a: " . $file_path;
                
                // Intentar guardarlo en la base de datos
                try {
                    $config_instance = Config::getInstance();
                    if ($config_instance->isRailway()) {
                        $stmt = $pdo->prepare("INSERT INTO ticket_attachments (ticket_id, filename, original_filename, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            null, // ticket_id null para prueba
                            $unique_filename,
                            $file['name'],
                            $file_path,
                            $file['size'],
                            pathinfo($file['name'], PATHINFO_EXTENSION),
                            $_SESSION['user_id']
                        ]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO ticket_attachments (ticket_id, filename, original_filename, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            null, // ticket_id null para prueba
                            $unique_filename,
                            $file['name'],
                            $file['size'],
                            pathinfo($file['name'], PATHINFO_EXTENSION),
                            $_SESSION['user_id']
                        ]);
                    }
                    $upload_result .= "<br>✅ Registro guardado en base de datos con ID: " . $pdo->lastInsertId();
                } catch (Exception $e) {
                    $upload_result .= "<br>❌ Error al guardar en base de datos: " . $e->getMessage();
                }
                
            } else {
                $upload_result = "❌ Error al mover el archivo subido";
                $debug_info['move_error'] = error_get_last();
            }
        }
    } else {
        $debug_info['no_file'] = 'No se recibió ningún archivo';
    }
}

// Obtener últimos tickets creados
$stmt = $pdo->query("
    SELECT t.*, u.name as cliente_name,
           (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) as attachment_count
    FROM tickets t
    JOIN users u ON t.cliente_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 10
");
$recent_tickets = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Subida de Archivos - KubeAgency</title>
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
            max-width: 1000px;
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
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn:hover {
            background: #38b2ac;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #4fd1c7;
        }

        .form-group input[type="file"] {
            background: #374151;
            border: 1px solid #4a5568;
            border-radius: 6px;
            padding: 10px;
            color: #e2e8f0;
            width: 100%;
        }

        pre {
            background: #374151;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 12px;
            white-space: pre-wrap;
        }

        .result {
            background: #065f46;
            border: 1px solid #10b981;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }

        .error {
            background: #7f1d1d;
            border: 1px solid #ef4444;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h3><i class="fas fa-upload"></i> Test de Subida de Archivos</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="test_file">Seleccionar archivo para probar:</label>
                    <input type="file" id="test_file" name="test_file" required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-upload"></i> Probar Subida
                </button>
            </form>

            <?php if ($upload_result): ?>
                <div class="result">
                    <?php echo $upload_result; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($debug_info)): ?>
        <div class="card">
            <h3><i class="fas fa-bug"></i> Información de Debug</h3>
            <pre><?php echo json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3><i class="fas fa-info-circle"></i> Configuración del Servidor</h3>
            <ul>
                <li><strong>upload_max_filesize:</strong> <?php echo ini_get('upload_max_filesize'); ?></li>
                <li><strong>post_max_size:</strong> <?php echo ini_get('post_max_size'); ?></li>
                <li><strong>max_file_uploads:</strong> <?php echo ini_get('max_file_uploads'); ?></li>
                <li><strong>memory_limit:</strong> <?php echo ini_get('memory_limit'); ?></li>
                <li><strong>max_execution_time:</strong> <?php echo ini_get('max_execution_time'); ?> segundos</li>
                <li><strong>Directorio uploads existe:</strong> <?php echo is_dir('uploads/tickets/') ? 'Sí' : 'No'; ?></li>
                <li><strong>Directorio uploads escribible:</strong> <?php echo is_writable('uploads/tickets/') ? 'Sí' : 'No'; ?></li>
                <li><strong>Entorno:</strong> <?php echo $config->getEnvironment(); ?></li>
            </ul>
        </div>

        <div class="card">
            <h3><i class="fas fa-ticket-alt"></i> Últimos Tickets Creados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Asunto</th>
                        <th>Cliente</th>
                        <th>Adjuntos</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tickets as $ticket): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['subject'] ?? $ticket['title'] ?? 'Sin asunto'); ?></td>
                        <td><?php echo htmlspecialchars($ticket['cliente_name']); ?></td>
                        <td><?php echo $ticket['attachment_count']; ?> archivos</td>
                        <td><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <a href="nuevo-ticket.php" class="btn">
                <i class="fas fa-plus"></i> Crear Ticket Normal
            </a>
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