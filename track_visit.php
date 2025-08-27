<?php
require_once('config.php');

// Permitir CORS para que funcione desde cualquier página
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Si es una petición OPTIONS (preflight), responder inmediatamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $pdo = getDBConnection();
    
    // Obtener parámetros UTM de la petición
    $utm_source = $_GET['utm_source'] ?? $_POST['utm_source'] ?? '';
    $utm_medium = $_GET['utm_medium'] ?? $_POST['utm_medium'] ?? '';
    $utm_campaign = $_GET['utm_campaign'] ?? $_POST['utm_campaign'] ?? '';
    $utm_content = $_GET['utm_content'] ?? $_POST['utm_content'] ?? '';
    $utm_term = $_GET['utm_term'] ?? $_POST['utm_term'] ?? '';
    
    // Obtener información del visitante
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $landing_page = $_GET['landing_page'] ?? $_POST['landing_page'] ?? '';
    
    // Solo registrar si hay al menos un parámetro UTM
    if ($utm_source || $utm_medium || $utm_campaign) {
        
        // Crear un fingerprint único del usuario
        $user_fingerprint = md5($ip_address . $user_agent . $utm_source . $utm_medium . $utm_campaign);
        
        // Verificar si ya existe una visita reciente del mismo usuario con los mismos parámetros UTM
        // Solo contar una visita por usuario por UTM cada 24 horas
        $stmt = $pdo->prepare("
            SELECT id FROM traffic_tracking 
            WHERE user_fingerprint = ? 
            AND utm_source = ? 
            AND utm_medium = ? 
            AND utm_campaign = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            LIMIT 1
        ");
        
        $stmt->execute([$user_fingerprint, $utm_source, $utm_medium, $utm_campaign]);
        
        if ($stmt->fetch()) {
            // Ya existe una visita reciente del mismo usuario con los mismos UTM
            echo json_encode([
                'success' => true,
                'message' => 'Visita ya registrada recientemente para este usuario',
                'duplicate' => true,
                'user_fingerprint' => $user_fingerprint
            ]);
            exit;
        }
        
        // Insertar la nueva visita en la base de datos
        $stmt = $pdo->prepare("
            INSERT INTO traffic_tracking (
                utm_source, utm_medium, utm_campaign, utm_content, utm_term,
                ip_address, user_agent, referrer, landing_page, user_fingerprint, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $utm_source,
            $utm_medium,
            $utm_campaign,
            $utm_content,
            $utm_term,
            $ip_address,
            $user_agent,
            $referrer,
            $landing_page,
            $user_fingerprint
        ]);
        
        $visit_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Visita única registrada exitosamente',
            'visit_id' => $visit_id,
            'user_fingerprint' => $user_fingerprint,
            'utm_data' => [
                'utm_source' => $utm_source,
                'utm_medium' => $utm_medium,
                'utm_campaign' => $utm_campaign,
                'utm_content' => $utm_content,
                'utm_term' => $utm_term
            ]
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No hay parámetros UTM para registrar'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
