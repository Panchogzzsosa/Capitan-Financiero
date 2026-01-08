<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;

    public function __construct() {
        $this->host = defined('SMTP_HOST') ? constant('SMTP_HOST') : (getenv('BREVO_SMTP_HOST') ?: 'smtp-relay.brevo.com');
        $this->port = defined('SMTP_PORT') ? (int)constant('SMTP_PORT') : (int)(getenv('BREVO_SMTP_PORT') ?: 587);
        $this->username = defined('SMTP_USERNAME') ? constant('SMTP_USERNAME') : (getenv('BREVO_SMTP_USERNAME') ?: '');
        $this->password = defined('SMTP_PASSWORD') ? constant('SMTP_PASSWORD') : (getenv('BREVO_SMTP_PASSWORD') ?: '');
        $this->fromEmail = defined('SMTP_FROM_EMAIL') ? constant('SMTP_FROM_EMAIL') : (getenv('MAIL_FROM_EMAIL') ?: 'no-reply@capitanfinanciero.com');
        $this->fromName = defined('SMTP_FROM_NAME') ? constant('SMTP_FROM_NAME') : (getenv('MAIL_FROM_NAME') ?: 'Capitán Financiero');
    }

    public function sendPurchaseConfirmation(array $customerData, array $orderData): bool {
        $mailer = new PHPMailer(true);
        try {
            if (!$this->username || !$this->password) {
                error_log("EmailService Error: Credenciales SMTP no encontradas (Username/Password vacíos).");
                return false;
            }
            // Configuración del servidor SMTP
            $mailer->isSMTP();
            $mailer->Host = $this->host;
            $mailer->SMTPAuth = true;
            $mailer->Username = $this->username;
            $mailer->Password = $this->password;
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = $this->port;
            $mailer->CharSet = 'UTF-8';

            // Destinatarios
            $mailer->setFrom($this->fromEmail, $this->fromName);
            $mailer->addAddress($customerData['email'], $customerData['name'] ?? '');

            // Contenido
            $mailer->isHTML(true);
            $mailer->Subject = 'Confirmación de compra — Capitán Financiero';
            $mailer->addCustomHeader('X-CF-Email-Design', 'v2');

            $html = $this->getHtmlContent($customerData, $orderData);

            $text = $this->getTextContent($customerData, $orderData);

            $mailer->Body = $html;
            $mailer->AltBody = $text;

            $mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("EmailService Error: " . $mailer->ErrorInfo);
            return false;
        }
    }

    private function getHtmlContent(array $customerData, array $orderData): string {
        $name = htmlspecialchars($customerData['name'] ?? 'Capitán', ENT_QUOTES, 'UTF-8');
        $orderNumber = htmlspecialchars($orderData['order_number'] ?? '', ENT_QUOTES, 'UTF-8');
        $totalAmount = htmlspecialchars((string)($orderData['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8');
        $productName = htmlspecialchars($orderData['product_name'] ?? 'Los 6 Pasos para tu Independencia Financiera', ENT_QUOTES, 'UTF-8');
        
        return '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmación de compra</title>
            <style>
                body{font-family:Arial,sans-serif;line-height:1.6;color:#333;background:#f5f5f5;margin:0;padding:0}
                .container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
                .header{background:#222F58;color:#fff;padding:30px 20px;text-align:center}
                .header h1{margin:0;font-size:24px}
                .content{padding:30px 20px}
                .section{margin-bottom:25px;padding-bottom:25px;border-bottom:1px solid #eee}
                .section:last-child{border-bottom:none}
                .highlight{color:#222F58;font-weight:bold}
                .btn{display:inline-block;padding:12px 24px;background:#0B5FFF;color:#fff !important;text-decoration:none;border-radius:5px;font-weight:bold;margin:10px 0}
                .btn-whatsapp{background:#25D366;color:#fff !important}
                .footer{background:#eee;padding:20px;text-align:center;font-size:12px;color:#666}
                ul{padding-left:20px}
                li{margin-bottom:8px}
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Gracias por tu compra</h1>
                </div>
                <div class="content">
                    <p>Hola <strong>'.$name.'</strong>,</p>
                    <p>¡Felicidades! Has dado el primer paso hacia tu libertad financiera. Hemos recibido tu orden correctamente.</p>
                    
                    <div class="section">
                        <h3>Detalles de tu Orden</h3>
                        <p>Orden: <strong>#'.$orderNumber.'</strong><br>
                        Total: <strong>$'.$totalAmount.'</strong></p>
                    </div>

                    <div class="section">
                        <h3>📚 Tu Programa: '.$productName.'</h3>
                        <p>Prepárate para transformar tus finanzas con este temario:</p>
                        <ol>
                            <li>Lo que nadie te dijo de invertir</li>
                            <li>El precio del éxito</li>
                            <li>El método para eliminar tus deudas</li>
                            <li>La ley de atracción del dinero</li>
                            <li>Dirige tu dinero como un capitán</li>
                            <li>Mi debut como inversionista</li>
                        </ol>
                    </div>

                    <div class="section">
                        <h3>🗓 Calendario de Sesiones</h3>
                        <p>Todas las sesiones son a las <strong>7:00 PM (Hora Centro de México)</strong>:</p>
                        <ul>
                            <li>Martes 13 Ene: Lo que nadie te dijo de invertir 🤫</li>
                            <li>Jueves 16 Ene: El precio del éxito 🏆</li>
                            <li>Martes 20 Ene: El método para eliminar tus deudas ✂️</li>
                            <li>Miércoles 21 Ene: Bono especial - Renunciar para emprender 🚀</li>
                            <li>Jueves 22 Ene: La ley de atracción del dinero 🧲</li>
                            <li>Martes 27 Ene: Dirige tu dinero como un capitán 🧭</li>
                            <li>Jueves 29 Ene: Mi debut como inversionista 🌱</li>
                        </ul>
                    </div>

                    <div class="section">
                        <h3>🚀 Accesos Directos</h3>
                        <p>Guarda estos enlaces para acceder a tus sesiones:</p>
                        <center>
                            <a href="https://us02web.zoom.us/j/5710327674?pwd=aBW3Yje8SyHSxvXHYHD3kxUWnBmf3O.1" class="btn">💻 Entrar al ZOOM</a>
                            <br>
                            <a href="https://chat.whatsapp.com/K1HxI15YqbbJz8yb9uKEKe" class="btn btn-whatsapp">📱 Unirme al Grupo WhatsApp</a>
                        </center>
                    </div>
                </div>
                <div class="footer">
                    <p>Capitán Financiero &copy; '.date('Y').'</p>
                    <p>Si tienes alguna duda, responde a este correo.</p>
                    <p><small>Ref: EmailService_v2_NEW | Generated: '.date('Y-m-d H:i:s').'</small></p>
                </div>
            </div>
        </body>
        </html>
        ';
    }

    private function getTextContent(array $customerData, array $orderData): string {
        return "Hola " . ($customerData['name'] ?? 'Capitán') . ",\n\n" .
               "Gracias por tu compra. Aquí tienes los detalles:\n\n" .
               "Orden: #" . ($orderData['order_number'] ?? '') . "\n" .
               "Total: $" . ($orderData['total_amount'] ?? '') . "\n\n" .
               "ACCESOS DIRECTOS:\n" .
               "Zoom: https://us02web.zoom.us/j/5710327674?pwd=aBW3Yje8SyHSxvXHYHD3kxUWnBmf3O.1\n" .
               "WhatsApp: https://chat.whatsapp.com/K1HxI15YqbbJz8yb9uKEKe\n\n" .
               "Calendario (7:00 PM CDMX):\n" .
               "- 13 Ene: Lo que nadie te dijo de invertir\n" .
               "- 16 Ene: El precio del éxito\n" .
               "- 20 Ene: Eliminar deudas\n" .
               "- 21 Ene: Bono Emprender\n" .
               "- 22 Ene: Ley de atracción\n" .
               "- 27 Ene: Dirige tu dinero\n" .
               "- 29 Ene: Mi debut inversionista\n\n" .
               "Capitán Financiero";
    }
}
?>
