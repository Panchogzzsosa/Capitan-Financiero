document.addEventListener('DOMContentLoaded', function() {
    console.log('Iniciando checkout...');
    
    // Función helper para formatear precios con comas
    function formatPrice(amountInCents) {
        const amount = amountInCents / 100;
        return amount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Variables globales
    let cart = [];
    let cartTotal = 0;
    let stripe;
    let elements;
    let paymentElement;
    let selectedPaymentMethod = null;
    let stripeButton;

    // Inicializar Stripe
    function initializeStripe() {
        console.log('🚀 Inicializando Stripe...');
        
        try {
            const stripeKey = 'pk_test_51RssEKDr6pwo00JYGrZoYOejqnTgUSWW2qkbjMXOImmjsEfrTaMSW7rSNoqjc5mBiLNnr27IY1FJwCNxHFnGPYc1001BfdumDI';
            //const stripeKey = 'pk_live_51RssE8DvIWKIbYVCtdnm6z66g5Khu4UaaykVte3EO3yU8H51JQebti2OxWfPUbTzbIDMmw0bZKHweuLBKwPCgoVc00WNmoJ1T3';
            console.log('🔑 Stripe Key:', stripeKey.substring(0, 20) + '...');
            
            stripe = Stripe(stripeKey);
            console.log('✅ Stripe inicializado correctamente');
            console.log('📊 Stripe object:', stripe);
            console.log('🔧 Stripe methods disponibles:', Object.keys(stripe));
            
        } catch (error) {
            console.error('❌ Error al inicializar Stripe:', error);
            console.error('Stack trace:', error.stack);
            throw error;
        }
    }

    // Cargar carrito desde localStorage
    function loadCart() {
        // Mantener carrito existente si está disponible
        
        const savedCart = localStorage.getItem('capitanFinancieroCart');
        console.log('Carrito guardado:', savedCart);
        
        if (savedCart) {
            try {
                cart = JSON.parse(savedCart);
                console.log('Carrito cargado:', cart);
                // Normalizar a centavos si viene en pesos
                cart = cart.map(item => {
                    if (typeof item.price === 'number' && item.price < 10000) {
                        return { ...item, price: item.price * 100 };
                    }
                    return item;
                });
            } catch (error) {
                console.error('Error al cargar el carrito:', error);
                cart = [];
            }
        } else {
            console.log('No hay carrito guardado, creando carrito de prueba');
            cart = [
                {
                    id: '1',
                    name: 'Programa de "Alineación Financiera I"',
                    price: 465000, // 4650.00 en centavos (precio original)
                    quantity: 1
                }
            ];
        }
        
        console.log('Carrito final:', cart);
    }

    // Actualizar resumen del checkout
    function updateCheckoutSummary() {
        console.log('Actualizando resumen del checkout...');
        
        cartTotal = 0;
        cart.forEach(item => {
            cartTotal += item.price * item.quantity;
        });

        // Calcular descuento y total final
        const subtotal = cartTotal;
        const discount = 0;
        const finalTotal = subtotal;
        
        cartTotal = finalTotal;

        // Actualizar precio del producto individual
        const productPriceElement = document.querySelector('.product-price');
        if (productPriceElement && cart.length > 0) {
            const item = cart[0];
            productPriceElement.textContent = `$${formatPrice(item.price)} MXN`;
        }

        // Actualizar precios en el HTML
        const subtotalElement = document.getElementById('subtotal');
        const totalElement = document.getElementById('total');
        
        if (subtotalElement) {
            subtotalElement.textContent = `$${formatPrice(subtotal)} MXN`;
        }
        if (totalElement) {
            totalElement.textContent = `$${formatPrice(finalTotal)} MXN`;
        }
        
        console.log('Resumen actualizado:', {
            subtotal: subtotal,
            discount: discount,
            finalTotal: finalTotal,
            total: (finalTotal / 100).toFixed(2)
        });
    }

    // Validar campos del formulario
    function validateForm() {
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        
        // Validar email básico
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValidEmail = emailRegex.test(email);
        
        // Validar que el teléfono tenga exactamente 10 dígitos (sin contar el código de país)
        const phoneDigits = phone.replace(/\D/g, '');
        const isValidPhone = phoneDigits.length === 10;
        
        const isValid = name.length > 0 && isValidEmail && isValidPhone;
        
        console.log('Validación del formulario:', {
            name: name.length > 0,
            email: isValidEmail,
            phone: isValidPhone,
            isValid: isValid
        });
        
        return isValid;
    }

    // Habilitar/deshabilitar botón de Stripe
    function updateStripeButton() {
        if (!stripeButton) {
            stripeButton = document.getElementById('stripe-button');
        }
        
        const isValid = validateForm();
        
        if (isValid) {
            stripeButton.disabled = false;
            stripeButton.classList.remove('disabled');
            stripeButton.style.cursor = 'pointer';
        } else {
            stripeButton.disabled = true;
            stripeButton.classList.add('disabled');
            stripeButton.style.cursor = 'not-allowed';
        }
    }

    // Crear Payment Intent
    async function createPaymentIntent() {
        try {
            console.log('Creando Payment Intent...');
            console.log('cartTotal (precio final con promoción):', cartTotal);
            console.log('Datos del formulario:', {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value
            });
            
            const requestData = {
                amount: cartTotal, // Este es el precio final con promoción ($1,899.00)
                email: document.getElementById('email').value,
                name: document.getElementById('name').value
            };
            
            console.log('Datos enviados al servidor (precio final):', requestData);
            
            const response = await fetch('create_payment_intent.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });

            const result = await response.json();
            console.log('Respuesta del servidor:', result);
            
            if (result.success) {
                console.log('✅ Payment Intent creado exitosamente');
                console.log('Client Secret:', result.client_secret);
                console.log('Payment Intent ID:', result.payment_intent_id);
                return {
                    client_secret: result.client_secret,
                    payment_intent_id: result.payment_intent_id
                };
            } else {
                throw new Error(result.message || 'Error al crear el Payment Intent');
            }
        } catch (error) {
            console.error('Error al crear Payment Intent:', error);
            throw error;
        }
    }

    // Manejar pago con Stripe
    async function handleStripePayment() {
        console.log('Iniciando pago con Stripe...');
        
        // Solo montar el Payment Element, NO confirmar automáticamente
        if (!paymentElement) {
            console.log('Montando Payment Element para Stripe...');
            await handleCardPayment(); // Esto monta el Payment Element
        }
        
        // Cambiar el estado del botón de Stripe sin que desaparezca
        const stripeButton = document.getElementById('stripe-button');
        stripeButton.classList.add('disabled');
        stripeButton.disabled = true;
        stripeButton.innerHTML = '<i class="fab fa-stripe"></i><span>Pagar con Stripe</span>';
        
        // Mostrar el botón de confirmar pago con transición suave
        const cardButton = document.getElementById('card-button');
        cardButton.style.display = 'flex';
        cardButton.style.marginTop = '2rem';
        
        // Esperar a que termine la expansión del formulario
        setTimeout(() => {
            cardButton.classList.add('show');
        }, 600); // Esperar a que termine la expansión del formulario
        
        // NO confirmar automáticamente - esperar a que el usuario haga clic en "Confirmar Pago"
        console.log('Payment Element listo. Esperando confirmación del usuario...');
    }

    // Manejar pago con tarjeta
    async function handleCardPayment() {
        console.log('Iniciando pago con tarjeta...');
        
        // Crear Payment Element si no existe
        if (!paymentElement) {
            console.log('Creando Payment Element...');
            
            // Primero crear el Payment Intent para obtener el clientSecret
            try {
                const paymentIntentData = await createPaymentIntent();
                console.log('Payment Intent Data obtenido:', paymentIntentData);
                
                elements = stripe.elements({
                    clientSecret: paymentIntentData.client_secret
                });
                
                paymentElement = elements.create('payment', {
                    layout: 'tabs',
                    paymentMethodOrder: ['card', 'apple_pay', 'google_pay']
                });
                
                // Crear contenedor para el Payment Element
                const cardContainer = document.createElement('div');
                cardContainer.id = 'payment-element';
                cardContainer.style.marginTop = '0'; // Sin margen para que salga del botón
                cardContainer.style.marginBottom = '1rem';
                cardContainer.style.padding = '1rem';
                cardContainer.style.border = '2px solid #e0e0e0';
                cardContainer.style.borderRadius = '8px';
                cardContainer.style.backgroundColor = 'white';
                cardContainer.style.opacity = '0';
                cardContainer.style.transform = 'scale(0.8) translateY(-50px)'; // Comienza pequeño y arriba
                cardContainer.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)'; // Transición suave
                cardContainer.style.transformOrigin = 'top center'; // Se expande desde arriba
                
                // Insertar después del botón de Stripe
                const stripeButton = document.getElementById('stripe-button');
                stripeButton.parentNode.insertBefore(cardContainer, stripeButton.nextSibling);
                
                console.log('Montando Payment Element...');
                paymentElement.mount('#payment-element');
                
                // Hacer aparecer el Payment Element expandiéndose desde el botón
                setTimeout(() => {
                    cardContainer.style.opacity = '1';
                    cardContainer.style.transform = 'scale(1) translateY(0)';
                }, 200); // Pequeño delay para sincronizar con la desaparición del botón
                
                // Manejar errores
                paymentElement.on('change', function(event) {
                    console.log('Payment Element change:', event);
                    const displayError = document.getElementById('payment-message');
                    if (!displayError) {
                        const errorDiv = document.createElement('div');
                        errorDiv.id = 'payment-message';
                        errorDiv.style.color = '#e53e3e';
                        errorDiv.style.fontSize = '14px';
                        errorDiv.style.marginTop = '10px';
                        cardContainer.appendChild(errorDiv);
                    }
                    
                    if (event.error) {
                        displayError.textContent = event.error.message;
                    } else {
                        displayError.textContent = '';
                    }
                });
                
            } catch (error) {
                console.error('Error al crear Payment Intent:', error);
                alert('Error al preparar el pago: ' + error.message);
                return;
            }
        } else {
            // Mostrar Payment Element si ya existe
            const existingElement = document.getElementById('payment-element');
            if (existingElement) {
                existingElement.style.display = 'block';
            }
        }
        
        console.log('Payment Element configurado correctamente');
        
        // Retornar una promesa que se resuelve cuando el Payment Element esté listo
        return new Promise((resolve) => {
            setTimeout(() => {
                console.log('Payment Element listo para usar');
                resolve();
            }, 1000); // Dar tiempo para que se monte completamente
        });
    }

    // Confirmar pago con tarjeta
    async function handleCardPaymentConfirm() {
        const submitButton = document.getElementById('card-button');
        const buttonText = submitButton.querySelector('span');
        
        console.log('Confirmando pago con tarjeta...');

            // Validar formulario
        const form = document.getElementById('checkout-form');
        if (!form.checkValidity()) {
            form.reportValidity();
                return;
            }

        // Deshabilitar botón
            submitButton.disabled = true;
        buttonText.textContent = 'Procesando...';

        try {
            // Verificar que el Payment Element esté montado
            if (!paymentElement) {
                throw new Error('Payment Element no está montado');
            }
            
            console.log('Confirmando pago...');
            console.log('Datos del formulario:', {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                address: document.getElementById('address').value
            });
            
            // Guardar datos del cliente en localStorage para success.html
            localStorage.setItem('customer_name', document.getElementById('name').value);
            localStorage.setItem('customer_email', document.getElementById('email').value);
            // Obtener el número completo con código de país
            const fullNumber = getFullPhoneNumber();
            console.log('Número completo con código de país:', fullNumber);
            localStorage.setItem('customer_phone', fullNumber);
            localStorage.setItem('customer_address', document.getElementById('address').value);
            
            // Confirmar pago usando el elements ya configurado
            console.log('=== ANTES DE CONFIRMAR PAGO ===');
            console.log('Elements:', elements);
            console.log('Payment Element:', paymentElement);
            
            try {
                console.log('🔄 Iniciando stripe.confirmPayment...');
                console.log('Elements configurado:', !!elements);
                console.log('Payment Element configurado:', !!paymentElement);
                console.log('Stripe instance:', !!stripe);
                
                // Verificar que todos los parámetros estén correctos
                const confirmParams = {
                    return_url: window.location.origin + '/capitanfinanciero/success.html',
                    payment_method_data: {
                        billing_details: {
                            name: document.getElementById('name').value,
                            email: document.getElementById('email').value,
                            phone: fullNumber,
                            address: {
                                line1: document.getElementById('address').value
                            }
                        }
                    }
                };
                
                console.log('📋 Parámetros de confirmación:', confirmParams);
                console.log('🔑 Elements object:', elements);
                console.log('🎯 Payment Element object:', paymentElement);
                
                // Intentar confirmar el pago con timeout
                const confirmPromise = stripe.confirmPayment({
                    elements,
                    confirmParams
                });
                
                // Agregar timeout para evitar que se cuelgue
                const timeoutPromise = new Promise((_, reject) => {
                    setTimeout(() => reject(new Error('Timeout en stripe.confirmPayment')), 30000); // 30 segundos
                });
                
                console.log('⏱️ Ejecutando stripe.confirmPayment con timeout...');
                
                const { error, paymentIntent } = await Promise.race([confirmPromise, timeoutPromise]);
                
                console.log('✅ stripe.confirmPayment completado');
                console.log('Error:', error);
                console.log('Payment Intent:', paymentIntent);
                
            } catch (confirmError) {
                console.error('❌ Error en stripe.confirmPayment:', confirmError);
                console.error('Stack trace:', confirmError.stack);
                console.error('Error name:', confirmError.name);
                console.error('Error message:', confirmError.message);
                
                // Mostrar error al usuario
                alert('Error al confirmar el pago: ' + confirmError.message);
                buttonText.textContent = 'Confirmar Pago';
                submitButton.disabled = false;
                return; // Salir de la función
            }
            
            console.log('=== DESPUÉS DE CONFIRMAR PAGO ===');

                if (error) {
                console.error('Error detallado en el pago:', error);
                console.error('Tipo de error:', error.type);
                console.error('Código de error:', error.code);
                console.error('Mensaje de error:', error.message);
                alert('Error en el pago: ' + error.message);
                buttonText.textContent = 'Confirmar Pago';
                submitButton.disabled = false;
            } else {
                console.log('Pago confirmado exitosamente:', paymentIntent);
                console.log('Estado del pago:', paymentIntent.status);
                console.log('=== PAGO EXITOSO - REDIRIGIENDO ===');
                
                // Mostrar mensaje de éxito
                buttonText.textContent = '¡Pago exitoso!';
                
                // Guardar UTM parameters en localStorage para que success.html los use
                const utmData = {
                    utm_source: document.getElementById('utm_source').value,
                    utm_medium: document.getElementById('utm_medium').value,
                    utm_campaign: document.getElementById('utm_campaign').value,
                    utm_content: document.getElementById('utm_content').value,
                    utm_term: document.getElementById('utm_term').value,
                    referrer: document.getElementById('referrer').value,
                    landing_page: document.getElementById('landing_page').value
                };
                localStorage.setItem('capitanFinancieroUTM', JSON.stringify(utmData));
                console.log('💾 UTM parameters guardados en localStorage:', utmData);
                
                // Guardar datos del cliente para success.html
                localStorage.setItem('customer_name', document.getElementById('name').value);
                localStorage.setItem('customer_email', document.getElementById('email').value);
                localStorage.setItem('customer_phone', getFullPhoneNumber());
                localStorage.setItem('customer_address', document.getElementById('address').value);
                
                console.log('✅ Datos del cliente guardados en localStorage');
                console.log('🔄 Redirigiendo a success.html...');
                
                // Stripe se encargará de la redirección automáticamente
                // No necesitamos hacer nada más aquí
            }
            
        } catch (error) {
            console.error('Error general:', error);
            console.error('Stack trace:', error.stack);
            alert('Error al procesar el pago: ' + error.message);
            buttonText.textContent = 'Confirmar Pago';
            submitButton.disabled = false;
        }
    }

    // Función para guardar la orden en la base de datos
    async function saveOrderToDatabase(paymentIntent) {
        try {
            console.log('🚀 === INICIANDO GUARDADO DE ORDEN ===');
            console.log('📋 Payment Intent recibido:', paymentIntent);
            console.log('🔑 Payment Intent ID:', paymentIntent.id);
            console.log('📊 Payment Intent Status:', paymentIntent.status);
            
            // Obtener el número completo con código de país
            const fullNumber = getFullPhoneNumber();
            console.log('📱 Número completo para BD:', fullNumber);
            
            // Verificar que todos los campos del formulario existen
            const nameField = document.getElementById('name');
            const emailField = document.getElementById('email');
            const addressField = document.getElementById('address');
            const utmSourceField = document.getElementById('utm_source');
            const utmMediumField = document.getElementById('utm_medium');
            const utmCampaignField = document.getElementById('utm_campaign');
            
            console.log('🔍 Verificando campos del formulario:');
            console.log('Campo nombre existe:', !!nameField);
            console.log('Campo email existe:', !!emailField);
            console.log('Campo dirección existe:', !!addressField);
            console.log('Campo utm_source existe:', !!utmSourceField);
            console.log('Campo utm_medium existe:', !!utmMediumField);
            console.log('Campo utm_campaign existe:', !!utmCampaignField);
            
            if (!nameField || !emailField) {
                throw new Error('Campos requeridos del formulario no encontrados');
            }
            
            // Obtener UTM parameters
            const utm_source = utmSourceField ? utmSourceField.value : '';
            const utm_medium = utmMediumField ? utmMediumField.value : '';
            const utm_campaign = utmCampaignField ? utmCampaignField.value : '';
            const utm_content = document.getElementById('utm_content') ? document.getElementById('utm_content').value : '';
            const utm_term = document.getElementById('utm_term') ? document.getElementById('utm_term').value : '';
            const referrer = document.getElementById('referrer') ? document.getElementById('referrer').value : '';
            const landing_page = document.getElementById('landing_page') ? document.getElementById('landing_page').value : '';
            
            console.log('🔍 UTM Parameters capturados para envío:');
            console.log('utm_source:', utm_source);
            console.log('utm_medium:', utm_medium);
            console.log('utm_campaign:', utm_campaign);
            console.log('utm_content:', utm_content);
            console.log('utm_term:', utm_term);
            console.log('referrer:', referrer);
            console.log('landing_page:', landing_page);
            
            const orderData = {
                customer_name: document.getElementById('name').value,
                customer_email: document.getElementById('email').value,
                customer_phone: fullNumber,
                customer_address: document.getElementById('address').value,
                stripe_payment_intent_id: paymentIntent.id,
                total_amount: (cartTotal / 100).toFixed(2),
                total_amount_cents: cartTotal,
                payment_method: 'card',
                // Datos de rastreo de tráfico
                utm_source: utm_source,
                utm_medium: utm_medium,
                utm_campaign: utm_campaign,
                utm_content: utm_content,
                utm_term: utm_term,
                referrer: referrer,
                landing_page: landing_page
            };
            
            console.log('🔍 Verificando UTM parameters antes de enviar:');
            console.log('utm_source:', document.getElementById('utm_source').value);
            console.log('utm_medium:', document.getElementById('utm_medium').value);
            console.log('utm_campaign:', document.getElementById('utm_campaign').value);
            console.log('utm_content:', document.getElementById('utm_content').value);
            console.log('utm_term:', document.getElementById('utm_term').value);
            console.log('referrer:', document.getElementById('referrer').value);
            console.log('landing_page:', document.getElementById('landing_page').value);
            
            console.log('Datos de la orden a enviar:', orderData);
            console.log('URL de destino: save_order.php');
            
            // Guardar UTM parameters en localStorage para debugging
            const utmData = {
                utm_source,
                utm_medium,
                utm_campaign,
                utm_content,
                utm_term,
                referrer,
                landing_page
            };
            localStorage.setItem('capitanFinancieroUTM', JSON.stringify(utmData));
            console.log('💾 UTM parameters guardados en localStorage para debugging:', utmData);
            
            const response = await fetch('save_order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                body: JSON.stringify(orderData)
            });

            console.log('Respuesta del servidor recibida');
            console.log('Status:', response.status);
            console.log('Status Text:', response.statusText);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            console.log('Resultado parseado:', result);
            
            if (result.success) {
                console.log('✅ Orden guardada correctamente:', result);
                // Limpiar el carrito después del pago exitoso
                        localStorage.removeItem('capitanFinancieroCart');
                console.log('Carrito limpiado del localStorage');
                return true;
                    } else {
                console.error('❌ Error al guardar la orden:', result.message);
                throw new Error(result.message || 'Error desconocido al guardar la orden');
                }

            } catch (error) {
            console.error('❌ Error al guardar la orden:', error);
            console.error('Stack trace:', error.stack);
            // No lanzar el error para no interrumpir el flujo del pago
            return false;
        }
    }

    // Función para resetear el estado del formulario
    function resetPaymentForm() {
        const stripeButton = document.getElementById('stripe-button');
        const cardButton = document.getElementById('card-button');
        const paymentElement = document.getElementById('payment-element');
        
        // Resetear botón de Stripe
        stripeButton.classList.remove('disabled');
        stripeButton.disabled = false;
        stripeButton.innerHTML = '<i class="fab fa-stripe"></i><span>Pagar con Stripe</span>';
        
        // Ocultar botón de confirmar
        cardButton.classList.remove('show');
        cardButton.style.display = 'none';
        
        // Ocultar Payment Element
        if (paymentElement) {
            paymentElement.style.display = 'none';
        }
    }

    // Configurar eventos de los botones
    function setupPaymentButtons() {
        const stripeButton = document.getElementById('stripe-button');
        const cardButton = document.getElementById('card-button');
        
        if (stripeButton) {
            stripeButton.addEventListener('click', handleStripePayment);
        }
        
        if (cardButton) {
            cardButton.addEventListener('click', handleCardPaymentConfirm);
        }
    }

    // Inicializar
    function init() {
        console.log('Inicializando checkout...');
        
        // Cargar carrito
        loadCart();
        
        // Actualizar resumen
        updateCheckoutSummary();
        
        // Inicializar Stripe
        initializeStripe();
        
        // Configurar botones de pago
        setupPaymentButtons();
        
        // Configurar validación de formulario
        setupFormValidation();
        
        // Validación inicial del botón
        updateStripeButton();
        
        console.log('Checkout inicializado correctamente');
    }

    // Configurar validación de formulario
    function setupFormValidation() {
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        
        // Event listeners para validación en tiempo real
        [nameInput, emailInput, phoneInput].forEach(input => {
            input.addEventListener('input', updateStripeButton);
            input.addEventListener('blur', updateStripeButton);
            input.addEventListener('change', updateStripeButton);
        });
        
        // Limitar teléfono a exactamente 10 dígitos
        phoneInput.addEventListener('input', function(e) {
            const value = e.target.value;
            const digits = value.replace(/\D/g, '');
            
            // Si hay más de 10 dígitos, truncar a 10
            if (digits.length > 10) {
                const truncatedDigits = digits.substring(0, 10);
                // Obtener solo los dígitos y limitar a 10
                e.target.value = value.replace(/\D/g, '').substring(0, 10);
            }
        });
        
        console.log('Validación de formulario configurada');
    }

    // Iniciar cuando el DOM esté listo
    init();
});