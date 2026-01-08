<?php
require_once 'vendor/autoload.php';
require_once 'EmailService.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Probe de versión del correo</h2>";

try {
    $service = new EmailService();

    $customerData = [
        'name' => 'Probe Usuario',
        'email' => 'probe@example.com'
    ];

    $orderData = [
        'order_number' => 'PROBE-000',
        'total_amount' => '0.00',
        'payment_method' => 'Probe'
    ];

    // Obtener HTML mediante reflexión sin enviar correo
    $reflection = new ReflectionClass('EmailService');
    $method = $reflection->getMethod('getHtmlContent');
    $method->setAccessible(true);
    $html = $method->invokeArgs($service, [$customerData, $orderData]);

    // Mostrar datos clave
    echo "<p><strong>Asunto esperado:</strong> Confirmación de compra — Capitán Financiero [CF v2]</p>";
    echo "<p><strong>Cabecera esperada:</strong> X-CF-Email-Design: v2</p>";

    // Mostrar fragmento del HTML y marcador del footer
    echo "<h3>Vista previa del HTML generado</h3>";
    echo "<div style='border:1px solid #ddd;padding:12px;border-radius:8px;background:#fafafa;'>";
    echo $html;
    echo "</div>";

    // Mostrar configuración SMTP parcialmente (sin exponer secretos)
    $host = getenv('BREVO_SMTP_HOST') ?: 'smtp-relay.brevo.com';
    $port = getenv('BREVO_SMTP_PORT') ?: '587';
    $user = getenv('BREVO_SMTP_USERNAME') ?: '';
    $maskedUser = $user ? substr($user, 0, 4) . str_repeat('*', max(strlen($user)-8, 0)) . substr($user, -4) : '(no definido)';

    echo "<hr>";
    echo "<h3>Configuración SMTP (parcial)</h3>";
    echo "<ul>";
    echo "<li>Host: " . htmlspecialchars($host) . "</li>";
    echo "<li>Port: " . htmlspecialchars($port) . "</li>";
    echo "<li>Username: " . htmlspecialchars($maskedUser) . "</li>";
    echo "</ul>";

    echo "<p>Si este HTML muestra 'Los 6 Pasos...' y el footer con 'Ref: EmailService_v2_NEW', entonces este servidor tiene la versión correcta.</p>";
    echo "<p>Si el correo real que recibes no trae el asunto '[CF v2]' ni el footer de referencia, el envío proviene de otra instancia/automatización.</p>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error en probe: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
