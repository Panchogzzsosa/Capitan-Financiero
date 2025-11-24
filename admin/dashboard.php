<?php
session_start();
require_once('../config.php');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get data from database
try {
    $pdo = getDBConnection();
    
    // Get newsletter subscribers
    $subscribers = $pdo->query("SELECT * FROM subscribers ORDER BY created_at DESC")->fetchAll();
    
    // Get form submissions
    $form_submissions = $pdo->query("SELECT * FROM form_submissions ORDER BY created_at DESC")->fetchAll();
    
    // Get traffic tracking data with UTM parameters
    $traffic_data = $pdo->query("
        SELECT tt.*, o.order_number, o.total_amount, o.status as order_status,
               c.name as customer_name, c.email as customer_email,
               p.name as product_name
        FROM traffic_tracking tt
        LEFT JOIN orders o ON tt.order_id = o.id
        LEFT JOIN customers c ON tt.customer_id = c.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        ORDER BY tt.created_at DESC
    ")->fetchAll();
    
    // Get UTM statistics
    $utm_stats = $pdo->query("
        SELECT 
            utm_source,
            utm_medium,
            utm_campaign,
            COUNT(DISTINCT user_fingerprint) as unique_visits,
            COUNT(CASE WHEN order_id IS NOT NULL THEN 1 END) as conversions,
            ROUND(COUNT(CASE WHEN order_id IS NOT NULL THEN 1 END) * 100.0 / COUNT(DISTINCT user_fingerprint), 2) as conversion_rate
        FROM traffic_tracking
        WHERE utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL
        GROUP BY utm_source, utm_medium, utm_campaign
        ORDER BY unique_visits DESC
    ")->fetchAll();
    
    // Get products sold by traffic source
    $products_by_source = $pdo->query("
        SELECT 
            COALESCE(tt.utm_source, 'Directo') as traffic_source,
            p.name as product_name,
            COUNT(oi.id) as times_sold,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.total_price) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN traffic_tracking tt ON o.id = tt.order_id
        WHERE o.status = 'completed'
        GROUP BY COALESCE(tt.utm_source, 'Directo'), p.name
        ORDER BY total_revenue DESC
    ")->fetchAll();
    
    // Get customers
    $customers = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC")->fetchAll();
    
    // Get orders with customer details
    $orders = $pdo->query("
        SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
        FROM orders o 
        JOIN customers c ON o.customer_id = c.id 
        ORDER BY o.created_at DESC
    ")->fetchAll();
    
    // Get payments
    $payments = $pdo->query("
        SELECT p.*, o.order_number, c.name as customer_name, c.email as customer_email
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        JOIN customers c ON o.customer_id = c.id 
        ORDER BY p.created_at DESC
    ")->fetchAll();
    
    // Get order items with product details
    $order_items = $pdo->query("
        SELECT oi.*, o.order_number, p.name as product_name, c.name as customer_name
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        JOIN products p ON oi.product_id = p.id 
        JOIN customers c ON o.customer_id = c.id 
        ORDER BY oi.created_at DESC
    ")->fetchAll();
    
    // Webinar registrations
    $webinars = $pdo->query("\n        SELECT nombre_completo, correo_electronico, numero_telefono, created_at\n        FROM webinar\n        ORDER BY created_at DESC\n    ")->fetchAll();
    
    // Get statistics
    $total_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $total_revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'completed'")->fetchColumn();
    $total_subscribers = $pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    $total_utm_visits = $pdo->query("SELECT COUNT(DISTINCT user_fingerprint) FROM traffic_tracking WHERE utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL")->fetchColumn();
    $utm_conversions = $pdo->query("SELECT COUNT(DISTINCT tt.user_fingerprint) FROM traffic_tracking tt JOIN orders o ON tt.order_id = o.id WHERE (tt.utm_source IS NOT NULL OR tt.utm_medium IS NOT NULL OR tt.utm_campaign IS NOT NULL)")->fetchColumn();
    
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
    <title>Admin Dashboard - Capitán Financiero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .admin-header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .admin-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .logout-btn {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 600;
            color: #222F58;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        .stat-card .icon {
            font-size: 1.5rem;
            color: #223058;
            margin-bottom: 1rem;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            background-color: #f8f9fa;
            color: #222F58;
        }

        .nav-tabs .nav-link.active {
            background-color: #222F58;
            color: white;
            border: none;
        }

        .tab-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border: none;
            background-color: #f8f9fa;
            color: #222F58;
            font-weight: 600;
            padding: 0.75rem;
            font-size: 0.9rem;
        }

        .table td {
            border: none;
            padding: 0.75rem;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 6px;
        }

        .btn-info {
            background-color: #223058 !important;
            border-color: #223058 !important;
            color: white !important;
        }
        
        .btn-info:hover {
            background-color: #223058 !important;
            border-color: #223058 !important;
            color: white !important;
        }
        
        .btn-info:focus {
            background-color: #223058 !important;
            border-color: #223058 !important;
            color: white !important;
            box-shadow: none !important;
            outline: none !important;
        }
        
        .btn-info:active {
            background-color: #223058 !important;
            border-color: #223058 !important;
            color: white !important;
            box-shadow: none !important;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin: 1rem 0;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }

        .page-link {
            color: #667eea;
            border-color: #dee2e6;
        }

        .page-link:hover {
            color: #222F58;
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }

        .page-item.active .page-link {
            background-color: #222F58;
            border-color: #222F58;
        }

        /* Estilos para el modal */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 95%;
        }

        .modal-header {
            background: linear-gradient(135deg, #222F58 0%, #667eea 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            border: none;
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .table-sm th,
        .table-sm td {
            padding: 0.4rem;
            font-size: 0.85rem;
        }

        .table-sm th {
            background-color: #f8f9fa;
            color: #222F58;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .admin-header h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .tab-content {
                padding: 1rem;
            }

            .logout-btn {
                position: relative;
                top: auto;
                right: auto;
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }
        }

        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
            <h1>Dashboard Administrativo</h1>
            <p>Gestión de clientes, órdenes y pagos</p>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $total_customers ?? 0; ?></h3>
                <p>Clientes Registrados</p>
            </div>
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3><?php echo $total_orders ?? 0; ?></h3>
                <p>Órdenes Totales</p>
            </div>
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h3>$<?php echo number_format($total_revenue ?? 0, 2); ?></h3>
                <p>Ingresos Totales</p>
            </div>
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3><?php echo $total_subscribers ?? 0; ?></h3>
                <p>Suscriptores Newsletter</p>
            </div>
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3><?php echo $total_utm_visits ?? 0; ?></h3>
                <p>Visitas con UTM</p>
            </div>
        </div>



        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button">
                    <i class="fas fa-shopping-cart"></i> Órdenes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button">
                    <i class="fas fa-users"></i> Clientes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button">
                    <i class="fas fa-credit-card"></i> Pagos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="newsletter-tab" data-bs-toggle="tab" data-bs-target="#newsletter" type="button">
                    <i class="fas fa-envelope"></i> Suscriptores
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="forms-tab" data-bs-toggle="tab" data-bs-target="#forms" type="button">
                    <i class="fas fa-file-alt"></i> Formularios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="traffic-tab" data-bs-toggle="tab" data-bs-target="#traffic" type="button">
                    <i class="fas fa-chart-bar"></i> Tráfico ManyChat
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="qr-tab" data-bs-toggle="tab" data-bs-target="#qr" type="button">
                    <i class="fas fa-qrcode"></i> Generar QR
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="webinar-tab" data-bs-toggle="tab" data-bs-target="#webinar" type="button">
                    <i class="fas fa-video"></i> Webinar
                </button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Orders Tab -->
            <div class="tab-pane fade show active" id="orders">
                <table id="ordersTable" class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Número de Orden</th>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Método de Pago</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                            <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'danger'); ?> status-badge">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Customers Tab -->
            <div class="tab-pane fade" id="customers">
                <table id="customersTable" class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Estado</th>
                            <th>Fecha de Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo htmlspecialchars($customer['address']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $customer['status'] === 'active' ? 'success' : 'secondary'; ?> status-badge">
                                    <?php echo ucfirst($customer['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($customer['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewCustomerDetails(<?php echo $customer['id']; ?>)">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Payments Tab -->
            <div class="tab-pane fade" id="payments">
                <table id="paymentsTable" class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Número de Orden</th>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Método de Pago</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($payment['order_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($payment['customer_email']); ?></td>
                            <td><strong>$<?php echo number_format($payment['amount'], 2); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php echo $payment['status'] === 'succeeded' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?> status-badge">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($payment['payment_method_type']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Newsletter Tab -->
            <div class="tab-pane fade" id="newsletter">
                <table id="subscribersTable" class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                            <th>Fecha de Suscripción</th>
                            <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscribers as $subscriber): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subscriber['id']); ?></td>
                                <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                <td><?php echo htmlspecialchars($subscriber['created_at']); ?></td>
                                <td>
                                    <button class="btn btn-danger btn-sm delete-btn" data-type="subscriber" data-id="<?php echo htmlspecialchars($subscriber['id']); ?>">
                                    <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                        <!-- Forms Tab -->
            <div class="tab-pane fade" id="forms">
                <table id="formsTable" class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                                <th>Email</th>
                            <th>Teléfono</th>
                            <th>Mensaje</th>
                            <th>Fecha de Envío</th>
                            <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($form_submissions as $submission): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($submission['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($submission['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($submission['apellido']); ?></td>
                                <td><?php echo htmlspecialchars($submission['correo']); ?></td>
                            <td><?php echo htmlspecialchars($submission['numero']); ?></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php echo htmlspecialchars($submission['mensaje']); ?>
                            </td>
                                <td><?php echo htmlspecialchars($submission['created_at']); ?></td>
                                <td>
                                    <button class="btn btn-danger btn-sm delete-btn" data-type="form" data-id="<?php echo htmlspecialchars($submission['id']); ?>">
                                    <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>

            <!-- Traffic Tab -->
            <div class="tab-pane fade" id="traffic">
                <div class="text-center">
                    <div class="mb-4">
                        <h4><i class="fas fa-chart-bar"></i> Tráfico ManyChat</h4>
                        <p class="text-muted">Análisis de tráfico de ManyChat con parámetros UTM.</p>
                    </div>
                    
                    <div class="text-center mb-4">
                        <a href="utm_links.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-link"></i> Ver Enlaces UTM
                        </a>
                        <a href="utm_analytics.php" class="btn btn-outline-info btn-sm ms-2">
                            <i class="fas fa-chart-line"></i> Gráficas UTM
                        </a>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title">Estadísticas de Tráfico</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($utm_stats)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No hay estadísticas de tráfico UTM aún</p>
                                        </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fuente de Tráfico</th>
                                                    <th>Visitas Únicas</th>
                                                    <th>Conversiones</th>
                                                    <th>Tasa de Conversión</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($utm_stats as $stat): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($stat['utm_source'] ?: 'Directo'); ?></td>
                                                    <td><?php echo htmlspecialchars($stat['unique_visits']); ?></td>
                                                    <td><?php echo htmlspecialchars($stat['conversions']); ?></td>
                                                    <td><?php echo htmlspecialchars($stat['conversion_rate']); ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title">Productos Vendidos por Fuente</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($products_by_source)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-shopping-cart fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No hay datos de productos por fuente de tráfico</p>
                                        </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fuente de Tráfico</th>
                                                    <th>Producto</th>
                                                    <th>Vendidos</th>
                                                    <th>Cantidad Total</th>
                                                    <th>Ingreso Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($products_by_source as $source_data): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($source_data['traffic_source']); ?></td>
                                                    <td><?php echo htmlspecialchars($source_data['product_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($source_data['times_sold']); ?></td>
                                                    <td><?php echo htmlspecialchars($source_data['total_quantity']); ?></td>
                                                    <td><strong>$<?php echo number_format($source_data['total_revenue'], 2); ?></strong></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabla detallada de tráfico -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Detalles de Tráfico UTM</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($traffic_data)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No hay datos de tráfico UTM aún</h5>
                                    <p class="text-muted">Para rastrear el tráfico de ManyChat, asegúrate de que tus enlaces incluyan parámetros UTM como:</p>
                                    <div class="alert alert-warning d-inline-block">
                                        <code>?utm_source=manychat&utm_medium=whatsapp&utm_campaign=curso_finanzas</code>
                                    </div>
                                </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="trafficTable" class="table">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Fuente UTM</th>
                                            <th>Medio UTM</th>
                                            <th>Campaña UTM</th>
                                            <th>Cliente</th>
                                            <th>Email</th>
                                            <th>Orden</th>
                                            <th>Producto</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($traffic_data as $traffic): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($traffic['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($traffic['utm_source'] ?: 'Directo'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($traffic['utm_medium'] ?: 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($traffic['utm_campaign'] ?: 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($traffic['customer_name']): ?>
                                                    <strong><?php echo htmlspecialchars($traffic['customer_name']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">Visitante</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['customer_email']): ?>
                                                    <?php echo htmlspecialchars($traffic['customer_email']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['order_number']): ?>
                                                    <span class="badge bg-success">
                                                        <?php echo htmlspecialchars($traffic['order_number']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin orden</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['product_name']): ?>
                                                    <?php echo htmlspecialchars($traffic['product_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['total_amount']): ?>
                                                    <strong>$<?php echo number_format($traffic['total_amount'], 2); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['order_status']): ?>
                                                    <span class="badge bg-<?php echo $traffic['order_status'] === 'completed' ? 'success' : ($traffic['order_status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($traffic['order_status']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($traffic['ip_address'] ?: 'N/A'); ?></small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Tab -->
            <div class="tab-pane fade" id="qr">
                <div class="text-center">
                    <div class="mb-4">
                        <h4><i class="fas fa-qrcode"></i> Generador de Código QR</h4>
                        <p class="text-muted">Genera un código QR que lleva directamente al carrito con el producto "Programa de Alineación Financiera I"</p>
                    </div>
                    
                    <a href="generate_qr.php" class="btn btn-outline-primary btn-md">
                        <i class="fas fa-qrcode"></i> Generar QR
                    </a>
                    
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> ¿Cómo funciona?</h6>
                            <ul class="text-start mb-0">
                                <li>Haz clic en "Generar Código QR del Carrito"</li>
                                <li>Se creará un código QR personalizado</li>
                                <li>Los clientes pueden escanearlo con cualquier app de QR</li>
                                <li>Serán llevados directamente al carrito con el producto</li>
                                <li>El producto se agregará automáticamente</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webinar Tab -->
            <div class="tab-pane fade" id="webinar">
                <div class="text-center">
                    <div class="mb-4">
                        <h4><i class="fas fa-video"></i> Webinar</h4>
                        <p class="text-muted">Gestión básica de webinars y enlaces de transmisión.</p>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Registro de Webinar</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="webinarTable" class="table">
                                    <thead>
                                        <tr>
                                            <th>Nombre completo</th>
                                            <th>Correo electrónico</th>
                                            <th>Número de teléfono</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($webinars as $w): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($w['nombre_completo']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($w['correo_electronico']); ?></td>
                                            <td><?php echo htmlspecialchars($w['numero_telefono']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalles de orden -->
    <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderModalLabel">Detalles de la Orden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderModalBody">
                    <!-- El contenido se cargará dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalles de cliente -->
    <div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalLabel">Detalles del Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="customerModalBody">
                    <!-- El contenido se cargará dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Espacio adicional en la parte inferior -->
    <div style="height: 3rem;"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#ordersTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#customersTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#paymentsTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#subscribersTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#formsTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#webinarTable').DataTable({
                order: [[0, 'asc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            
            <?php if (!empty($traffic_data)): ?>
            $('#trafficTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            <?php endif; ?>
        });

        // Delete functionality
        $('.delete-btn').on('click', function() {
            const id = $(this).data('id');
            const type = $(this).data('type');
            const confirmMessage = type === 'subscriber' ? 
                '¿Estás seguro de que deseas eliminar este suscriptor?' : 
                '¿Estás seguro de que deseas eliminar esta presentación de formulario?';

            if (confirm(confirmMessage)) {
                $.ajax({
                    url: 'delete.php',
                    method: 'POST',
                    data: { id: id, type: type },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error al procesar la solicitud');
                    }
                });
            }
        });

        // View order details
        function viewOrderDetails(orderId) {
            // Mostrar loading en el modal
            $('#orderModalBody').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Cargando detalles...</p></div>');
            $('#orderModal').modal('show');
            
            // Cargar detalles de la orden
            $.ajax({
                url: 'get_order_details.php',
                method: 'POST',
                data: { order_id: orderId },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        $('#orderModalBody').html('<div class="alert alert-danger">Error: ' + response.error + '</div>');
                    } else {
                        displayOrderDetails(response);
                    }
                },
                error: function() {
                    $('#orderModalBody').html('<div class="alert alert-danger">Error al cargar los detalles de la orden</div>');
                }
            });
        }

        // Display order details in modal
        function displayOrderDetails(data) {
            const order = data.order;
            const items = data.items;
            const payment = data.payment;
            
            let modalContent = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Información del Cliente</h6>
                        <div class="mb-3">
                            <strong>Nombre:</strong> ${order.customer_name}
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong> ${order.customer_email}
                        </div>
                        <div class="mb-3">
                            <strong>Teléfono:</strong> ${order.customer_phone}
                        </div>
                        <div class="mb-3">
                            <strong>Dirección:</strong> ${order.customer_address}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Información de la Orden</h6>
                        <div class="mb-3">
                            <strong>Número de Orden:</strong> ${order.order_number}
                        </div>
                        <div class="mb-3">
                            <strong>Fecha:</strong> ${new Date(order.created_at).toLocaleDateString('es-ES')}
                        </div>
                        <div class="mb-3">
                            <strong>Estado:</strong> 
                            <span class="badge bg-${order.status === 'completed' ? 'success' : (order.status === 'pending' ? 'warning' : 'danger')}">
                                ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>Método de Pago:</strong> ${order.payment_method}
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h6 class="text-muted mb-3">Productos Comprados</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            items.forEach(item => {
                // Usar unit_price_cents si está disponible, sino unit_price
                const price = item.product_price_cents ? (item.product_price_cents / 100) : parseFloat(item.product_price);
                
                modalContent += `
                    <tr>
                        <td><strong>${item.product_name}</strong></td>
                        <td>${item.product_description || 'Sin descripción'}</td>
                        <td>${item.quantity}</td>
                        <td><strong>$${price.toFixed(2)} MXN</strong></td>
                    </tr>
                `;
            });
            
            modalContent += `
                        </tbody>
                    </table>
                </div>
                
                <div class="text-end" style="margin-top: 0.5rem;">
                    <h5>Total: <strong>$${(order.total_amount_cents / 100).toFixed(2)} MXN</strong></h5>
                </div>
            `;
            
            if (payment) {
                modalContent += `
                    <hr class="my-4">
                    <h6 class="text-muted mb-3">Información del Pago</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong>ID de Pago:</strong> ${payment.stripe_payment_intent_id}
                            </div>
                            <div class="mb-2">
                                <strong>Estado:</strong> 
                                <span class="badge bg-${payment.status === 'succeeded' ? 'success' : (payment.status === 'pending' ? 'warning' : 'danger')}">
                                    ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong>Método:</strong> ${payment.payment_method_type}
                            </div>
                            <div class="mb-2">
                                <strong>Fecha:</strong> ${new Date(payment.created_at).toLocaleDateString('es-ES')}
                            </div>
                        </div>
                    </div>
                `;
            }
            
            $('#orderModalBody').html(modalContent);
        }

        // View customer details
        function viewCustomerDetails(customerId) {
            // Mostrar loading en el modal
            $('#customerModalBody').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Cargando detalles...</p></div>');
            $('#customerModal').modal('show');
            
            // Cargar detalles del cliente
            $.ajax({
                url: 'get_customer_details.php',
                method: 'POST',
                data: { customer_id: customerId },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        $('#customerModalBody').html('<div class="alert alert-danger">Error: ' + response.error + '</div>');
                    } else {
                        displayCustomerDetails(response);
                    }
                },
                error: function() {
                    $('#customerModalBody').html('<div class="alert alert-danger">Error al cargar los detalles del cliente</div>');
                }
            });
        }

        // Display customer details in modal
        function displayCustomerDetails(data) {
            const customer = data.customer;
            const orders = data.orders;
            const stats = data.stats;
            const products = data.products;
            
            let modalContent = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Información del Cliente</h6>
                        <div class="mb-3">
                            <strong>Nombre:</strong> ${customer.name}
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong> ${customer.email}
                        </div>
                        <div class="mb-3">
                            <strong>Teléfono:</strong> ${customer.phone}
                        </div>
                        <div class="mb-3">
                            <strong>Dirección:</strong> ${customer.address}
                        </div>
                        <div class="mb-3">
                            <strong>Estado:</strong> 
                            <span class="badge bg-${customer.status === 'active' ? 'success' : 'secondary'}">
                                ${customer.status.charAt(0).toUpperCase() + customer.status.slice(1)}
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>Fecha de Registro:</strong> ${new Date(customer.created_at).toLocaleDateString('es-ES')}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Estadísticas de Compras</h6>
                        <div class="mb-3">
                            <strong>Total Gastado:</strong> <span class="text-success">$${parseFloat(stats.total_spent).toFixed(2)}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Órdenes Completadas:</strong> ${stats.total_orders || 0}
                        </div>
                        <div class="mb-3">
                            <strong>Valor Promedio por Orden:</strong> $${stats.average_order_value ? parseFloat(stats.average_order_value).toFixed(2) : '0.00'}
                        </div>
                        <div class="mb-3">
                            <strong>Primera Compra:</strong> ${stats.first_order_date ? new Date(stats.first_order_date).toLocaleDateString('es-ES') : 'N/A'}
                        </div>
                        <div class="mb-3">
                            <strong>Última Compra:</strong> ${stats.last_order_date ? new Date(stats.last_order_date).toLocaleDateString('es-ES') : 'N/A'}
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h6 class="text-muted mb-3">Productos Comprados</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Descripción</th>
                                <th>Cantidad Total</th>
                                <th>Veces Comprado</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (products.length > 0) {
                products.forEach(product => {
                    modalContent += `
                        <tr>
                            <td><strong>${product.product_name}</strong></td>
                            <td>${product.product_description || 'Sin descripción'}</td>
                            <td>${product.total_quantity}</td>
                            <td>${product.times_purchased}</td>
                        </tr>
                    `;
                });
            } else {
                modalContent += `
                    <tr>
                        <td colspan="4" class="text-center text-muted">No ha comprado productos aún</td>
                    </tr>
                `;
            }
            
            modalContent += `
                        </tbody>
                    </table>
                </div>
                
                <hr class="my-4">
                
                <h6 class="text-muted mb-3">Historial de Órdenes</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Número de Orden</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Items</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (orders.length > 0) {
                orders.forEach(order => {
                    modalContent += `
                        <tr>
                            <td><strong>${order.order_number}</strong></td>
                            <td>${new Date(order.created_at).toLocaleDateString('es-ES')}</td>
                            <td><strong>$${parseFloat(order.total_amount).toFixed(2)}</strong></td>
                            <td>
                                <span class="badge bg-${order.status === 'completed' ? 'success' : (order.status === 'pending' ? 'warning' : 'danger')}">
                                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                </span>
                            </td>
                            <td>${order.total_items || 0}</td>
                        </tr>
                    `;
                });
            } else {
                modalContent += `
                    <tr>
                        <td colspan="5" class="text-center text-muted">No tiene órdenes aún</td>
                    </tr>
                `;
            }
            
            modalContent += `
                        </tbody>
                    </table>
                </div>
            `;
            
            $('#customerModalBody').html(modalContent);
        }
    </script>
</body>
</html>