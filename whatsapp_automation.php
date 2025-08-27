<?php
/**
 * Sistema de Automatización de WhatsApp
 * Alternativa a ManyChat para agregar usuarios a grupos automáticamente
 */

require_once 'config.php';

class WhatsAppAutomation {
    private $pdo;
    private $whatsapp_token;
    private $phone_number_id;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        
        // Configuración de WhatsApp Business API
        // 🔑 PASO 1: Ve a developers.facebook.com
        // 🔑 PASO 2: Crea app → WhatsApp → Obtén estos valores:
        $this->whatsapp_token = 'YOUR_WHATSAPP_ACCESS_TOKEN'; // 👈 PEGA TU TOKEN AQUÍ
        $this->phone_number_id = 'YOUR_PHONE_NUMBER_ID'; // 👈 PEGA TU PHONE ID AQUÍ
    }
    
    /**
     * Procesa todos los usuarios pendientes de agregar a grupos
     */
    public function processPendingUsers() {
        try {
            // Obtener usuarios pendientes con información completa
            $stmt = $this->pdo->prepare("
                SELECT 
                    gs.id as subscription_id,
                    gs.customer_id,
                    gs.order_id,
                    c.name as customer_name,
                    c.phone as customer_phone,
                    wg.name as group_name,
                    wg.group_link,
                    p.name as product_name
                FROM group_subscriptions gs
                JOIN customers c ON gs.customer_id = c.id
                JOIN whatsapp_groups wg ON gs.group_id = wg.id
                JOIN orders o ON gs.order_id = o.id
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE gs.status = 'pending'
                ORDER BY gs.created_at ASC
            ");
            
            $stmt->execute();
            $pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            foreach ($pending_users as $user) {
                $result = $this->addUserToWhatsAppGroup($user);
                $results[] = $result;
                
                // Pequeña pausa entre mensajes para evitar rate limiting
                sleep(2);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Error procesando usuarios pendientes: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Agrega un usuario específico al grupo de WhatsApp
     */
    private function addUserToWhatsAppGroup($user) {
        try {
            // Limpiar número de teléfono (formato internacional)
            $phone = $this->cleanPhoneNumber($user['customer_phone']);
            
            if (!$phone) {
                throw new Exception("Número de teléfono inválido: " . $user['customer_phone']);
            }
            
            // Enviar mensaje de bienvenida con enlace al grupo
            $message_sent = $this->sendWelcomeMessage($phone, $user);
            
            if ($message_sent) {
                // Actualizar estado en la base de datos
                $this->updateSubscriptionStatus($user['subscription_id'], 'added');
                $this->logAction($user['customer_id'], $user['order_id'], 'add_to_whatsapp_group', 'success', [
                    'phone' => $phone,
                    'group_name' => $user['group_name'],
                    'message_sent' => true
                ]);
                
                return [
                    'success' => true,
                    'customer' => $user['customer_name'],
                    'phone' => $phone,
                    'group' => $user['group_name']
                ];
            } else {
                throw new Exception("No se pudo enviar el mensaje");
            }
            
        } catch (Exception $e) {
            // Marcar como fallido
            $this->updateSubscriptionStatus($user['subscription_id'], 'failed');
            $this->logAction($user['customer_id'], $user['order_id'], 'add_to_whatsapp_group', 'failed', null, $e->getMessage());
            
            return [
                'success' => false,
                'customer' => $user['customer_name'],
                'phone' => $user['customer_phone'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envía mensaje de bienvenida con enlace al grupo
     */
    private function sendWelcomeMessage($phone, $user) {
        $message = "🎉 ¡Hola {$user['customer_name']}!\n\n";
        $message .= "¡Gracias por adquirir {$user['product_name']}! 📚\n\n";
        $message .= "Te invitamos a unirte a nuestro grupo exclusivo de WhatsApp donde podrás:\n";
        $message .= "✅ Recibir contenido adicional\n";
        $message .= "✅ Hacer preguntas directamente\n";
        $message .= "✅ Conectar con otros estudiantes\n\n";
        $message .= "👥 Únete aquí: {$user['group_link']}\n\n";
        $message .= "¡Nos vemos en el grupo! 🚀\n\n";
        $message .= "_Equipo Capitán Financiero_";
        
        // Datos para la API de WhatsApp
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        // Enviar mediante WhatsApp Business API
        return $this->sendWhatsAppMessage($data);
    }
    
    /**
     * Envía mensaje usando WhatsApp Business API
     */
    private function sendWhatsAppMessage($data) {
        $url = "https://graph.facebook.com/v18.0/{$this->phone_number_id}/messages";
        
        $headers = [
            'Authorization: Bearer ' . $this->whatsapp_token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return true;
        } else {
            error_log("Error WhatsApp API: " . $response);
            return false;
        }
    }
    
    /**
     * Limpia y formatea número de teléfono
     */
    private function cleanPhoneNumber($phone) {
        // Remover espacios, guiones y paréntesis
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Si empieza con +52 (México), mantenerlo
        if (strpos($phone, '+52') === 0) {
            return substr($phone, 1); // Remover + para la API
        }
        
        // Si empieza con 52, mantenerlo
        if (strpos($phone, '52') === 0 && strlen($phone) >= 12) {
            return $phone;
        }
        
        // Si es número local mexicano, agregar código de país
        if (strlen($phone) === 10) {
            return '52' . $phone;
        }
        
        // Si empieza con 1 (formato local con 1), agregar 52
        if (strpos($phone, '1') === 0 && strlen($phone) === 11) {
            return '52' . substr($phone, 1);
        }
        
        return null; // Número inválido
    }
    
    /**
     * Actualiza estado de suscripción al grupo
     */
    private function updateSubscriptionStatus($subscription_id, $status) {
        $stmt = $this->pdo->prepare("
            UPDATE group_subscriptions 
            SET status = :status, added_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':status' => $status,
            ':id' => $subscription_id
        ]);
    }
    
    /**
     * Registra acción en logs
     */
    private function logAction($customer_id, $order_id, $action, $status, $response_data = null, $error_message = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO manychat_logs (customer_id, order_id, action, status, response_data, error_message)
            VALUES (:customer_id, :order_id, :action, :status, :response_data, :error_message)
        ");
        
        $stmt->execute([
            ':customer_id' => $customer_id,
            ':order_id' => $order_id,
            ':action' => $action,
            ':status' => $status,
            ':response_data' => $response_data ? json_encode($response_data) : null,
            ':error_message' => $error_message
        ]);
    }
    
    /**
     * Obtiene estadísticas de procesamiento
     */
    public function getStats() {
        $stmt = $this->pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM group_subscriptions 
            GROUP BY status
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Si se ejecuta directamente, procesar usuarios pendientes
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $automation = new WhatsAppAutomation();
    $results = $automation->processPendingUsers();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'processed' => count($results),
        'results' => $results,
        'stats' => $automation->getStats()
    ]);
}
?>
