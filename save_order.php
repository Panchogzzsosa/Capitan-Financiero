<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'vendor/autoload.php';

try {
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('No se recibieron datos');
    }
    
    // Validar datos requeridos
    $required_fields = ['customer_name', 'customer_email', 'stripe_payment_intent_id', 'total_amount', 'total_amount_cents'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    // Conectar a la base de datos
    $pdo = getDBConnection();
    
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
            ':name' => $input['customer_name'],
            ':email' => $input['customer_email'],
            ':phone' => $input['customer_phone'] ?? null,
            ':address' => $input['customer_address'] ?? null
        ]);
        
        // Obtener ID del cliente
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = :email");
        $stmt->execute([':email' => $input['customer_email']]);
        $customer = $stmt->fetch();
        $customer_id = $customer['id'];
        
        // Crear orden
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, customer_id, stripe_payment_intent_id, total_amount, total_amount_cents, payment_method, status, utm_source, utm_medium, utm_campaign, utm_content, utm_term, referrer)
            VALUES (:order_number, :customer_id, :stripe_payment_intent_id, :total_amount, :total_amount_cents, :payment_method, 'completed', :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :referrer)
        ");
        
        $stmt->execute([
            ':order_number' => $order_number,
            ':customer_id' => $customer_id,
            ':stripe_payment_intent_id' => $input['stripe_payment_intent_id'],
            ':total_amount' => $input['total_amount'],
            ':total_amount_cents' => $input['total_amount_cents'],
            ':payment_method' => $input['payment_method'] ?? 'card',
            ':utm_source' => $input['utm_source'] ?? null,
            ':utm_medium' => $input['utm_medium'] ?? null,
            ':utm_campaign' => $input['utm_campaign'] ?? null,
            ':utm_content' => $input['utm_content'] ?? null,
            ':utm_term' => $input['utm_term'] ?? null,
            ':referrer' => $input['referrer'] ?? null
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
            ':utm_source' => $input['utm_source'] ?? null,
            ':utm_medium' => $input['utm_medium'] ?? null,
            ':utm_campaign' => $input['utm_campaign'] ?? null,
            ':utm_content' => $input['utm_content'] ?? null,
            ':utm_term' => $input['utm_term'] ?? null,
            ':referrer' => $input['referrer'] ?? null,
            ':landing_page' => $input['landing_page'] ?? null,
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
            ':stripe_payment_intent_id' => $input['stripe_payment_intent_id'],
            ':amount' => $input['total_amount'],
            ':amount_cents' => $input['total_amount_cents'],
            ':payment_method_type' => $input['payment_method'] ?? 'card'
        ]);
        
        // Agregar items de orden (asumiendo que es el curso básico por ahora)
        $product_id = 1; // Curso Básico de Finanzas
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, unit_price, unit_price_cents, total_price, total_price_cents)
            VALUES (:order_id, :product_id, 1, :unit_price, :unit_price_cents, :total_price, :total_price_cents)
        ");
        
        $stmt->execute([
            ':order_id' => $order_id,
            ':product_id' => $product_id,
            ':unit_price' => $input['total_amount'],
            ':unit_price_cents' => $input['total_amount_cents'],
            ':total_price' => $input['total_amount'],
            ':total_price_cents' => $input['total_amount_cents']
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
        
        // 🚀 NUEVA FUNCIONALIDAD: Automatización WhatsApp inmediata
        try {
            require_once 'whatsapp_automation.php';
            $automation = new WhatsAppAutomation();
            
            // Procesar solo este usuario específico
            $automation->processPendingUsers();
            
        } catch (Exception $e) {
            // Log del error pero no fallar la orden
            error_log("Error en automatización WhatsApp: " . $e->getMessage());
        }
        
        // 📧 NUEVA FUNCIONALIDAD: Envío automático de correo de confirmación
        try {
            require_once 'brevo_config.php';
            $mailer = new BrevoMailer();
            
            // Preparar datos del cliente
            $customerData = [
                'name' => $input['customer_name'],
                'email' => $input['customer_email'],
                'phone' => $input['customer_phone'] ?? null,
                'address' => $input['customer_address'] ?? null
            ];
            
            // Preparar datos de la orden
            $orderData = [
                'order_number' => $order_number,
                'total_amount' => $input['total_amount'],
                'payment_method' => $input['payment_method'] ?? 'card',
                'order_id' => $order_id
            ];
            
            // Enviar correo de confirmación
            $emailSent = $mailer->sendPurchaseConfirmation($customerData, $orderData);
            
            if ($emailSent) {
                error_log("✅ Correo de confirmación enviado exitosamente a: " . $input['customer_email']);
                
                // Registrar el envío del correo en la base de datos
                $stmt = $pdo->prepare("
                    INSERT INTO email_logs (customer_id, order_id, email_type, status, sent_at)
                    VALUES (:customer_id, :order_id, 'purchase_confirmation', 'sent', NOW())
                ");
                $stmt->execute([
                    ':customer_id' => $customer_id,
                    ':order_id' => $order_id
                ]);
                
            } else {
                error_log("⚠️ Error al enviar correo de confirmación a: " . $input['customer_email']);
                
                // Registrar el error en la base de datos
                $stmt = $pdo->prepare("
                    INSERT INTO email_logs (customer_id, order_id, email_type, status, error_message, sent_at)
                    VALUES (:customer_id, :order_id, 'purchase_confirmation', 'failed', 'Error al enviar correo', NOW())
                ");
                $stmt->execute([
                    ':customer_id' => $customer_id,
                    ':order_id' => $order_id
                ]);
            }
            
        } catch (Exception $e) {
            // Log del error pero no fallar la orden
            error_log("Error en envío de correo de confirmación: " . $e->getMessage());
            
            // Registrar el error en la base de datos si es posible
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO email_logs (customer_id, order_id, email_type, status, error_message, sent_at)
                    VALUES (:customer_id, :order_id, 'purchase_confirmation', 'failed', :error_message, NOW())
                ");
                $stmt->execute([
                    ':customer_id' => $customer_id,
                    ':order_id' => $order_id,
                    ':error_message' => $e->getMessage()
                ]);
            } catch (Exception $dbError) {
                error_log("Error al registrar error de correo en BD: " . $dbError->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Orden guardada correctamente',
            'order_id' => $order_id,
            'order_number' => $order_number,
            'customer_id' => $customer_id
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error al guardar orden: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la orden: ' . $e->getMessage()
    ]);
}
?>
