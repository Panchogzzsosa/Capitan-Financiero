<?php
session_start();
require_once('../config.php');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if all required parameters are present
if (!isset($_POST['type']) || !isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$type = $_POST['type'];
$id = intval($_POST['id']);

// Validate type
if (!in_array($type, ['subscriber', 'form'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($type === 'subscriber') {
        $stmt = $pdo->prepare("DELETE FROM subscribers WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("DELETE FROM form_submissions WHERE id = ?");
    }
    
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}