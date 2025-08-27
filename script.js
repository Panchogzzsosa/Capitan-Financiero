document.addEventListener('DOMContentLoaded', function() {
    // Variables globales para el carrito
    let cart = [];
    let cartTotal = 0;

    // Función para agregar producto automáticamente desde URL
    function addProductFromURL() {
        console.log('Verificando parámetros de URL...');
        const urlParams = new URLSearchParams(window.location.search);
        const addToCart = urlParams.get('add_to_cart');
        const productId = urlParams.get('product_id');
        const productName = urlParams.get('product_name');
        const productPrice = urlParams.get('product_price');

        console.log('Parámetros encontrados:', { addToCart, productId, productName, productPrice });

        if (addToCart === '1' && productId && productName && productPrice) {
            console.log('Agregando producto al carrito...');
            
            // Decodificar el nombre del producto
            const decodedName = decodeURIComponent(productName);
            console.log('Nombre decodificado:', decodedName);
            
            // Agregar al carrito
            const product = {
                id: parseInt(productId),
                name: decodedName,
                price: parseInt(productPrice),
                quantity: 1
            };
            
            console.log('Producto a agregar:', product);
            
            cart.push(product);
            updateCart();
            
            // Mostrar mensaje de confirmación
            showToast(`Producto "${decodedName}" agregado al carrito`, 'success');
            
            // También mostrar un mensaje en la consola para debug
            console.log(`✅ Producto "${decodedName}" agregado exitosamente al carrito`);
            console.log(`📊 Estado del carrito: ${cart.length} productos, Total: $${cartTotal} MXN`);
            
            // Limpiar la URL
            window.history.replaceState({}, document.title, window.location.pathname);
            
            // Abrir el carrito automáticamente
            setTimeout(() => {
                if (cartOverlay) {
                    console.log('Abriendo carrito automáticamente...');
                    cartOverlay.style.display = 'flex';
                } else {
                    console.log('Error: cartOverlay no encontrado');
                    // Intentar encontrar el elemento nuevamente
                    const cartOverlayRetry = document.getElementById('cart-overlay');
                    if (cartOverlayRetry) {
                        console.log('Elemento encontrado en segundo intento, abriendo carrito...');
                        cartOverlayRetry.style.display = 'flex';
                    } else {
                        console.log('No se pudo encontrar cart-overlay después de múltiples intentos');
                    }
                }
            }, 1000);
        } else {
            console.log('No se cumplieron las condiciones para agregar producto');
        }
    }

    // Ejecutar la función después de que todos los elementos del carrito estén disponibles
    setTimeout(() => {
        addProductFromURL();
    }, 100);

    // Cookie consent functionality
    const cookieBanner = document.querySelector('.cookie-banner');
    const acceptCookiesBtn = document.getElementById('accept-cookies');

    // Check if user has already accepted cookies
    if (!localStorage.getItem('cookiesAccepted')) {
        setTimeout(() => {
            cookieBanner.classList.add('show');
        }, 1000);
    }

    acceptCookiesBtn.addEventListener('click', () => {
        localStorage.setItem('cookiesAccepted', 'true');
        cookieBanner.classList.remove('show');
    });

    // Navegación móvil
    const navToggle = document.getElementById('nav-toggle');
    const navMenu = document.querySelector('.nav-menu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });
    }

    // Carrito de compras
    const cartButton = document.getElementById('cart-button');
    const cartOverlay = document.getElementById('cart-overlay');
    const closeCart = document.getElementById('close-cart');
    const cartItems = document.getElementById('cart-items');
    const cartCount = document.getElementById('cart-count');
    const cartTotalElement = document.getElementById('cart-total');

    // Checkout
    const checkoutBtn = document.getElementById('checkout-btn');
    const checkoutOverlay = document.getElementById('checkout-overlay');
    const closeCheckout = document.getElementById('close-checkout');
    const checkoutForm = document.getElementById('checkout-form');

    // Funciones del carrito
    function updateCart() {
        console.log('🔄 Actualizando carrito...');
        console.log('🛒 Estado del carrito:', cart);
        
        // Guardar carrito en localStorage
        localStorage.setItem('capitanFinancieroCart', JSON.stringify(cart));
        console.log('💾 Carrito guardado en localStorage');
        
        // Actualizar contador del carrito
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        if (cartCount) {
            cartCount.textContent = totalItems;
            console.log('🔢 Contador del carrito actualizado:', totalItems);
        } else {
            console.log('❌ Error: cartCount no encontrado');
        }

        // Actualizar items del carrito
        if (cartItems) {
            cartItems.innerHTML = '';
            cartTotal = 0;

            if (cart.length === 0) {
                cartItems.innerHTML = '<p style="text-align: center; color: #666; padding: 2rem;">Tu carrito está vacío</p>';
                if (cartTotalElement) cartTotalElement.textContent = '$0 MXN';
                console.log('🛒 Carrito vacío mostrado');
                return;
            }

            cart.forEach((item, index) => {
                const itemElement = document.createElement('div');
                itemElement.className = 'cart-item';
                itemElement.innerHTML = `
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">$${item.price} MXN</div>
                    </div>
                    <div class="cart-item-quantity">
                        <button class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
                        <span>${item.quantity}</span>
                        <button class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
                    </div>
                    <button class="remove-item" onclick="removeFromCart(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                cartItems.appendChild(itemElement);
                cartTotal += item.price * item.quantity;
            });

            if (cartTotalElement) {
                cartTotalElement.textContent = `$${cartTotal.toLocaleString()} MXN`;
                console.log('💰 Total del carrito actualizado:', cartTotal);
            } else {
                console.log('❌ Error: cartTotalElement no encontrado');
            }
        } else {
            console.log('❌ Error: cartItems no encontrado');
        }
    }

    // Funciones globales para el carrito (necesarias para los onclick)
    window.updateQuantity = function(index, change) {
        cart[index].quantity += change;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
        }
        updateCart();
    };

    window.removeFromCart = function(index) {
        cart.splice(index, 1);
        updateCart();
    };

    // Agregar al carrito
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn, .add-to-cart-minimal');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = parseInt(this.getAttribute('data-product-price'));

            // Verificar si el producto ya está en el carrito
            const existingItem = cart.find(item => item.id === productId);
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    id: productId,
                    name: productName,
                    price: productPrice,
                    quantity: 1
                });
            }

            updateCart();
            
            // Mostrar notificación
            showToast('Producto agregado al carrito', 'success');
        });
    });

    // Abrir/cerrar carrito
    if (cartButton && cartOverlay) {
        cartButton.addEventListener('click', () => {
            cartOverlay.classList.add('active');
        });
    }

    if (closeCart && cartOverlay) {
        closeCart.addEventListener('click', () => {
            cartOverlay.classList.remove('active');
        });

        cartOverlay.addEventListener('click', (e) => {
            if (e.target === cartOverlay) {
                cartOverlay.classList.remove('active');
            }
        });
    }

    // Checkout
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', () => {
            if (cart.length === 0) {
                showToast('Tu carrito está vacío', 'error');
                return;
            }
            
            // Redirigir a la página de checkout
            window.location.href = 'checkout.html';
        });
    }

    if (closeCheckout && checkoutOverlay) {
        closeCheckout.addEventListener('click', () => {
            checkoutOverlay.classList.remove('active');
        });

        checkoutOverlay.addEventListener('click', (e) => {
            if (e.target === checkoutOverlay) {
                checkoutOverlay.classList.remove('active');
            }
        });
    }

    function updateCheckoutSummary() {
        const checkoutItems = document.getElementById('checkout-items');
        const checkoutTotal = document.getElementById('checkout-total');
        
        checkoutItems.innerHTML = '';
        let total = 0;

        cart.forEach(item => {
            const itemElement = document.createElement('div');
            itemElement.className = 'checkout-item';
            itemElement.innerHTML = `
                <span>${item.name} x${item.quantity}</span>
                <span>$${(item.price * item.quantity).toLocaleString()} MXN</span>
            `;
            checkoutItems.appendChild(itemElement);
            total += item.price * item.quantity;
        });

        checkoutTotal.textContent = `$${total.toLocaleString()} MXN`;
    }

    // Procesar checkout
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = {
                name: document.getElementById('checkout-name').value,
                email: document.getElementById('checkout-email').value,
                phone: document.getElementById('checkout-phone').value,
                address: document.getElementById('checkout-address').value,
                payment: document.querySelector('input[name="payment"]:checked').value,
                items: cart,
                total: cartTotal
            };

            // Mostrar loading
            const payBtn = this.querySelector('.pay-btn');
            const originalText = payBtn.innerHTML;
            payBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            payBtn.disabled = true;

            // Enviar orden al servidor
            fetch('process_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error de conexión con el servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Limpiar carrito
                    cart = [];
                    updateCart();
                    
                    // Cerrar checkout
                    checkoutOverlay.classList.remove('active');
                    checkoutForm.reset();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast(error.message || 'Error al procesar la orden. Por favor, intenta nuevamente.', 'error');
            })
            .finally(() => {
                // Restaurar botón
                payBtn.innerHTML = originalText;
                payBtn.disabled = false;
            });
        });
    }

    // Función para mostrar notificaciones
    function showToast(message, type = 'info') {
        console.log('🍞 Mostrando toast:', { message, type });
        
        const toastContainer = document.querySelector('.toast-container') || createToastContainer();
        console.log('🍞 Toast container encontrado:', toastContainer);
        
        const toast = document.createElement('div');
        toast.classList.add('toast', type);
        toast.textContent = message;
        toastContainer.appendChild(toast);
        
        console.log('🍞 Toast creado y agregado:', toast);

        setTimeout(() => {
            toast.remove();
            console.log('🍞 Toast removido después de 5 segundos');
        }, 5000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.classList.add('toast-container');
        document.body.appendChild(container);
        return container;
    }

    // Navegación suave
    const navLinks = document.querySelectorAll('.nav-menu a[href^="#"]');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            
            if (targetSection) {
                const headerHeight = document.querySelector('.main-header').offsetHeight;
                const targetPosition = targetSection.offsetTop - headerHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Scroll header
    let lastScrollTop = 0;
    window.addEventListener('scroll', () => {
        const header = document.querySelector('.main-header');
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scrolling down
            header.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            header.style.transform = 'translateY(0)';
        }
        
        lastScrollTop = scrollTop;
    });

    // Cargar carrito desde localStorage
    const savedCart = localStorage.getItem('capitanFinancieroCart');
    if (savedCart) {
        try {
            cart = JSON.parse(savedCart);
            
            // Verificar si hay productos agregados via QR y mostrar notificación
            const qrProducts = cart.filter(item => item.addedViaQR);
            if (qrProducts.length > 0) {
                qrProducts.forEach(item => {
                    showToast(`Producto "${item.name}" agregado desde QR`, 'success');
                    // Remover la marca de QR para futuras operaciones
                    delete item.addedViaQR;
                });
                localStorage.setItem('capitanFinancieroCart', JSON.stringify(cart));
            }
        } catch (e) {
            console.error('Error al cargar carrito desde localStorage:', e);
            cart = [];
        }
    }
    
    // Inicializar carrito
    updateCart();

    // Código existente para el popup y formularios
    const ctaButton = document.querySelector('.header-button');
    const popupOverlay = document.querySelector('.popup-overlay');
    const closePopup = document.querySelector('.close-popup');
    
    // Abrir popup automáticamente después de 30 segundos
    setTimeout(function() {
        if (popupOverlay) {
            popupOverlay.style.display = 'flex';
        }
    }, 30000); // 30000 ms = 30 segundos
    const infoForm = document.getElementById('info-form');
    const newsletterForms = document.querySelectorAll('#newsletter-form');
    const numeroInput = document.getElementById('numero');
    
    // Add input validation for phone number
    if (numeroInput) {
        numeroInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        numeroInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    }

    if (ctaButton && popupOverlay) {
        ctaButton.addEventListener('click', function() {
            popupOverlay.style.display = 'flex';
        });
    }

    if (closePopup && popupOverlay) {
        closePopup.addEventListener('click', function() {
            popupOverlay.style.display = 'none';
        });

        popupOverlay.addEventListener('click', function(e) {
            if (e.target === popupOverlay) {
                popupOverlay.style.display = 'none';
            }
        });
    }

    // Handle form submission
    if (infoForm) {
        infoForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Get form data
            const formData = {
                nombre: document.getElementById('nombre').value,
                apellido: document.getElementById('apellido').value,
                correo: document.getElementById('correo').value,
                numero: document.getElementById('numero').value,
                mensaje: document.getElementById('mensaje').value
            };

            // Send form data to the server
            fetch('submit_form.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error de conexión con el servidor');
                }
                return response.json();
            })
            .then(data => {
                // Clear form and close popup
                infoForm.reset();
                popupOverlay.style.display = 'none';

                // Show response message using toast
                showToast(data.message, data.success ? 'success' : 'error');
            })
            .catch(error => {
                showToast(error.message || 'Error de conexión. Por favor, verifica tu conexión a internet e intenta nuevamente.', 'error');
            });
        });
    }

    // Handle newsletter form submissions
    if (newsletterForms) {
        newsletterForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = this.querySelector('input[type="email"]').value;

                // Send the email to the server
                fetch('subscribe.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.reset();
                    }
                    showToast(data.message, data.success ? 'success' : 'error');
                })
                .catch(error => {
                    showToast('Error de conexión. Por favor, verifica tu conexión a internet e intenta nuevamente.', 'error');
                });
            });
        });
    }

    // Función para agregar al carrito desde el modal
    window.addToCartFromModal = function() {
        console.log('🛒 Función addToCartFromModal ejecutada');
        
        const productId = '1';
        const productName = 'Programa de "Alineación Financiera I"';
        const productPrice = 1499;

        console.log('📦 Producto a agregar:', { productId, productName, productPrice });
        console.log('🛒 Estado actual del carrito:', cart);

        // Verificar si el producto ya está en el carrito
        const existingItem = cart.find(item => item.id === productId);
        if (existingItem) {
            existingItem.quantity += 1;
            console.log('➕ Producto existente, cantidad incrementada a:', existingItem.quantity);
        } else {
            cart.push({
                id: productId,
                name: productName,
                price: productPrice,
                quantity: 1
            });
            console.log('🆕 Nuevo producto agregado al carrito');
        }

        console.log('🛒 Carrito después de agregar:', cart);
        
        updateCart();
        
        // Mostrar notificación
        showToast('Producto agregado al carrito', 'success');
        
        console.log('✅ Producto agregado exitosamente, carrito no se abre automáticamente');
    };

    // Función para mostrar el temario
    window.verTemario = function() {
        // Crear un modal más atractivo para mostrar el temario
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        `;

        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            padding: 2rem;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        `;

        modalContent.innerHTML = `
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <h2 style="color: #222F58; margin: 0 0 0.5rem 0; font-size: 1.8rem;">Temario del Programa</h2>
                <h3 style="color: #666; margin: 0; font-size: 1.2rem; font-weight: normal;">"Alineación Financiera I"</h3>
            </div>
            
            <div style="margin-bottom: 2rem;">
                <p style="color: #555; line-height: 1.6; margin-bottom: 1rem;">
                    Descubre el contenido completo de nuestro programa diseñado para transformar tu relación con el dinero:
                </p>
            </div>

            <div style="margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; margin-bottom: 1rem; padding: 0.8rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1A237E;">
                    <span style="background: #1A237E; color: white; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: bold;">1</span>
                    <span style="color: #333; font-weight: 500;">Mitos de la inversión</span>
                </div>
                
                <div style="display: flex; align-items: center; margin-bottom: 1rem; padding: 0.8rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1A237E;">
                    <span style="background: #1A237E; color: white; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: bold;">2</span>
                    <span style="color: #333; font-weight: 500;">¿Cómo salir de deudas?</span>
                </div>
                
                <div style="display: flex; align-items: center; margin-bottom: 1rem; padding: 0.8rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1A237E;">
                    <span style="background: #1A237E; color: white; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: bold;">3</span>
                    <span style="color: #333; font-weight: 500;">Abundancia y pensamiento holístico </span>
                </div>
                
                <div style="display: flex; align-items: center; margin-bottom: 1rem; padding: 0.8rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1A237E;">
                    <span style="background: #1A237E; color: white; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: bold;">4</span>
                    <span style="color: #333; font-weight: 500;">¿Por qué administrarme?</span>
                </div>
                
                <div style="display: flex; align-items: center; margin-bottom: 1rem; padding: 0.8rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1A237E;">
                    <span style="background: #1A237E; color: white; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: bold;">5</span>
                    <span style="color: #333; font-weight: 500;">¿Cómo me preparo para invertir?</span>
                </div>
                
                <div style="display: flex; align-items: center; margin-bottom: 1rem; padding: 0.8rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1A237E;">
                    <span style="background: #1A237E; color: white; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: bold;">6</span>
                    <span style="color: #333; font-weight: 500;">Bienes Raíces</span>
                </div>
            </div>



            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button onclick="this.closest('.modal-overlay').remove()" style="
                    background: #6c757d;
                    color: white;
                    border: none;
                    padding: 0.8rem 1.5rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 1rem;
                    transition: background 0.3s ease;
                " onmouseover="this.style.background='#5a6268'" onmouseout="this.style.background='#6c757d'">
                    Cerrar
                </button>
                <button onclick="addToCartFromModal(); this.closest('.modal-overlay').remove()" style="
                    background: #222F58;
                    color: white;
                    border: none;
                    padding: 0.8rem 1.5rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 1rem;
                    transition: background 0.3s ease;
                " onmouseover="this.style.background='#1a233f'" onmouseout="this.style.background='#222F58'">
                    Agregar al carrito
                </button>
            </div>
        `;

        // Agregar el contenido al modal
        modal.appendChild(modalContent);
        
        // Agregar clase para identificar el modal
        modal.className = 'modal-overlay';
        
        // Cerrar modal al hacer clic fuera de él
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });

        document.body.appendChild(modal);
    };
});