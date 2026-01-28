<?php
// modules/public/productos.php - RUTAS CORRECTAS
require_once __DIR__ . '/../../config.php';      // Sube 2 niveles
require_once __DIR__ . '/../../includes/functions.php';  
require_once __DIR__ . '/../../db_connection.php';
require_once __DIR__ . '/../../includes/header_public.php';    

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bogati - Productos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* VARIABLES DE COLOR BOGATI */
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

    /* HERO ANIMADO BOGATI */
    .banner-container {
        position: relative;
        height: 90vh;
        min-height: 800px;
        overflow: hidden;
        border-radius: 0 0 40px 40px;
        margin: 0;
    }

    .hero-gradient {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, 
            rgba(253, 184, 19, 0.15) 0%, 
            rgba(139, 69, 19, 0.1) 50%, 
            rgba(255, 224, 130, 0.05) 100%);
        z-index: 2;
    }

    .banner-image {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    .banner-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        animation: zoomParallax 30s infinite alternate;
    }

    @keyframes zoomParallax {
        0% { transform: scale(1) translateY(0); }
        100% { transform: scale(1.1) translateY(-20px); }
    }

    /* CUADRADO BLANCO ANIMADO */
    .text-overlay-box.premium-card {
        position: absolute;
        top: 50%;
        right: 80px;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        border-radius: 30px;
        padding: 60px 50px;
        width: 600px;
        box-shadow: 
            0 30px 70px rgba(0, 0, 0, 0.25),
            0 0 0 1px rgba(255, 255, 255, 0.4),
            inset 0 1px 0 rgba(255, 255, 255, 0.6);
        border: 2px solid rgba(255, 255, 255, 0.5);
        z-index: 3;
        animation: floatBounce 8s ease-in-out infinite;
        border-left: 8px solid var(--amarillo);
        overflow: hidden;
    }

    @keyframes floatBounce {
        0%, 100% { transform: translateY(-50%) translateX(0) rotate(0deg); }
        33% { transform: translateY(-52%) translateX(5px) rotate(0.5deg); }
        66% { transform: translateY(-48%) translateX(-5px) rotate(-0.5deg); }
    }

    /* Borde animado superior */
    .text-overlay-box.premium-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, 
            var(--amarillo) 0%, 
            var(--cafe) 50%, 
            var(--amarillo-oscuro) 100%);
        animation: borderFlow 4s infinite linear;
        z-index: 4;
    }

    @keyframes borderFlow {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }

    /* Elementos decorativos flotantes */
    .floating-elements {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        z-index: 2;
        pointer-events: none;
    }

    .floating-element {
        position: absolute;
        font-size: 3rem;
        opacity: 0.15;
        animation: floatElement 10s ease-in-out infinite;
        filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2));
    }

    .floating-element:nth-child(1) { top: 15%; left: 8%; animation-delay: 0s; }
    .floating-element:nth-child(2) { top: 65%; left: 88%; animation-delay: 2s; }
    .floating-element:nth-child(3) { top: 85%; left: 12%; animation-delay: 4s; }
    .floating-element:nth-child(4) { top: 35%; left: 85%; animation-delay: 6s; }

    @keyframes floatElement {
        0%, 100% { 
            transform: translateY(0) rotate(0deg) scale(1); 
            opacity: 0.1;
        }
        50% { 
            transform: translateY(-40px) rotate(15deg) scale(1.3); 
            opacity: 0.25;
        }
    }

    /* CONTENIDO DEL CUADRADO BLANCO */
    .text-content {
        position: relative;
        z-index: 1;
    }

    /* BADGE CATEGORÍA */
    .hero-badge {
        display: inline-block;
        padding: 12px 28px;
        background: linear-gradient(135deg, var(--cafe), var(--cafe-oscuro));
        color: var(--blanco);
        border-radius: 50px;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 1.2px;
        margin-bottom: 25px;
        text-transform: uppercase;
        box-shadow: 0 8px 25px rgba(139, 69, 19, 0.4);
        animation: pulseBadge 3s infinite;
        position: relative;
        overflow: hidden;
    }

    @keyframes pulseBadge {
        0%, 100% { 
            transform: scale(1); 
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.4);
        }
        50% { 
            transform: scale(1.05); 
            box-shadow: 0 12px 35px rgba(139, 69, 19, 0.6);
        }
    }

    .hero-badge::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, 
            transparent 0%, 
            rgba(255, 255, 255, 0.3) 50%, 
            transparent 100%);
        transition: left 0.7s ease;
    }

    .hero-badge:hover::before {
        left: 100%;
    }

    /* TÍTULO PRINCIPAL */
    .main-title.hero-title {
        font-family: 'Montserrat', sans-serif;
        font-size: 4.8rem;
        font-weight: 900;
        color: var(--cafe-oscuro);
        margin: 0 0 20px 0;
        line-height: 1.1;
        position: relative;
        text-transform: uppercase;
        letter-spacing: 2px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        animation: typingTitle 1.5s steps(30, end);
        overflow: hidden;
        white-space: nowrap;
    }

    @keyframes typingTitle {
        from { width: 0; }
        to { width: 100%; }
    }

    .main-title.hero-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 80px;
        height: 4px;
        background: linear-gradient(90deg, var(--amarillo), var(--amarillo-oscuro));
        border-radius: 2px;
    }

    /* DESCRIPCIÓN */
    .description.hero-description {
        color: var(--gris-oscuro);
        font-size: 1.3rem;
        line-height: 1.7;
        margin: 30px 0 40px 0;
        padding-right: 20px;
        position: relative;
        animation: slideUpText 1s ease-out 0.5s both;
        font-weight: 400;
    }

    @keyframes slideUpText {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .brand-name {
        color: var(--amarillo-oscuro);
        font-weight: 800;
        position: relative;
        display: inline-block;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }

    .brand-name::after {
        content: '';
        position: absolute;
        bottom: 2px;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, transparent, var(--cafe), transparent);
        opacity: 0.6;
    }

    /* FEATURES MINI */
    .hero-features {
        display: flex;
        gap: 20px;
        margin: 35px 0 45px;
        flex-wrap: wrap;
    }

    .hero-feature-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 25px;
        background: linear-gradient(135deg, 
            rgba(253, 184, 19, 0.15), 
            rgba(255, 224, 130, 0.1));
        border-radius: 15px;
        border: 2px solid rgba(253, 184, 19, 0.3);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        animation: bounceInFeature 0.8s ease-out;
        animation-fill-mode: both;
    }

    .hero-feature-item:nth-child(1) { animation-delay: 0.8s; }
    .hero-feature-item:nth-child(2) { animation-delay: 1s; }

    @keyframes bounceInFeature {
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

    .hero-feature-item:hover {
        transform: translateY(-5px) scale(1.05);
        background: linear-gradient(135deg, 
            rgba(253, 184, 19, 0.25), 
            rgba(255, 224, 130, 0.2));
        box-shadow: 0 15px 30px rgba(253, 184, 19, 0.3);
        border-color: var(--amarillo);
    }

    .hero-feature-item i {
        color: var(--amarillo);
        font-size: 1.6rem;
        min-width: 30px;
    }

    .hero-feature-item span {
        color: var(--cafe-oscuro);
        font-weight: 700;
        font-size: 1.1rem;
        white-space: nowrap;
    }

    /* ESTADÍSTICAS HERO */
    .hero-stats {
        display: flex;
        gap: 30px;
        margin: 40px 0 50px;
        padding: 25px;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 20px;
        border: 2px solid rgba(139, 69, 19, 0.2);
        backdrop-filter: blur(10px);
        animation: fadeInStats 1s ease-out 1.2s both;
    }

    @keyframes fadeInStats {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .hero-stat-item {
        flex: 1;
        text-align: center;
        padding: 15px;
        transition: all 0.3s ease;
        position: relative;
    }

    .hero-stat-item:hover {
        transform: translateY(-8px);
    }

    .hero-stat-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--amarillo), var(--cafe));
        border-radius: 2px;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .hero-stat-item:hover::before {
        opacity: 1;
    }

    .hero-stat-number {
        font-size: 3.2rem;
        font-weight: 900;
        color: var(--amarillo-oscuro);
        line-height: 1;
        margin-bottom: 8px;
        display: block;
        position: relative;
    }

    .hero-stat-number::after {
        content: '+';
        font-size: 2rem;
        position: relative;
        top: -10px;
    }

    .hero-stat-label {
        font-size: 1rem;
        color: var(--cafe);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* BOTONES HERO MEJORADOS */
    .hero-action-buttons {
        display: flex;
        gap: 20px;
        margin-top: 30px;
    }

    .hero-btn {
        position: relative;
        padding: 20px 45px;
        border-radius: 50px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        font-size: 1.1rem;
        border: none;
        cursor: pointer;
        animation: shakeButton 0.8s ease-in-out 1.5s;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }

    @keyframes shakeButton {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    .hero-btn::before {
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

    .hero-btn:hover::before {
        left: 100%;
    }

    .hero-btn:hover {
        transform: translateY(-8px) scale(1.05);
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    }

    .hero-btn-primary {
        background: linear-gradient(135deg, var(--amarillo), var(--amarillo-oscuro));
        color: var(--negro);
        box-shadow: 0 10px 30px rgba(253, 184, 19, 0.4);
    }

    .hero-btn-secondary {
        background: linear-gradient(135deg, var(--cafe), var(--cafe-oscuro));
        color: var(--blanco);
        box-shadow: 0 10px 30px rgba(139, 69, 19, 0.4);
    }

    .hero-btn i {
        font-size: 1.4rem;
        transition: transform 0.3s ease;
    }

    .hero-btn:hover i {
        transform: rotate(15deg) scale(1.2);
    }

    .hero-btn span {
        position: relative;
        z-index: 1;
    }

    /* PROMO BADGE */
    .hero-promo-badge {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        background: linear-gradient(135deg, 
            rgba(255, 224, 130, 0.9), 
            rgba(255, 255, 255, 0.9));
        padding: 20px 35px;
        border-radius: 50px;
        margin-top: 35px;
        border: 2px solid rgba(253, 184, 19, 0.4);
        position: relative;
        overflow: hidden;
        animation: glowPromo 3s infinite alternate;
    }

    @keyframes glowPromo {
        0%, 100% { 
            box-shadow: 0 0 30px rgba(255, 224, 130, 0.5);
        }
        50% { 
            box-shadow: 0 0 50px rgba(255, 224, 130, 0.8);
        }
    }

    .hero-promo-badge::before {
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
        animation: shineEffect 3s infinite linear;
    }

    @keyframes shineEffect {
        0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
    }

    .hero-promo-badge i {
        color: var(--amarillo-oscuro);
        font-size: 2rem;
        animation: spinSlow 8s linear infinite;
        position: relative;
        z-index: 1;
    }

    @keyframes spinSlow {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .hero-promo-badge span {
        color: var(--cafe-oscuro);
        font-weight: 800;
        font-size: 1.2rem;
        letter-spacing: 1px;
        position: relative;
        z-index: 1;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .text-overlay-box.premium-card {
            width: 500px;
            padding: 50px 40px;
            right: 50px;
        }
        
        .main-title.hero-title {
            font-size: 4rem;
        }
    }

    @media (max-width: 992px) {
        .banner-container {
            height: 80vh;
            min-height: 700px;
        }
        
        .text-overlay-box.premium-card {
            position: relative;
            top: auto;
            right: auto;
            transform: none;
            width: 90%;
            max-width: 700px;
            margin: -150px auto 0;
            padding: 40px;
        }
        
        .hero-stats {
            flex-wrap: wrap;
        }
        
        .hero-features {
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .banner-container {
            height: 70vh;
            min-height: 600px;
        }
        
        .text-overlay-box.premium-card {
            margin: -100px auto 0;
            padding: 35px 30px;
        }
        
        .main-title.hero-title {
            font-size: 3rem;
        }
        
        .description.hero-description {
            font-size: 1.1rem;
        }
        
        .hero-action-buttons {
            flex-direction: column;
            gap: 15px;
        }
        
        .hero-btn {
            width: 100%;
            justify-content: center;
        }
        
        .hero-features {
            flex-direction: column;
            align-items: center;
        }
        
        .hero-feature-item {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 576px) {
        .text-overlay-box.premium-card {
            padding: 30px 25px;
            margin: -80px auto 0;
        }
        
        .main-title.hero-title {
            font-size: 2.5rem;
        }
        
        .hero-stat-number {
            font-size: 2.5rem;
        }
        
        .hero-promo-badge {
            padding: 15px 25px;
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }
    }

    /* ANIMACIONES PARA HOVER */
    @keyframes pulse-hover {
        0% { box-shadow: 0 10px 30px rgba(253, 184, 19, 0.4); }
        50% { box-shadow: 0 10px 40px rgba(253, 184, 19, 0.7); }
        100% { box-shadow: 0 10px 30px rgba(253, 184, 19, 0.4); }
    }

    @keyframes float-hover {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-5px); }
    }
    /* CUADRADO BLANCO MÁS COMPACTO */
.text-overlay-box.premium-card.compact-card {
    padding: 35px 40px !important;
    width: 500px !important;
    animation: floatBounce 6s ease-in-out infinite !important;
}

.compact-card .header-compact {
    margin-bottom: 15px !important;
}

/* Badge más pequeño */
.hero-badge.compact-badge {
    padding: 8px 20px !important;
    font-size: 0.9rem !important;
    margin-bottom: 10px !important;
}

/* Título más compacto */
.main-title.hero-title.compact-title {
    font-size: 3.8rem !important;
    margin-bottom: 15px !important;
    line-height: 1 !important;
}

.main-title.hero-title.compact-title::after {
    bottom: -8px !important;
    width: 50px !important;
    height: 3px !important;
}

/* Descripción más compacta */
.description.hero-description.compact-description {
    font-size: 1.1rem !important;
    line-height: 1.5 !important;
    margin: 15px 0 20px 0 !important;
    padding-right: 0 !important;
}

/* Botones más compactos */
.hero-action-buttons.compact-buttons {
    margin: 20px 0 15px 0 !important;
    gap: 12px !important;
}

.hero-btn.compact-btn {
    padding: 16px 30px !important;
    font-size: 1rem !important;
    gap: 10px !important;
}

.hero-btn.compact-btn i {
    font-size: 1.2rem !important;
}

/* Promo badge más compacto */
.hero-promo-badge.compact-promo {
    padding: 12px 25px !important;
    margin-top: 15px !important;
    gap: 10px !important;
}

.hero-promo-badge.compact-promo i {
    font-size: 1.6rem !important;
}

.hero-promo-badge.compact-promo span {
    font-size: 1rem !important;
}

/* Animación más rápida para elementos compactos */
.compact-card .animate-typing {
    animation: typingTitle 1.2s steps(30, end) !important;
}

.compact-card .animate-shake {
    animation: shakeButton 0.6s ease-in-out 0.5s !important;
}

/* Responsive para diseño compacto */
@media (max-width: 1200px) {
    .text-overlay-box.premium-card.compact-card {
        width: 450px !important;
        padding: 30px 35px !important;
        right: 40px !important;
    }
    
    .main-title.hero-title.compact-title {
        font-size: 3.2rem !important;
    }
}

@media (max-width: 992px) {
    .text-overlay-box.premium-card.compact-card {
        width: 85% !important;
        max-width: 550px !important;
        margin: -120px auto 0 !important;
        padding: 30px !important;
    }
    
    .main-title.hero-title.compact-title {
        font-size: 2.8rem !important;
    }
    
    .description.hero-description.compact-description {
        font-size: 1rem !important;
    }
}

@media (max-width: 768px) {
    .text-overlay-box.premium-card.compact-card {
        margin: -80px auto 0 !important;
        padding: 25px !important;
    }
    
    .main-title.hero-title.compact-title {
        font-size: 2.4rem !important;
    }
    
    .hero-action-buttons.compact-buttons {
        flex-direction: column !important;
        gap: 10px !important;
    }
    
    .hero-btn.compact-btn {
        width: 100% !important;
        justify-content: center !important;
    }
}

@media (max-width: 576px) {
    .text-overlay-box.premium-card.compact-card {
        padding: 20px !important;
        margin: -60px auto 0 !important;
    }
    
    .main-title.hero-title.compact-title {
        font-size: 2rem !important;
    }
    
    .hero-badge.compact-badge {
        padding: 6px 15px !important;
        font-size: 0.8rem !important;
    }
    
    .hero-promo-badge.compact-promo {
        padding: 10px 15px !important;
        flex-direction: column !important;
        text-align: center !important;
        gap: 8px !important;
    }
}
    </style>
</head>
<body>

<div class="products-page">

<!-- ================= HERO ANIMADO BOGATI ================= -->
<section class="main-banner-section">
    <div class="banner-container">
        <!-- Gradiente de fondo -->
        <div class="hero-gradient"></div>
        
        <!-- Elementos decorativos flotantes -->
        <div class="floating-elements">
            <div class="floating-element">🍦</div>
            <div class="floating-element">✨</div>
            <div class="floating-element">☕</div>
            <div class="floating-element">⭐</div>
        </div>
        
        <!-- Imagen principal -->
        <div class="banner-image">
            <img src="<?php echo base_url('imagenes/Horizontal-Helados.jpg'); ?>" alt="Bogati Heladería y Cafetería" class="banner-img" loading="lazy">
        </div>
        
        <!-- Cuadro blanco estilo carrusel -->
        <div class="text-overlay-box premium-card compact-card">
    <div class="text-content">
        <!-- Badge y título más compactos -->
        <div class="header-compact">
            <span class="hero-badge compact-badge animate-pulse">
                <i class="fas fa-crown"></i>
                EXPERIENCIA PREMIUM
            </span>
            
            <h1 class="main-title hero-title compact-title animate-typing">
                BOGATI<br>DELICIOSA
            </h1>
        </div>
        
        <!-- Descripción más compacta -->
        <p class="description hero-description compact-description">
            Descubre el <span class="brand-name">mundo Bogati</span>: donde la tradición 
            se encuentra con la innovación. Helados artesanales 100% naturales y 
            una cafetería con el auténtico sabor colombiano. 
            <strong>Cada bocado es una experiencia única.</strong>
        </p>
        
        <!-- Botones más compactos -->
        <div class="hero-action-buttons compact-buttons">
            <a href="#heladeria" class="hero-btn hero-btn-primary compact-btn animate-shake">
                <i class="fas fa-ice-cream"></i>
                <span>VER HELADERÍA</span>
            </a>
            
            <a href="#cafeteria" class="hero-btn hero-btn-secondary compact-btn animate-shake">
                <i class="fas fa-coffee"></i>
                <span>VER CAFETERÍA</span>
            </a>
        </div>
        
        <!-- Promo badge más compacto -->
        <div class="hero-promo-badge compact-promo animate-glow">
            <i class="fas fa-gift animate-spin-slow"></i>
            <span>¡20% DESCUENTO en tu primera visita!</span>
        </div>
    </div>
</div>
</section>

<!-- ============ FEATURES BAR ============ -->
<div class="features-bar slide-in">
  <div class="feature-item">
    <div class="feature-icon"><i class="fas fa-seedling"></i></div>
    <span>100% NATURAL</span>
  </div>
  <div class="feature-item">
    <div class="feature-icon"><i class="fas fa-heart"></i></div>
    <span>HECHO CON AMOR</span>
  </div>
  <div class="feature-item">
    <div class="feature-icon"><i class="fas fa-leaf"></i></div>
    <span>INGREDIENTES FRESCOS</span>
  </div>
  <div class="feature-item">
    <div class="feature-icon"><i class="fas fa-award"></i></div>
    <span>CALIDAD PREMIUM</span>
  </div>
</div>

<!-- ================= MAIN CONTENT ================= -->
<main class="products-main">

<!-- ===== HELADERÍA ===== -->
<section id="heladeria" class="product-section active-section">

  <div class="categories-grid container grid-3-columns">

    <!-- Clásicos -->
    <div class="product-category-card" data-aos="fade-up">
      <div class="category-image">
        <img src="../../imagenes/catalogo_heladeria1.png" alt="Helados Clásicos Bogati">
        <div class="category-overlay">
          <div class="overlay-content">
            <h3>CLÁSICOS</h3>
            <p>Los sabores que siempre amas</p>
          </div>
        </div>
      </div>
      <div class="category-details">
        <span><i class="fas fa-crown"></i> Más vendidos</span>
        <span><i class="fas fa-star"></i> 15 sabores</span>
      </div>
    </div>

    <!-- Especiales -->
    <div class="product-category-card" data-aos="fade-up" data-aos-delay="100">
      <div class="category-image">
        <img src="../../imagenes/catalogo_heladeria2.png" alt="Helados Especiales Bogati">
        <div class="category-overlay">
          <div class="overlay-content">
            <h3>ESPECIALES</h3>
            <p>Creaciones únicas</p>
          </div>
        </div>
      </div>
      <div class="category-details">
        <span><i class="fas fa-check-circle"></i> 12 sabores</span>
        <span><i class="fas fa-clock"></i> Siempre disponibles</span>
      </div>
    </div>

    <!-- Gourmet -->
    <div class="product-category-card" data-aos="fade-up" data-aos-delay="200">
      <div class="category-image">
        <img src="../../imagenes/catalogo_heladeria3.png" alt="Helados Gourmet Bogati">
        <div class="category-overlay">
          <div class="overlay-content">
            <h3>GOURMET</h3>
            <p>Sabores premium</p>
          </div>
        </div>
      </div>
      <div class="category-details">
        <span><i class="fas fa-gem"></i> Premium</span>
        <span><i class="fas fa-leaf"></i> Naturales</span>
      </div>
    </div>

  </div>
</section>

<!-- ===== CAFETERÍA ===== -->
<section id="cafeteria" class="product-section">
  <div class="container section-header">
  </div>

  <div class="categories-grid container grid-3-columns">

    <!-- Cafés Especiales -->
    <div class="product-category-card" data-aos="fade-up">
      <div class="category-image">
        <img src="../../imagenes/catalogo_cafeteria.png" alt="Cafés Especiales Bogati">
        <div class="category-overlay">
          <div class="overlay-content">
            <h3>CAFÉS ESPECIALES</h3>
            <p>Selección premium</p>
          </div>
        </div>
      </div>
      <div class="category-details">
        <span><i class="fas fa-mug-hot"></i> 8 variedades</span>
        <span><i class="fas fa-leaf"></i> Orgánico</span>
      </div>
    </div>

    <!-- Bebidas Frías -->
    <div class="product-category-card" data-aos="fade-up" data-aos-delay="100">
      <div class="category-image">
        <img src="../../imagenes/bebidas_frias.png" alt="Bebidas Frías Bogati">
        <div class="category-overlay">
          <div class="overlay-content">
            <h3>BEBIDAS FRÍAS</h3>
            <p>Refrescantes y deliciosas</p>
          </div>
        </div>
      </div>
      <div class="category-details">
        <span><i class="fas fa-snowflake"></i> Frías</span>
        <span><i class="fas fa-glass-whiskey"></i> 10 opciones</span>
      </div>
    </div>

   <!-- Repostería -->
<div class="product-category-card" data-aos="fade-up" data-aos-delay="200">
  <div class="category-image">
    <img src="../../imagenes/catalogo_reposteria.png" alt="Repostería Bogati">
    <div class="category-overlay">
      <div class="overlay-content">
        <h3>REPOSTERÍA</h3>
        <p>Dulces acompañamientos</p>
      </div>
    </div>
  </div>
  <div class="category-details">
    <span><i class="fas fa-cookie-bite"></i> Postres</span>
    <span><i class="fas fa-bread-slice"></i> Panadería</span>
  </div>
</div>
  </div>
</section>

</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animación de contadores
    const statNumbers = document.querySelectorAll('.hero-stat-number');
    
    statNumbers.forEach(stat => {
        const target = parseInt(stat.getAttribute('data-target'));
        animateCounter(stat, target);
    });
    
    function animateCounter(element, target) {
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            if (target === 100) {
                element.textContent = Math.floor(current) + '%';
            } else {
                element.textContent = Math.floor(current);
            }
        }, 40);
    }
    
    // Efecto de escritura para título
    const title = document.querySelector('.main-title.hero-title');
    title.style.animation = 'typingTitle 1.5s steps(30, end)';
    
    // Efecto hover en botones
    const buttons = document.querySelectorAll('.hero-btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.animation = 'pulse-hover 1.5s infinite';
        });
        btn.addEventListener('mouseleave', function() {
            this.style.animation = '';
        });
    });
    
    // Efecto de brillo en features
    const features = document.querySelectorAll('.hero-feature-item');
    features.forEach(feature => {
        feature.addEventListener('mouseenter', function() {
            this.style.animation = 'float-hover 2s infinite';
        });
        feature.addEventListener('mouseleave', function() {
            this.style.animation = '';
        });
    });

    // Navegación entre secciones (tu código original)
    const heladeriaBtn = document.querySelector('a[href="#heladeria"]');
    const cafeteriaBtn = document.querySelector('a[href="#cafeteria"]');

    const heladeriaSection = document.getElementById('heladeria');
    const cafeteriaSection = document.getElementById('cafeteria');

    heladeriaSection.classList.add('active-section');
    cafeteriaSection.classList.remove('active-section');

    if (heladeriaBtn) {
        heladeriaBtn.addEventListener('click', function(e) {
            e.preventDefault();
            heladeriaSection.classList.add('active-section');
            cafeteriaSection.classList.remove('active-section');
            scrollToSection(heladeriaSection);
        });
    }

    if (cafeteriaBtn) {
        cafeteriaBtn.addEventListener('click', function(e) {
            e.preventDefault();
            cafeteriaSection.classList.add('active-section');
            heladeriaSection.classList.remove('active-section');
            scrollToSection(cafeteriaSection);
        });
    }

    function scrollToSection(section) {
        window.scrollTo({
            top: section.offsetTop - 100,
            behavior: 'smooth'
        });
    }

    if (window.location.hash === '#cafeteria') {
        if (cafeteriaSection) cafeteriaSection.classList.add('active-section');
        if (heladeriaSection) heladeriaSection.classList.remove('active-section');
    }
});
</script>

</body>
</html>
