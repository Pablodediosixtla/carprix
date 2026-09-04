<?php
// Lógica de Temporada para fondo
$mes_actual = date('n'); 
$imagen_temporada = "hero-default.jpg"; 

if ($mes_actual == 12) {
    $imagen_temporada = "hero-navidad.jpg";
} elseif ($mes_actual >= 6 && $mes_actual <= 8) {
    $imagen_temporada = "hero-verano.jpg";
}

// Datos de Reseñas (Por ahora estáticos)
$reseñas = [
    ["nombre" => "Pablo Ojeda", "fecha" => "02 enero 2026", "comentario" => "La atención es amable y eficiente. Sin duda volvería a CARPRIX.", "avatar" => "https://i.pravatar.cc/150?u=pablo"],
    ["nombre" => "Karla Moreno", "fecha" => "13 febrero 2026", "comentario" => "Lugar ideal para comprar mi primer auto. Mil opciones y todo transparente.", "avatar" => "https://i.pravatar.cc/150?u=karla"],
    ["nombre" => "Román Montero", "fecha" => "22 noviembre 2025", "comentario" => "Te atienden bien, me pagaron excelente por mi auto usado.", "avatar" => "https://i.pravatar.cc/150?u=roman"],
    ["nombre" => "Diego Flores", "fecha" => "22 abril 2025", "comentario" => "Gestión impecable. El financiamiento fue mucho más rápido de lo esperado.", "avatar" => "https://i.pravatar.cc/150?u=diego"]
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark" data-theme-locked="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a1a1a">
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicon-180.png">
    <script src="js/theme.js?v=20260803-3"></script>
    <title>CARPRIX | Confianza que te mueve</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=20260803-3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <nav class="container nav-flex">
            <div class="logo">
                <a href="index.php" class="brand-logo-link" aria-label="CARPRIX - Inicio">
                    <img class="brand-logo brand-logo-full theme-logo theme-logo-dark" src="img/brand/logo-carprix-dark.svg" alt="CARPRIX - Confianza que te mueve">
                    <img class="brand-logo brand-logo-full theme-logo theme-logo-light" src="img/brand/logo-carprix-light.svg" alt="CARPRIX - Confianza que te mueve">
                    <img class="brand-logo brand-logo-mobile theme-logo theme-logo-dark" src="img/brand/logo-carprix-wordmark-dark.svg" alt="CARPRIX">
                    <img class="brand-logo brand-logo-mobile theme-logo theme-logo-light" src="img/brand/logo-carprix-wordmark-light.svg" alt="CARPRIX">
                </a>
            </div>
            
            <ul class="nav-menu" id="nav-menu">
                <li><a href="views/catalogo.php">Compra</a></li>
                <li><a href="views/vende.php">Vende</a></li>
                <li><a href="views/nosotros.php">Nosotros</a></li>
                <li><a href="views/contacto.php">Contacto</a></li>
                <li><a href="operativo/login.php" class="btn-outline" data-operativo-access>Iniciar Sesión</a></li>
            </ul>
            <div class="nav-actions">
                <div class="menu-toggle" id="mobile-menu"><i class="fas fa-bars"></i></div>
            </div>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-bg-container">
            <div class="hero-slide active" style="background-image: url('img/<?php echo $imagen_temporada; ?>');"></div>
            <div class="hero-slide" style="background-image: url('img/hero-default.jpg');"></div>
            <div class="hero-slide" style="background-image: url('img/hero-verano.jpg');"></div>
        </div>
        <div class="hero-overlay"></div>
        <div class="container hero-content">
            <h1>Encuentra tu próximo seminuevo</h1>
            <p class="brand-accent">CONFIANZA QUE TE MUEVE</p>
            
            <div class="search-box">
                <form action="views/catalogo.php" method="GET" class="search-form">
                    <select name="marca" id="index-marca">
                        <option value="">Marca</option>
                    </select>
                    <input type="number" name="presupuesto" placeholder="Presupuesto máx.">
                    <button type="submit" class="btn-search">BUSCAR</button>
                </form>
            </div>
        </div>
    </section>

    <section class="container section-padding">
        <h2 class="section-title">Autos destacados</h2>
        <div class="car-grid" id="car-grid">
            <p style="text-align: center; grid-column: 1 / -1; color: var(--gray-text);">Cargando inventario...</p>
        </div>
        <div class="view-all-container">
            <a href="views/catalogo.php" class="btn-view-all">Ver catálogo completo <i class="fas fa-arrow-right"></i></a>
        </div>
    </section>

    <section class="reviews-section">
        <div class="container">
            <h2 class="section-title-left">Lo que opinan nuestros clientes</h2>
        </div>
        <div class="reviews-slider">
            <div class="reviews-track">
                <?php for($i=0; $i<2; $i++): ?>
                    <?php foreach($reseñas as $reseña): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <img src="<?php echo $reseña['avatar']; ?>" alt="User" class="avatar">
                            <div class="user-meta">
                                <strong><?php echo $reseña['nombre']; ?></strong>
                                <span><?php echo $reseña['fecha']; ?></span>
                            </div>
                        </div>
                        <p class="review-text"><?php echo $reseña['comentario']; ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <footer class="main-footer">
        <div class="container footer-grid">
            <div class="footer-col"><h4 class="footer-title title-green">Conoce más</h4><ul><li><a href="views/nosotros.php">¿Quiénes Somos?</a></li><li><a href="views/vende.php">Vende tu auto</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-white">Legales</h4><ul><li><a href="views/aviso-privacidad.php">Aviso de privacidad</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-grey">Ayuda</h4><ul><li><a href="views/contacto.php">Contacto</a></li><li><a href="views/nosotros.php">Preguntas frecuentes</a></li></ul></div>
            <div class="footer-col footer-right"><a href="#" class="back-to-home">Regresa al inicio <i class="fas fa-chevron-up"></i></a></div>
        </div>
        <div class="footer-bottom container">
            <a href="index.php" class="footer-brand-link" aria-label="CARPRIX - Inicio">
                <img src="img/brand/logo-carprix-dark.svg" alt="CARPRIX - Confianza que te mueve" class="footer-brand-logo">
            </a>
            <p>&copy; <?php echo date('Y'); ?> CARPRIX. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/public-operativo-session.js?v=20260810-3"></script>
    <script src="js/scripts.js?v=20260828-1"></script>
</body>
</html>