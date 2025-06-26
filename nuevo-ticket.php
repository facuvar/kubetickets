<?php
session_start();

// Verificar acceso de cliente
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cliente') {
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

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'media';
    $category = trim($_POST['category'] ?? '');
    
    if (!empty($subject) && !empty($description)) {
        try {
            // Generar número de ticket
            $prefix = $config['ticket_prefix'] ?? 'KUBE';
            $stmt = $pdo->query("SELECT MAX(id) as max_id FROM tickets");
            $max_id = $stmt->fetch()['max_id'] ?? 0;
            $ticket_number = $prefix . '-' . str_pad($max_id + 1, 3, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO tickets (ticket_number, subject, description, priority, category, cliente_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ticket_number, $subject, $description, $priority, $category, $_SESSION['user_id']]);
            $ticket_id = $pdo->lastInsertId();
            
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
            }
            
            $message = "Ticket {$ticket_number} creado exitosamente. Será atendido a la brevedad.";
            
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
    <title>Crear Nuevo Ticket - KubeAgency</title>
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

        /* Fondo animado con partículas */
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
            font-size: 12px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header a { 
            color: white; 
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .header a:hover {
            color: #4fd1c7;
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

        .header h2 i {
            color: #4fd1c7;
            filter: drop-shadow(0 0 8px rgba(79, 209, 199, 0.3));
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
            position: relative;
        }

        .form-card {
            background: linear-gradient(135deg, rgba(45, 55, 72, 0.95) 0%, rgba(55, 65, 81, 0.95) 100%);
            border: 1px solid rgba(74, 85, 104, 0.8);
            border-radius: 12px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4fd1c7, #38b2ac, #4fd1c7);
            border-radius: 12px 12px 0 0;
        }

        .card-header {
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
        }

        .card-header h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #f7fafc;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .card-header h3 i {
            color: #4fd1c7;
            filter: drop-shadow(0 0 10px rgba(79, 209, 199, 0.4));
        }

        .card-header p {
            color: #a0aec0;
            font-size: 0.95rem;
        }

        .form-grid {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #e2e8f0;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .form-group label i {
            color: #4fd1c7;
            font-size: 0.8rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid rgba(74, 85, 104, 0.6);
            border-radius: 8px;
            background: rgba(26, 32, 44, 0.8);
            color: #e2e8f0;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4fd1c7;
            box-shadow: 0 0 0 3px rgba(79, 209, 199, 0.1), 0 0 15px rgba(79, 209, 199, 0.2);
            background: rgba(26, 32, 44, 0.95);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group select {
            background-image: url("data:image/svg+xml;charset=utf-8,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234fd1c7' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 1rem center;
            background-repeat: no-repeat;
            background-size: 1rem;
            padding-right: 3rem;
            cursor: pointer;
        }

        .form-group select option {
            background: #1a202c;
            color: #e2e8f0;
            padding: 0.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #4fd1c7 0%, #38b2ac 100%);
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 209, 199, 0.3);
            background: linear-gradient(135deg, #38b2ac 0%, #319795 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, transparent 0%, rgba(74, 85, 104, 0.3) 100%);
            border: 2px solid rgba(74, 85, 104, 0.6);
            color: #cbd5e0;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, rgba(74, 85, 104, 0.3) 0%, rgba(74, 85, 104, 0.5) 100%);
            border-color: #4fd1c7;
            color: #4fd1c7;
            box-shadow: 0 10px 20px rgba(74, 85, 104, 0.2);
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            backdrop-filter: blur(10px);
            animation: slideInDown 0.5s ease;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.15) 0%, rgba(56, 178, 172, 0.15) 100%);
            border: 1px solid rgba(72, 187, 120, 0.3);
            color: #68d391;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(245, 101, 101, 0.15) 0%, rgba(229, 62, 62, 0.15) 100%);
            border: 1px solid rgba(245, 101, 101, 0.3);
            color: #fc8181;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .required {
            color: #f56565;
        }
        
        .help-text {
            font-size: 0.8rem;
            color: #a0aec0;
            margin-top: 0.5rem;
            line-height: 1.4;
        }
        
        .priority-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-top: 0.75rem;
        }
        
        .priority-item {
            background: linear-gradient(135deg, rgba(79, 209, 199, 0.05) 0%, rgba(56, 178, 172, 0.08) 100%);
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(79, 209, 199, 0.1);
            cursor: default;
        }

        .priority-item:hover {
            background: linear-gradient(135deg, rgba(79, 209, 199, 0.1) 0%, rgba(56, 178, 172, 0.15) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(79, 209, 199, 0.1);
        }
        
        .priority-baja { border-left: 4px solid #6b7280; }
        .priority-media { border-left: 4px solid #3b82f6; }
        .priority-alta { border-left: 4px solid #f59e0b; }
        .priority-critica { border-left: 4px solid #ef4444; }

        .file-upload-area {
            border: 2px dashed rgba(74, 85, 104, 0.6);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            background: rgba(26, 32, 44, 0.3);
        }

        .file-upload-area:hover {
            border-color: #4fd1c7;
            background: rgba(79, 209, 199, 0.05);
        }

        .file-upload-area input[type="file"] {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(26, 32, 44, 0.8);
            border: 1px solid rgba(74, 85, 104, 0.6);
            border-radius: 6px;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .category-hint {
            background: rgba(79, 209, 199, 0.05);
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            text-align: center;
            border: 1px solid rgba(79, 209, 199, 0.1);
        }
        
        @media (max-width: 768px) {
            .container { 
                padding: 1rem; 
            }
            
            .form-card {
                padding: 1.5rem;
            }
            
            .actions { 
                flex-direction: column; 
            }
            
            .btn {
                width: 100%;
            }
            
            .card-header h3 {
                font-size: 1.5rem;
            }
        }

        /* Animaciones para los elementos del formulario */
        .form-group {
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        .form-group:nth-child(5) { animation-delay: 0.5s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div><a href="index.php"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a></div>
        <h2><i class="fas fa-microchip"></i> KubeAgency Control - Nuevo Ticket</h2>
        <div>
            <a href="mis-tickets.php" class="btn btn-secondary" style="margin-right: 1rem;">
                <i class="fas fa-list"></i> Mis Tickets
            </a>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
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

        <div class="form-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-plus-circle"></i> 
                    Nuevo Ticket de Soporte
                </h3>
                <p>Complete el formulario para reportar un problema o solicitar ayuda</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="subject">
                            <i class="fas fa-edit"></i>
                            Asunto del Ticket <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="subject" 
                            name="subject" 
                            placeholder="Ej: Error al cargar la página de reportes"
                            value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                            required
                            maxlength="255"
                        >
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i>
                            Escriba un título claro y descriptivo del problema o solicitud
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">
                            <i class="fas fa-tags"></i>
                            Categoría
                        </label>
                        <select id="category" name="category">
                            <option value="">Seleccionar categoría</option>
                            <option value="tecnico" <?php echo ($_POST['category'] ?? '') === 'tecnico' ? 'selected' : ''; ?>>🔧 Soporte Técnico</option>
                            <option value="cuenta" <?php echo ($_POST['category'] ?? '') === 'cuenta' ? 'selected' : ''; ?>>👤 Problema de Cuenta</option>
                            <option value="facturacion" <?php echo ($_POST['category'] ?? '') === 'facturacion' ? 'selected' : ''; ?>>💳 Facturación</option>
                            <option value="funcionalidad" <?php echo ($_POST['category'] ?? '') === 'funcionalidad' ? 'selected' : ''; ?>>✨ Nueva Funcionalidad</option>
                            <option value="general" <?php echo ($_POST['category'] ?? '') === 'general' ? 'selected' : ''; ?>>💬 Consulta General</option>
                        </select>
                        <div class="category-grid">
                            <div class="category-hint">🔧 Técnico</div>
                            <div class="category-hint">👤 Cuenta</div>
                            <div class="category-hint">💳 Facturación</div>
                            <div class="category-hint">✨ Funcionalidad</div>
                            <div class="category-hint">💬 General</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">
                            <i class="fas fa-exclamation-triangle"></i>
                            Prioridad <span class="required">*</span>
                        </label>
                        <select id="priority" name="priority" required>
                            <option value="baja" <?php echo ($_POST['priority'] ?? '') === 'baja' ? 'selected' : ''; ?>>
                                🟢 Baja - Consulta general
                            </option>
                            <option value="media" <?php echo ($_POST['priority'] ?? 'media') === 'media' ? 'selected' : ''; ?>>
                                🟡 Media - Problema que no bloquea trabajo
                            </option>
                            <option value="alta" <?php echo ($_POST['priority'] ?? '') === 'alta' ? 'selected' : ''; ?>>
                                🟠 Alta - Problema que impacta trabajo
                            </option>
                            <option value="critica" <?php echo ($_POST['priority'] ?? '') === 'critica' ? 'selected' : ''; ?>>
                                🔴 Crítica - Sistema fuera de servicio
                            </option>
                        </select>
                        
                        <div class="priority-info">
                            <div class="priority-item priority-baja">
                                <strong>🟢 Baja:</strong> Consultas, dudas generales
                            </div>
                            <div class="priority-item priority-media">
                                <strong>🟡 Media:</strong> Problemas menores
                            </div>
                            <div class="priority-item priority-alta">
                                <strong>🟠 Alta:</strong> Impacta productividad
                            </div>
                            <div class="priority-item priority-critica">
                                <strong>🔴 Crítica:</strong> Sistema crítico
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">
                            <i class="fas fa-file-alt"></i>
                            Descripción Detallada <span class="required">*</span>
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            placeholder="Describa detalladamente el problema, incluyendo:
• ¿Qué estaba haciendo cuando ocurrió?
• ¿Qué mensaje de error apareció (si aplica)?
• ¿Desde cuándo está ocurriendo?
• ¿Ha intentado alguna solución?
• Pasos para reproducir el problema"
                            required
                        ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="help-text">
                            <i class="fas fa-lightbulb"></i>
                            Proporcione la mayor cantidad de detalles posibles para una resolución más rápida
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="attachments">
                            <i class="fas fa-paperclip"></i>
                            Archivos Adjuntos
                        </label>
                        <div class="file-upload-area">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #4fd1c7; margin-bottom: 0.5rem;"></i>
                            <p>Arrastre archivos aquí o haga clic para seleccionar</p>
                            <input 
                                type="file" 
                                id="attachments" 
                                name="attachments[]" 
                                multiple 
                                accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip"
                            >
                        </div>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i>
                            Capturas de pantalla, documentos o archivos relevantes.<br>
                            <strong>Tamaño máximo:</strong> <?php echo $config['max_file_size'] ?? 10; ?>MB por archivo | 
                            <strong>Tipos permitidos:</strong> <?php echo $config['allowed_extensions'] ?? 'jpg,jpeg,png,pdf,doc,docx,txt,zip'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i>
                        Crear Ticket
                    </button>
                    <a href="mis-tickets.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i>
                        Ver Mis Tickets
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 