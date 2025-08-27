<?php
session_start();
require_once('../config.php');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Estadísticas generales de UTM
    $total_utm_visits = $pdo->query("SELECT COUNT(DISTINCT user_fingerprint) FROM traffic_tracking WHERE utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL")->fetchColumn();
    
    // Consulta corregida para conversiones - contar visitas UTM que tienen order_id
    $total_conversions = $pdo->query("
        SELECT COUNT(DISTINCT order_id) 
        FROM traffic_tracking 
        WHERE (utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL)
        AND order_id IS NOT NULL
    ")->fetchColumn();
    
    // Top fuentes de tráfico
    $top_sources = $pdo->query("
        SELECT utm_source, COUNT(DISTINCT user_fingerprint) as visits
        FROM traffic_tracking 
        WHERE utm_source IS NOT NULL 
        GROUP BY utm_source 
        ORDER BY visits DESC 
        LIMIT 10
    ")->fetchAll();
    
    // Top campañas
    $top_campaigns = $pdo->query("
        SELECT utm_campaign, COUNT(DISTINCT user_fingerprint) as visits
        FROM traffic_tracking 
        WHERE utm_campaign IS NOT NULL 
        GROUP BY utm_campaign 
        ORDER BY visits DESC 
        LIMIT 10
    ")->fetchAll();
    
    // Top medios
    $top_mediums = $pdo->query("
        SELECT utm_medium, COUNT(DISTINCT user_fingerprint) as visits
        FROM traffic_tracking 
        WHERE utm_medium IS NOT NULL 
        GROUP BY utm_medium 
        ORDER BY visits DESC 
        LIMIT 10
    ")->fetchAll();
    
    // Visitas por día (últimos 30 días)
    $daily_visits = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(DISTINCT user_fingerprint) as visits
        FROM traffic_tracking 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND (utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL)
        GROUP BY DATE(created_at)
        ORDER BY date
    ")->fetchAll();
    
    // Conversiones por fuente
    $conversions_by_source = $pdo->query("
        SELECT 
            COALESCE(utm_source, 'Directo') as source,
            COUNT(DISTINCT user_fingerprint) as visits,
            COUNT(DISTINCT order_id) as conversions,
            ROUND(COUNT(DISTINCT order_id) * 100.0 / COUNT(DISTINCT user_fingerprint), 2) as conversion_rate
        FROM traffic_tracking
        WHERE utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL
        GROUP BY COALESCE(utm_source, 'Directo')
        ORDER BY visits DESC
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../Img/Logo.png" type="image/x-icon">
    <title>Analytics UTM - Capitán Financiero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .admin-header {
            background: linear-gradient(135deg, #222F58 0%, #667eea 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 3rem;
        }

        .back-btn {
            position: absolute;
            top: 2rem;
            left: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin: 1rem 0;
        }

        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .metric-number {
            font-size: 2.5rem;
            font-weight: 600;
            color: #222F58;
            margin-bottom: 0.5rem;
        }

        .metric-label {
            color: #6c757d;
            font-size: 1rem;
        }

        .chart-title {
            color: #222F58;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container position-relative">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
            <div class="text-center">
                <h1><i class="fas fa-chart-line"></i> Analytics UTM</h1>
                <p>Análisis visual del tráfico UTM y conversiones</p>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Métricas principales -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="metric-card">
                    <div class="metric-number"><?php echo $total_utm_visits ?? 0; ?></div>
                    <div class="metric-label">Total Visitas UTM</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="metric-card">
                    <div class="metric-number"><?php echo $total_conversions ?? 0; ?></div>
                    <div class="metric-label">Total Conversiones</div>
                </div>
            </div>
        </div>

        <!-- Gráficas -->
        <div class="row">
            <!-- Top Fuentes de Tráfico -->
            <div class="col-lg-6">
                <div class="stats-card">
                    <h4 class="chart-title">Top Fuentes de Tráfico</h4>
                    <div class="chart-container">
                        <canvas id="sourcesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Campañas -->
            <div class="col-lg-6">
                <div class="stats-card">
                    <h4 class="chart-title">Top Cursos</h4>
                    <div class="chart-container">
                        <canvas id="campaignsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Medios -->
            <div class="col-lg-6">
                <div class="stats-card">
                    <h4 class="chart-title">Top Medios</h4>
                    <div class="chart-container">
                        <canvas id="mediumsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Visitas por Día -->
            <div class="col-lg-6">
                <div class="stats-card">
                    <h4 class="chart-title">Visitas por Día (Últimos 30 días)</h4>
                    <div class="chart-container">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Conversiones por Fuente -->
            <div class="col-12">
                <div class="stats-card">
                    <h4 class="chart-title">Conversiones por Fuente de Tráfico</h4>
                    <div class="chart-container">
                        <canvas id="conversionsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Preparar datos para las gráficas
        const sourcesData = <?php echo json_encode($top_sources); ?>;
        const campaignsData = <?php echo json_encode($top_campaigns); ?>;
        const mediumsData = <?php echo json_encode($top_mediums); ?>;
        const dailyData = <?php echo json_encode($daily_visits); ?>;
        const conversionsData = <?php echo json_encode($conversions_by_source); ?>;

        // Colores para las gráficas
        const colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
            '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
        ];

        // Gráfica de Top Fuentes
        new Chart(document.getElementById('sourcesChart'), {
            type: 'doughnut',
            data: {
                labels: sourcesData.map(item => item.utm_source || 'Sin fuente'),
                datasets: [{
                    data: sourcesData.map(item => item.visits),
                    backgroundColor: colors.slice(0, sourcesData.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfica de Top Campañas
        new Chart(document.getElementById('campaignsChart'), {
            type: 'doughnut',
            data: {
                labels: campaignsData.map(item => item.utm_campaign || 'Sin campaña'),
                datasets: [{
                    data: campaignsData.map(item => item.visits),
                    backgroundColor: colors.slice(0, campaignsData.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfica de Top Medios
        new Chart(document.getElementById('mediumsChart'), {
            type: 'doughnut',
            data: {
                labels: mediumsData.map(item => item.utm_medium || 'Sin medio'),
                datasets: [{
                    data: mediumsData.map(item => item.visits),
                    backgroundColor: colors.slice(0, mediumsData.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfica de Visitas por Día
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels: dailyData.map(item => new Date(item.date).toLocaleDateString('es-ES')),
                datasets: [{
                    label: 'Visitas UTM',
                    data: dailyData.map(item => item.visits),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Gráfica de Conversiones por Fuente
        new Chart(document.getElementById('conversionsChart'), {
            type: 'bar',
            data: {
                labels: conversionsData.map(item => item.source),
                datasets: [{
                    label: 'Visitas',
                    data: conversionsData.map(item => item.visits),
                    backgroundColor: '#36A2EB',
                    borderColor: '#36A2EB',
                    borderWidth: 1
                }, {
                    label: 'Conversiones',
                    data: conversionsData.map(item => item.conversions),
                    backgroundColor: '#4BC0C0',
                    borderColor: '#4BC0C0',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
