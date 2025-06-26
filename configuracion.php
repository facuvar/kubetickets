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

$message = '';
$error = '';

// Procesar formulario de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_config') {
        try {
            $configs = [
                'ticket_prefix' => $_POST['ticket_prefix'] ?? 'KUBE',
                'max_file_size' => $_POST['max_file_size'] ?? '10',
                'allowed_extensions' => $_POST['allowed_extensions'] ?? 'jpg,jpeg,png,pdf,doc,docx,txt,zip',
                'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
                'auto_assignment' => isset($_POST['auto_assignment']) ? '1' : '0',
                'company_name' => $_POST['company_name'] ?? 'KubeAgency',
                'support_email' => $_POST['support_email'] ?? 'soporte@kubeagency.co',
                'max_tickets_per_user' => $_POST['max_tickets_per_user'] ?? '0'
            ];
            
            foreach ($configs as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO system_config (config_key, config_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE config_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = "Configuración actualizada correctamente.";
        } catch (Exception $e) {
            $error = "Error al actualizar configuración: " . $e->getMessage();
        }
    }
}

// Obtener configuración actual
$stmt = $pdo->query("SELECT config_key, config_value FROM system_config");
$current_config = [];
while ($row = $stmt->fetch()) {
    $current_config[$row['config_key']] = $row['config_value'];
}

// Valores por defecto
$defaults = [
    'ticket_prefix' => 'KUBE',
    'max_file_size' => '10',
    'allowed_extensions' => 'jpg,jpeg,png,pdf,doc,docx,txt,zip',
    'email_notifications' => '1',
    'auto_assignment' => '0',
    'company_name' => 'KubeAgency',
    'support_email' => 'soporte@kubeagency.co',
    'max_tickets_per_user' => '0'
];

// Obtener estadísticas del sistema
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM tickets");
$stats['total_tickets'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'cliente'");
$stats['total_clients'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'agente'");
$stats['total_agents'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM ticket_attachments");
$stats['total_attachments'] = $stmt->fetch()['total'];

// Obtener información del servidor
$server_info = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $pdo->query('SELECT VERSION() as version')->fetch()['version'],
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time')
];

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - KubeAgency</title>
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4fd1c7;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            background: rgba(55, 65, 81, 0.8);
            border: 1px solid rgba(74, 85, 104, 0.6);
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 0.875rem;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4fd1c7;
            box-shadow: 0 0 0 3px rgba(79, 209, 199, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .btn {
            background: #4fd1c7;
            color: #1a1d29;
            padding: 0.75rem 1.5rem;
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(74, 85, 104, 0.3);
        }

        .info-label {
            font-weight: 500;
            color: #4fd1c7;
        }

        .info-value {
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
        <h2><i class="fas fa-microchip"></i> KubeAgency Control - Configuración</h2>
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

        <!-- Estadísticas del Sistema -->
        <div class="card">
            <h3><i class="fas fa-chart-bar"></i> Estadísticas del Sistema</h3>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_tickets']; ?></div>
                    <div class="stat-label">Total Tickets</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_clients']; ?></div>
                    <div class="stat-label">Clientes Registrados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_agents']; ?></div>
                    <div class="stat-label">Agentes Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_attachments']; ?></div>
                    <div class="stat-label">Archivos Adjuntos</div>
                </div>
            </div>
        </div>

        <!-- Configuración General -->
        <div class="card">
            <h3><i class="fas fa-cog"></i> Configuración General</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_config">
                
                <div class="form-grid">
                    <div>
                        <div class="form-group">
                            <label for="company_name">Nombre de la Empresa</label>
                            <input type="text" id="company_name" name="company_name" 
                                   value="<?php echo htmlspecialchars($current_config['company_name'] ?? $defaults['company_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="support_email">Email de Soporte</label>
                            <input type="email" id="support_email" name="support_email" 
                                   value="<?php echo htmlspecialchars($current_config['support_email'] ?? $defaults['support_email']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="ticket_prefix">Prefijo de Tickets</label>
                            <input type="text" id="ticket_prefix" name="ticket_prefix" 
                                   value="<?php echo htmlspecialchars($current_config['ticket_prefix'] ?? $defaults['ticket_prefix']); ?>"
                                   placeholder="Ej: KUBE, SUPPORT, etc.">
                        </div>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="max_file_size">Tamaño Máximo de Archivo (MB)</label>
                            <input type="number" id="max_file_size" name="max_file_size" 
                                   value="<?php echo htmlspecialchars($current_config['max_file_size'] ?? $defaults['max_file_size']); ?>"
                                   min="1" max="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="allowed_extensions">Extensiones Permitidas</label>
                            <input type="text" id="allowed_extensions" name="allowed_extensions" 
                                   value="<?php echo htmlspecialchars($current_config['allowed_extensions'] ?? $defaults['allowed_extensions']); ?>"
                                   placeholder="jpg,png,pdf,doc,txt">
                        </div>
                        
                        <div class="form-group">
                            <label for="max_tickets_per_user">Máximo Tickets por Usuario (0 = ilimitado)</label>
                            <input type="number" id="max_tickets_per_user" name="max_tickets_per_user" 
                                   value="<?php echo htmlspecialchars($current_config['max_tickets_per_user'] ?? $defaults['max_tickets_per_user']); ?>"
                                   min="0">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Opciones del Sistema</label>
                    <div class="checkbox-group">
                        <input type="checkbox" id="email_notifications" name="email_notifications" 
                               <?php echo ($current_config['email_notifications'] ?? $defaults['email_notifications']) === '1' ? 'checked' : ''; ?>>
                        <label for="email_notifications">Activar notificaciones por email</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="auto_assignment" name="auto_assignment" 
                               <?php echo ($current_config['auto_assignment'] ?? $defaults['auto_assignment']) === '1' ? 'checked' : ''; ?>>
                        <label for="auto_assignment">Asignación automática de tickets</label>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Guardar Configuración
                </button>
            </form>
        </div>

        <!-- Información del Servidor -->
        <div class="card">
            <h3><i class="fas fa-server"></i> Información del Servidor</h3>
            
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Versión PHP:</span>
                        <span class="info-value"><?php echo $server_info['php_version']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Versión MySQL:</span>
                        <span class="info-value"><?php echo $server_info['mysql_version']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Entorno:</span>
                        <span class="info-value"><?php echo $config->getEnvironment(); ?></span>
                    </div>
                </div>
                
                <div>
                    <div class="info-item">
                        <span class="info-label">Límite de subida:</span>
                        <span class="info-value"><?php echo $server_info['upload_max_filesize']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tamaño POST máximo:</span>
                        <span class="info-value"><?php echo $server_info['post_max_size']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Límite de memoria:</span>
                        <span class="info-value"><?php echo $server_info['memory_limit']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegación -->
        <div class="card">
            <a href="gestionar-archivos.php" class="btn btn-secondary">
                <i class="fas fa-hdd"></i> Gestionar Archivos
            </a>
            <a href="usuarios.php" class="btn btn-secondary">
                <i class="fas fa-users"></i> Gestionar Usuarios
            </a>
            <a href="reportes.php" class="btn btn-secondary">
                <i class="fas fa-chart-line"></i> Ver Reportes
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>
</body>
</html> 