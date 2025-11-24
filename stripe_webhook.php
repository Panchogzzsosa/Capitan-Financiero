<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'vendor/autoload.php';

// Configurar Stripe
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Obtener el payload del webhook
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$endpoint_secret = 'whsec_sdTeePFAyrsisvfzjZxYUGgx85yu1Hnt';

try {
    // Verificar la firma del webhook
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    // Payload inválido
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    // Firma inválida
    http_response_code(400);
    exit();
}

// Manejar el evento
switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        handleCheckoutSessionCompleted($session);
        break;
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;
        handlePaymentIntentSucceeded($paymentIntent);
        break;
    default:
        // Evento no manejado
        echo json_encode(['status' => 'ignored']);
}

function handleCheckoutSessionCompleted($session) {
    try {
        // Conectar a la base de datos
        $pdo = getDBConnection();
        
        // Obtener información del cliente desde los metadatos de Stripe
        $customer_email = $session->customer_details->email ?? null;
        $customer_name = $session->customer_details->name ?? null;
        $customer_phone = $session->customer_details->phone ?? null;
        $customer_address = $session->customer_details->address ?? null;
        
        // Si no hay datos del cliente, intentar obtenerlos del Payment Intent
        if (!$customer_email && $session->payment_intent) {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
            $customer_email = $paymentIntent->metadata->customer_email ?? null;
            $customer_name = $paymentIntent->metadata->customer_name ?? null;
        }
        
        // Validar datos requeridos
        if (!$customer_email || !$customer_name) {
            error_log("Webhook: Datos de cliente incompletos para session: " . $session->id);
            return;
        }
        
        // Generar número de orden único
        $order_number = 'CF_' . date('Ymd') . '_' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        try {
            // Insertar o actualizar cliente
            $stmt = $pdo->prepare("
                INSERT INTO customers (name, email, phone, address) 
                VALUES (:name, :email, :phone, :address)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    phone = VALUES(phone),
                    address = VALUES(address),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                ':name' => $customer_name,
                ':email' => $customer_email,
                ':phone' => $customer_phone,
                ':address' => $customer_address ? json_encode($customer_address) : null
            ]);
            
            // Obtener ID del cliente
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = :email");
            $stmt->execute([':email' => $customer_email]);
            $customer = $stmt->fetch();
            $customer_id = $customer['id'];
            
            // Crear orden
            $stmt = $pdo->prepare("
                INSERT INTO orders (order_number, customer_id, stripe_payment_intent_id, total_amount, total_amount_cents, payment_method, status, utm_source, utm_medium, utm_campaign, utm_content, utm_term, referrer)
                VALUES (:order_number, :customer_id, :stripe_payment_intent_id, :total_amount, :total_amount_cents, :payment_method, 'completed', :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :referrer)
            ");
            
            $total_amount = $session->amount_total / 100; // Convertir de centavos
            $total_amount_cents = $session->amount_total;
            
            $stmt->execute([
                ':order_number' => $order_number,
                ':customer_id' => $customer_id,
                ':stripe_payment_intent_id' => $session->payment_intent,
                ':total_amount' => $total_amount,
                ':total_amount_cents' => $total_amount_cents,
                ':payment_method' => 'stripe_checkout',
                ':utm_source' => $session->metadata->utm_source ?? null,
                ':utm_medium' => $session->metadata->utm_medium ?? null,
                ':utm_campaign' => $session->metadata->utm_campaign ?? null,
                ':utm_content' => $session->metadata->utm_content ?? null,
                ':utm_term' => $session->metadata->utm_term ?? null,
                ':referrer' => $session->metadata->referrer ?? null
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // Registrar información de tráfico
            $stmt = $pdo->prepare("
                INSERT INTO traffic_tracking (order_id, customer_id, utm_source, utm_medium, utm_campaign, utm_content, utm_term, referrer, landing_page, user_agent, ip_address, session_id)
                VALUES (:order_id, :customer_id, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :referrer, :landing_page, :user_agent, :ip_address, :session_id)
            ");
            
            $stmt->execute([
                ':order_id' => $order_id,
                ':customer_id' => $customer_id,
                ':utm_source' => $session->metadata->utm_source ?? null,
                ':utm_medium' => $session->metadata->utm_medium ?? null,
                ':utm_campaign' => $session->metadata->utm_campaign ?? null,
                ':utm_content' => $session->metadata->utm_content ?? null,
                ':utm_term' => $session->metadata->utm_term ?? null,
                ':referrer' => $session->metadata->referrer ?? null,
                ':landing_page' => $session->metadata->landing_page ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':session_id' => session_id() ?? null
            ]);
            
            // Crear pago
            $stmt = $pdo->prepare("
                INSERT INTO payments (order_id, stripe_payment_intent_id, amount, amount_cents, payment_method_type, status)
                VALUES (:order_id, :stripe_payment_intent_id, :amount, :amount_cents, :payment_method_type, 'succeeded')
            ");
            
            $stmt->execute([
                ':order_id' => $order_id,
                ':stripe_payment_intent_id' => $session->payment_intent,
                ':amount' => $total_amount,
                ':amount_cents' => $total_amount_cents,
                ':payment_method_type' => 'stripe_checkout'
            ]);
            
            // Agregar items de orden
            $product_id = 1; // Curso Básico de Finanzas
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, unit_price_cents, total_price, total_price_cents)
                VALUES (:order_id, :product_id, 1, :unit_price, :unit_price_cents, :total_price, :total_price_cents)
            ");
            
            $stmt->execute([
                ':order_id' => $order_id,
                ':product_id' => $product_id,
                ':unit_price' => $total_amount,
                ':unit_price_cents' => $total_amount_cents,
                ':total_price' => $total_amount,
                ':total_price_cents' => $total_amount_cents
            ]);
            
            // Agregar cliente al grupo de WhatsApp
            $stmt = $pdo->prepare("
                INSERT INTO group_subscriptions (customer_id, group_id, order_id, status)
                VALUES (:customer_id, :group_id, :order_id, 'pending')
            ");
            
            $stmt->execute([
                ':customer_id' => $customer_id,
                ':group_id' => 1, // Grupo del Curso Básico
                ':order_id' => $order_id
            ]);
            
            // Log para ManyChat
            $stmt = $pdo->prepare("
                INSERT INTO manychat_logs (customer_id, order_id, action, status)
                VALUES (:customer_id, :order_id, 'add_to_whatsapp_group', 'pending')
            ");
            
            $stmt->execute([
                ':customer_id' => $customer_id,
                ':order_id' => $order_id
            ]);
            
            // Confirmar transacción
            $pdo->commit();
            
            // 🚀 Automatización WhatsApp inmediata
            try {
                require_once 'whatsapp_automation.php';
                $automation = new WhatsAppAutomation();
                $automation->processPendingUsers();
            } catch (Exception $e) {
                error_log("Error en automatización WhatsApp (webhook): " . $e->getMessage());
            }
            
            // 📧 Envío automático de correo de confirmación
            try {
                require_once 'brevo_config.php';
                $mailer = new BrevoMailer();
                
                $customerData = [
                    'name' => $customer_name,
                    'email' => $customer_email,
                    'phone' => $customer_phone,
                    'address' => $customer_address
                ];
                
                $orderData = [
                    'order_number' => $order_number,
                    'total_amount' => $total_amount,
                    'payment_method' => 'stripe_checkout',
                    'order_id' => $order_id
                ];
                
                $emailSent = $mailer->sendPurchaseConfirmation($customerData, $orderData);
                
                if ($emailSent) {
                    // Registrar el envío del correo
                    $stmt = $pdo->prepare("
                        INSERT INTO email_logs (customer_id, order_id, email_type, status, sent_at)
                        VALUES (:customer_id, :order_id, 'purchase_confirmation', 'sent', NOW())
                    ");
                    $stmt->execute([
                        ':customer_id' => $customer_id,
                        ':order_id' => $order_id
                    ]);
                }
                
            } catch (Exception $e) {
                error_log("Error en envío de correo (webhook): " . $e->getMessage());
            }
            
            error_log("✅ Webhook: Orden procesada exitosamente - Order ID: $order_id, Customer: $customer_email");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("❌ Webhook: Error al procesar orden: " . $e->getMessage());
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("❌ Webhook: Error general: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handlePaymentIntentSucceeded($paymentIntent) {
    // Manejar pagos que no pasan por checkout.session.completed
    // Por ejemplo, pagos directos con Payment Intents
    error_log("Payment Intent succeeded: " . $paymentIntent->id);
    
    // Si no hay session asociada, procesar como pago directo
    if (!$paymentIntent->metadata->session_id) {
        handleCheckoutSessionCompleted((object)[
            'id' => 'direct_payment_' . $paymentIntent->id,
            'payment_intent' => $paymentIntent->id,
            'amount_total' => $paymentIntent->amount,
            'customer_details' => (object)[
                'email' => $paymentIntent->metadata->customer_email ?? null,
                'name' => $paymentIntent->metadata->customer_name ?? null,
                'phone' => $paymentIntent->metadata->customer_phone ?? null,
                'address' => $paymentIntent->metadata->customer_address ?? null
            ],
            'metadata' => $paymentIntent->metadata
        ]);
    }
}

echo json_encode(['status' => 'success']);
?>
