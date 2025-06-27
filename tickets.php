<?php
session_start();

// Verificar acceso
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

// Procesar acciones
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $ticket_id = $_POST['ticket_id'] ?? '';
        
        if (!empty($ticket_id)) {
            try {
                // Comenzar transacción para eliminar todo relacionado con el ticket
                $pdo->beginTransaction();
                
                // Eliminar archivos adjuntos del ticket
                $stmt = $pdo->prepare("SELECT filename FROM ticket_attachments WHERE ticket_id = ?");
                $stmt->execute([$ticket_id]);
                $files = $stmt->fetchAll();
                
                foreach ($files as $file) {
                    // Verificar que filename no esté vacío y sea válido
                    if (!empty($file['filename']) && trim($file['filename']) !== '') {
                        $file_path = __DIR__ . '/uploads/tickets/' . $file['filename'];
                        // Verificar que el archivo existe y no es un directorio
                        if (file_exists($file_path) && is_file($file_path)) {
                            unlink($file_path);
                        }
                    }
                }
                
                // Eliminar registros de archivos
                $stmt = $pdo->prepare("DELETE FROM ticket_attachments WHERE ticket_id = ?");
                $stmt->execute([$ticket_id]);
                
                // Eliminar mensajes del ticket
                $stmt = $pdo->prepare("DELETE FROM ticket_messages WHERE ticket_id = ?");
                $stmt->execute([$ticket_id]);
                
                // Eliminar el ticket
                $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
                $stmt->execute([$ticket_id]);
                
                $pdo->commit();
                $message = 'Ticket eliminado exitosamente';
                
            } catch(PDOException $e) {
                $pdo->rollBack();
                $error = 'Error al eliminar ticket: ' . $e->getMessage();
            }
        } else {
            $error = 'ID de ticket no válido';
        }
    }
}

