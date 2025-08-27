<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../config.php';
require_once '../whatsapp_automation.php';

$automation = new WhatsAppAutomation();
$stats = $automation->getStats();

// Procesar acciones
if ($_POST['action'] ?? '' === 'process_pending') {
    $results = $automation->processPendingUsers();
    $success_message = "Procesamiento completado. " . count($results) . " usuarios procesados.";
}

// Obtener logs recientes
$pdo = getDBConnection();
$logs_stmt = $pdo->prepare("
    SELECT 
        ml.*,
        c.name as customer_name,
        c.email as customer_email,
        c.phone as customer_phone,
        o.order_number
    FROM manychat_logs ml
    JOIN customers c ON ml.customer_id = c.id
    JOIN orders o ON ml.order_id = o.id
    ORDER BY ml.created_at DESC
    LIMIT 50
");
$logs_stmt->execute();
$logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener suscripciones pendientes
$pending_stmt = $pdo->prepare("
    SELECT 
        gs.*,
        c.name as customer_name,
        c.email as customer_email,
        c.phone as customer_phone,
        wg.name as group_name,
        p.name as product_name,
        o.order_number
    FROM group_subscriptions gs
    JOIN customers c ON gs.customer_id = c.id
    JOIN whatsapp_groups wg ON gs.group_id = wg.id
    JOIN orders o ON gs.order_id = o.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE gs.status = 'pending'
    ORDER BY gs.created_at DESC
");
$pending_stmt->execute();
$pending = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard WhatsApp - Capitán Financiero</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #222F58; color: white; padding: 20px; margin: -20px -20px 20px -20px; border-radius: 10px 10px 0 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #222F58; }
        .stat-number { font-size: 2em; font-weight: bold; color: #222F58; }
        .stat-label { color: #666; font-size: 0.9em; }
        .btn { background: #222F58; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #1a2344; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-added { color: #28a745; font-weight: bold; }
        .status-failed { color: #dc3545; font-weight: bold; }
        .status-success { color: #28a745; font-weight: bold; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .section { margin: 30px 0; }
        .section h2 { color: #222F58; border-bottom: 2px solid #222F58; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Dashboard WhatsApp Automation</h1>
            <p>Sistema de automatización para grupos de WhatsApp</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="section">
            <h2>📊 Estadísticas</h2>
            <div class="stats">
                <?php foreach ($stats as $stat): ?>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stat['count'] ?></div>
                        <div class="stat-label"><?= ucfirst($stat['status']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Acciones -->
        <div class="section">
            <h2>🔄 Acciones</h2>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="process_pending">
                <button type="submit" class="btn btn-success">
                    ▶️ Procesar Usuarios Pendientes
                </button>
            </form>
            <a href="../run_whatsapp_automation.php" target="_blank" class="btn">
                👁️ Ver Procesamiento Detallado
            </a>
        </div>

        <!-- Usuarios Pendientes -->
        <div class="section">
            <h2>⏳ Usuarios Pendientes (<?= count($pending) ?>)</h2>
            <?php if (empty($pending)): ?>
                <p style="color: #28a745;">✅ No hay usuarios pendientes</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Producto</th>
                            <th>Grupo</th>
                            <th>Orden</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['customer_name']) ?></strong><br>
                                    <small><?= htmlspecialchars($item['customer_email']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($item['customer_phone']) ?></td>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= htmlspecialchars($item['group_name']) ?></td>
                                <td><?= htmlspecialchars($item['order_number']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Logs Recientes -->
        <div class="section">
            <h2>📋 Logs Recientes</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Acción</th>
                        <th>Estado</th>
                        <th>Orden</th>
                        <th>Fecha</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($log['customer_name']) ?></strong><br>
                                <small><?= htmlspecialchars($log['customer_phone']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td class="status-<?= $log['status'] ?>"><?= ucfirst($log['status']) ?></td>
                            <td><?= htmlspecialchars($log['order_number']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                            <td>
                                <?php if ($log['error_message']): ?>
                                    <small style="color: #dc3545;"><?= htmlspecialchars($log['error_message']) ?></small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="text-align: center; margin-top: 40px; color: #666;">
            <p><a href="dashboard.php" class="btn">← Volver al Dashboard Principal</a></p>
        </div>
    </div>
</body>
</html>
