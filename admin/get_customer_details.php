<?php
session_start();
require_once('../config.php');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$customer_id = $_POST['customer_id'] ?? null;

if (!$customer_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cliente requerido']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Obtener detalles del cliente
    $customer_query = "
        SELECT * FROM customers WHERE id = ?
    ";
    
    $customer_stmt = $pdo->prepare($customer_query);
    $customer_stmt->execute([$customer_id]);
    $customer = $customer_stmt->fetch();
    
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente no encontrado']);
        exit;
    }
    
    // Obtener todas las órdenes del cliente
    $orders_query = "
        SELECT o.*, 
               COUNT(oi.id) as total_items,
               SUM(oi.quantity) as total_quantity
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.customer_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ";
    
    $orders_stmt = $pdo->prepare($orders_query);
    $orders_stmt->execute([$customer_id]);
    $orders = $orders_stmt->fetchAll();
    
    // Calcular estadísticas del cliente
    $stats_query = "
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            SUM(o.total_amount) as total_spent,
            AVG(o.total_amount) as average_order_value,
            MIN(o.created_at) as first_order_date,
            MAX(o.created_at) as last_order_date
        FROM orders o 
        WHERE o.customer_id = ? AND o.status = 'completed'
    ";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$customer_id]);
    $stats = $stats_stmt->fetch();
    
    // Obtener productos comprados por el cliente
    $products_query = "
        SELECT p.name as product_name, p.description as product_description,
               SUM(oi.quantity) as total_quantity,
               COUNT(DISTINCT o.id) as times_purchased
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        JOIN products p ON oi.product_id = p.id 
        WHERE o.customer_id = ? AND o.status = 'completed'
        GROUP BY p.id, p.name, p.description
        ORDER BY total_quantity DESC
    ";
    
    $products_stmt = $pdo->prepare($products_query);
    $products_stmt->execute([$customer_id]);
    $products = $products_stmt->fetchAll();
    
    // Preparar la respuesta
    $response = [
        'customer' => $customer,
        'orders' => $orders,
        'stats' => $stats,
        'products' => $products
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
