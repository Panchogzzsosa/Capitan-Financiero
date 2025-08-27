<?php
/**
 * CONFIGURACIÓN DE WHATSAPP BUSINESS API
 * 
 * PASO 1: Ve a developers.facebook.com
 * PASO 2: Crea una app de WhatsApp Business
 * PASO 3: Obtén estos valores y reemplázalos
 */

// 🔑 CONFIGURACIÓN REQUERIDA
const WHATSAPP_CONFIG = [
    // Token de acceso (desde Facebook Developers)
    'access_token' => 'EAAxxxxxxxxxxxxxxxxxxxxxxx', // CAMBIAR
    
    // ID del número de teléfono (desde WhatsApp Business Manager)
    'phone_number_id' => '1234567890123456', // CAMBIAR
    
    // Número de teléfono verificado (formato: 521234567890)
    'phone_number' => '521234567890', // CAMBIAR
    
    // Webhook verify token (para recibir respuestas)
    'webhook_verify_token' => 'mi_token_secreto_123' // CAMBIAR
];

/**
 * EJEMPLO DE MENSAJE QUE SE ENVÍA:
 * 
 * 🎉 ¡Hola Francisco Gonzalez!
 * 
 * ¡Gracias por adquirir Curso Básico de Finanzas! 📚
 * 
 * Te invitamos a unirte a nuestro grupo exclusivo de WhatsApp donde podrás:
 * ✅ Recibir contenido adicional
 * ✅ Hacer preguntas directamente
 * ✅ Conectar con otros estudiantes
 * 
 * 👥 Únete aquí: https://chat.whatsapp.com/ejemplo123
 * 
 * ¡Nos vemos en el grupo! 🚀
 * 
 * _Equipo Capitán Financiero_
 */

/**
 * FORMATOS DE TELÉFONO SOPORTADOS:
 * - +52 81 1234 5678 → 5281123456789
 * - 81 1234 5678 → 5281123456789  
 * - 8112345678 → 5281123456789
 * - 521234567890 → 521234567890 (ya correcto)
 */
?>
