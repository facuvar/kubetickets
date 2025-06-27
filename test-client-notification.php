<?php
// Archivo de prueba para verificar notificaciones de respuesta de cliente
session_start();

// Verificar acceso de admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'includes/email.php';

$message = '';
$result = false;

if ($_POST) {
    try {
        $emailService = new EmailService();
        
        // Datos de prueba simulando un ticket y respuesta de cliente
        $ticket_data = [
            'id' => 1,
            'ticket_number' => 'KUBE-001',
            'subject' => 'Problema de prueba con notificación',
            'cliente_name' => 'Cliente de Prueba',
            'cliente_email' => 'cliente@ejemplo.com',
            'cliente_company' => 'Empresa de Prueba',
            'priority' => 'alta',
            'agente_name' => 'Agente Asignado',
            'agente_email' => 'agente@kubeagency.co'
        ];
        
        $message_data = [
            'message' => 'Esta es una respuesta de prueba del cliente. Por favor ayúdeme con mi problema urgente.',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $client_name = 'Cliente de Prueba';
        
        // Enviar notificación de prueba
        $result = $emailService->notifyClientResponse($ticket_data, $message_data, $client_name);
        
        if ($result) {
            $message = "✅ Notificación de respuesta de cliente enviada exitosamente a agentes/admins";
        } else {
            $message = "❌ Error al enviar la notificación";
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
    <title>Prueba Notificación Cliente - KUBE Soporte</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1d29;
            min-height: 100vh;
            color: #e2e8f0;
            font-size: 13px;
            line-height: 1.4;
        }

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
            max-width: 800px;
            margin: 0 auto;
            padding: 1rem;
        }

        .card {
            background: #2d3748;
            border-radius: 6px;
            padding: 1.5rem;
            border: 1px solid #4a5568;
            margin-bottom: 1rem;
        }

        .card h1 {
            color: #4fd1c7;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info {
            background: rgba(56, 178, 172, 0.1);
            border: 1px solid rgba(56, 178, 172, 0.3);
            color: #a7f3d0;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .btn {
            background: #4fd1c7;
            color: #1a1d29;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #38b2ac;
            transform: translateY(-1px);
        }

        .message {
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            font-weight: 500;
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

        .test-details {
            background: #374151;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }

        .test-details h3 {
            color: #4fd1c7;
            margin-bottom: 0.5rem;
        }

        .test-details p {
            margin-bottom: 0.5rem;
            font-size: 12px;
        }

        .test-details strong {
            color: #f7fafc;
        }
    </style>
</head>
<body>
    <div class="header">
        <div><a href="index.php"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a></div>
        <h2><i class="fas fa-headset"></i> KUBE Soporte - Prueba Notificación Cliente</h2>
        <div><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></div>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-bell"></i> Prueba de Notificación de Respuesta de Cliente</h1>
            
            <div class="info">
                <strong>ℹ️ Información:</strong><br>
                Esta herramienta permite probar que las notificaciones por email funcionen cuando un cliente responde en un ticket.
                Se enviará un email de prueba a todos los administradores y al agente asignado (si existe).
            </div>

            <div class="test-details">
                <h3>📋 Detalles de la Prueba</h3>
                <p><strong>Ticket:</strong> KUBE-001 - Problema de prueba con notificación</p>
                <p><strong>Cliente:</strong> Cliente de Prueba (cliente@ejemplo.com)</p>
                <p><strong>Empresa:</strong> Empresa de Prueba</p>
                <p><strong>Prioridad:</strong> Alta</p>
                <p><strong>Mensaje de prueba:</strong> "Esta es una respuesta de prueba del cliente. Por favor ayúdeme con mi problema urgente."</p>
                <p><strong>Destinatarios:</strong> Todos los admins + agente asignado (si existe)</p>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $result ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                    <?php if ($result): ?>
                        <br><small>Revisa las bandejas de entrada de los administradores y agentes.</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Enviar Prueba de Notificación
                </button>
            </form>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #4a5568;">
                <p style="font-size: 12px; opacity: 0.8;">
                    <strong>Nota:</strong> Esta prueba simula una respuesta de cliente en un ticket. 
                    El sistema debería enviar notificaciones a todos los administradores y al agente asignado al ticket.
                </p>
            </div>
        </div>
    </div>
</body>
</html> 