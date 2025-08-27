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

$order_id = $_POST['order_id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de orden requerido']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Obtener detalles de la orden con información del cliente
    $order_query = "
        SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address
        FROM orders o 
        JOIN customers c ON o.customer_id = c.id 
        WHERE o.id = ?
    ";
    
    $order_stmt = $pdo->prepare($order_query);
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Orden no encontrada']);
        exit;
    }
    
    // Obtener los productos de la orden
    $items_query = "
        SELECT oi.*, p.name as product_name, p.description as product_description, 
               oi.unit_price as product_price, oi.unit_price_cents as product_price_cents
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ";
    
    $items_stmt = $pdo->prepare($items_query);
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll();
    
    // Obtener información del pago
    $payment_query = "
        SELECT * FROM payments WHERE order_id = ?
    ";
    
    $payment_stmt = $pdo->prepare($payment_query);
    $payment_stmt->execute([$order_id]);
    $payment = $payment_stmt->fetch();
    
    // Preparar la respuesta
    $response = [
        'order' => $order,
        'items' => $order_items,
        'payment' => $payment
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
