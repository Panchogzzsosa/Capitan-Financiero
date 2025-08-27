<?php
session_start();
require_once('../config.php');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get domain from config or use localhost
$domain = 'http://localhost/capitanfinanciero/';

// Define UTM parameters for different platforms
$utm_links = [
    'instagram' => [
        'stories' => [
            'utm_source' => 'instagram',
            'utm_medium' => 'stories',
            'utm_campaign' => 'curso_finanzas'
        ],
        'posts' => [
            'utm_source' => 'instagram',
            'utm_medium' => 'posts',
            'utm_campaign' => 'curso_finanzas'
        ],
        'reels' => [
            'utm_source' => 'instagram',
            'utm_medium' => 'reels',
            'utm_campaign' => 'curso_finanzas'
        ],
        'bio' => [
            'utm_source' => 'instagram',
            'utm_medium' => 'bio',
            'utm_campaign' => 'curso_finanzas'
        ]
    ],
    'facebook' => [
        'posts' => [
            'utm_source' => 'facebook',
            'utm_medium' => 'posts',
            'utm_campaign' => 'curso_finanzas'
        ],
        'stories' => [
            'utm_source' => 'facebook',
            'utm_medium' => 'stories',
            'utm_campaign' => 'curso_finanzas'
        ],
        'ads' => [
            'utm_source' => 'facebook',
            'utm_medium' => 'ads',
            'utm_campaign' => 'curso_finanzas'
        ],
        'groups' => [
            'utm_source' => 'facebook',
            'utm_medium' => 'groups',
            'utm_campaign' => 'curso_finanzas'
        ]
    ],
    'email' => [
        'newsletter' => [
            'utm_source' => 'email',
            'utm_medium' => 'newsletter',
            'utm_campaign' => 'curso_finanzas'
        ],
        'promotional' => [
            'utm_source' => 'email',
            'utm_medium' => 'promotional',
            'utm_campaign' => 'curso_finanzas'
        ],
        'welcome' => [
            'utm_source' => 'email',
            'utm_medium' => 'welcome',
            'utm_campaign' => 'curso_finanzas'
        ]
    ],
    'whatsapp' => [
        'manychat' => [
            'utm_source' => 'manychat',
            'utm_medium' => 'whatsapp',
            'utm_campaign' => 'curso_finanzas'
        ],
        'direct' => [
            'utm_source' => 'whatsapp',
            'utm_medium' => 'direct',
            'utm_campaign' => 'curso_finanzas'
        ]
    ],
    'manychat' => [
        'instagram' => [
            'utm_source' => 'manychat',
            'utm_medium' => 'instagram',
            'utm_campaign' => 'curso_finanzas'
        ],
        'facebook' => [
            'utm_source' => 'manychat',
            'utm_medium' => 'facebook',
            'utm_campaign' => 'curso_finanzas'
        ],
        'whatsapp' => [
            'utm_source' => 'manychat',
            'utm_medium' => 'whatsapp',
            'utm_campaign' => 'curso_finanzas'
        ],
    ]
];

