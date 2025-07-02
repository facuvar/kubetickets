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
            $prefix = $config['ticket_prefix'] ?? 'KUBE';
            $stmt = $pdo->query("SELECT MAX(id) as max_id FROM tickets");
            $max_id = $stmt->fetch()['max_id'] ?? 0;
            $ticket_number = $prefix . '-' . str_pad($max_id + 1, 3, '0', STR_PAD_LEFT);
            
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1d29;
            color: #e2e8f0;
            min-height: 100vh;
            overflow-x: hidden;
            width: 100%;
            min-width: 0;
            font-size: 13px;
            line-height: 1.4;
        }

        /* Fondo minimalista */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a1d29 0%, #232840 100%);
            z-index: -1;
        }

        .header {
            background: #2d3748;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #4a5568;
            font-size: 12px;
        }

        .header a { 
            color: #cbd5e0; 
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .header a:hover {
            color: #38b2ac;
        }

        .header h2 {
            color: #f7fafc;
            font-size: 1.25rem;
            font-weight: 500;
            margin: 0;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .form-card {
            background: #2d3748;
            border-radius: 6px;
            padding: 2rem;
            border: 1px solid #4a5568;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .form-card h1 {
            color: #f7fafc;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #e2e8f0;
            font-weight: 500;
            font-size: 12px;
        }

        input[type="text"], 
        textarea, 
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #4a5568;
            border-radius: 4px;
            font-size: 12px;
            transition: border-color 0.15s ease;
            background: #374151;
            color: #e2e8f0;
        }

        input[type="text"]:focus, 
        textarea:focus, 
        select:focus {
            outline: none;
            border-color: #38b2ac;
            box-shadow: 0 0 0 0.2rem rgba(56, 178, 172, 0.25);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            background: #38b2ac;
            color: #fff;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s ease;
        }

        .btn:hover {
            background: #319795;
        }

        .btn-secondary {
            background: #6b7280;
            border: 1px solid #4a5568;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 12px;
        }

        .alert-success {
            background: #065f46;
            color: #a7f3d0;
            border: 1px solid #047857;
        }

        .alert-error {
            background: #7f1d1d;
            color: #fca5a5;
            border: 1px solid #b91c1c;
        }

        .client-info {
            background: #1e3a8a;
            border: 1px solid #3b82f6;
            border-radius: 4px;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 11px;
        }

        .client-info strong {
            color: #60a5fa;
        }

        .file-upload {
            border: 2px dashed #4a5568;
            border-radius: 4px;
            padding: 2rem;
            text-align: center;
            background: #374151;
            cursor: pointer;
            transition: border-color 0.2s ease;
        }

        .file-upload:hover {
            border-color: #38b2ac;
            background: #4b5563;
        }

        .file-upload input {
            display: none;
        }

        .file-upload small {
            color: #9ca3af;
            font-size: 10px;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: auto;
            background: #374151;
            border: 1px solid #4a5568;
        }

        .checkbox-wrapper label {
            margin-bottom: 0;
            font-size: 12px;
            color: #cbd5e0;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .container {
                margin: 1rem auto;
                padding: 0 1rem;
            }
            
            .header {
                flex-direction: column;
                gap: 0.5rem;
                padding: 1rem;
            }
            
            .header h2 {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div><a href="index.php"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a></div>
        <h2><i class="fas fa-plus-circle"></i> KUBE Soporte - Crear Ticket</h2>
        <div>
            <a href="tickets.php" class="btn btn-secondary" style="margin-right: 1rem;"><i class="fas fa-list"></i> Ver Tickets</a>
            <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="form-card">
            <h1><i class="fas fa-ticket-alt"></i> Crear Nuevo Ticket</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    ✓ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ⚠ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="cliente_id">Seleccionar Cliente / Empresa *</label>
                    <select id="cliente_id" name="cliente_id" required onchange="updateClientInfo()">
                        <option value="">-- Seleccione el cliente para quien crear el ticket --</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($cliente['name']); ?>"
                                    data-email="<?php echo htmlspecialchars($cliente['email']); ?>"
                                    data-company="<?php echo htmlspecialchars($cliente['company'] ?? ''); ?>"
                                    <?php echo (($_POST['cliente_id'] ?? '') == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['name']); ?>
                                <?php if ($cliente['company']): ?>
                                    (<?php echo htmlspecialchars($cliente['company']); ?>)
                                <?php endif; ?>
                                - <?php echo htmlspecialchars($cliente['email']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="client-info" class="client-info" style="display: none;">
                        <strong>Cliente seleccionado:</strong><br>
                        <span id="client-details"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject">Asunto del Ticket *</label>
                    <input type="text" id="subject" name="subject" required 
                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                           placeholder="Resuma brevemente el problema o solicitud">
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
                    <label for="description">Descripción Detallada *</label>
                    <textarea id="description" name="description" required 
                              placeholder="Describa el problema con el mayor detalle posible..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Archivos Adjuntos (opcional)</label>
                    <div class="file-upload" onclick="document.getElementById('attachments').click()">
                        <input type="file" id="attachments" name="attachments[]" multiple 
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip,.rar">
                        <div id="file-upload-text">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #38b2ac; margin-bottom: 0.5rem;"></i><br>
                            Haga clic para seleccionar archivos<br>
                            <small>Máx. 10MB por archivo. Formatos: JPG, PNG, PDF, DOC, DOCX, TXT, ZIP, RAR</small>
                        </div>
                    </div>
                </div>

                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="auto_assign" name="auto_assign" value="1">
                        <label for="auto_assign">Auto-asignarme este ticket</label>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn"><i class="fas fa-plus"></i> Crear Ticket</button>
                    <a href="tickets.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
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
                
                let details = `Nombre: <strong>${name}</strong><br>`;
                details += `Email: ${email}<br>`;
                if (company) {
                    details += `Empresa: ${company}`;
                }
                
                clientDetails.innerHTML = details;
                clientInfo.style.display = 'block';
            } else {
                clientInfo.style.display = 'none';
            }
        }

        // Mostrar archivos seleccionados
        document.getElementById('attachments').addEventListener('change', function(e) {
            const text = document.getElementById('file-upload-text');
            const fileCount = e.target.files.length;
            
            if (fileCount > 0) {
                text.innerHTML = `<i class="fas fa-check-circle" style="font-size: 2rem; color: #38b2ac; margin-bottom: 0.5rem;"></i><br>${fileCount} archivo(s) seleccionado(s)<br><small>Haga clic para cambiar la selección</small>`;
            } else {
                text.innerHTML = `<i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #38b2ac; margin-bottom: 0.5rem;"></i><br>Haga clic para seleccionar archivos<br><small>Máx. 10MB por archivo. Formatos: JPG, PNG, PDF, DOC, DOCX, TXT, ZIP, RAR</small>`;
            }
        });
    </script>
</body>
</html> 