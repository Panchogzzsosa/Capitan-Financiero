<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'capitan_financiero';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $data = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        throw new Exception('Dirección de correo electrónico inválida');
    }
    
    $stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (:email)");
    $stmt->execute(['email' => $email]);
    
    echo json_encode([
        'success' => true,
        'message' => '¡Gracias por suscribirte a nuestro newsletter!'
    ]);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry error code
        echo json_encode([
            'success' => false,
            'message' => 'Este correo electrónico ya está registrado'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al procesar la solicitud'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>