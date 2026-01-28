<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header_public.php';
?>
<!-- SECCIÓN CARRUSEL BOGATI MEJORADA -->
<section class="carousel-section fade-in">
    <div id="carouselBogati" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#carouselBogati" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Nuestros productos"></button>
            <button type="button" data-bs-target="#carouselBogati" data-bs-slide-to="1" aria-label="Experiencia Bogati"></button>
            <button type="button" data-bs-target="#carouselBogati" data-bs-slide-to="2" aria-label="Promociones especiales"></button>
        </div>
        <div class="carousel-inner">
            <!-- Slide 1: Productos -->
            <div class="carousel-item active">
                <img src="imagenes/Horizontal-Nevados.jpg" class="d-block w-100" alt="Bogati productos">
                <div class="carousel-overlay"></div>
                <div class="carousel-caption">
                    <div class="caption-container animate-float">
                        <span class="badge-category bg-cafe animate-pulse">Novedad</span>
                        <h2 class="animate-typing">Helados Artesanales Bogati</h2>
                        <p class="animate-slide-up delay-1">Elaborados con ingredientes 100% naturales y el auténtico sabor tradicional que nos caracteriza.</p>
                        <div class="carousel-features">
                            <div class="feature-item animate-bounce-in delay-2">
                                <i class="fas fa-leaf"></i>
                                <span>Ingredientes naturales</span>
                            </div>
                            <div class="feature-item animate-bounce-in delay-3">
                                <i class="fas fa-heart"></i>
                                <span>Hecho con amor</span>
                            </div>
                        </div>
                      
                 
                    </div>
                </div>
            </div>
            
            <!-- Slide 2: Experiencia -->
            <div class="carousel-item">
                <img src="imagenes/Horizontal-Helados.jpg" class="d-block w-100" alt="Bogati experiencia">
                <div class="carousel-overlay"></div>
                <div class="carousel-caption">
                    <div class="caption-container animate-float">
                        <span class="badge-category bg-amarillo animate-pulse">Experiencia</span>
                        <h2 class="animate-typing">Vive la Experiencia Bogati</h2>
                        <p class="animate-slide-up delay-1">Más que un helado, es un momento de felicidad compartida en familia y con amigos.</p>
                        <div class="experience-stats">
                            <div class="stat-item animate-counter delay-2" data-target="67">
                                <div class="stat-number">0+</div>
                                <div class="stat-label">Sabores únicos</div>
                            </div>
                            <div class="stat-item animate-counter delay-3" data-target="23">
                                <div class="stat-number">0+</div>
                                <div class="stat-label">Años de tradición</div>
                            </div>
                            <div class="stat-item animate-counter delay-4" data-target="100">
                                <div class="stat-number">0%</div>
                                <div class="stat-label">Calidad garantizada</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Slide 3: Promociones -->
            <div class="carousel-item">
                <img src="imagenes/Horizontal-Promociones.jpg" class="d-block w-100" alt="Bogati Boguibox">
                <div class="carousel-overlay"></div>
                <div class="carousel-caption">
                    <div class="caption-container animate-float">
                        <span class="badge-category bg-cafe-claro animate-pulse">Promoción</span>
                        <h2 class="animate-typing">Boguibox Especial</h2>
                        <p class="animate-slide-up delay-1">Disfruta de nuestras cajas sorpresa con una selección especial de helados y toppings.</p>
                        <div class="promo-highlight animate-glow delay-2">
                            <i class="fas fa-gift animate-spin-slow"></i>
                            <span>20% de descuento en tu primera Boguibox</span>
                        </div>
                       
                    </div>
                </div>
            </div>
        </div>
        
        <button class="carousel-control-prev animate-slide-left" type="button" data-bs-target="#carouselBogati" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next animate-slide-right" type="button" data-bs-target="#carouselBogati" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
        </button>
        
        <!-- Contador de slides -->
        <div class="carousel-counter animate-fade-in">
            <span class="current-slide">01</span>
            <span class="separator">/</span>
            <span class="total-slides">03</span>
        </div>
    </div>
