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

// Obtener tickets del cliente con información adicional
$stmt = $pdo->prepare("
    SELECT t.*, 
           a.name as agente_name,
           (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id AND tm.is_internal = 0) as total_messages,
           (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id AND tm.is_internal = 0 AND tm.user_id != t.cliente_id) as agent_responses,
           (SELECT tm.created_at FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.created_at DESC LIMIT 1) as last_activity,
           (SELECT u.name FROM ticket_messages tm JOIN users u ON tm.user_id = u.id WHERE tm.ticket_id = t.id ORDER BY tm.created_at DESC LIMIT 1) as last_responder
    FROM tickets t 
    LEFT JOIN users a ON t.agente_id = a.id 
    WHERE t.cliente_id = ? 
    ORDER BY t.updated_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tickets - KubeAgency</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1d29;
            min-height: 100vh;
            color: #e2e8f0;
            overflow-x: hidden;
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
        
        .header a { color: white; text-decoration: none; }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .card {
            background: #2d3748;
            border-radius: 6px;
            padding: 1rem;
            border: 1px solid #4a5568;
            margin-bottom: 1rem;
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
        
        .ticket-card {
            background: #2d3748;
            border-radius: 4px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border: 1px solid #4a5568;
            border-left: 2px solid #6b7280;
            transition: background-color 0.2s ease;
        }
        
        .ticket-card:hover {
            background: #374151;
        }
        
                .ticket-card.status-abierto {
            border-left-color: #4fd1c7;
        }

        .ticket-card.status-proceso {
            border-left-color: #f6ad55;
        }

        .ticket-card.status-cerrado {
            border-left-color: #6b7280;
        }

        .ticket-card.has-responses {
            border-right: 2px solid #6b7280;
        }

        .ticket-card.has-responses::after {
            content: '';
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 6px;
            height: 6px;
            background: #6b7280;
            border-radius: 50%;
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .ticket-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .ticket-id {
            color: #4fd1c7;
            font-weight: 600;
            background: rgba(56, 178, 172, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .ticket-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .status-badge, .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .status-abierto { background: rgba(79, 209, 199, 0.8); color: #1a202c; }
        .status-proceso { background: rgba(246, 173, 85, 0.8); color: #1a202c; }
        .status-cerrado { background: rgba(107, 114, 128, 0.8); color: #e2e8f0; }
        
        .priority-baja { background: #718096; }
        .priority-media { background: #6b7280; }
        .priority-alta { background: #ed8936; }
        .priority-urgente { background: #e53e3e; }
        
        .ticket-description {
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .ticket-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            opacity: 0.7;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(56, 178, 172, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(56, 178, 172, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(56, 178, 172, 0.2);
            background: rgba(56, 178, 172, 0.15);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .ticket-header { flex-direction: column; align-items: stretch; }
            .ticket-meta { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div><a href="index.php"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a></div>
        <h2><i class="fas fa-microchip"></i> KubeAgency Control - Mis Tickets</h2>
        <div>
            <a href="nuevo-ticket.php" class="btn" style="margin-right: 1rem;">
                <i class="fas fa-plus"></i> Nuevo Ticket
            </a>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
        <?php if (count($tickets) > 0): ?>
            <!-- Estadísticas rápidas -->
            <?php 
            $abiertos = count(array_filter($tickets, fn($t) => $t['status'] === 'abierto'));
            $en_proceso = count(array_filter($tickets, fn($t) => $t['status'] === 'proceso'));
            $cerrados = count(array_filter($tickets, fn($t) => $t['status'] === 'cerrado'));
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" style="color: #4fd1c7;"><?php echo $abiertos; ?></div>
                    <div class="stat-label">Abiertos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #4fd1c7;"><?php echo $en_proceso; ?></div>
                    <div class="stat-label">En Proceso</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #4fd1c7;"><?php echo $cerrados; ?></div>
                    <div class="stat-label">Cerrados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #4fd1c7;"><?php echo count($tickets); ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>

            <!-- Lista de tickets -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <h3 style="margin: 0;">
                        <i class="fas fa-list"></i> Mis Tickets (<?php echo count($tickets); ?>)
                    </h3>
                    
                    <?php if (array_filter($tickets, fn($t) => $t['agent_responses'] > 0)): ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; color: #4fd1c7; background: rgba(56, 178, 172, 0.1); padding: 0.5rem 1rem; border-radius: 20px; border: 1px solid rgba(56, 178, 172, 0.2);">
                            <div style="width: 8px; height: 8px; background: #4fd1c7; border-radius: 50%; box-shadow: 0 0 8px rgba(79, 209, 199, 0.6);"></div>
                            <span>Tickets con respuestas nuevas</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card status-<?php echo $ticket['status']; ?><?php echo $ticket['agent_responses'] > 0 ? ' has-responses' : ''; ?>">
                        <div class="ticket-header">
                            <div>
                                <div class="ticket-id">
                                    <a href="ticket-detalle.php?id=<?php echo $ticket['id']; ?>" style="color: #4fd1c7; text-decoration: none;">
                                        <?php echo htmlspecialchars($ticket['ticket_number'] ?? 'KUBE-' . str_pad($ticket['id'], 3, '0', STR_PAD_LEFT)); ?>
                                    </a>
                                </div>
                                <div class="ticket-title">
                                    <a href="ticket-detalle.php?id=<?php echo $ticket['id']; ?>" style="color: white; text-decoration: none;">
                                        <?php echo htmlspecialchars($ticket['subject'] ?? $ticket['title']); ?>
                                    </a>
                                </div>
                            </div>
                                                    <div class="ticket-badges">
                            <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                <?php echo str_replace('_', ' ', ucfirst($ticket['status'])); ?>
                            </span>
                            <span class="priority-badge priority-<?php echo $ticket['priority']; ?>">
                                <?php echo ucfirst($ticket['priority']); ?>
                            </span>
                            <?php if ($ticket['agent_responses'] > 0): ?>
                                <span class="response-badge" style="background: linear-gradient(135deg, #38b2ac, #4fd1c7); padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.8rem; color: white; margin-left: 0.5rem;">
                                    <i class="fas fa-reply"></i> <?php echo $ticket['agent_responses']; ?> respuesta<?php echo $ticket['agent_responses'] > 1 ? 's' : ''; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        </div>
                        
                        <div class="ticket-description">
                            <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                        </div>
                        
                        <div class="ticket-meta">
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>Creado: <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></span>
                            </div>
                            
                            <?php if ($ticket['last_activity']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-clock" style="color: #4fd1c7;"></i>
                                    <span>Última actividad: <?php echo date('d/m/Y H:i', strtotime($ticket['last_activity'])); ?>
                                        <?php if ($ticket['last_responder']): ?>
                                            por <strong><?php echo htmlspecialchars($ticket['last_responder']); ?></strong>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Actualizado: <?php echo date('d/m/Y H:i', strtotime($ticket['updated_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="meta-item">
                                <i class="fas fa-user-tie" style="color: <?php echo $ticket['agente_name'] ? '#4fd1c7' : '#6b7280'; ?>;"></i>
                                <span>Agente: <?php echo htmlspecialchars($ticket['agente_name'] ?? 'Sin asignar'); ?></span>
                            </div>
                            
                            <?php if ($ticket['total_messages'] > 0): ?>
                                <div class="meta-item">
                                    <i class="fas fa-comments" style="color: #4fd1c7;"></i>
                                    <span><?php echo $ticket['total_messages']; ?> mensaje<?php echo $ticket['total_messages'] > 1 ? 's' : ''; ?> en conversación</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($ticket['closed_at']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-check-circle" style="color: #4fd1c7;"></i>
                                    <span>Cerrado: <?php echo date('d/m/Y H:i', strtotime($ticket['closed_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>No tienes tickets registrados</h3>
                    <p style="margin: 1rem 0;">Cuando tengas algún problema o consulta, puedes crear un ticket de soporte.</p>
                    <a href="nuevo-ticket.php" class="btn">
                        <i class="fas fa-plus"></i> Crear mi primer ticket
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 