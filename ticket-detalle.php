<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Configuración de base de datos
$host = 'localhost';
$dbname = 'sistema_tickets_kube';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener ID del ticket
$ticket_id = $_GET['id'] ?? 0;
if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

$message = '';
$error = '';

// Obtener configuración
$stmt = $pdo->query("SELECT config_key, config_value FROM system_config");
$config = [];
while ($row = $stmt->fetch()) {
    $config[$row['config_key']] = $row['config_value'];
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // Cambiar estado del ticket
        if ($action === 'change_status' && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'agente')) {
            // Obtener estado anterior
            $stmt = $pdo->prepare("SELECT status FROM tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $old_status = $stmt->fetch()['status'];
            
            $new_status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $ticket_id]);
            
            if ($new_status === 'cerrado') {
                $stmt = $pdo->prepare("UPDATE tickets SET closed_at = NOW() WHERE id = ?");
                $stmt->execute([$ticket_id]);
            }
            
            // Enviar notificación de cambio de estado
            if (($config['email_notifications'] ?? '1') === '1' && $old_status !== $new_status) {
                require_once 'includes/email.php';
                $emailService = new EmailService();
                
                // Obtener datos del ticket y cliente
                $stmt = $pdo->prepare("
                    SELECT t.*, u.name as cliente_name, u.email as cliente_email, u.company as cliente_company,
                           changer.name as changed_by_name
                    FROM tickets t
                    JOIN users u ON t.cliente_id = u.id
                    JOIN users changer ON changer.id = ?
                    WHERE t.id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $ticket_id]);
                $ticket_data = $stmt->fetch();
                
                $emailService->notifyStatusChange($ticket_data, $old_status, $new_status, $ticket_data['changed_by_name']);
            }
            
            $message = "Estado del ticket actualizado correctamente.";
        }
        
        // Asignar agente
        if ($action === 'assign_agent' && $_SESSION['user_role'] === 'admin') {
            $agent_id = $_POST['agent_id'] ?: null;
            $stmt = $pdo->prepare("UPDATE tickets SET agente_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$agent_id, $ticket_id]);
            
            // Notificar al agente asignado
            if ($agent_id && ($config['email_notifications'] ?? '1') === '1') {
                require_once 'includes/email.php';
                $emailService = new EmailService();
                
                // Obtener datos del ticket y agente
                $stmt = $pdo->prepare("
                    SELECT t.*, 
                           cliente.name as cliente_name, cliente.email as cliente_email, cliente.company as cliente_company,
                           agente.name as agente_name, agente.email as agente_email
                    FROM tickets t
                    JOIN users cliente ON t.cliente_id = cliente.id
                    JOIN users agente ON t.agente_id = agente.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$ticket_id]);
                $ticket_data = $stmt->fetch();
                
                $emailService->notifyTicketAssignment($ticket_data, $ticket_data['agente_email'], $ticket_data['agente_name']);
            }
            
            $message = "Agente asignado correctamente.";
        }
        
        // Agregar respuesta
        if ($action === 'add_response') {
            $response = trim($_POST['response']);
            $is_internal = isset($_POST['is_internal']) ? 1 : 0;
            
            if (!empty($response)) {
                $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ticket_id, $_SESSION['user_id'], $response, $is_internal]);
                $response_id = $pdo->lastInsertId();
                
                // Procesar archivos adjuntos
                if (!empty($_FILES['attachments']['name'][0])) {
                    $upload_dir = 'uploads/tickets/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $allowed_extensions = explode(',', $config['allowed_extensions'] ?? 'jpg,jpeg,png,pdf,doc,docx,txt');
                    $max_size = ($config['max_file_size'] ?? 10) * 1024 * 1024; // MB to bytes
                    
                    foreach ($_FILES['attachments']['name'] as $key => $filename) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            
                            if (in_array($file_extension, $allowed_extensions) && $_FILES['attachments']['size'][$key] <= $max_size) {
                                $unique_filename = uniqid() . '_' . $filename;
                                $file_path = $upload_dir . $unique_filename;
                                
                                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $file_path)) {
                                    $stmt = $pdo->prepare("INSERT INTO ticket_attachments (ticket_id, message_id, filename, original_filename, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $stmt->execute([
                                        $ticket_id, 
                                        $response_id, 
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
                
                // Actualizar timestamp del ticket
                $stmt = $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$ticket_id]);
                
                // Enviar notificación de nueva respuesta (solo si no es nota interna y el que responde no es el cliente)
                if (($config['email_notifications'] ?? '1') === '1' && !$is_internal && $_SESSION['user_role'] !== 'cliente') {
                    require_once 'includes/email.php';
                    $emailService = new EmailService();
                    
                    // Obtener datos del ticket y mensaje
                    $stmt = $pdo->prepare("
                        SELECT t.*, 
                               cliente.name as cliente_name, cliente.email as cliente_email, cliente.company as cliente_company,
                               responder.name as responder_name
                        FROM tickets t
                        JOIN users cliente ON t.cliente_id = cliente.id
                        JOIN users responder ON responder.id = ?
                        WHERE t.id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $ticket_id]);
                    $ticket_data = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("SELECT * FROM ticket_messages WHERE id = ?");
                    $stmt->execute([$response_id]);
                    $message_data = $stmt->fetch();
                    
                    $emailService->notifyNewResponse($ticket_data, $message_data, $ticket_data['responder_name']);
                }
                
                $message = "Respuesta agregada correctamente.";
            }
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener información del ticket
$stmt = $pdo->prepare("
    SELECT t.*, 
           cliente.name as cliente_name, cliente.email as cliente_email, cliente.company as cliente_company,
           agente.name as agente_name, agente.email as agente_email
    FROM tickets t
    LEFT JOIN users cliente ON t.cliente_id = cliente.id
    LEFT JOIN users agente ON t.agente_id = agente.id
    WHERE t.id = ?
");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}

// Verificar permisos
if ($_SESSION['user_role'] === 'cliente' && $ticket['cliente_id'] != $_SESSION['user_id']) {
    header('Location: mis-tickets.php');
    exit;
}

// Obtener respuestas/mensajes
$stmt = $pdo->prepare("
    SELECT tm.*, u.name as user_name, u.role as user_role
    FROM ticket_messages tm
    JOIN users u ON tm.user_id = u.id
    WHERE tm.ticket_id = ?
    ORDER BY tm.created_at ASC
");
$stmt->execute([$ticket_id]);
$messages = $stmt->fetchAll();

// Obtener adjuntos
$stmt = $pdo->prepare("
    SELECT ta.*, u.name as uploaded_by_name
    FROM ticket_attachments ta
    JOIN users u ON ta.uploaded_by = u.id
    WHERE ta.ticket_id = ?
    ORDER BY ta.created_at ASC
");
$stmt->execute([$ticket_id]);
$attachments = $stmt->fetchAll();

// Obtener agentes para asignación
$agents = [];
if ($_SESSION['user_role'] === 'admin') {
    $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'agente' ORDER BY name");
    $agents = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?php echo htmlspecialchars($ticket['ticket_number']); ?> - KubeAgency</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1d29;
            min-height: 100vh;
            color: #e2e8f0;
            overflow-x: hidden;
            font-size: 13px;
            line-height: 1.4;
            margin: 0px;
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
        
        .header a { color: white; text-decoration: none; }



        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        .ticket-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1rem;
        }

        .ticket-main {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .ticket-card {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 4px;
            padding: 1rem;
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #4a5568;
        }

        .ticket-title {
            color: #f7fafc;
            font-size: 1.125rem;
            font-weight: 500;
            margin: 0;
        }

        .ticket-id {
            color: #3182ce;
            font-size: 12px;
            font-weight: 500;
        }

        .ticket-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .meta-label {
            color: #cbd5e0;
            font-size: 11px;
            font-weight: 500;
        }

        .meta-value {
            color: #e2e8f0;
            font-size: 13px;
        }

        .ticket-description {
            background: #374151;
            border: 1px solid #4a5568;
            border-radius: 4px;
            padding: 0.75rem;
            color: #e2e8f0;
            font-size: 13px;
            line-height: 1.5;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .sidebar-card {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 4px;
            padding: 0.75rem;
        }

        .sidebar-title {
            color: #f7fafc;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .conversation {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 4px;
            padding: 1rem;
        }

        .conversation-title {
            color: #f7fafc;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .message {
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            border-radius: 4px;
            border: 1px solid #4a5568;
        }

        .message:last-child {
            margin-bottom: 0;
        }

        .message-user {
            background: #374151;
        }

        .message-agent {
            background: #1e40af;
            background: linear-gradient(135deg, #1e40af 0%, #3182ce 100%);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .message-author {
            color: #f7fafc;
            font-size: 12px;
            font-weight: 500;
        }

        .message-date {
            color: #cbd5e0;
            font-size: 10px;
        }

        .message-content {
            color: #e2e8f0;
            font-size: 13px;
            line-height: 1.5;
        }

        .response-form {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 4px;
            padding: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            color: #cbd5e0;
            font-size: 11px;
            font-weight: 500;
        }

        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #4a5568;
            border-radius: 4px;
            background: rgba(26, 27, 35, 0.8);
            color: #e2e8f0;
            font-size: 13px;
            transition: border-color 0.2s ease;
            font-family: inherit;
        }

        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3182ce;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group select {
            background-image: url("data:image/svg+xml;charset=utf-8,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%233182ce' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1rem;
            padding-right: 2rem;
        }

        .form-group select option {
            background: #1a1d29;
            color: #e2e8f0;
        }

        .btn {
            padding: 0.375rem 0.75rem;
            border: 1px solid #4a5568;
            border-radius: 4px;
            background: #6b7280;
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 400;
            transition: background-color 0.2s ease;
            font-family: inherit;
            font-size: 11px;
        }

        .btn:hover {
            background: #4b5563;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid #4a5568;
            color: #cbd5e0;
        }
        
        .btn-secondary:hover {
            background: #4a5568;
        }

        .badge {
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 500;
        }

        .badge-abierto { background: #3182ce; color: white; }
        .badge-en-progreso { background: #ed8936; color: white; }
        .badge-resuelto { background: #38a169; color: white; }
        .badge-cerrado { background: #718096; color: white; }

        .badge-alta { background: #e53e3e; color: white; }
        .badge-media { background: #ed8936; color: white; }
        .badge-baja { background: #38a169; color: white; }

        .attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .attachment {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: #374151;
            border: 1px solid #4a5568;
            border-radius: 4px;
            padding: 0.375rem 0.5rem;
            font-size: 11px;
        }

        .attachment a {
            color: #3182ce;
            text-decoration: none;
        }

        .attachment a:hover {
            color: #2c5282;
        }

        @media (max-width: 768px) {
            .ticket-layout {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="<?php echo $_SESSION['user_role'] === 'cliente' ? 'mis-tickets.php' : 'tickets.php'; ?>">
                <i class="fas fa-arrow-left"></i> Volver a Tickets
            </a>
        </div>
        <h2><i class="fas fa-ticket-alt"></i> Ticket <?php echo htmlspecialchars($ticket['ticket_number']); ?></h2>
        <div>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div style="background: #10b981; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background: #ef4444; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Información del Ticket -->
        <div class="ticket-card">
            <div class="ticket-header">
                <h3><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                <div style="margin-top: 1rem;">
                    <span class="badge badge-<?php echo strtolower($ticket['status']); ?>">
                        <?php echo ucfirst($ticket['status']); ?>
                    </span>
                    <span class="badge badge-<?php echo strtolower($ticket['priority']); ?>">
                        <?php echo ucfirst($ticket['priority']); ?>
                    </span>
                </div>
            </div>
            
            <div class="ticket-meta">
                <div class="meta-item">
                    <div class="meta-label">Cliente</div>
                    <div class="meta-value"><?php echo htmlspecialchars($ticket['cliente_name']); ?></div>
                    <div style="font-size: 0.8rem; opacity: 0.8;"><?php echo htmlspecialchars($ticket['cliente_email']); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Agente Asignado</div>
                    <div class="meta-value"><?php echo $ticket['agente_name'] ? htmlspecialchars($ticket['agente_name']) : 'Sin asignar'; ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Creado</div>
                    <div class="meta-value"><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Actualizado</div>
                    <div class="meta-value"><?php echo date('d/m/Y H:i', strtotime($ticket['updated_at'])); ?></div>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <h4 style="color: #4fd1c7; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-align-left"></i> Descripción:
                </h4>
                <p style="margin-top: 0.5rem; line-height: 1.6; color: #ffffff;"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
            </div>
        </div>

        <!-- Acciones Administrativas -->
        <?php if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'agente'): ?>
            <div class="ticket-card">
                <h3><i class="fas fa-cog"></i> Acciones del Ticket</h3>
                <div class="grid-2">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_status">
                        <div class="form-group">
                            <label>Cambiar Estado</label>
                            <select name="status" required>
                                <option value="abierto" <?php echo $ticket['status'] === 'abierto' ? 'selected' : ''; ?>>Abierto</option>
                                <option value="proceso" <?php echo $ticket['status'] === 'proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                <option value="cerrado" <?php echo $ticket['status'] === 'cerrado' ? 'selected' : ''; ?>>Cerrado</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Actualizar Estado
                        </button>
                    </form>
                    
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="assign_agent">
                            <div class="form-group">
                                <label>Asignar Agente</label>
                                <select name="agent_id">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>" 
                                                <?php echo $ticket['agente_id'] == $agent['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agent['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn">
                                <i class="fas fa-user-plus"></i> Asignar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Archivos Adjuntos -->
        <?php if (!empty($attachments)): ?>
            <div class="ticket-card">
                <h3><i class="fas fa-paperclip"></i> Archivos Adjuntos</h3>
                <?php foreach ($attachments as $attachment): ?>
                    <div class="attachment">
                        <i class="fas fa-file"></i>
                        <a href="uploads/tickets/<?php echo htmlspecialchars($attachment['filename']); ?>" 
                           target="_blank">
                            <?php echo htmlspecialchars($attachment['original_filename']); ?>
                        </a>
                        <span style="opacity: 0.7; font-size: 0.9rem;">
                            (<?php echo round($attachment['file_size'] / 1024, 2); ?> KB)
                            - Subido por <?php echo htmlspecialchars($attachment['uploaded_by_name']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Respuestas/Mensajes -->
        <div class="ticket-card">
            <h3><i class="fas fa-comments"></i> Conversación</h3>
            <?php foreach ($messages as $msg): ?>
                <div class="message <?php echo $msg['is_internal'] ? 'internal' : ''; ?>">
                    <div class="message-header">
                        <strong><?php echo htmlspecialchars($msg['user_name']); ?></strong>
                        <span>
                            <?php if ($msg['is_internal']): ?>
                                <i class="fas fa-lock" title="Nota interna"></i>
                            <?php endif; ?>
                            <?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?>
                        </span>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Agregar Respuesta -->
        <div class="ticket-card">
            <h3><i class="fas fa-reply"></i> Agregar Respuesta</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_response">
                
                <div class="form-group">
                    <label for="response">Respuesta</label>
                    <textarea id="response" name="response" placeholder="Escribe tu respuesta aquí..." required></textarea>
                </div>
                
                <?php if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'agente'): ?>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_internal" style="width: auto; margin-right: 0.5rem;">
                            Nota interna (solo visible para administradores y agentes)
                        </label>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Archivos Adjuntos</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip">
                        <label for="attachments" class="file-input-label">
                            <i class="fas fa-upload"></i> Seleccionar Archivos
                        </label>
                    </div>
                    <div style="font-size: 0.8rem; opacity: 0.8; margin-top: 0.5rem;">
                        Tamaño máximo: <?php echo $config['max_file_size'] ?? 10; ?>MB. 
                        Tipos permitidos: <?php echo $config['allowed_extensions'] ?? 'jpg,jpeg,png,pdf,doc,docx,txt'; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Enviar Respuesta
                </button>
            </form>
        </div>
    </div>

    <script>
        // Mostrar nombres de archivos seleccionados
        document.getElementById('attachments').addEventListener('change', function() {
            const files = this.files;
            const label = document.querySelector('.file-input-label');
            
            if (files.length > 0) {
                const fileNames = Array.from(files).map(f => f.name).join(', ');
                label.innerHTML = '<i class="fas fa-file"></i> ' + (files.length === 1 ? files[0].name : files.length + ' archivos seleccionados');
            } else {
                label.innerHTML = '<i class="fas fa-upload"></i> Seleccionar Archivos';
            }
        });
    </script>
</body>
</html> 