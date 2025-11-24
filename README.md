# 🚀 Capitán Financiero - Plataforma de Educación Financiera

## 📋 Descripción General

**Capitán Financiero** es una plataforma web completa de educación financiera que ofrece cursos, coaching y recursos para el desarrollo de habilidades financieras. El proyecto incluye un sistema de e-commerce completo, panel de administración, automatización de WhatsApp y gestión de leads.

## ✨ Características Principales

### 🎓 **Educación Financiera**
- Cursos interactivos con módulos descargables
- Guías de inversión y finanzas personales
- Coaching personalizado 1:1
- Comunidad exclusiva de WhatsApp
- Certificados digitales

### 🛒 **Sistema E-commerce Completo**
- Catálogo de productos con diseño responsive
- Carrito de compras deslizable
- Checkout seguro con Stripe
- Procesamiento automático de órdenes
- Emails de confirmación personalizados
- Panel de administración

### 🤖 **Automatización Inteligente**
- Integración automática con WhatsApp
- Seguimiento de leads y conversiones
- Sistema de newsletter automatizado
- Generación de códigos QR
- Tracking de visitas y UTM

### 📱 **Experiencia de Usuario**
- Diseño mobile-first y responsive
- Navegación intuitiva y accesible
- Animaciones suaves y transiciones
- Notificaciones toast en tiempo real
- Estados de carga optimizados

## 🏗️ Arquitectura del Sistema

### **Frontend**
- HTML5 semántico y accesible
- CSS3 con variables personalizables
- JavaScript ES6+ modular
- Animaciones CSS y JS
- Diseño responsive con breakpoints

### **Backend**
- PHP 8.0+ con PDO
- Base de datos MySQL/MariaDB
- API REST para procesamiento
- Seguridad con prepared statements
- Transacciones SQL para integridad

### **Integraciones**
- **Stripe**: Procesamiento de pagos
- **WhatsApp Business API**: Automatización
- **PHPMailer**: Emails transaccionales
- **QR Code Generator**: Códigos de acceso

## 📁 Estructura del Proyecto

```
capitanfinanciero/
├── 📄 Archivos Principales
│   ├── index.html              # Página principal
│   ├── checkout.html           # Página de checkout
│   ├── success.html            # Página de éxito
│   ├── aviso-privacidad.html  # Política de privacidad
│   └── qr-cart.html           # Generador de QR
│
├── 🎨 Estilos y Diseño
│   ├── styles.css              # Estilos principales
│   ├── hero_fix.css            # Estilos del hero
│   ├── popup.css               # Estilos de popups
│   └── scroll-animations.css   # Animaciones de scroll
│
├── ⚡ JavaScript
│   ├── script.js               # Funcionalidad principal
│   ├── checkout.js             # Lógica de checkout
│   └── scroll-animations.js    # Animaciones
│
├── 🔧 Backend PHP
│   ├── config.php              # Configuración general
│   ├── process_order.php       # Procesamiento de órdenes
│   ├── create_payment_intent.php # Creación de pagos
│   ├── submit_form.php         # Formularios
│   ├── subscribe.php           # Newsletter
│   └── track_visit.php         # Tracking de visitas
│
├── 🤖 Automatización WhatsApp
│   ├── whatsapp_automation.php # Automatización principal
│   ├── whatsapp_auto_add.php   # Agregar contactos
│   ├── run_whatsapp_automation.php # Ejecutor
│   └── whatsapp_config_example.php # Configuración
│
├── 👨‍💼 Panel de Administración
│   ├── dashboard.php           # Dashboard principal
│   ├── login.php               # Autenticación
│   ├── logout.php              # Cerrar sesión
│   ├── delete.php              # Eliminar registros
│   └── generate_qr.php         # Generar códigos QR
│
├── 🗄️ Base de Datos
│   ├── capitan_financiero.sql  # Estructura principal
│   └── order_details.sql       # Tablas de órdenes
│
├── 🖼️ Recursos Multimedia
│   ├── Img/                    # Imágenes del sitio
│   └── Mont/                   # Fuentes tipográficas
│
└── 📦 Dependencias
    └── vendor/                 # Composer packages
        ├── stripe/stripe-php    # SDK de Stripe
        ├── endroid/qr-code     # Generador de QR
        └── phpmailer/phpmailer # Envío de emails
```

