<?php
session_start();

// Configuración de base de datos
$host = 'localhost';
$dbname = 'sistema_tickets_kube';
$username = 'root';
$password = '';

// Verificar si el usuario está logueado
$user_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? null;

// Si no está logueado, redirigir al login
if (!$user_logged_in) {
    header('Location: login.php');
    exit;
}

// Conectar a la base de datos
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    try {
        $pdo_temp = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS $dbname");
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        include 'database/setup.php';
    } catch(PDOException $e2) {
        die("Error de conexión: " . $e2->getMessage());
    }
}

// Obtener estadísticas según el rol del usuario
$stats = [];
try {
    if ($user_role === 'cliente') {
        // Para clientes: solo mostrar SUS tickets
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE cliente_id = ? AND status = 'abierto'");
        $stmt->execute([$user_id]);
        $stats['tickets_abiertos'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE cliente_id = ? AND status = 'cerrado'");
        $stmt->execute([$user_id]);
        $stats['tickets_cerrados'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE cliente_id = ? AND status = 'proceso'");
        $stmt->execute([$user_id]);
        $stats['tickets_proceso'] = $stmt->fetch()['count'] ?? 0;
    } else {
        // Para admin/agente: mostrar estadísticas globales
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'abierto'");
        $stats['tickets_abiertos'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'cerrado'");
        $stats['tickets_cerrados'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'proceso'");
        $stats['tickets_proceso'] = $stmt->fetch()['count'] ?? 0;
    }
    
    $stats['total'] = $stats['tickets_abiertos'] + $stats['tickets_cerrados'] + $stats['tickets_proceso'];
    
    // Calcular eficiencia
    $efficiency = 0;
    if ($stats['total'] > 0) {
        $efficiency = round(($stats['tickets_cerrados'] / $stats['total']) * 100);
    }
    
} catch(PDOException $e) {
    $stats = ['tickets_abiertos' => 0, 'tickets_cerrados' => 0, 'tickets_proceso' => 0, 'total' => 0];
    $efficiency = 0;
}

// Actividad reciente según el rol
if ($user_role === 'cliente') {
    // Para clientes: solo mostrar SUS tickets
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT t.*, u.name as cliente_name, u.company as cliente_company 
        FROM tickets t 
        LEFT JOIN users u ON t.cliente_id = u.id 
        WHERE t.cliente_id = ?
        ORDER BY t.updated_at DESC 
        LIMIT 8
    ");
    $stmt->execute([$user_id]);
    $actividad_reciente = $stmt->fetchAll();
    
    // No mostrar top empresas para clientes
    $empresas_top = [];
} else {
    // Para admin/agente: mostrar actividad global
    $stmt = $pdo->query("
        SELECT t.*, u.name as cliente_name, u.company as cliente_company 
        FROM tickets t 
        LEFT JOIN users u ON t.cliente_id = u.id 
        ORDER BY t.updated_at DESC 
        LIMIT 8
    ");
    $actividad_reciente = $stmt->fetchAll();
    
    // Top empresas solo para admin/agente
    $stmt = $pdo->query("
        SELECT u.company, COUNT(t.id) as ticket_count 
        FROM users u 
        LEFT JOIN tickets t ON u.id = t.cliente_id 
        WHERE u.role = 'cliente' AND u.company IS NOT NULL AND u.company != ''
        GROUP BY u.company 
        ORDER BY ticket_count DESC 
        LIMIT 5
    ");
    $empresas_top = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KubeAgency - Control Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            border-bottom: 1px solid #4a5568;
            padding: 0.75rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #38b2ac;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo i {
            background: linear-gradient(135deg, #38b2ac, #4fd1c7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #a0aec0;
        }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #38b2ac, #4fd1c7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        .user-name {
            font-weight: 500;
            color: #f7fafc;
        }

        .user-role {
            font-size: 0.75rem;
            color: #a0aec0;
            text-transform: uppercase;
            background: rgba(56, 178, 172, 0.2);
            padding: 0.125rem 0.5rem;
            border-radius: 8px;
            border: 1px solid rgba(56, 178, 172, 0.3);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-link {
            color: #cbd5e0;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link:hover {
            background: rgba(56, 178, 172, 0.1);
            color: #38b2ac;
        }

        .logout-btn {
            color: #f56565;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            font-weight: 400;
        }

        .logout-btn:hover {
            background: rgba(245, 101, 101, 0.1);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            width: 100%;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #38b2ac, #4fd1c7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: #a0aec0;
            font-size: 1.125rem;
            font-weight: 400;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: rgba(56, 178, 172, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(56, 178, 172, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(56, 178, 172, 0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.5s ease;
            opacity: 0;
        }

        .stat-card:hover::before {
            opacity: 1;
            animation: shimmer 2s infinite;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(56, 178, 172, 0.2);
            background: rgba(56, 178, 172, 0.15);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #38b2ac, #4fd1c7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .stat-label {
            color: #a0aec0;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }

        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .chart-card {
            background: rgba(56, 178, 172, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(56, 178, 172, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(56, 178, 172, 0.15);
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #f7fafc;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-title i {
            color: #38b2ac;
        }

        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        .activity-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .activity-card {
            background: rgba(56, 178, 172, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(56, 178, 172, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(56, 178, 172, 0.15);
        }

        .activity-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #f7fafc;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activity-title i {
            color: #38b2ac;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .activity-item:hover {
            background: rgba(56, 178, 172, 0.1);
            border-color: rgba(56, 178, 172, 0.2);
        }

        .activity-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #38b2ac, #4fd1c7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.875rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content h4 {
            color: #f7fafc;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .activity-content p {
            color: #a0aec0;
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
        }

        .activity-content small {
            color: #718096;
            font-size: 0.625rem;
        }

        .activity-time {
            color: #38b2ac;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .company-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .company-item:hover {
            background: rgba(56, 178, 172, 0.1);
            border-color: rgba(56, 178, 172, 0.2);
        }

        .company-name {
            color: #f7fafc;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .company-tickets {
            color: #38b2ac;
            font-size: 0.875rem;
            font-weight: 600;
            background: rgba(56, 178, 172, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
        }

        .quick-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(56, 178, 172, 0.03);
            border-radius: 12px;
            border: 1px solid rgba(56, 178, 172, 0.1);
        }

        .quick-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            border: 1px solid rgba(74, 85, 104, 0.3);
            background: rgba(45, 55, 72, 0.6);
            color: #cbd5e0;
            backdrop-filter: blur(5px);
            min-width: 120px;
            justify-content: center;
        }

        .quick-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(56, 178, 172, 0.15);
            background: rgba(56, 178, 172, 0.1);
            border-color: rgba(56, 178, 172, 0.3);
            color: #4fd1c7;
        }

        .quick-btn.primary {
            background: linear-gradient(135deg, rgba(56, 178, 172, 0.8), rgba(79, 209, 199, 0.8));
            color: white;
            border-color: rgba(56, 178, 172, 0.4);
            font-weight: 600;
        }

        .quick-btn.primary:hover {
            background: linear-gradient(135deg, rgba(56, 178, 172, 0.9), rgba(79, 209, 199, 0.9));
            box-shadow: 0 4px 12px rgba(56, 178, 172, 0.25);
            color: white;
        }

        .quick-btn i {
            font-size: 0.875rem;
        }

        .quick-btn span {
            font-size: 0.75rem;
            font-weight: inherit;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            border: 1px solid rgba(56, 178, 172, 0.3);
            background: rgba(56, 178, 172, 0.1);
            color: #38b2ac;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(56, 178, 172, 0.2);
            background: rgba(56, 178, 172, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #38b2ac, #4fd1c7);
            color: white;
            border-color: transparent;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #319795, #38b2ac);
            box-shadow: 0 8px 20px rgba(56, 178, 172, 0.3);
        }

        .empty-activity {
            text-align: center;
            padding: 2rem;
            color: #718096;
        }

        .empty-activity i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        @media (max-width: 1024px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .activity-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .dashboard-title {
                font-size: 2rem;
            }
            
            .quick-actions {
                gap: 0.5rem;
                padding: 0.75rem;
            }
            
            .quick-btn {
                min-width: 100px;
                padding: 0.5rem 0.75rem;
                font-size: 0.7rem;
            }
            
            .quick-btn span {
                font-size: 0.7rem;
            }
            
            .quick-btn i {
                font-size: 0.8rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .user-info {
                order: -1;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-microchip"></i>
                KubeAgency Control
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></div>
                    <div class="user-role"><?php echo strtoupper($user_role); ?></div>
                </div>
            </div>
            
            <div class="header-actions">
                <?php if ($user_role === 'cliente'): ?>
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="mis-tickets.php" class="nav-link">
                        <i class="fas fa-ticket-alt"></i> Mis Tickets
                    </a>
                    <a href="nuevo-ticket.php" class="nav-link">
                        <i class="fas fa-plus"></i> Nuevo Ticket
                    </a>
                <?php endif; ?>
                
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Centro de Control</h1>
            <p class="dashboard-subtitle">
                <?php if ($user_role === 'cliente'): ?>
                    Panel de control de tickets - Vista de cliente
                <?php else: ?>
                    Sistema de gestión de tickets de soporte
                <?php endif; ?>
            </p>
        </div>

        <!-- Acciones rápidas -->
        <div class="quick-actions">
            <?php if ($user_role === 'admin' || $user_role === 'agente'): ?>
                <a href="tickets.php" class="quick-btn primary">
                    <i class="fas fa-list-ul"></i>
                    <span>Gestionar Tickets</span>
                </a>
                <?php if ($user_role === 'admin'): ?>
                    <a href="usuarios.php" class="quick-btn">
                        <i class="fas fa-users-cog"></i>
                        <span>Usuarios</span>
                    </a>
                    <a href="reportes.php" class="quick-btn">
                        <i class="fas fa-chart-line"></i>
                        <span>Reportes</span>
                    </a>
                    <a href="configuracion.php" class="quick-btn">
                        <i class="fas fa-cog"></i>
                        <span>Configuración</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($user_role === 'cliente'): ?>
                <a href="mis-tickets.php" class="quick-btn primary">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Mis Tickets</span>
                </a>
                <a href="nuevo-ticket.php" class="quick-btn">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nuevo Ticket</span>
                </a>
            <?php endif; ?>
        </div>

        <!-- Estadísticas principales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $efficiency; ?>%</div>
                <div class="stat-label">Eficiencia del Sistema</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['tickets_abiertos']; ?></div>
                <div class="stat-label">
                    <?php echo $user_role === 'cliente' ? 'Mis Tickets Abiertos' : 'Tickets Abiertos'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['tickets_proceso']; ?></div>
                <div class="stat-label">
                    <?php echo $user_role === 'cliente' ? 'Mis Tickets en Proceso' : 'Tickets en Proceso'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['tickets_cerrados']; ?></div>
                <div class="stat-label">
                    <?php echo $user_role === 'cliente' ? 'Mis Tickets Resueltos' : 'Tickets Resueltos'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">
                    <?php echo $user_role === 'cliente' ? 'Total Mis Tickets' : 'Total Tickets'; ?>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    <?php echo $user_role === 'cliente' ? 'Estado de Mis Tickets' : 'Estado de Tickets'; ?>
                </div>
                <div class="chart-container">
                    <canvas id="estadosChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-bar"></i>
                    <?php echo $user_role === 'cliente' ? 'Mis Tickets por Estado' : 'Tickets por Estado'; ?>
                </div>
                <div class="chart-container">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Actividad y empresas -->
        <div class="activity-section">
            <div class="activity-card">
                <div class="activity-title">
                    <i class="fas fa-stream"></i>
                    <?php if ($user_role === 'cliente'): ?>
                        Mis Tickets Recientes
                    <?php else: ?>
                        Actividad Reciente
                    <?php endif; ?>
                </div>
                <div style="max-height: 400px; overflow-y: auto; overflow-x: hidden;">
                    <?php if (count($actividad_reciente) > 0): ?>
                        <?php foreach ($actividad_reciente as $ticket): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-ticket-alt"></i>
                                </div>
                                <div class="activity-content">
                                    <h4>Ticket #<?php echo htmlspecialchars($ticket['ticket_number'] ?? 'KUBE-' . str_pad($ticket['id'], 3, '0', STR_PAD_LEFT)); ?></h4>
                                    <p><?php echo htmlspecialchars($ticket['title'] ?? $ticket['subject'] ?? 'Sin título'); ?></p>
                                    <?php if ($user_role !== 'cliente'): ?>
                                        <small><?php echo htmlspecialchars($ticket['cliente_name'] ?? 'Cliente'); ?> - <?php echo htmlspecialchars($ticket['cliente_company'] ?? ''); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time">
                                    <?php echo date('H:i', strtotime($ticket['updated_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-activity">
                            <i class="fas fa-inbox"></i>
                            <p>No hay tickets recientes</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($user_role !== 'cliente' && count($empresas_top) > 0): ?>
                <div class="activity-card">
                    <div class="activity-title">
                        <i class="fas fa-building"></i>
                        Top Clientes
                    </div>
                    <div style="overflow: hidden;">
                        <?php foreach ($empresas_top as $empresa): ?>
                            <div class="company-item">
                                <div class="company-name"><?php echo htmlspecialchars($empresa['company']); ?></div>
                                <div class="company-tickets"><?php echo $empresa['ticket_count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>


    </div>

    <script>
        // Configuración global de Chart.js
        Chart.defaults.color = '#a0aec0';
        Chart.defaults.borderColor = 'rgba(56, 178, 172, 0.2)';

        // Gráfico de estados (donut)
        const estadosCtx = document.getElementById('estadosChart').getContext('2d');
        new Chart(estadosCtx, {
            type: 'doughnut',
            data: {
                labels: ['Abiertos', 'En Proceso', 'Cerrados'],
                datasets: [{
                    data: [<?php echo $stats['tickets_abiertos']; ?>, <?php echo $stats['tickets_proceso']; ?>, <?php echo $stats['tickets_cerrados']; ?>],
                    backgroundColor: [
                        'rgba(248, 113, 113, 0.8)',
                        'rgba(251, 191, 36, 0.8)', 
                        'rgba(34, 197, 94, 0.8)'
                    ],
                    borderColor: [
                        'rgba(248, 113, 113, 1)',
                        'rgba(251, 191, 36, 1)',
                        'rgba(34, 197, 94, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            color: '#a0aec0'
                        }
                    }
                },
                elements: {
                    arc: {
                        borderWidth: 2
                    }
                }
            }
        });

        // Gráfico de barras
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Abiertos', 'En Proceso', 'Cerrados'],
                datasets: [{
                    label: 'Tickets',
                    data: [<?php echo $stats['tickets_abiertos']; ?>, <?php echo $stats['tickets_proceso']; ?>, <?php echo $stats['tickets_cerrados']; ?>],
                    backgroundColor: [
                        'rgba(248, 113, 113, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(34, 197, 94, 0.8)'
                    ],
                    borderColor: [
                        'rgba(248, 113, 113, 1)',
                        'rgba(251, 191, 36, 1)',
                        'rgba(34, 197, 94, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: Math.max(<?php echo max($stats['tickets_abiertos'], $stats['tickets_proceso'], $stats['tickets_cerrados']); ?>) + 5,
                        grid: {
                            color: 'rgba(56, 178, 172, 0.1)'
                        },
                        ticks: {
                            color: '#a0aec0'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#a0aec0'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 