// Function to build UTM URL
function buildUtmUrl($domain, $params) {
    $url = $domain . '/index.html?';
    $utm_parts = [];
    foreach ($params as $key => $value) {
        $utm_parts[] = $key . '=' . urlencode($value);
    }
    return $url . implode('&', $utm_parts);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../Img/Logo.png" type="image/x-icon">
    <title>Enlaces UTM - Capitán Financiero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .admin-header {
            background: linear-gradient(135deg, #222F58 0%, #667eea 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 3rem;
        }

        .back-btn {
            position: absolute;
            top: 2rem;
            left: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
        }

        .platform-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .platform-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .instagram-icon { color: #E4405F; }
        .facebook-icon { color: #1877F2; }
        .email-icon { color: #EA4335; }
        .whatsapp-icon { color: #25D366; }
        .manychat-icon { color: #00D4AA; }

        .utm-link {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            word-break: break-all;
            position: relative;
        }

        .copy-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: #667eea;
            border: none;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: #222F58;
        }

        .copy-btn.copied {
            background: #28a745;
        }

        .platform-title {
            color: #222F58;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .link-type {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.5rem;
            text-transform: capitalize;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container position-relative">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
            <div class="text-center">
                <h1><i class="fas fa-link"></i> Enlaces UTM</h1>
                <p>Enlaces personalizados para redes sociales y email</p>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Instagram -->
            <div class="col-lg-6">
                <div class="platform-card">
                    <div class="text-center">
                        <div class="platform-icon instagram-icon">
                            <i class="fab fa-instagram"></i>
                        </div>
                        <h3 class="platform-title">Instagram</h3>
                    </div>
                    
                    <?php foreach ($utm_links['instagram'] as $type => $params): ?>
                        <div class="mb-3">
                            <div class="link-type"><?php echo ucfirst($type); ?></div>
                            <div class="utm-link">
                                <?php echo buildUtmUrl($domain, $params); ?>
                                <button class="copy-btn" onclick="copyToClipboard(this, '<?php echo buildUtmUrl($domain, $params); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Facebook -->
            <div class="col-lg-6">
                <div class="platform-card">
                    <div class="text-center">
                        <div class="platform-icon facebook-icon">
                            <i class="fab fa-facebook"></i>
                        </div>
                        <h3 class="platform-title">Facebook</h3>
                    </div>
                    
                    <?php foreach ($utm_links['facebook'] as $type => $params): ?>
                        <div class="mb-3">
                            <div class="link-type"><?php echo ucfirst($type); ?></div>
                            <div class="utm-link">
                                <?php echo buildUtmUrl($domain, $params); ?>
                                <button class="copy-btn" onclick="copyToClipboard(this, '<?php echo buildUtmUrl($domain, $params); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Email -->
            <div class="col-lg-6">
                <div class="platform-card">
                    <div class="text-center">
                        <div class="platform-icon email-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3 class="platform-title">Email</h3>
                    </div>
                    
                    <?php foreach ($utm_links['email'] as $type => $params): ?>
                        <div class="mb-3">
                            <div class="link-type"><?php echo ucfirst($type); ?></div>
                            <div class="utm-link">
                                <?php echo buildUtmUrl($domain, $params); ?>
                                <button class="copy-btn" onclick="copyToClipboard(this, '<?php echo buildUtmUrl($domain, $params); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- WhatsApp -->
            <div class="col-lg-6">
                <div class="platform-card">
                    <div class="text-center">
                        <div class="platform-icon whatsapp-icon">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <h3 class="platform-title">WhatsApp</h3>
                    </div>
                    
                    <?php foreach ($utm_links['whatsapp'] as $type => $params): ?>
                        <div class="mb-3">
                            <div class="link-type"><?php echo ucfirst($type); ?></div>
                            <div class="utm-link">
                                <?php echo buildUtmUrl($domain, $params); ?>
                                <button class="copy-btn" onclick="copyToClipboard(this, '<?php echo buildUtmUrl($domain, $params); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ManyChat -->
            <div class="col-lg-6">
                <div class="platform-card">
                    <div class="text-center">
                        <div class="platform-icon manychat-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h3 class="platform-title">ManyChat</h3>
                    </div>
                    
                    <?php foreach ($utm_links['manychat'] as $type => $params): ?>
                        <div class="mb-3">
                            <div class="link-type"><?php echo ucfirst(str_replace('_', ' ', $type)); ?></div>
                            <div class="utm-link">
                                <?php echo buildUtmUrl($domain, $params); ?>
                                <button class="copy-btn" onclick="copyToClipboard(this, '<?php echo buildUtmUrl($domain, $params); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(button, text) {
            navigator.clipboard.writeText(text).then(function() {
                // Change button appearance
                const icon = button.querySelector('i');
                icon.className = 'fas fa-check';
                button.classList.add('copied');
                button.innerHTML = '<i class="fas fa-check"></i>';
                
                // Reset after 2 seconds
                setTimeout(function() {
                    button.classList.remove('copied');
                    button.innerHTML = '<i class="fas fa-copy"></i>';
                }, 2000);
                
                // Show success message
                showToast('Enlace copiado al portapapeles');
            }).catch(function(err) {
                console.error('Error al copiar: ', err);
                showToast('Error al copiar el enlace');
            });
        }

        function showToast(message) {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = 'position-fixed';
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header">
                        <strong class="me-auto">Notificación</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Remove after 3 seconds
            setTimeout(function() {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html>