## 🚀 Instalación y Configuración

### **Requisitos del Sistema**
- PHP 8.0 o superior
- MySQL 5.7+ o MariaDB 10.2+
- Composer para dependencias
- Servidor web (Apache/Nginx)
- SSL para Stripe (recomendado)

### **1. Preparación del Entorno**
```bash
# Clonar el repositorio
git clone [URL_DEL_REPOSITORIO]
cd capitanfinanciero

# Instalar dependencias
composer install
```

### **2. Configuración de Base de Datos**
```sql
-- Crear base de datos
CREATE DATABASE capitan_financiero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Importar estructura
mysql -u root -p capitan_financiero < DataBase/capitan_financiero.sql
mysql -u root -p capitan_financiero < DataBase/order_details.sql
```

### **3. Configuración de Archivos**
```php
// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'capitan_financiero');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');

// Claves de Stripe (obtener desde dashboard.stripe.com)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...'); // Clave pública TEST
define('STRIPE_SECRET_KEY', 'sk_test_...'); // Clave secreta TEST
```

### **4. Configuración de WhatsApp**
```php
// whatsapp_config.php
define('WHATSAPP_API_KEY', 'tu_api_key');
define('WHATSAPP_PHONE_ID', 'tu_phone_id');
define('WHATSAPP_BUSINESS_ID', 'tu_business_id');
```

### **5. Configuración del Servidor**
```apache
# .htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Headers de seguridad
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

## 💳 Configuración de Pagos

### **Stripe (Recomendado)**
1. Crear cuenta en [stripe.com](https://stripe.com)
2. Obtener claves de API desde el dashboard
3. Configurar webhooks para eventos de pago
4. Probar con tarjetas de prueba

### **Productos Disponibles**
| Producto | Precio | Descripción |
|----------|--------|-------------|
| **Curso Básico de Finanzas** | $1,999 MXN | 8 módulos + material + certificado |
| **Guía de Inversión** | $899 MXN | 200+ páginas + casos prácticos |
| **Kit Premium** | $3,999 MXN | Todo incluido + consultoría 1:1 |

## 🔐 Seguridad y Privacidad

### **Medidas Implementadas**
- ✅ Validación de formularios en frontend y backend
- ✅ Sanitización de datos con PDO
- ✅ Transacciones SQL para integridad
- ✅ Headers de seguridad HTTP
- ✅ Protección CSRF
- ✅ Rate limiting en formularios
- ✅ Logs de auditoría

### **Cumplimiento**
- ✅ Política de privacidad
- ✅ Aviso legal
- ✅ Cookies consent
- ✅ RGPD/GDPR ready

## 📊 Panel de Administración

### **Funcionalidades**
- 📈 Dashboard con métricas en tiempo real
- 👥 Gestión de usuarios y leads
- 📦 Administración de productos
- 💰 Reportes de ventas
- 📧 Gestión de emails
- 🔍 Logs de sistema

### **Acceso**
```
URL: /admin/
Usuario: admin
Contraseña: password (cambiar en producción)
```

## 🤖 Automatización WhatsApp

### **Funcionalidades**
- 📱 Agregar contactos automáticamente
- 💬 Mensajes de bienvenida personalizados
- 📊 Seguimiento de conversiones
- 🎯 Segmentación de audiencia
- 📈 Reportes de engagement

### **Configuración**
1. Crear app en [developers.facebook.com](https://developers.facebook.com)
2. Configurar WhatsApp Business API
3. Obtener tokens de acceso
4. Configurar webhooks

## 📈 Analytics y Tracking

### **Métricas Implementadas**
- 📊 Visitas y páginas vistas
- 🎯 Conversiones por fuente
- 📱 Dispositivos y navegadores
- 🌍 Ubicación geográfica
- ⏱️ Tiempo en sitio
- 🔄 Tasa de rebote

### **Integraciones**
- Google Analytics 4
- Facebook Pixel
- UTM tracking
- Eventos personalizados

## 🎨 Personalización

### **Colores Corporativos**
```css
:root {
  --primary-color: #222F58;      /* Azul principal */
  --secondary-color: #EAEAEA;    /* Gris claro */
  --accent-color: #28a745;       /* Verde éxito */
  --warning-color: #ffc107;      /* Amarillo */
  --danger-color: #dc3545;       /* Rojo */
  --text-color: #333;            /* Texto principal */
  --light-text: #666;            /* Texto secundario */
}
```

### **Fuentes**
- **Principal**: Montserrat (Google Fonts)
- **Secundaria**: Arial, sans-serif
- **Tamaños**: 14px base, escalado responsive

### **Breakpoints Responsive**
```css
/* Mobile First */
@media (min-width: 768px) { /* Tablet */ }
@media (min-width: 1024px) { /* Desktop */ }
@media (min-width: 1440px) { /* Large Desktop */ }
```

## 🚀 Despliegue

### **Entorno de Desarrollo**
```bash
# Servidor local con PHP
php -S localhost:8000

