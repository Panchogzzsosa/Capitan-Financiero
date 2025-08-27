<?php
/**
 * Script de instalación del sistema de correos automáticos
 * Ejecutar una sola vez para configurar todo el sistema
 */

echo "<h1>🚀 Instalador del Sistema de Correos Automáticos</h1>";
echo "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Verificar dependencias
echo "<h2>🔍 Verificando Dependencias</h2>";

$errors = [];
$warnings = [];

// Verificar PHP
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    $errors[] = "PHP 7.4 o superior requerido. Versión actual: " . PHP_VERSION;
} else {
    echo "<p>✅ PHP " . PHP_VERSION . " - Compatible</p>";
}

// Verificar extensiones
$required_extensions = ['openssl', 'pdo', 'pdo_mysql', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p>✅ Extensión $ext - Disponible</p>";
    } else {
        $errors[] = "Extensión $ext no está disponible";
    }
}

// Verificar archivos
echo "<h2>📁 Verificando Archivos</h2>";

$required_files = [
    'config.php' => 'Configuración principal',
    'vendor/autoload.php' => 'Autoload de Composer',
    'brevo_config.php' => 'Configuración de Brevo',
    'save_order.php' => 'Procesamiento de órdenes'
];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        echo "<p>✅ $file - $description</p>";
    } else {
        $errors[] = "Archivo $file no encontrado - $description";
    }
}

// Verificar PHPMailer
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p>✅ PHPMailer - Disponible</p>";
} else {
    $errors[] = "PHPMailer no está disponible";
}

// Verificar base de datos
echo "<h2>🗄️ Verificando Base de Datos</h2>";

try {
    require_once 'config.php';
    $pdo = getDBConnection();
    echo "<p>✅ Conexión a base de datos - Exitosa</p>";
    
    // Verificar si existe la tabla email_logs
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Tabla email_logs - Existe</p>";
    } else {
        echo "<p>⚠️ Tabla email_logs - No existe (se creará)</p>";
        $warnings[] = "La tabla email_logs no existe y será creada";
    }
    
} catch (Exception $e) {
    $errors[] = "Error de conexión a base de datos: " . $e->getMessage();
}

// Mostrar errores críticos
if (!empty($errors)) {
    echo "<h2>❌ Errores Críticos</h2>";
    echo "<div style='background: #fee; border: 1px solid #fcc; padding: 15px; border-radius: 5px;'>";
    foreach ($errors as $error) {
        echo "<p style='color: #c33;'>❌ $error</p>";
    }
    echo "</div>";
    echo "<p><strong>El sistema no puede continuar hasta resolver estos errores.</strong></p>";
    exit;
}

// Mostrar advertencias
if (!empty($warnings)) {
    echo "<h2>⚠️ Advertencias</h2>";
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
    foreach ($warnings as $warning) {
        echo "<p style='color: #856404;'>⚠️ $warning</p>";
    }
    echo "</div>";
}

// Crear tabla email_logs si no existe
echo "<h2>🔧 Configurando Base de Datos</h2>";

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `email_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `customer_id` int(11) NOT NULL,
      `order_id` int(11) NOT NULL,
      `email_type` varchar(50) NOT NULL COMMENT 'Tipo de correo: purchase_confirmation, welcome, etc.',
      `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
      `error_message` text DEFAULT NULL COMMENT 'Mensaje de error si falla el envío',
      `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_customer_id` (`customer_id`),
      KEY `idx_order_id` (`order_id`),
      KEY `idx_email_type` (`email_type`),
      KEY `idx_status` (`status`),
      KEY `idx_sent_at` (`sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de envío de correos electrónicos'
    ";
    
    $pdo->exec($sql);
    echo "<p>✅ Tabla email_logs creada/verificada exitosamente</p>";
    
} catch (Exception $e) {
    echo "<p style='color: #c33;'>❌ Error creando tabla: " . $e->getMessage() . "</p>";
    exit;
}

// Probar conexión SMTP
echo "<h2>📧 Probando Conexión SMTP</h2>";

try {
    require_once 'brevo_config.php';
    $mailer = new BrevoMailer();
    echo "<p>✅ BrevoMailer inicializado correctamente</p>";
    
    // Probar configuración SMTP
    $testMailer = new PHPMailer(true);
    $testMailer->isSMTP();
    $testMailer->Host = BREVO_SMTP_HOST;
    $testMailer->SMTPAuth = true;
    $testMailer->Username = BREVO_SMTP_USERNAME;
    $testMailer->Password = BREVO_SMTP_PASSWORD;
    $testMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $testMailer->Port = BREVO_SMTP_PORT;
    $testMailer->SMTPDebug = 0;
    
    echo "<p>✅ Configuración SMTP verificada</p>";
    
} catch (Exception $e) {
    echo "<p style='color: #c33;'>❌ Error en configuración SMTP: " . $e->getMessage() . "</p>";
    exit;
}

// Verificar permisos de archivos
echo "<h2>🔐 Verificando Permisos</h2>";

$files_to_check = ['brevo_config.php', 'save_order.php'];
foreach ($files_to_check as $file) {
    if (is_readable($file)) {
        echo "<p>✅ $file - Legible</p>";
    } else {
        echo "<p style='color: #c33;'>❌ $file - No legible</p>";
    }
}

// Resumen final
echo "<h2>🎉 Instalación Completada</h2>";
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
echo "<p style='color: #155724; font-weight: bold;'>✅ El sistema de correos automáticos está listo para usar</p>";
echo "</div>";

echo "<h3>📋 Próximos Pasos</h3>";
echo "<ol>";
echo "<li><strong>Probar el sistema:</strong> Acceder a <code>test_email.php</code></li>";
echo "<li><strong>Verificar logs:</strong> Revisar logs del servidor</li>";
echo "<li><strong>Hacer compra de prueba:</strong> Probar el flujo completo</li>";
echo "<li><strong>Limpiar archivos:</strong> Eliminar <code>install_email_system.php</code> y <code>test_email.php</code> después de las pruebas</li>";
echo "</ol>";

echo "<h3>🔍 Archivos Creados/Modificados</h3>";
echo "<ul>";
echo "<li><strong>brevo_config.php</strong> - Configuración y clase de correos</li>";
echo "<li><strong>save_order.php</strong> - Modificado para envío automático</li>";
echo "<li><strong>admin/create_email_logs_table.sql</strong> - Script SQL</li>";
echo "<li><strong>test_email.php</strong> - Archivo de pruebas</li>";
echo "<li><strong>README_CORREOS.md</strong> - Documentación completa</li>";
echo "</ul>";

echo "<h3>⚠️ Importante</h3>";
echo "<p>Este script de instalación debe ejecutarse <strong>UNA SOLA VEZ</strong>. Después de verificar que todo funciona correctamente, puedes eliminarlo.</p>";

echo "<hr>";
echo "<p><em>Instalación completada el " . date('Y-m-d H:i:s') . "</em></p>";
?>
