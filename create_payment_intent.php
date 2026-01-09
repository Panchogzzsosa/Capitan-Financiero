<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
require_once 'vendor/autoload.php';

// Configurar Stripe
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Definir precios y descuentos autorizados (Server-Side Source of Truth)
const BASE_PRICE_CENTS = 465000; // $4,650.00 MXN

// Mapa de descuentos: Código => Monto a descontar en centavos
const VALID_DISCOUNTS = [
    'CAPITAN01' => 275100, // Descuento de $2,751.00 -> Final: $1,899.00
    'MONEI26'   => 315100  // Descuento de $3,151.00 -> Final: $1,499.00
];

try {
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('No se recibieron datos');
    }
    
    // Calcular el monto en el servidor
    $finalAmount = BASE_PRICE_CENTS;
    $appliedDiscountCode = null;

    if (isset($input['discount_code']) && !empty($input['discount_code'])) {
        $code = strtoupper(trim($input['discount_code']));
        if (isset(VALID_DISCOUNTS[$code])) {
            $discountAmount = VALID_DISCOUNTS[$code];
            $finalAmount = max(0, BASE_PRICE_CENTS - $discountAmount);
            $appliedDiscountCode = $code;
        }
    }
    
    // VALIDACIÓN DE SEGURIDAD PARA CACHÉ Y MANIPULACIÓN
    // Si el cliente envía un monto diferente al calculado por el servidor, rechazamos la transacción.
    // Esto detecta usuarios con scripts cacheados (que calculan precios antiguos) y evita cobros incorrectos.
    if (isset($input['amount'])) {
        $clientAmount = intval($input['amount']);
        // Permitimos una pequeña diferencia de 0 (exactitud total requerida)
        if ($clientAmount !== $finalAmount) {
            // Caso específico: Usuario con caché antiguo intentando pagar precio viejo
            throw new Exception('El precio del producto ha cambiado o tu sesión expiró. Por favor recarga la página para ver el precio actualizado.');
        }
    }
    
    // Crear Payment Intent con el monto calculado por el servidor
    $payment_intent = \Stripe\PaymentIntent::create([
        'amount' => $finalAmount,
        'currency' => 'mxn',
        'automatic_payment_methods' => [
            'enabled' => true,
        ],
        'payment_method_options' => [
            'card' => [
                'installments' => [
                    'enabled' => true
                ]
            ]
        ],
        'metadata' => [
            'order_id' => uniqid('CF_'),
            'customer_email' => $input['email'] ?? '',
            'customer_name' => $input['name'] ?? '',
            'discount_code' => $appliedDiscountCode ?? 'NONE',
            'base_price' => BASE_PRICE_CENTS,
            'original_request_amount' => $input['amount'] ?? 'N/A'
        ]
    ]);
    
    echo json_encode([
        'success' => true,
        'client_secret' => $payment_intent->client_secret,
        'payment_intent_id' => $payment_intent->id,
        'amount' => $finalAmount,
        'discount_applied' => $appliedDiscountCode
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