# O con XAMPP/WAMP
# Colocar en htdocs/www
```

### **Producción**
1. **Servidor**: Apache/Nginx con PHP 8.0+
2. **Base de datos**: MySQL 8.0+ optimizado
3. **SSL**: Certificado válido (requerido para Stripe)
4. **CDN**: Para imágenes y recursos estáticos
5. **Backup**: Automático de base de datos

### **Optimizaciones**
- ✅ Compresión GZIP
- ✅ Cache de navegador
- ✅ Minificación CSS/JS
- ✅ Optimización de imágenes
- ✅ Lazy loading
- ✅ Service Worker (PWA)

## 🧪 Testing

### **Pruebas Recomendadas**
- ✅ Funcionalidad del carrito
- ✅ Proceso de checkout
- ✅ Procesamiento de pagos
- ✅ Envío de emails
- ✅ Responsive design
- ✅ Performance en móviles
- ✅ Seguridad de formularios

### **Herramientas de Testing**
- Browser DevTools
- Google PageSpeed Insights
- GTmetrix
- WebPageTest
- Lighthouse

## 📚 Documentación Adicional

### **Archivos de Configuración**
- `config.php` - Configuración general
- `stripe_config.php` - Configuración de Stripe
- `brevo_config.php` - Configuración de email
- `whatsapp_config_example.php` - Configuración WhatsApp

### **Scripts de Utilidad**
- `install_email_system.php` - Instalación del sistema de emails
- `fix_database_utm_tracking.sql` - Correcciones de base de datos
- `run_whatsapp_automation.php` - Ejecutor de automatización

## 🆘 Soporte y Mantenimiento

### **Contacto Técnico**
- 📧 **Email**: contacto@capitanfinanciero.com
- 📱 **WhatsApp**: +52 1 811 240 0075
- 🌐 **Sitio**: [capitanfinanciero.com](https://capitanfinanciero.com)

### **Mantenimiento Recomendado**
- 🔄 **Diario**: Revisar logs de errores
- 📊 **Semanal**: Análisis de métricas
- 🔒 **Mensual**: Actualizaciones de seguridad
- 📈 **Trimestral**: Revisión de performance
- 🎯 **Anual**: Auditoría completa del sistema

## 📄 Licencia y Créditos

### **Licencia**
© 2025 **Capitán Financiero**. Todos los derechos reservados.

### **Tecnologías Utilizadas**
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **Backend**: PHP 8.0+, MySQL 8.0+
- **Pagos**: Stripe API
- **Email**: PHPMailer + SMTP
- **QR**: Endroid QR Code Generator
- **Automatización**: WhatsApp Business API

### **Créditos**
- **Desarrollo**: Equipo de Capitán Financiero
- **Diseño**: UX/UI optimizado para conversión
- **Arquitectura**: Patrón MVC simplificado
- **Seguridad**: Mejores prácticas OWASP

---

## 🌟 Características Destacadas

> **"Transformando vidas a través de la educación financiera"**

- 🎓 **Educación de Calidad**: Contenido validado por expertos
- 💰 **Inversión Inteligente**: Herramientas prácticas y reales
- 🤝 **Comunidad Activa**: Soporte continuo y networking
- 📱 **Tecnología Moderna**: Plataforma actualizada y segura
- 🚀 **Crecimiento Constante**: Mejoras continuas del sistema

---

**¿Listo para transformar tu futuro financiero?** 🚀💰

*Este proyecto representa la vanguardia en plataformas de educación financiera, combinando tecnología moderna con contenido de calidad para crear una experiencia de aprendizaje excepcional.*
