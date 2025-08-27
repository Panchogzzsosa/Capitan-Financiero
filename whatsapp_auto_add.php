<?php
/**
 * SISTEMA AVANZADO: Agregar usuarios AUTOMÁTICAMENTE a grupos
 * 
 * IMPORTANTE: Para agregar automáticamente necesitas:
 * 1. WhatsApp Business API con permisos de grupo
 * 2. Tu número debe ser ADMIN del grupo
 * 3. El usuario debe haber interactuado contigo antes
 */

require_once 'config.php';

class WhatsAppAutoAdd {
    private $pdo;
    private $whatsapp_token;
    private $phone_number_id;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->whatsapp_token = 'YOUR_WHATSAPP_ACCESS_TOKEN'; // 👈 TU TOKEN
        $this->phone_number_id = 'YOUR_PHONE_NUMBER_ID'; // 👈 TU PHONE ID
    }
    
    /**
     * MÉTODO 1: Agregar directamente al grupo (requiere Group ID)
     */
    public function addUserToGroupDirectly($phone, $group_id) {
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $group_id, // ID del grupo (no el enlace)
            'type' => 'text',
            'text' => [
                'body' => "Nuevo miembro agregado: +$phone"
            ]
        ];
        
        // Agregar participante al grupo
        $add_data = [
            'messaging_product' => 'whatsapp',
            'group_id' => $group_id,
            'action' => 'add_participant',
            'participant_phone' => $phone
        ];
        
        return $this->sendGroupAction($add_data);
    }
    
    /**
     * MÉTODO 2: Crear invitación personalizada al grupo
     */
    public function createGroupInvitation($phone, $user_data) {
        // Mensaje más persuasivo con botón de acción
        $message = "🎉 ¡Felicidades {$user_data['customer_name']}!\n\n";
        $message .= "Tu compra de {$user_data['product_name']} fue exitosa ✅\n\n";
        $message .= "🚀 SIGUIENTE PASO IMPORTANTE:\n";
        $message .= "Únete a nuestro grupo VIP donde recibirás:\n\n";
        $message .= "💎 Contenido exclusivo del curso\n";
        $message .= "📞 Soporte directo conmigo\n";
        $message .= "👥 Comunidad de estudiantes exitosos\n";
        $message .= "🎁 Bonos y recursos adicionales\n\n";
        $message .= "⏰ ¡Solo tienes 24 horas para unirte!\n\n";
        
        // Crear mensaje con botón interactivo
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $message
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'join_group_' . $user_data['order_id'],
                                'title' => '🚀 UNIRME AL GRUPO'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'info_group_' . $user_data['order_id'],
                                'title' => '❓ Más información'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        return $this->sendWhatsAppMessage($data);
    }
    
    /**
     * MÉTODO 3: Sistema híbrido (Recomendado)
     */
    public function processUserHybrid($user) {
        $phone = $this->cleanPhoneNumber($user['customer_phone']);
        
        // Paso 1: Enviar invitación con botones
        $invitation_sent = $this->createGroupInvitation($phone, $user);
        
        if ($invitation_sent) {
            // Marcar como "invitation_sent"
            $this->updateSubscriptionStatus($user['subscription_id'], 'invitation_sent');
            
            // Programar auto-add para 24 horas después
            $this->scheduleAutoAdd($user, 24); // 24 horas
            
            return [
                'success' => true,
                'action' => 'invitation_sent',
                'customer' => $user['customer_name'],
                'next_action' => 'auto_add_in_24h'
            ];
        }
        
        return ['success' => false, 'error' => 'No se pudo enviar invitación'];
    }
    
    /**
     * Programar auto-add después de X horas
     */
    private function scheduleAutoAdd($user, $hours) {
        $execute_at = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO scheduled_actions (
                customer_id, order_id, action_type, 
                execute_at, data, status
            ) VALUES (
                :customer_id, :order_id, 'auto_add_to_group',
                :execute_at, :data, 'pending'
            )
        ");
        
        $stmt->execute([
            ':customer_id' => $user['customer_id'],
            ':order_id' => $user['order_id'],
            ':execute_at' => $execute_at,
            ':data' => json_encode($user)
        ]);
    }
    
    /**
     * Procesar acciones programadas (ejecutar con CRON cada hora)
     */
    public function processScheduledActions() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM scheduled_actions 
            WHERE execute_at <= NOW() 
            AND status = 'pending'
            ORDER BY execute_at ASC
        ");
        
        $stmt->execute();
        $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        foreach ($actions as $action) {
            $user_data = json_decode($action['data'], true);
            
            // Verificar si ya se unió al grupo manualmente
            if ($this->userAlreadyInGroup($user_data)) {
                $this->markActionCompleted($action['id'], 'user_joined_manually');
                continue;
            }
            
            // Agregar automáticamente
            $result = $this->addUserToGroupDirectly(
                $this->cleanPhoneNumber($user_data['customer_phone']),
                $user_data['group_id']
            );
            
            if ($result) {
                $this->markActionCompleted($action['id'], 'auto_added');
                $this->updateSubscriptionStatus($user_data['subscription_id'], 'added');
                $results[] = ['success' => true, 'customer' => $user_data['customer_name']];
            } else {
                $this->markActionCompleted($action['id'], 'failed');
                $results[] = ['success' => false, 'customer' => $user_data['customer_name']];
            }
        }
        
        return $results;
    }
    
    /**
     * Verificar si usuario ya está en el grupo
     */
    private function userAlreadyInGroup($user_data) {
        // Aquí podrías implementar lógica para verificar
        // si el usuario ya se unió manualmente
        // Por ahora, asumimos que no
        return false;
    }
    
    /**
     * Marcar acción como completada
     */
    private function markActionCompleted($action_id, $result) {
        $stmt = $this->pdo->prepare("
            UPDATE scheduled_actions 
            SET status = 'completed', result = :result, completed_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':id' => $action_id,
            ':result' => $result
        ]);
    }
    
    // Métodos auxiliares (copiados del archivo principal)
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
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code === 200;
    }
    
    private function sendGroupAction($data) {
        // Implementar llamada específica para acciones de grupo
        // Esto requiere permisos especiales de WhatsApp Business API
        return true; // Placeholder
    }
    
    private function cleanPhoneNumber($phone) {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        if (strpos($phone, '+52') === 0) {
            return substr($phone, 1);
        }
        
        if (strpos($phone, '52') === 0 && strlen($phone) >= 12) {
            return $phone;
        }
        
        if (strlen($phone) === 10) {
            return '52' . $phone;
        }
        
        return null;
    }
    
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
}

// Crear tabla para acciones programadas (ejecutar una vez)
/*
CREATE TABLE scheduled_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    execute_at DATETIME NOT NULL,
    data JSON,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    result VARCHAR(100),
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
);
*/
?>

