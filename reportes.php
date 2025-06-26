<?php
session_start();

// Verificar acceso de admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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

// Obtener estadísticas para reportes
$stats = [];

// Tickets por estado
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM tickets 
    GROUP BY status
");
$tickets_por_estado = $stmt->fetchAll();

// Tickets por prioridad
$stmt = $pdo->query("
    SELECT priority, COUNT(*) as count 
    FROM tickets 
    GROUP BY priority
");
$tickets_por_prioridad = $stmt->fetchAll();

// Tickets por mes (últimos 6 meses)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mes,
        COUNT(*) as count
    FROM tickets 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mes
");
$tickets_por_mes = $stmt->fetchAll();

// Top 5 clientes con más tickets
$stmt = $pdo->query("
    SELECT 
        u.name, u.company, COUNT(t.id) as total_tickets
    FROM users u
    LEFT JOIN tickets t ON u.id = t.cliente_id
    WHERE u.role = 'cliente'
    GROUP BY u.id
    ORDER BY total_tickets DESC
    LIMIT 5
");
$top_clientes = $stmt->fetchAll();

// Rendimiento de agentes
$stmt = $pdo->query("
    SELECT 
        u.name,
        COUNT(t.id) as tickets_asignados,
        COUNT(CASE WHEN t.status = 'cerrado' THEN 1 END) as tickets_resueltos,
        CASE 
            WHEN COUNT(t.id) = 0 THEN 0 
            ELSE ROUND(COUNT(CASE WHEN t.status = 'cerrado' THEN 1 END) * 100.0 / COUNT(t.id), 2) 
        END as porcentaje_resolucion
    FROM users u
    LEFT JOIN tickets t ON u.id = t.agente_id
    WHERE u.role = 'agente'
    GROUP BY u.id
    ORDER BY porcentaje_resolucion DESC
");
$rendimiento_agentes = $stmt->fetchAll();

// Estadísticas generales
$stmt = $pdo->query("SELECT COUNT(*) as total FROM tickets");
$total_tickets = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'cliente'");
$total_clientes = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'agente'");
$total_agentes = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as promedio 
    FROM tickets 
    WHERE status = 'cerrado' AND closed_at IS NOT NULL
");
$tiempo_promedio = $stmt->fetch()['promedio'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - KubeAgency</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            color: white; 
            text-decoration: none; 
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }

        .report-card {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .report-title {
            color: #f7fafc;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: #374151;
            border: 1px solid #4a5568;
            border-radius: 6px;
            padding: 1rem;
            text-align: center;
            transition: background-color 0.2s ease;
        }

        .stat-item:hover {
            background: #4a5568;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 600;
            color: #38b2ac;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #cbd5e0;
            font-size: 12px;
            font-weight: 400;
        }

        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .chart-card {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 6px;
            padding: 1rem;
            height: 300px;
        }

        .chart-title {
            color: #f7fafc;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            position: relative;
            height: 220px;
            width: 100%;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 6px;
            border: 1px solid #4a5568;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            background: #2d3748;
        }

        .table th,
        .table td {
            padding: 0.75rem 0.5rem;
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

        @media (max-width: 1024px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
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
        <h2><i class="fas fa-microchip"></i> KubeAgency Control - Reportes</h2>
        <div>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Estadísticas principales -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_tickets; ?></div>
                <div class="stat-label">Total Tickets</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_clientes; ?></div>
                <div class="stat-label">Total Clientes</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_agentes; ?></div>
                <div class="stat-label">Total Agentes</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo round($tiempo_promedio); ?>h</div>
                <div class="stat-label">Tiempo Promedio Resolución</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    Tickets por Estado
                </div>
                <div class="chart-container">
                    <canvas id="estadosChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-bar"></i>
                    Tickets por Prioridad
                </div>
                <div class="chart-container">
                    <canvas id="prioridadChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top 5 Clientes -->
        <div class="report-card">
            <div class="report-title">
                <i class="fas fa-users"></i>
                Top 5 Clientes con Más Tickets
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Empresa</th>
                            <th>Total Tickets</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_clientes as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente['name']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['company'] ?? '-'); ?></td>
                                <td><?php echo $cliente['total_tickets']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rendimiento de Agentes -->
        <div class="report-card">
            <div class="report-title">
                <i class="fas fa-user-tie"></i>
                Rendimiento de Agentes
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Agente</th>
                            <th>Tickets Asignados</th>
                            <th>Tickets Resueltos</th>
                            <th>% Resolución</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rendimiento_agentes as $agente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($agente['name']); ?></td>
                                <td><?php echo $agente['tickets_asignados']; ?></td>
                                <td><?php echo $agente['tickets_resueltos']; ?></td>
                                <td><?php echo number_format($agente['porcentaje_resolucion'] ?? 0, 2); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Configuración global de Chart.js
        Chart.defaults.color = '#a0aec0';
        Chart.defaults.borderColor = 'rgba(56, 178, 172, 0.2)';

        // Gráfico de estados
        const estadosCtx = document.getElementById('estadosChart').getContext('2d');
        new Chart(estadosCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php foreach ($tickets_por_estado as $estado): ?>'<?php echo ucfirst($estado['status']); ?>',<?php endforeach; ?>],
                datasets: [{
                    data: [<?php foreach ($tickets_por_estado as $estado): ?><?php echo $estado['count']; ?>,<?php endforeach; ?>],
                    backgroundColor: [
                        'rgba(248, 113, 113, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(96, 165, 250, 0.8)'
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
                        labels: { color: '#a0aec0' }
                    }
                }
            }
        });

        // Gráfico de prioridad
        const prioridadCtx = document.getElementById('prioridadChart').getContext('2d');
        new Chart(prioridadCtx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($tickets_por_prioridad as $prioridad): ?>'<?php echo ucfirst($prioridad['priority']); ?>',<?php endforeach; ?>],
                datasets: [{
                    label: 'Tickets',
                    data: [<?php foreach ($tickets_por_prioridad as $prioridad): ?><?php echo $prioridad['count']; ?>,<?php endforeach; ?>],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(251, 146, 60, 0.8)',
                        'rgba(248, 113, 113, 0.8)'
                    ],
                    borderWidth: 2,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(56, 178, 172, 0.1)' },
                        ticks: { color: '#a0aec0' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#a0aec0' }
                    }
                }
            }
        });
    </script>
</body>
</html> 