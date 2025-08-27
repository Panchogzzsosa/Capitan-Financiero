<?php
/**
 * EJECUTOR DE AUTOMATIZACIÓN DE WHATSAPP
 * 
 * Puedes ejecutar este archivo de 3 formas diferentes:
 */

require_once 'whatsapp_automation.php';

// Crear instancia del automatizador
$automation = new WhatsAppAutomation();

echo "<h1>🤖 Automatización WhatsApp - Capitán Financiero</h1>";

// Mostrar estadísticas actuales
echo "<h2>📊 Estadísticas Actuales:</h2>";
$stats = $automation->getStats();
echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr><th>Estado</th><th>Cantidad</th></tr>";
foreach ($stats as $stat) {
    echo "<tr><td>{$stat['status']}</td><td>{$stat['count']}</td></tr>";
}
echo "</table>";

// Procesar usuarios pendientes
echo "<h2>🔄 Procesando usuarios pendientes...</h2>";
$results = $automation->processPendingUsers();

if (isset($results['error'])) {
    echo "<p style='color: red;'>❌ Error: {$results['error']}</p>";
} else {
    echo "<h3>✅ Resultados del procesamiento:</h3>";
    echo "<ul>";
    
    foreach ($results as $result) {
        if ($result['success']) {
            echo "<li style='color: green;'>✅ {$result['customer']} ({$result['phone']}) agregado a {$result['group']}</li>";
        } else {
            echo "<li style='color: red;'>❌ {$result['customer']} ({$result['phone']}) - Error: {$result['error']}</li>";
        }
    }
    
    echo "</ul>";
    echo "<p><strong>Total procesados: " . count($results) . "</strong></p>";
}

// Mostrar estadísticas finales
echo "<h2>📊 Estadísticas Finales:</h2>";
$final_stats = $automation->getStats();
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Estado</th><th>Cantidad</th></tr>";
foreach ($final_stats as $stat) {
    echo "<tr><td>{$stat['status']}</td><td>{$stat['count']}</td></tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>🚀 Formas de Ejecutar la Automatización:</h2>";
echo "<ol>";
echo "<li><strong>Manual:</strong> Visita este archivo en el navegador</li>";
echo "<li><strong>CRON Job:</strong> Cada 5 minutos automáticamente</li>";
echo "<li><strong>Webhook:</strong> Inmediatamente después de cada compra</li>";
echo "</ol>";

echo "<h3>📝 Configuración de CRON Job:</h3>";
echo "<code>*/5 * * * * php /ruta/completa/whatsapp_automation.php</code>";

echo "<h3>📝 URL para Webhook:</h3>";
echo "<code>" . $_SERVER['HTTP_HOST'] . "/capitanfinanciero/whatsapp_automation.php</code>";
?>
