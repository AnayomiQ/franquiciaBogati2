<?php
require_once __DIR__ . '/../../config.php';      
require_once __DIR__ . '/../../includes/functions.php';  
require_once __DIR__ . '/../../db_connection.php';
?>

    <div class="products-page">
        <!-- ================= HERO ================= -->
        <section class="products-hero">
            <div class="hero-background">
                <div class="floating-icecream floating-1"><i class="fas fa-ice-cream"></i></div>
                <div class="floating-icecream floating-2"><i class="fas fa-mug-hot"></i></div>
            </div>

            <div class="container hero-content">
                <div class="hero-text animate-fade-up">
                    <h1 class="hero-title">
                        <span class="title-line">NUESTRA CARTA</span>
                        <span class="title-line highlight">DELICIOSA</span>
                    </h1>

                    <p class="hero-description">
                        Descubre el <span class="highlight-text">mundo Bogati</span>: helados artesanales
                        100% naturales y una cafetería con el mejor sabor.
                        <span class="highlight-text">Experiencias únicas</span> creadas con pasión.
                    </p>

                    <div class="hero-buttons">
                        <a href="#heladeria" class="hero-button primary">
                            <i class="fas fa-ice-cream"></i>
                            <span>Ver Helados</span>
                        </a>
                        <a href="#cafeteria" class="hero-button secondary">
                            <i class="fas fa-coffee"></i>
                            <span>Ver Cafetería</span>
                        </a>
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
                <div class="container section-header">
                    <h2 class="section-title">
                        <span class="title-main">HELADERÍA ARTESANAL</span>
                        <span class="title-sub">Deliciosamente buenos para ti</span>
                    </h2>
                    <p class="section-intro">Descubre nuestros helados artesanales elaborados con ingredientes 100% naturales</p>
                </div>

                <div class="categories-grid container grid-3-columns">
                    <!-- Clásicos -->
                    <div class="product-category-card" data-aos="fade-up">
                        <div class="category-image">
                            <img src="imagenes/catálogo_heladeria1.jpg" alt="Helados Clásicos Bogati">
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
                            <img src="imagenes/catálogo_heladeria2.jpg" alt="Helados Especiales Bogati">
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
                            <img src="imagenes/catálogo_heladeria3.jpg" alt="Helados Gourmet Bogati">
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
                    <div class="section-badge coffee-badge">
                        <i class="fas fa-coffee"></i>
                        <span>Cafetería Bogati</span>
                    </div>

                    <h2 class="section-title">
                        <span class="title-main">CAFETERÍA BOGATI</span>
                        <span class="title-sub">El mejor café para tu día a día</span>
                    </h2>
                    <p class="section-intro">Disfruta de nuestros cafés especiales preparados con granos selectos</p>
                </div>

                <div class="categories-grid container grid-3-columns">
                    <!-- Cafés Especiales -->
                    <div class="product-category-card" data-aos="fade-up">
                        <div class="category-image">
                            <img src="imagenes/catálogo_cafeteria.jpg" alt="Cafés Especiales Bogati">
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
                            <img src="imagenes/cafeteria_frios.jpg" alt="Bebidas Frías Bogati">
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
                            <img src="imagenes/cafeteria_reposteria.jpg" alt="Repostería Bogati">
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
        // Funcionalidad para mostrar/ocultar secciones
        document.addEventListener('DOMContentLoaded', function() {
            // Botones del hero
            const heladeriaBtn = document.querySelector('a[href="#heladeria"]');
            const cafeteriaBtn = document.querySelector('a[href="#cafeteria"]');
            
            // Secciones
            const heladeriaSection = document.getElementById('heladeria');
            const cafeteriaSection = document.getElementById('cafeteria');
            
            // Mostrar heladería por defecto
            heladeriaSection.classList.add('active-section');
            cafeteriaSection.classList.remove('active-section');
            
            // Eventos para los botones
            heladeriaBtn.addEventListener('click', function(e) {
                e.preventDefault();
                heladeriaSection.classList.add('active-section');
                cafeteriaSection.classList.remove('active-section');
                scrollToSection(heladeriaSection);
            });
            
            cafeteriaBtn.addEventListener('click', function(e) {
                e.preventDefault();
                cafeteriaSection.classList.add('active-section');
                heladeriaSection.classList.remove('active-section');
                scrollToSection(cafeteriaSection);
            });
            
            // Función para scroll suave
            function scrollToSection(section) {
                window.scrollTo({
                    top: section.offsetTop - 100,
                    behavior: 'smooth'
                });
            }
            
            // Detectar hash en URL
            if (window.location.hash === '#cafeteria') {
                cafeteriaSection.classList.add('active-section');
                heladeriaSection.classList.remove('active-section');
            }
        });
    </script>
</body>
</html>