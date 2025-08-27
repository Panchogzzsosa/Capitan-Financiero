<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    // Create database connection
    $pdo = getDBConnection();
    
    // Get and validate form data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Basic validation
    if (!isset($data['nombre']) || !isset($data['apellido']) || !isset($data['correo']) || !isset($data['numero']) || !isset($data['mensaje'])) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    // Validate email
    if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Correo electrónico inválido');
    }
    
    // Prepare and execute the SQL statement
    $stmt = $pdo->prepare("INSERT INTO form_submissions (nombre, apellido, correo, numero, mensaje) VALUES (:nombre, :apellido, :correo, :numero, :mensaje)");
    
    $stmt->execute([
        'nombre' => $data['nombre'],
        'apellido' => $data['apellido'],
        'correo' => $data['correo'],
        'numero' => $data['numero'],
        'mensaje' => $data['mensaje']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => '¡Gracias por tu mensaje! Nos pondremos en contacto contigo pronto.'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la solicitud. Por favor, intenta nuevamente más tarde.'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>