<?php
session_start();
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../vendor/autoload.php');

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// URL base de tu sitio - puedes cambiar esto según tu configuración
//$baseUrl = 'https://prueba.capitanfinanciero.com'; // Cambia por tu dominio real

// Si estás probando localmente, usa esta línea en su lugar:
$baseUrl = 'http://localhost/capitanfinanciero';

// Crear URL para el carrito con parámetros UTM para rastreo
$cartUrl = $baseUrl . '/checkout.html?utm_source=qr_code&utm_medium=qr_code&utm_campaign=Alineación_Financiera_I&utm_content=programa_basico&utm_term=finanzas_personales';

// Crear el código QR
$qrCode = new QrCode($cartUrl);

// Crear el writer
$writer = new PngWriter();

// Generar el QR
$result = $writer->write($qrCode);

// Convertir a string base64 para mostrar en HTML
$qrDataUri = 'data:image/png;base64,' . base64_encode($result->getString());

// También guardar el QR como archivo
$qrPath = __DIR__ . '/../Img/qr_cart_alineacion_financiera.png';
$result->saveToFile($qrPath);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../Img/Logo.png" type="image/x-icon">
    <title>Generar QR - Capitán Financiero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .qr-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
            text-align: center;
            max-width: 500px;
            margin: 2rem auto;
        }
        
        .qr-code {
            margin: 2rem 0;
            padding: 1rem;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        
        .qr-code img {
            max-width: 100%;
            height: auto;
        }
        
        .download-btn {
            background: #222F58;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            background: #1a233f;
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        
        .url-info {
            background: #e9ecef;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="qr-container">
            <h2><i class="fas fa-qrcode"></i> Código QR con UTM</h2>
            <p class="text-muted">Escanea este código QR para ir a la página principal con parámetros UTM para rastrear el tráfico offline</p>
            
            <div class="qr-code">
                <img src="<?php echo $qrDataUri; ?>" alt="QR Code con UTM" />
            </div>
            
            <div class="url-info">
                <strong>URL del QR con UTM:</strong><br>
                <?php echo htmlspecialchars($cartUrl); ?>
            </div>
            
            <div class="mt-4">
                <a href="../Img/qr_cart_alineacion_financiera.png" download="qr_utm_alineacion_financiera.png" class="download-btn">
                    <i class="fas fa-download"></i> Descargar QR
                </a>
                
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </div>
            
            <div class="mt-4">
                <h5>Parámetros UTM incluidos:</h5>
                <ul class="text-start">
                    <li><strong>utm_source:</strong> qr_code (identifica que viene de un código QR)</li>
                    <li><strong>utm_medium:</strong> offline (indica que es tráfico offline)</li>
                    <li><strong>utm_campaign:</strong> alineacion_financiera (nombre de la campaña)</li>
                    <li><strong>utm_content:</strong> programa_basico (contenido específico)</li>
                    <li><strong>utm_term:</strong> finanzas_personales (palabras clave)</li>
                </ul>
                
                <h5 class="mt-3">Instrucciones:</h5>
                <ol class="text-start">
                    <li>Descarga el código QR</li>
                    <li>Imprímelo o muéstralo en pantalla</li>
                    <li>Los clientes pueden escanearlo con cualquier app de QR</li>
                    <li>Serán dirigidos a la página principal con parámetros UTM</li>
                    <li>Podrás rastrear en tu dashboard que vienen desde este código QR</li>
                </ol>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
