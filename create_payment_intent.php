<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
require_once 'vendor/autoload.php';

// Configurar Stripe
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

try {
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('No se recibieron datos');
    }
    
    if (!isset($input['amount']) || empty($input['amount'])) {
        throw new Exception('Monto requerido');
    }
    
    // Validar que el monto sea un número válido
    $amount = intval($input['amount']);
    if ($amount <= 0) {
        throw new Exception('Monto inválido');
    }
    
    // Crear Payment Intent
    $payment_intent = \Stripe\PaymentIntent::create([
        'amount' => $amount,
        'currency' => 'mxn',
        'automatic_payment_methods' => [
            'enabled' => true,
        ],
        'metadata' => [
            'order_id' => uniqid('CF_'),
            'customer_email' => $input['email'] ?? '',
            'customer_name' => $input['name'] ?? ''
        ]
    ]);
    
    echo json_encode([
        'success' => true,
        'client_secret' => $payment_intent->client_secret,
        'payment_intent_id' => $payment_intent->id
    ]);
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Stripe API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en el procesamiento del pago: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('General Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 