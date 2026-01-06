<?php
require_once('config.php');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            banner_enabled TINYINT(1) NOT NULL DEFAULT 1,
            banner_text VARCHAR(255) NOT NULL DEFAULT 'CODIGO DE DESCUENTO : CAPITAN26',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    $settings = $pdo->query("SELECT banner_enabled, banner_text FROM site_settings WHERE id = 1")->fetch();
    if (!$settings) {
        echo json_encode([
            'enabled' => true,
            'text' => 'CODIGO DE DESCUENTO : CAPITAN26'
        ]);
        exit;
    }
    echo json_encode([
        'enabled' => (int)$settings['banner_enabled'] === 1,
        'text' => (string)$settings['banner_text']
    ]);
} catch (Exception $e) {
    echo json_encode([
        'enabled' => true,
        'text' => 'CODIGO DE DESCUENTO : CAPITAN26'
    ]);
}
