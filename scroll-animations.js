// ===== ANIMACIONES DE SCROLL =====

// Función para verificar si un elemento está visible en el viewport
function isElementInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Función para verificar si un elemento está parcialmente visible
function isElementPartiallyVisible(element) {
    const rect = element.getBoundingClientRect();
    const windowHeight = window.innerHeight || document.documentElement.clientHeight;
    
    return (
        rect.top < windowHeight &&
        rect.bottom > 0
    );
}

// Función para activar la animación de un elemento
function activateAnimation(element) {
    if (element && !element.classList.contains('animate-active')) {
        element.classList.add('animate-active');
    }
}

// Función para desactivar la animación de un elemento (opcional, para re-animaciones)
function deactivateAnimation(element) {
    if (element && element.classList.contains('animate-active')) {
        element.classList.remove('animate-active');
    }
}

// Función principal para manejar las animaciones de scroll
function handleScrollAnimations() {
    const animatedElements = document.querySelectorAll('.scroll-animate');
    
    animatedElements.forEach(element => {
        if (isElementPartiallyVisible(element)) {
            activateAnimation(element);
        }
    });
}

// Función para animar elementos específicos con delays escalonados
function animateStaggeredElements() {
    const mvvPanels = document.querySelectorAll('.mvv-panel');
    const productCards = document.querySelectorAll('.product-card-minimal');
    
    // Animar paneles MVV con delays escalonados
    mvvPanels.forEach((panel, index) => {
        if (isElementPartiallyVisible(panel)) {
            setTimeout(() => {
                activateAnimation(panel);
            }, index * 200); // 200ms de delay entre cada panel
        }
    });
    
    // Animar tarjetas de productos con delays escalonados
    productCards.forEach((card, index) => {
        if (isElementPartiallyVisible(card)) {
            setTimeout(() => {
                activateAnimation(card);
            }, index * 150); // 150ms de delay entre cada tarjeta
        }
    });
}

// Función para animar elementos del hero cuando la página carga
function animateHeroElements() {
    const heroTitle = document.querySelector('.hero-content .titulo');
    const heroDescription = document.querySelector('.hero-content .titulo-descripcion');
    
    if (heroTitle) {
        setTimeout(() => {
            activateAnimation(heroTitle);
        }, 500);
    }
    
    if (heroDescription) {
        setTimeout(() => {
            activateAnimation(heroDescription);
        }, 800);
    }
}

// Función para optimizar el rendimiento usando Intersection Observer (si está disponible)
function setupIntersectionObserver() {
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    if (element.classList.contains('scroll-animate')) {
                        activateAnimation(element);
                    }
                }
            });
        }, {
            threshold: 0.1, // Activar cuando el 10% del elemento sea visible
            rootMargin: '0px 0px -50px 0px' // Activar 50px antes de que el elemento sea completamente visible
        });
        
        // Observar todos los elementos con animación
        const animatedElements = document.querySelectorAll('.scroll-animate');
        animatedElements.forEach(element => {
            observer.observe(element);
        });
        
        return observer;
    }
    return null;
}

// Función para configurar las animaciones
function setupScrollAnimations() {
    // Configurar Intersection Observer si está disponible
    const observer = setupIntersectionObserver();
    
    // Si no hay Intersection Observer, usar scroll events
    if (!observer) {
        // Agregar event listener para scroll
        window.addEventListener('scroll', handleScrollAnimations);
        
        // También verificar en load para elementos ya visibles
        window.addEventListener('load', handleScrollAnimations);
    }
    
    // Animar elementos del hero al cargar
    animateHeroElements();
    
    // Verificar animaciones al cargar la página
    setTimeout(() => {
        handleScrollAnimations();
        animateStaggeredElements();
    }, 100);
}

// Función para re-animar elementos (útil para testing)
function resetAnimations() {
    const animatedElements = document.querySelectorAll('.scroll-animate');
    animatedElements.forEach(element => {
        deactivateAnimation(element);
    });
    
    // Re-animar después de un breve delay
    setTimeout(() => {
        handleScrollAnimations();
        animateStaggeredElements();
    }, 100);
}

// Inicializar las animaciones cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupScrollAnimations);
} else {
    setupScrollAnimations();
}

// Exportar funciones para uso global (opcional)
window.scrollAnimations = {
    setup: setupScrollAnimations,
    reset: resetAnimations,
    animate: handleScrollAnimations
};