// Obtener tickets
$stmt = $pdo->query("
    SELECT t.*, u.name as cliente_name, u.company as cliente_company,
           a.name as agente_name
    FROM tickets t 
    LEFT JOIN users u ON t.cliente_id = u.id 
    LEFT JOIN users a ON t.agente_id = a.id 
    ORDER BY t.created_at DESC
");
$tickets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tickets - KubeAgency</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1d29;
            min-height: 100vh;
            color: #e2e8f0;
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

        .header h1 {
            color: #f7fafc;
            font-size: 1.25rem;
            font-weight: 500;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header .actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.375rem 0.75rem;
            border: 1px solid #4a5568;
            border-radius: 4px;
            background: #6b7280;
            color: white;
            text-decoration: none;
            font-size: 11px;
            font-weight: 400;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-family: inherit;
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
            overflow-x: hidden;
            min-width: 0;
        }

        .tickets-table {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 4px;
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .table th,
        .table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #4a5568;
        }

        .table th {
            background: #374151;
            color: #f7fafc;
            font-weight: 500;
            font-size: 11px;
        }

        .table td {
            color: #e2e8f0;
        }

        .table tr:hover td {
            background: #374151;
        }

        .ticket-subject {
            color: #3182ce;
            text-decoration: none;
            font-weight: 500;
        }

        .ticket-subject:hover {
            color: #2c5282;
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

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #4a5568;
        }
        
        .card {
            background: #2d3748;
            border-radius: 6px;
            padding: 1rem;
            border: 1px solid #4a5568;
            margin-bottom: 1rem;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 6px;
            border: 1px solid #4a5568;
        }
        
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            background: #2d3748;
        }
        
        .tickets-table th {
            background: #374151;
            color: #f7fafc;
            font-weight: 500;
            font-size: 11px;
            padding: 0.75rem 0.5rem;
            text-align: left;
            border-bottom: 1px solid #4a5568;
        }
        
        .tickets-table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #4a5568;
            color: #e2e8f0;
            font-size: 12px;
        }
        
        .tickets-table tr:hover td {
            background: #374151;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-abierto { 
            background: #3182ce; 
            color: white; 
        }
        
        .status-proceso { 
            background: #ed8936; 
            color: white; 
        }
        
        .status-cerrado { 
            background: #38a169; 
            color: white; 
        }
        
        .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .priority-alta { 
            background: #e53e3e; 
            color: white; 
        }
        
        .priority-media { 
            background: #ed8936; 
            color: white; 
        }
        
        .priority-baja { 
            background: #38a169; 
            color: white; 
        }
        
        .priority-critica { 
            background: #c53030; 
            color: white; 
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 12px;
        }
        
        .alert-success {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #2f855a;
        }
        
        .alert-error {
            background: #fed7d7;
            border: 1px solid #feb2b2;
            color: #c53030;
        }
        
        .btn-danger {
            background: #e53e3e;
            border: 1px solid #c53030;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c53030;
        }
        
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 10px;
        }
        
        /* Modal de confirmación */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background: #2d3748;
            margin: 15% auto;
            padding: 2rem;
            border: 1px solid #4a5568;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        
        .modal-header {
            margin-bottom: 1rem;
        }
        
        .modal-header h3 {
            color: #f7fafc;
            margin-bottom: 0.5rem;
        }
        
        .modal-header p {
            color: #a0aec0;
            font-size: 0.9rem;
        }
        
        .modal-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
        <h2><i class="fas fa-headset"></i> KUBE Soporte - Tickets</h2>
        <div>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h3 style="margin-bottom: 1rem;">
                <i class="fas fa-ticket-alt"></i> Todos los Tickets (<?php echo count($tickets); ?>)
            </h3>
            
            <?php if (count($tickets) > 0): ?>
                <div class="table-container">
                    <table class="tickets-table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Asunto</th>
                                <th>Cliente</th>
                                <th>Empresa</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th>Agente</th>
                                <th>Creado</th>
                                <th style="width: 80px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ticket['ticket_number'] ?? 'KUBE-' . str_pad($ticket['id'], 3, '0', STR_PAD_LEFT)); ?></td>
                                    <td>
                                        <a href="ticket-detalle.php?id=<?php echo $ticket['id']; ?>" style="color: white; text-decoration: none;">
                                            <strong><?php echo htmlspecialchars($ticket['subject'] ?? $ticket['title']); ?></strong>
                                        </a>
                                        <br>
                                        <small style="opacity: 0.8;">
                                            <?php echo substr(htmlspecialchars($ticket['description']), 0, 100); ?>...
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['cliente_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['cliente_company'] ?? '-'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                            <?php echo str_replace('_', ' ', $ticket['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo $ticket['priority']; ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['agente_name'] ?? 'Sin asignar'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></td>
                                    <td>
                                        <button onclick="confirmDelete(<?php echo $ticket['id']; ?>, '<?php echo htmlspecialchars($ticket['ticket_number'] ?? 'KUBE-' . str_pad($ticket['id'], 3, '0', STR_PAD_LEFT), ENT_QUOTES); ?>')" 
                                                class="btn btn-danger btn-small" 
                                                title="Eliminar ticket">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <h4>No hay tickets registrados</h4>
                    <p>Los tickets aparecerán aquí cuando los clientes los creen.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #e53e3e;"></i> Confirmar Eliminación</h3>
                <p>¿Estás seguro de que deseas eliminar el ticket <strong id="ticketNumber"></strong>?</p>
                <p style="color: #f6ad55; font-size: 0.8rem; margin-top: 0.5rem;">
                    Esta acción <strong>NO SE PUEDE DESHACER</strong> y eliminará:
                </p>
                <ul style="text-align: left; color: #a0aec0; font-size: 0.8rem; margin: 0.5rem 0;">
                    <li>El ticket y toda su información</li>
                    <li>Todos los mensajes y respuestas</li>
                    <li>Archivos adjuntos del servidor</li>
                </ul>
            </div>
            <div class="modal-actions">
                <button onclick="closeDeleteModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button onclick="deleteTicket()" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para eliminar -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="ticket_id" id="deleteTicketId">
    </form>

    <script>
        let ticketToDelete = null;

        function confirmDelete(ticketId, ticketNumber) {
            ticketToDelete = ticketId;
            document.getElementById('ticketNumber').textContent = ticketNumber;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            ticketToDelete = null;
        }

        function deleteTicket() {
            if (ticketToDelete) {
                document.getElementById('deleteTicketId').value = ticketToDelete;
                document.getElementById('deleteForm').submit();
            }
        }

        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }

        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html> 