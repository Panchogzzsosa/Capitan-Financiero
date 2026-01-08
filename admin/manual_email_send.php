<?php
session_start();
require_once('../config.php');
require_once('../vendor/autoload.php');
require_once('../EmailService.php');

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y sanitizar inputs
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $product = trim($_POST['product_name'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $orderId = trim($_POST['order_id'] ?? '');

    // Validaciones básicas
    if (empty($email) || empty($orderId)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios (Email u Orden)']);
        exit;
    }

    // Preparar datos para EmailService
    $customerData = [
        'name' => $name,
        'email' => $email
    ];

    $orderData = [
        'order_number' => $orderId,
        'total_amount' => $price,
        'product_name' => $product // Usado dinámicamente en EmailService
    ];

    try {
        $emailService = new EmailService();
        $result = $emailService->sendPurchaseConfirmation($customerData, $orderData);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Correo enviado correctamente a ' . $email]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al enviar el correo. Revisa los logs de error de PHP.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Excepción: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
