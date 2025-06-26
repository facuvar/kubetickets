<?php
// Iniciar sesión solo si no hay una activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar acceso de admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once 'includes/email.php';

$message = '';
$result = false;

if ($_POST) {
    $test_email = $_POST['test_email'] ?? 'info@kubeagency.co';
    $emailService = new EmailService();
    
    $subject = 'Prueba de Sistema de Tickets KubeAgency';
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 30px 20px; text-align: center; }
            .content { padding: 30px 20px; line-height: 1.6; }
            .success { background: #10b981; color: white; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>KubeAgency</h1>
                <p>Sistema de Soporte Técnico</p>
            </div>
            <div class="content">
                <h2>¡Configuración de Email Exitosa!</h2>
                <p>Este es un email de prueba para verificar que el sistema de notificaciones esté funcionando correctamente.</p>
                
                <div class="success">
                    ✅ La configuración de SendGrid está funcionando correctamente
                </div>
                
                <p><strong>Funcionalidades habilitadas:</strong></p>
                <ul>
                    <li>Notificaciones de nuevos tickets a administradores</li>
                    <li>Notificaciones de cambios de estado a clientes</li>
                    <li>Notificaciones de nuevas respuestas a clientes</li>
                    <li>Notificaciones de asignación a agentes</li>
                </ul>
                
                <p>El sistema está listo para enviar notificaciones automáticamente.</p>
                <p><strong>Enviado el:</strong> ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    try {
        $result = $emailService->sendEmail($test_email, $subject, $body, true);
        
        if ($result) {
            $message = "✅ Email enviado exitosamente a $test_email";
        } else {
            $message = "❌ Error al enviar el email";
        }
    } catch (Exception $e) {
        $message = "❌ Excepción: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Email - KubeAgency</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0a0b0d;
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }

        /* Fondo con patrón hexagonal */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 25% 25%, #1a1b23 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, #16213e 0%, transparent 50%),
                linear-gradient(135deg, #0a0b0d 0%, #1a1b23 50%, #0f1419 100%);
            background-size: 300px 300px, 300px 300px, 100% 100%;
            z-index: -2;
        }

        /* Patrón hexagonal sutil */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 1px 1px, rgba(56, 178, 172, 0.1) 1px, transparent 0);
            background-size: 30px 30px;
            z-index: -1;
        }
        .header {
            background: rgba(26, 27, 35, 0.8);
            backdrop-filter: blur(20px);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(56, 178, 172, 0.1);
            position: relative;
            margin-bottom: 2rem;
        }

        .header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #38b2ac, #4fd1c7, #38b2ac, transparent);
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
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background: rgba(26, 27, 35, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(56, 178, 172, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #38b2ac, #4fd1c7, #38b2ac);
        }
        h1 {
            color: #4fd1c7;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
            font-size: 2rem;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: white;
        }
        input[type="email"] {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 1px solid rgba(56, 178, 172, 0.2);
            border-radius: 12px;
            background: rgba(56, 178, 172, 0.05);
            color: white;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        input[type="email"]::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        input[type="email"]:focus {
            outline: none;
            border-color: #38b2ac;
            background: rgba(56, 178, 172, 0.1);
            box-shadow: 0 0 0 3px rgba(56, 178, 172, 0.1);
            transform: translateY(-1px);
        }
        .btn {
            background: linear-gradient(135deg, #38b2ac, #4fd1c7);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-family: inherit;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(56, 178, 172, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 600;
        }
        .success {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4fd1c7;
            text-decoration: none;
            margin-top: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .back-link:hover {
            color: #38b2ac;
            background: rgba(56, 178, 172, 0.1);
            transform: translateX(-3px);
        }
        .info {
            background: rgba(56, 178, 172, 0.1);
            border: 1px solid rgba(56, 178, 172, 0.3);
            color: #a7f3d0;
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <div class="header">
        <div><a href="configuracion.php"><i class="fas fa-arrow-left"></i> Volver a Configuración</a></div>
        <h2><i class="fas fa-microchip"></i> KubeAgency Control - Test Email</h2>
        <div><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></div>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-paper-plane"></i> Prueba de Sistema de Emails</h1>
            
            <div class="info">
                <strong>ℹ️ Información:</strong><br>
                Esta herramienta permite probar que el sistema de notificaciones por email esté funcionando correctamente. 
                Se enviará un email de prueba usando la configuración de SendGrid.
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $result ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                    <?php if ($result): ?>
                        <br><small>Revisa tu bandeja de entrada y carpeta de spam.</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="test_email">Email de destino para la prueba:</label>
                    <input type="email" id="test_email" name="test_email" 
                           value="<?php echo htmlspecialchars($_POST['test_email'] ?? 'info@kubeagency.co'); ?>" 
                           required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Enviar Email de Prueba
                </button>
            </form>

        </div>
    </div>
</body>
</html> 
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-family: inherit;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(56, 178, 172, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 600;
        }
        .success {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4fd1c7;
            text-decoration: none;
            margin-top: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .back-link:hover {
            color: #38b2ac;
            background: rgba(56, 178, 172, 0.1);
            transform: translateX(-3px);
        }
        .info {
            background: rgba(56, 178, 172, 0.1);
            border: 1px solid rgba(56, 178, 172, 0.3);
            color: #a7f3d0;
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <div class="header">
        <div><a href="configuracion.php"><i class="fas fa-arrow-left"></i> Volver a Configuración</a></div>
        <h2><i class="fas fa-microchip"></i> KubeAgency Control - Test Email</h2>
        <div><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></div>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-paper-plane"></i> Prueba de Sistema de Emails</h1>
            
            <div class="info">
                <strong>ℹ️ Información:</strong><br>
                Esta herramienta permite probar que el sistema de notificaciones por email esté funcionando correctamente. 
                Se enviará un email de prueba usando la configuración de SendGrid.
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $result ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                    <?php if ($result): ?>
                        <br><small>Revisa tu bandeja de entrada y carpeta de spam.</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="test_email">Email de destino para la prueba:</label>
                    <input type="email" id="test_email" name="test_email" 
                           value="<?php echo htmlspecialchars($_POST['test_email'] ?? 'info@kubeagency.co'); ?>" 
                           required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Enviar Email de Prueba
                </button>
            </form>

        </div>
    </div>
</body>
</html> 