</section>

<style>
    /* Variables de colores Bogati */
    :root {
        --amarillo: #FDB813;
        --amarillo-claro: #FFE082;
        --amarillo-oscuro: #FF8C00;
        --blanco: #FFFFFF;
        --negro: #000000;
        --gris-oscuro: #333333;
        --cafe: #8B4513;
        --cafe-claro: #A0522D;
        --cafe-oscuro: #654321;
        --gris-fondo: #F5F5F5;
        --sombra: rgba(0, 0, 0, 0.1);
    }
    
    /* Contenedor del cuadrado blanco mejorado */
    .carousel-caption {
        position: absolute;
        top: 50%;
        left: 10%;
        transform: translateY(-50%);
        text-align: left;
        max-width: 600px;
        z-index: 2;
        padding: 0;
        background: transparent;
        box-shadow: none;
    }
    
    .caption-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        padding: 2.5rem;
        border-radius: 20px;
        border-left: 6px solid var(--amarillo);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        position: relative;
        overflow: hidden;
    }
    
    /* Efecto de borde animado */
    .caption-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, 
            var(--amarillo) 0%, 
            var(--cafe) 50%, 
            var(--amarillo-oscuro) 100%);
        animation: borderFlow 3s infinite linear;
    }
    
    /* Badges categoría */
    .badge-category {
        display: inline-block;
        padding: 0.6rem 1.5rem;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.8px;
        margin-bottom: 1.2rem;
        color: var(--blanco);
        text-transform: uppercase;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    .bg-cafe { background: linear-gradient(135deg, var(--cafe), var(--cafe-oscuro)); }
    .bg-amarillo { background: linear-gradient(135deg, var(--amarillo), var(--amarillo-oscuro)); color: var(--negro); }
    .bg-cafe-claro { background: linear-gradient(135deg, var(--cafe-claro), var(--cafe)); }
    
    /* Títulos y texto */
    .carousel-caption h2 {
        color: var(--cafe-oscuro);
        font-size: 2.8rem;
        font-weight: 800;
        margin-bottom: 1.2rem;
        line-height: 1.2;
        position: relative;
        padding-bottom: 0.5rem;
    }
    
    .carousel-caption h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 3px;
        background: var(--amarillo);
        border-radius: 2px;
    }
    
    .carousel-caption p {
        color: var(--gris-oscuro);
        font-size: 1.2rem;
        line-height: 1.6;
        margin-bottom: 1.5rem;
        padding-right: 1rem;
    }
    
    /* Elementos de características */
    .carousel-features {
        display: flex;
        gap: 2rem;
        margin: 1.8rem 0;
    }
    
    .feature-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        color: var(--cafe);
        font-weight: 600;
        padding: 0.8rem 1.2rem;
        background: var(--amarillo-claro);
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    
    .feature-item:hover {
        transform: translateY(-5px);
        background: var(--amarillo);
        box-shadow: 0 8px 20px rgba(253, 184, 19, 0.3);
    }
    
    .feature-item i {
        color: var(--cafe);
        font-size: 1.3rem;
    }
    
    /* Estadísticas */
    .experience-stats {
        display: flex;
        gap: 2rem;
        margin: 2rem 0;
    }
    
    .stat-item {
        text-align: center;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        flex: 1;
    }
    
    .stat-item:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    }
    
    .stat-number {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--amarillo-oscuro);
        line-height: 1;
        margin-bottom: 0.3rem;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: var(--cafe);
        font-weight: 600;
    }
    
    /* Promoción destacada */
    .promo-highlight {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: linear-gradient(135deg, var(--amarillo-claro), #FFF8E1);
        padding: 1.2rem 1.8rem;
        border-radius: 12px;
        border-left: 5px solid var(--amarillo);
        margin: 1.8rem 0;
        position: relative;
        overflow: hidden;
    }
    
    .promo-highlight::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, 
            transparent 30%, 
            rgba(255, 255, 255, 0.4) 50%, 
            transparent 70%);
        animation: shine 3s infinite linear;
    }
    
    .promo-highlight i {
        color: var(--amarillo-oscuro);
        font-size: 1.8rem;
        z-index: 1;
    }
    
    .promo-highlight span {
        color: var(--cafe-oscuro);
        font-weight: 700;
        font-size: 1.1rem;
        z-index: 1;
    }
    
    /* Botones mejorados */
    .btn-bogati {
        background: linear-gradient(135deg, var(--amarillo), var(--amarillo-oscuro));
        color: var(--negro);
        padding: 1rem 2.8rem;
        border-radius: 50px;
        font-weight: 700;
        border: none;
        transition: all 0.4s ease;
        box-shadow: 0 6px 20px rgba(253, 184, 19, 0.4);
        position: relative;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-bogati::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, 
            transparent 0%, 
            rgba(255, 255, 255, 0.4) 50%, 
            transparent 100%);
        transition: left 0.7s ease;
    }
    
    .btn-bogati:hover::before {
        left: 100%;
    }
    
    .btn-bogati-alt {
        background: linear-gradient(135deg, var(--cafe), var(--cafe-oscuro));
        color: var(--blanco);
        padding: 1rem 2.8rem;
        border-radius: 50px;
        font-weight: 700;
        border: none;
        transition: all 0.4s ease;
        box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
    }
    
    .btn-bogati-promo {
        background: linear-gradient(135deg, var(--cafe-claro), var(--cafe));
        color: var(--blanco);
        padding: 1rem 2.8rem;
        border-radius: 50px;
        font-weight: 700;
        border: none;
        transition: all 0.4s ease;
        box-shadow: 0 6px 20px rgba(160, 82, 45, 0.4);
    }
    
    .btn-bogati:hover, .btn-bogati-alt:hover, .btn-bogati-promo:hover {
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 12px 30px rgba(0,0,0,0.25);
        color: var(--blanco);
    }
    
    .btn-bogati:hover { 
        background: linear-gradient(135deg, var(--amarillo-oscuro), #FFA000); 
    }
    .btn-bogati-alt:hover { 
        background: linear-gradient(135deg, var(--cafe-oscuro), #5D4037); 
    }
    .btn-bogati-promo:hover { 
        background: linear-gradient(135deg, var(--cafe), #795548); 
    }
    
    /* NUEVAS ANIMACIONES AGREGADAS */
    
    /* Flotación suave para el cuadrado */
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
    
    .animate-float {
        animation: float 6s ease-in-out infinite;
    }
    
    /* Efecto de escritura */
    @keyframes typing {
        from { width: 0; }
        to { width: 100%; }
    }
    
    .animate-typing {
        overflow: hidden;
        white-space: nowrap;
        animation: typing 1.5s steps(30, end);
    }
    
    /* Bounce in */
    @keyframes bounceIn {
        0% { 
            opacity: 0;
            transform: scale(0.3) translateY(50px); 
        }
        50% { 
            opacity: 0.9;
            transform: scale(1.05); 
        }
        80% { 
            opacity: 1;
            transform: scale(0.95); 
        }
        100% { 
            opacity: 1;
            transform: scale(1); 
        }
    }
    
    .animate-bounce-in {
        animation: bounceIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    
    /* Pulse para badges */
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .animate-pulse {
        animation: pulse 2s infinite;
    }
    
    /* Shake para botones */
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    .animate-shake {
        animation: shake 0.8s ease-in-out;
    }
    
    /* Hover pulsante */
    @keyframes pulse-hover {
        0% { box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4); }
        50% { box-shadow: 0 6px 30px rgba(139, 69, 19, 0.7); }
        100% { box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4); }
    }
    
    .animate-pulse-hover:hover {
        animation: pulse-hover 1.5s infinite;
    }
    
    /* Glow effect */
    @keyframes glow {
        0%, 100% { 
            box-shadow: 0 0 20px rgba(255, 224, 130, 0.5); 
        }
        50% { 
            box-shadow: 0 0 40px rgba(255, 224, 130, 0.8); 
        }
    }
    
    .animate-glow {
        animation: glow 2s infinite;
    }
    
    /* Rotación lenta */
    @keyframes spin-slow {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .animate-spin-slow {
        animation: spin-slow 8s linear infinite;
    }
    
    /* Flotación en hover */
    @keyframes float-hover {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-5px); }
    }
    
    .animate-float-hover:hover {
        animation: float-hover 2s infinite;
    }
    
    /* Deslizamiento de flechas */
    @keyframes slide-left {
        0%, 100% { transform: translateX(0); }
        50% { transform: translateX(-5px); }
    }
    
    @keyframes slide-right {
        0%, 100% { transform: translateX(0); }
        50% { transform: translateX(5px); }
    }
    
    .animate-slide-left:hover {
        animation: slide-left 0.5s;
    }
    
    .animate-slide-right:hover {
        animation: slide-right 0.5s;
    }
    
    /* Fade in */
    @keyframes fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .animate-fade-in {
        animation: fade-in 1s ease-out;
    }
    
    /* Borde fluido */
    @keyframes borderFlow {
        0% { background-position: -100% 0; }
        100% { background-position: 200% 0; }
    }
    
    /* Efecto shine */
    @keyframes shine {
        0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
    }
    
    /* Counter animation */
    @keyframes countUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-counter {
        animation: countUp 1s ease-out forwards;
    }
    
    /* Slide up original (mantener compatibilidad) */
    @keyframes slideUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-slide-up {
        opacity: 0;
        transform: translateY(30px);
        animation: slideUp 0.8s ease-out forwards;
    }
    
    /* Delays para animaciones */
    .delay-1 { animation-delay: 0.2s; }
    .delay-2 { animation-delay: 0.4s; }
    .delay-3 { animation-delay: 0.6s; }
    .delay-4 { animation-delay: 0.8s; }
    .delay-5 { animation-delay: 1s; }
    
    /* Responsive */
    @media (max-width: 992px) {
        .carousel-caption {
            left: 5%;
            right: 5%;
            max-width: 550px;
        }
        
        .caption-container {
            padding: 2rem;
        }
        
        .carousel-caption h2 {
            font-size: 2.2rem;
        }
    }
    
    @media (max-width: 768px) {
        .carousel-caption {
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
        }
        
        .caption-container {
            padding: 1.5rem;
        }
        
        .carousel-caption h2 {
            font-size: 1.8rem;
        }
        
        .carousel-caption p {
            font-size: 1rem;
        }
        
        .carousel-features, .experience-stats {
            flex-wrap: wrap;
            gap: 1rem;
        }
    }
</style>

<!-- Script para animación de contadores -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Contador de slides
        const carousel = document.getElementById('carouselBogati');
        const currentSlideSpan = document.querySelector('.current-slide');
        
        carousel.addEventListener('slid.bs.carousel', function(event) {
            const activeIndex = event.to;
            currentSlideSpan.textContent = (activeIndex + 1).toString().padStart(2, '0');
            
            // Animar contadores en el slide activo
            const activeSlide = carousel.querySelector('.carousel-item.active');
            const counters = activeSlide.querySelectorAll('.animate-counter');
            
            counters.forEach((counter, index) => {
                const target = counter.getAttribute('data-target');
                if (target) {
                    animateCounter(counter.querySelector('.stat-number'), target, index * 300);
                }
            });
        });
        
        // Función para animar contadores
        function animateCounter(element, target, delay) {
            setTimeout(() => {
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    element.textContent = Math.floor(current) + (target === 100 ? '%' : '+');
                }, 30);
            }, delay);
        }
        
        // Inicializar contadores en el primer slide
        setTimeout(() => {
            const firstSlide = carousel.querySelector('.carousel-item.active');
            const counters = firstSlide.querySelectorAll('.animate-counter');
            
            counters.forEach((counter, index) => {
                const target = counter.getAttribute('data-target');
                if (target) {
                    animateCounter(counter.querySelector('.stat-number'), target, index * 300);
                }
            });
        }, 1000);
    });
