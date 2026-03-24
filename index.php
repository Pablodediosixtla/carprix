<?php
// Lógica de Temporada para fondo
$mes_actual = date('n'); 
$imagen_temporada = "hero-default.jpg"; 

if ($mes_actual == 12) {
    $imagen_temporada = "hero-navidad.jpg";
} elseif ($mes_actual >= 6 && $mes_actual <= 8) {
    $imagen_temporada = "hero-verano.jpg";
}

// Datos de autos (3 destacados)
$autos = [
    ["marca" => "BMW", "modelo" => "Serie 3", "precio" => "549,000", "año" => 2021, "km" => "35,000 km", "img" => "https://images.unsplash.com/photo-1555215695-3004980ad54e?q=80&w=400"],
    ["marca" => "Audi", "modelo" => "Q5", "precio" => "720,000", "año" => 2022, "km" => "12,000 km", "img" => "https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?q=80&w=400"],
    ["marca" => "Mazda", "modelo" => "CX-5", "precio" => "415,000", "año" => 2019, "km" => "58,000 km", "img" => "https://images.unsplash.com/photo-1532581133564-9ca19d086050?q=80&w=400"],
];

// Datos de Reseñas
$reseñas = [
    ["nombre" => "Pablo Ojeda", "fecha" => "02 enero 2025", "comentario" => "La atención es amable y eficiente. Sin duda volvería a CARPRIX.", "avatar" => "https://i.pravatar.cc/150?u=pablo"],
    ["nombre" => "Karla Moreno", "fecha" => "13 febrero 2023", "comentario" => "Lugar ideal para comprar mi primer auto. Mil opciones y todo transparente.", "avatar" => "https://i.pravatar.cc/150?u=karla"],
    ["nombre" => "Román Montero", "fecha" => "22 noviembre 2022", "comentario" => "Te atienden bien, me pagaron excelente por mi auto usado.", "avatar" => "https://i.pravatar.cc/150?u=roman"],
    ["nombre" => "Diego Flores", "fecha" => "22 abril 2024", "comentario" => "Gestión impecable. El financiamiento fue mucho más rápido de lo esperado.", "avatar" => "https://i.pravatar.cc/150?u=diego"]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARPRIX | Confianza que te mueve</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <nav class="container nav-flex">
            <div class="logo"><span class="white-text">CAR</span><span class="green-text">PRIX</span></div>
            
            <ul class="nav-menu" id="nav-menu">
                <li><a href="#">Compra</a></li>
                <li><a href="#">Vende</a></li>
                <li><a href="#">Nosotros</a></li>
                <li><a href="#">Contacto</a></li>
                <li><a href="#" class="btn-outline">Iniciar Sesión</a></li>
            </ul>

            <div class="menu-toggle" id="mobile-menu">
                <i class="fas fa-bars"></i>
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
                <form action="#" class="search-form">
                    <select name="marca">
                        <option value="">Marca</option>
                        <option value="bmw">BMW</option>
                        <option value="audi">Audi</option>
                    </select>
                    <input type="number" placeholder="Presupuesto máx.">
                    <button type="submit" class="btn-search">BUSCAR</button>
                </form>
            </div>
        </div>
    </section>

    <section class="container section-padding">
        <h2 class="section-title">Autos destacados</h2>
        <div class="car-grid">
            <?php foreach($autos as $auto): ?>
            <div class="car-card">
                <div class="car-img"><img src="<?php echo $auto['img']; ?>" alt="Auto"><span class="year-badge"><?php echo $auto['año']; ?></span></div>
                <div class="car-info">
                    <h3><?php echo $auto['marca'] . " " . $auto['modelo']; ?></h3>
                    <p class="km"><?php echo $auto['km']; ?></p>
                    <p class="price">$<?php echo $auto['precio']; ?></p>
                    <a href="#" class="btn-details">Ver detalles</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="view-all-container">
            <a href="#" class="btn-view-all">Ver catálogo completo <i class="fas fa-arrow-right"></i></a>
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
            <div class="footer-col">
                <h4 class="footer-title title-green">Conoce más</h4>
                <ul>
                    <li><a href="#">¿Quiénes Somos?</a></li>
                    <li><a href="#">Vende tu auto</a></li>
                    <li><a href="#">Sucursales</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4 class="footer-title title-white">Legales</h4>
                <ul>
                    <li><a href="#">Aviso de privacidad</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4 class="footer-title title-grey">Ayuda</h4>
                <ul>
                    <li><a href="#">Contacto</a></li>
                    <li><a href="#">Preguntas frecuentes</a></li>
                </ul>
            </div>
            <div class="footer-col footer-right">
                <a href="#" class="back-to-home">Regresa al inicio <i class="fas fa-chevron-up"></i></a>
            </div>
        </div>
        <div class="footer-bottom container">
            <p>&copy; <?php echo date('Y'); ?> <span class="white-text">CAR</span><span class="green-text">PRIX</span>. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/scripts.js"></script>
</body>
</html>