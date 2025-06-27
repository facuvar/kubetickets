<?php
session_start();

// Verificar acceso de admin/agente
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'agente'])) {
    header('Location: login.php');
    exit;
}

// Usar configuración automática
require_once 'config.php';

try {
    $config = Config::getInstance();
    $pdo = $config->getDbConnection();
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$message = '';
$error = '';

// Obtener configuración
$stmt = $pdo->query("SELECT config_key, config_value FROM system_config");
$config = [];
while ($row = $stmt->fetch()) {
    $config[$row['config_key']] = $row['config_value'];
}

// Obtener lista de clientes
$stmt = $pdo->query("SELECT id, name, email, company FROM users WHERE role = 'cliente' ORDER BY name");
$clientes = $stmt->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'media';
    $category = trim($_POST['category'] ?? '');
    $auto_assign = $_POST['auto_assign'] ?? '0';
    
    if (!empty($cliente_id) && !empty($subject) && !empty($description)) {
        try {
            // Generar número de ticket
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets");
            $count = $stmt->fetch()['count'];
            $ticket_number = 'KUBE-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            
            // Insertar ticket (detectar estructura de tabla Railway vs Localhost)
            $config_instance = Config::getInstance();
            if ($config_instance->isRailway()) {
                // En Railway: usar campo title
                $stmt = $pdo->prepare("
                    INSERT INTO tickets (ticket_number, title, subject, description, priority, category, cliente_id, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'abierto', NOW(), NOW())
                ");
                $stmt->execute([$ticket_number, $subject, $subject, $description, $priority, $category, $cliente_id]);
            } else {
                // En localhost: usar campo subject
                $stmt = $pdo->prepare("
                    INSERT INTO tickets (ticket_number, cliente_id, subject, description, priority, category, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'abierto', NOW(), NOW())
                ");
                $stmt->execute([$ticket_number, $cliente_id, $subject, $description, $priority, $category]);
            }
            $ticket_id = $pdo->lastInsertId();
            
            // Auto-asignar si es solicitado y es admin
            if ($auto_assign === '1' && $_SESSION['user_role'] === 'admin') {
                $stmt = $pdo->prepare("UPDATE tickets SET agente_id = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $ticket_id]);
            }
            
            // Procesar archivos adjuntos si existen
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                foreach ($_FILES['attachments']['name'] as $key => $filename) {
                    if (!empty($filename)) {
                        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
                        
                        if (in_array($file_extension, $allowed_extensions) && $_FILES['attachments']['size'][$key] <= 10485760) {
                            $unique_filename = uniqid() . '_' . $filename;
                            $upload_dir = 'uploads/tickets/';
                            
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            
                            $file_path = $upload_dir . $unique_filename;
                            
                            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $file_path)) {
                                $config_instance = Config::getInstance();
                                if ($config_instance->isRailway()) {
                                    $stmt = $pdo->prepare("INSERT INTO ticket_attachments (ticket_id, filename, original_filename, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $stmt->execute([
                                        $ticket_id, 
                                        $unique_filename, 
                                        $filename, 
                                        $file_path,
                                        $_FILES['attachments']['size'][$key], 
                                        $file_extension, 
                                        $_SESSION['user_id']
                                    ]);
                                } else {
                                    $stmt = $pdo->prepare("INSERT INTO ticket_attachments (ticket_id, filename, original_filename, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                                    $stmt->execute([
                                        $ticket_id, 
                                        $unique_filename, 
                                        $filename, 
                                        $_FILES['attachments']['size'][$key], 
                                        $file_extension, 
                                        $_SESSION['user_id']
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            
            // Agregar mensaje inicial del sistema
            $system_message = "Ticket creado por " . $_SESSION['user_name'] . " (" . $_SESSION['user_role'] . ") en nombre del cliente.";
            $stmt = $pdo->prepare("
                INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal, created_at) 
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$ticket_id, $_SESSION['user_id'], $system_message]);
            
            // Enviar notificaciones por email
            if (($config['email_notifications'] ?? '1') === '1') {
                require_once 'includes/email.php';
                $emailService = new EmailService();
                
                // Obtener datos completos del ticket para el email
                $stmt = $pdo->prepare("
                    SELECT t.*, u.name as cliente_name, u.email as cliente_email, u.company as cliente_company
                    FROM tickets t
                    JOIN users u ON t.cliente_id = u.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$ticket_id]);
                $ticket_data = $stmt->fetch();
                
                // Obtener emails de administradores
                $stmt = $pdo->query("SELECT email FROM users WHERE role = 'admin'");
                $admin_emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Notificar a administradores
                if (!empty($admin_emails)) {
                    $emailService->notifyNewTicket($ticket_data, $admin_emails);
                }
                
                // Notificar al cliente
                $emailService->notifyNewTicketToClient($ticket_data);
            }
            
            $message = "Ticket {$ticket_number} creado exitosamente para el cliente.";
            
            // Limpiar formulario
            $_POST = [];
        } catch(PDOException $e) {
            $error = 'Error al crear ticket: ' . $e->getMessage();
        }
    } else {
        $error = 'Complete todos los campos obligatorios';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Ticket - KUBE Soporte</title>
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
            margin: 0;
        }

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
                              radial-gradient(circle at 80% 20%, rgba(79, 209, 199, 0.08) 0%, transparent 50%),
                              radial-gradient(circle at 40% 40%, rgba(79, 209, 199, 0.05) 0%, transparent 50%);
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

        .header a { color: white; text-decoration: none; }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .form-card {
            background: rgba(45, 55, 72, 0.95);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid #4a5568;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
        }

        .form-card h1 {
            color: #4fd1c7;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #f7fafc;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #4a5568;
            border-radius: 6px;
            background: #2d3748;
            color: #e2e8f0;
            font-size: 13px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4fd1c7;
            box-shadow: 0 0 0 3px rgba(79, 209, 199, 0.1);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            background: linear-gradient(135deg, #4fd1c7 0%, #38b2ac 100%);
            color: #1a1d29;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 209, 199, 0.3);
        }

        .btn-secondary {
            background: #4a5568;
            color: #e2e8f0;
        }

        .btn-secondary:hover {
            background: #718096;
            box-shadow: 0 10px 25px rgba(74, 85, 104, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            opacity: 0;
            position: absolute;
            z-index: -1;
        }

        .file-input-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #374151;
            border: 1px dashed #4a5568;
            border-radius: 6px;
            color: #e2e8f0;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            justify-content: center;
        }

        .file-input-button:hover {
            background: #4a5568;
            border-color: #4fd1c7;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: auto;
        }

        .client-info {
            background: rgba(79, 209, 199, 0.1);
            border: 1px solid rgba(79, 209, 199, 0.3);
            border-radius: 6px;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div><a href="index.php"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a></div>
        <h2><i class="fas fa-headset"></i> KUBE Soporte - Crear Ticket</h2>
        <div>
            <a href="tickets.php" class="btn btn-secondary" style="margin-right: 1rem;">
                <i class="fas fa-list"></i> Ver Tickets
            </a>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
        <div class="form-card">
            <h1><i class="fas fa-plus-circle"></i> Crear Nuevo Ticket</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="cliente_id">Cliente *</label>
                    <select id="cliente_id" name="cliente_id" required onchange="updateClientInfo()">
                        <option value="">Seleccionar cliente...</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($cliente['name']); ?>"
                                    data-email="<?php echo htmlspecialchars($cliente['email']); ?>"
                                    data-company="<?php echo htmlspecialchars($cliente['company'] ?? ''); ?>"
                                    <?php echo (($_POST['cliente_id'] ?? '') == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['name']); ?>
                                <?php if ($cliente['company']): ?>
                                    - <?php echo htmlspecialchars($cliente['company']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="client-info" class="client-info" style="display: none;">
                        <strong>Información del cliente:</strong><br>
                        <span id="client-details"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject">Asunto *</label>
                    <input type="text" id="subject" name="subject" required 
                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                           placeholder="Resuma brevemente el problema">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="priority">Prioridad</label>
                        <select id="priority" name="priority">
                            <option value="baja" <?php echo (($_POST['priority'] ?? 'media') === 'baja') ? 'selected' : ''; ?>>Baja</option>
                            <option value="media" <?php echo (($_POST['priority'] ?? 'media') === 'media') ? 'selected' : ''; ?>>Media</option>
                            <option value="alta" <?php echo (($_POST['priority'] ?? 'media') === 'alta') ? 'selected' : ''; ?>>Alta</option>
                            <option value="critica" <?php echo (($_POST['priority'] ?? 'media') === 'critica') ? 'selected' : ''; ?>>Crítica</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="category">Categoría</label>
                        <select id="category" name="category">
                            <option value="soporte" <?php echo (($_POST['category'] ?? '') === 'soporte') ? 'selected' : ''; ?>>Soporte Técnico</option>
                            <option value="bug" <?php echo (($_POST['category'] ?? '') === 'bug') ? 'selected' : ''; ?>>Reporte de Bug</option>
                            <option value="feature" <?php echo (($_POST['category'] ?? '') === 'feature') ? 'selected' : ''; ?>>Solicitud de Funcionalidad</option>
                            <option value="consulta" <?php echo (($_POST['category'] ?? '') === 'consulta') ? 'selected' : ''; ?>>Consulta General</option>
                            <option value="otro" <?php echo (($_POST['category'] ?? '') === 'otro') ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Descripción detallada *</label>
                    <textarea id="description" name="description" required 
                              placeholder="Describa el problema con el mayor detalle posible..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="attachments">Archivos adjuntos (opcional)</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="attachments" name="attachments[]" multiple class="file-input" 
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip,.rar">
                        <label for="attachments" class="file-input-button">
                            <i class="fas fa-paperclip"></i>
                            Seleccionar archivos (máx. 10MB cada uno)
                        </label>
                    </div>
                    <small style="opacity: 0.7; font-size: 11px;">
                        Formatos permitidos: JPG, PNG, PDF, DOC, DOCX, TXT, ZIP, RAR
                    </small>
                </div>

                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="auto_assign" name="auto_assign" value="1">
                        <label for="auto_assign">Auto-asignarme este ticket</label>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Crear Ticket
                    </button>
                    <a href="tickets.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateClientInfo() {
            const select = document.getElementById('cliente_id');
            const clientInfo = document.getElementById('client-info');
            const clientDetails = document.getElementById('client-details');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const name = option.getAttribute('data-name');
                const email = option.getAttribute('data-email');
                const company = option.getAttribute('data-company');
                
                let details = `<strong>${name}</strong><br>`;
                details += `📧 ${email}<br>`;
                if (company) {
                    details += `🏢 ${company}`;
                }
                
                clientDetails.innerHTML = details;
                clientInfo.style.display = 'block';
            } else {
                clientInfo.style.display = 'none';
            }
        }

        // Actualizar nombre del archivo seleccionado
        document.getElementById('attachments').addEventListener('change', function(e) {
            const label = document.querySelector('.file-input-button');
            const fileCount = e.target.files.length;
            
            if (fileCount > 0) {
                label.innerHTML = `<i class="fas fa-paperclip"></i> ${fileCount} archivo(s) seleccionado(s)`;
            } else {
                label.innerHTML = `<i class="fas fa-paperclip"></i> Seleccionar archivos (máx. 10MB cada uno)`;
            }
        });
    </script>
</body>
</html> 