</script>

<!-- Añadir Font Awesome para iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    /* Variables de colores Bogati */
    :root {
        --amarillo: #FDB813;
        --amarillo-claro: #FFE082;
        --amarillo-oscuro: #FF8C00;
        --blanco: #FFFFFF;
        --negro: #000000;
        --gris-oscuro: #333333;
        --cafe: #8B4513;
        --cafe-claro: #A0522D;
        --cafe-oscuro: #654321;
        --gris-fondo: #F5F5F5;
        --sombra: rgba(0, 0, 0, 0.1);
    }
    
    /* Contenedor del cuadrado blanco mejorado */
    .carousel-caption {
        position: absolute;
        top: 50%;
        left: 10%;
        transform: translateY(-50%);
        text-align: left;
        max-width: 600px;
        z-index: 2;
        padding: 0;
        background: transparent;
        box-shadow: none;
    }
    
    .caption-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        padding: 2.5rem;
        border-radius: 20px;
        border-left: 6px solid var(--amarillo);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        position: relative;
        overflow: hidden;
    }
    
    /* Efecto de borde animado */
    .caption-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, 
            var(--amarillo) 0%, 
            var(--cafe) 50%, 
            var(--amarillo-oscuro) 100%);
        animation: borderFlow 3s infinite linear;
    }
    
    /* Badges categoría */
    .badge-category {
        display: inline-block;
        padding: 0.6rem 1.5rem;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.8px;
        margin-bottom: 1.2rem;
        color: var(--blanco);
        text-transform: uppercase;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    .bg-cafe { background: linear-gradient(135deg, var(--cafe), var(--cafe-oscuro)); }
    .bg-amarillo { background: linear-gradient(135deg, var(--amarillo), var(--amarillo-oscuro)); color: var(--negro); }
    .bg-cafe-claro { background: linear-gradient(135deg, var(--cafe-claro), var(--cafe)); }
    
    /* Títulos y texto */
    .carousel-caption h2 {
        color: var(--cafe-oscuro);
        font-size: 2.8rem;
        font-weight: 800;
        margin-bottom: 1.2rem;
        line-height: 1.2;
        position: relative;
        padding-bottom: 0.5rem;
    }
    
    .carousel-caption h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 3px;
        background: var(--amarillo);
        border-radius: 2px;
    }
    
    .carousel-caption p {
        color: var(--gris-oscuro);
        font-size: 1.2rem;
        line-height: 1.6;
        margin-bottom: 1.5rem;
        padding-right: 1rem;
    }
    
    /* Elementos de características */
    .carousel-features {
        display: flex;
        gap: 2rem;
        margin: 1.8rem 0;
    }
    
    .feature-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        color: var(--cafe);
        font-weight: 600;
        padding: 0.8rem 1.2rem;
        background: var(--amarillo-claro);
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    
    .feature-item:hover {
        transform: translateY(-5px);
        background: var(--amarillo);
        box-shadow: 0 8px 20px rgba(253, 184, 19, 0.3);
    }
    
    .feature-item i {
        color: var(--cafe);
        font-size: 1.3rem;
    }
    
    /* Estadísticas */
    .experience-stats {
        display: flex;
        gap: 2rem;
        margin: 2rem 0;
    }
    
    .stat-item {
        text-align: center;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        flex: 1;
    }
    
    .stat-item:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    }
    
    .stat-number {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--amarillo-oscuro);
        line-height: 1;
        margin-bottom: 0.3rem;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: var(--cafe);
        font-weight: 600;
    }
    
    /* Promoción destacada */
    .promo-highlight {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: linear-gradient(135deg, var(--amarillo-claro), #FFF8E1);
        padding: 1.2rem 1.8rem;
        border-radius: 12px;
        border-left: 5px solid var(--amarillo);
        margin: 1.8rem 0;
        position: relative;
        overflow: hidden;
    }
    
    .promo-highlight::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, 
            transparent 30%, 
            rgba(255, 255, 255, 0.4) 50%, 
            transparent 70%);
        animation: shine 3s infinite linear;
    }
    
    .promo-highlight i {
        color: var(--amarillo-oscuro);
        font-size: 1.8rem;
        z-index: 1;
    }
    
    .promo-highlight span {
        color: var(--cafe-oscuro);
        font-weight: 700;
        font-size: 1.1rem;
        z-index: 1;
    }
    
    /* Botones mejorados */
    .btn-bogati {
        background: linear-gradient(135deg, var(--amarillo), var(--amarillo-oscuro));
        color: var(--negro);
        padding: 1rem 2.8rem;
        border-radius: 50px;
        font-weight: 700;
        border: none;
        transition: all 0.4s ease;
        box-shadow: 0 6px 20px rgba(253, 184, 19, 0.4);
        position: relative;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-bogati::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, 
            transparent 0%, 
            rgba(255, 255, 255, 0.4) 50%, 
            transparent 100%);
        transition: left 0.7s ease;
    }
    
    .btn-bogati:hover::before {
        left: 100%;
    }
    
    .btn-bogati-alt {
        background: linear-gradient(135deg, var(--cafe), var(--cafe-oscuro));
        color: var(--blanco);
        padding: 1rem 2.8rem;
        border-radius: 50px;
        font-weight: 700;
        border: none;
        transition: all 0.4s ease;
        box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
    }
    
    .btn-bogati-promo {
        background: linear-gradient(135deg, var(--cafe-claro), var(--cafe));
        color: var(--blanco);
        padding: 1rem 2.8rem;
        border-radius: 50px;
        font-weight: 700;
        border: none;
        transition: all 0.4s ease;
        box-shadow: 0 6px 20px rgba(160, 82, 45, 0.4);
    }
    
    .btn-bogati:hover, .btn-bogati-alt:hover, .btn-bogati-promo:hover {
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 12px 30px rgba(0,0,0,0.25);
        color: var(--blanco);
    }
    
    .btn-bogati:hover { 
        background: linear-gradient(135deg, var(--amarillo-oscuro), #FFA000); 
    }
    .btn-bogati-alt:hover { 
        background: linear-gradient(135deg, var(--cafe-oscuro), #5D4037); 
    }
    .btn-bogati-promo:hover { 
        background: linear-gradient(135deg, var(--cafe), #795548); 
    }
    
    /* NUEVAS ANIMACIONES AGREGADAS */
    
    /* Flotación suave para el cuadrado */
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
    
    .animate-float {
        animation: float 6s ease-in-out infinite;
    }
    
    /* Efecto de escritura */
    @keyframes typing {
        from { width: 0; }
        to { width: 100%; }
    }
    
    .animate-typing {
        overflow: hidden;
        white-space: nowrap;
        animation: typing 1.5s steps(30, end);
    }
    
    /* Bounce in */
    @keyframes bounceIn {
        0% { 
            opacity: 0;
            transform: scale(0.3) translateY(50px); 
        }
        50% { 
            opacity: 0.9;
            transform: scale(1.05); 
        }
        80% { 
            opacity: 1;
            transform: scale(0.95); 
        }
        100% { 
            opacity: 1;
            transform: scale(1); 
        }
    }
    
    .animate-bounce-in {
        animation: bounceIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    
    /* Pulse para badges */
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .animate-pulse {
        animation: pulse 2s infinite;
    }
    
    /* Shake para botones */
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    .animate-shake {
        animation: shake 0.8s ease-in-out;
    }
    
    /* Hover pulsante */
    @keyframes pulse-hover {
        0% { box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4); }
        50% { box-shadow: 0 6px 30px rgba(139, 69, 19, 0.7); }
        100% { box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4); }
    }
    
    .animate-pulse-hover:hover {
        animation: pulse-hover 1.5s infinite;
    }
    
    /* Glow effect */
    @keyframes glow {
        0%, 100% { 
            box-shadow: 0 0 20px rgba(255, 224, 130, 0.5); 
        }
        50% { 
            box-shadow: 0 0 40px rgba(255, 224, 130, 0.8); 
        }
    }
    
    .animate-glow {
        animation: glow 2s infinite;
    }
    
    /* Rotación lenta */
    @keyframes spin-slow {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .animate-spin-slow {
        animation: spin-slow 8s linear infinite;
    }
    
    /* Flotación en hover */
    @keyframes float-hover {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-5px); }
    }
    
    .animate-float-hover:hover {
        animation: float-hover 2s infinite;
    }
    
    /* Deslizamiento de flechas */
    @keyframes slide-left {
        0%, 100% { transform: translateX(0); }
        50% { transform: translateX(-5px); }
    }
    
    @keyframes slide-right {
        0%, 100% { transform: translateX(0); }
        50% { transform: translateX(5px); }
    }
    
    .animate-slide-left:hover {
        animation: slide-left 0.5s;
    }
    
    .animate-slide-right:hover {
        animation: slide-right 0.5s;
    }
    
    /* Fade in */
    @keyframes fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .animate-fade-in {
        animation: fade-in 1s ease-out;
    }
    
    /* Borde fluido */
    @keyframes borderFlow {
        0% { background-position: -100% 0; }
        100% { background-position: 200% 0; }
    }
    
    /* Efecto shine */
    @keyframes shine {
        0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
    }
    
    /* Counter animation */
    @keyframes countUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-counter {
        animation: countUp 1s ease-out forwards;
    }
    
    /* Slide up original (mantener compatibilidad) */
    @keyframes slideUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-slide-up {
        opacity: 0;
        transform: translateY(30px);
        animation: slideUp 0.8s ease-out forwards;
    }
    
    /* Delays para animaciones */
    .delay-1 { animation-delay: 0.2s; }
    .delay-2 { animation-delay: 0.4s; }
    .delay-3 { animation-delay: 0.6s; }
    .delay-4 { animation-delay: 0.8s; }
    .delay-5 { animation-delay: 1s; }
    
    /* Responsive */
    @media (max-width: 992px) {
        .carousel-caption {
            left: 5%;
            right: 5%;
            max-width: 550px;
        }
        
        .caption-container {
            padding: 2rem;
        }
        
        .carousel-caption h2 {
            font-size: 2.2rem;
        }
    }
    
    @media (max-width: 768px) {
        .carousel-caption {
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
        }
        
        .caption-container {
            padding: 1.5rem;
        }
        
        .carousel-caption h2 {
            font-size: 1.8rem;
        }
        
        .carousel-caption p {
            font-size: 1rem;
        }
        
        .carousel-features, .experience-stats {
            flex-wrap: wrap;
            gap: 1rem;
        }
    }
</style>

<!-- Script para animación de contadores -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Contador de slides
        const carousel = document.getElementById('carouselBogati');
        const currentSlideSpan = document.querySelector('.current-slide');
        
        carousel.addEventListener('slid.bs.carousel', function(event) {
            const activeIndex = event.to;
            currentSlideSpan.textContent = (activeIndex + 1).toString().padStart(2, '0');
            
            // Animar contadores en el slide activo
            const activeSlide = carousel.querySelector('.carousel-item.active');
            const counters = activeSlide.querySelectorAll('.animate-counter');
            
            counters.forEach((counter, index) => {
                const target = counter.getAttribute('data-target');
                if (target) {
                    animateCounter(counter.querySelector('.stat-number'), target, index * 300);
                }
            });
        });
        
        // Función para animar contadores
        function animateCounter(element, target, delay) {
            setTimeout(() => {
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    element.textContent = Math.floor(current) + (target === 100 ? '%' : '+');
                }, 30);
            }, delay);
        }
        
        // Inicializar contadores en el primer slide
        setTimeout(() => {
            const firstSlide = carousel.querySelector('.carousel-item.active');
            const counters = firstSlide.querySelectorAll('.animate-counter');
            
            counters.forEach((counter, index) => {
                const target = counter.getAttribute('data-target');
                if (target) {
                    animateCounter(counter.querySelector('.stat-number'), target, index * 300);
                }
            });
        }, 1000);
    });
</script>

<!-- Añadir Font Awesome para iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<!-- SECCIÓN NOSOTROS -->
<section class="section bogati-nosotros" id="nosotros">
    <div class="container text-center">
        <h2 class="section-title">¿POR QUÉ BOGATI?</h2>
        <p class="section-subtitle">
            Somos más que helados, somos una experiencia única que combina tradición, innovación y el mejor sabor.
        </p>

        <div class="features-grid">
            <div class="feature-card fade-in">
                <div class="feature-icon"><i class="fas fa-leaf"></i></div>
                <h3>100% Natural</h3>
                <p>Ingredientes frescos y naturales sin conservantes artificiales.</p>
            </div>

            <div class="feature-card fade-in">
                <div class="feature-icon"><i class="fas fa-heart"></i></div>
                <h3>Hecho con Amor</h3>
                <p>Preparados artesanalmente con técnicas tradicionales.</p>
            </div>

            <div class="feature-card fade-in">
                <div class="feature-icon"><i class="fas fa-star"></i></div>
                <h3>Calidad Premium</h3>
                <p>Excelencia garantizada en cada bocado.</p>
            </div>
        </div>

        <!-- HISTORIA -->
        <div class="story-block story-highlight" id="inicio-de-todo">
            <h3>EL INICIO DE TODO</h3>

            <p>
                El <strong>16 de octubre de 2018</strong>, Santiago y Kathy abrieron el primer local
                en Riobamba, marcando el inicio de una empresa familiar.
            </p>

            <p>
                En la inauguración regalaron <strong>1000 helados con crema y queso</strong>.
            </p>

            <div class="inicio-imagen">
                <img src="imagenes/Inicio.png" alt="Primer local Bogati">
            </div>
        </div>

        <div class="story-block">
            <h3>HELADOS CON QUESO</h3>

            <p>
                En 2017, durante un paseo familiar en <strong>Ibarra</strong>, Belén probó este postre
                y dijo:
            </p>

            <blockquote>
                “Ya sé a qué nos vamos a dedicar: Helados con Queso”.
            </blockquote>

            <p>
                Así nació la idea que hoy es Bogati.
            </p>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="main-footer">
    <div class="footer-content">
        <div class="footer-info">
            <div class="footer-logo">BOGATI</div>
            <p>Helados con Queso – Buenos por fuera, buenos por dentro</p>

            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-tiktok"></i></a>
                <a href="#"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>

        <div class="footer-links">
            <h5>CONTACTO</h5>
            <p><i class="fas fa-phone"></i> 1800-BOGATI</p>
            <p><i class="fas fa-envelope"></i> info@bogati.com</p>
            <p><i class="fas fa-map-marker-alt"></i> Ecuador</p>
        </div>
    </div>

    <div class="copyright text-center mt-4">
        <p>&copy; <?= date('Y'); ?> Bogati Franquicia. Todos los derechos reservados.</p>
        <p>Proyecto Universitario</p>
    </div>
    </div>
</footer>

<script>
    // Smooth scroll para navegación interna
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Header efecto al hacer scroll
    window.addEventListener('scroll', function() {
        const header = document.querySelector('.header');
        if (window.scrollY > 50) {
            header.style.background = 'rgba(249, 227, 202, 0.95)';
            header.style.backdropFilter = 'blur(10px)';
        } else {
            header.style.background = '#f9e3ca';
            header.style.backdropFilter = 'none';
        }
    });
</script>
<script>
    document.getElementById("btn-conocenos").addEventListener("click", function(e) {
        e.preventDefault();

        const seccion = document.getElementById("inicio-de-todo");
        seccion.classList.add("show");

        seccion.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    });
</script>

</body>

</html>

<?php
require_once __DIR__ . '/includes/footer.php';
?>