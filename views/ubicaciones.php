<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sucursales | CARPRIX</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/ubicaciones.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-dark">

    <header class="main-header">
        <nav class="container nav-flex">
            <div class="logo"><a href="../index.php" style="text-decoration:none;"><span class="white-text">CAR</span><span class="green-text">PRIX</span></a></div>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="catalogo.php">Compra</a></li>
                <li><a href="vende.php">Vende</a></li>
                <li><a href="nosotros.php">Nosotros</a></li>
                <li><a href="contacto.php">Contacto</a></li>
                <li><a href="#" class="btn-outline">Iniciar Sesión</a></li>
            </ul>
            <div class="menu-toggle" id="mobile-menu"><i class="fas fa-bars"></i></div>
        </nav>
    </header>

    <main class="container ubicaciones-main">
        <section class="ubicaciones-header">
            <h1>Nuestras <span class="green-text">Sucursales</span></h1>
            <p>Visítanos en cualquiera de nuestros puntos de venta oficiales y recibe atención personalizada.</p>
        </section>

        <div class="sucursales-grid">
            
            <div class="sucursal-card">
                <div class="sucursal-img">
                    <img src="https://images.unsplash.com/photo-1605330022464-e6962f3a699c?q=80&w=600" alt="Sucursal Guadalajara">
                </div>
                <div class="sucursal-info">
                    <h3>Guadalajara</h3>
                    <p><i class="fas fa-map-marker-alt green-text"></i> Av. López Mateos Sur #4500, Zapopan, Jalisco.</p>
                    <p><i class="fas fa-phone-alt green-text"></i> 33 3333 3333</p>
                    <p><i class="fas fa-clock green-text"></i> Lun - Vie: 9:00 - 19:00 | Sáb: 10:00 - 15:00</p>
                </div>
            </div>

            <div class="sucursal-card">
                <div class="sucursal-img">
                    <img src="https://images.unsplash.com/photo-1586523179299-81347895e7c8?q=80&w=600" alt="Sucursal Zacatecas">
                </div>
                <div class="sucursal-info">
                    <h3>Zacatecas</h3>
                    <p><i class="fas fa-map-marker-alt green-text"></i> Blvd. El Bote 202, Colonia Ciudad Argentum, 98040 Zacatecas, Zac.</p>
                    <p><i class="fas fa-phone-alt green-text"></i> 49 2333 3333</p>
                    <p><i class="fas fa-clock green-text"></i> Lun - Vie: 9:00 - 18:30 | Sáb: 10:00 - 14:00</p>
                </div>
            </div>

            <div class="sucursal-card">
                <div class="sucursal-img">
                    <img src="https://images.unsplash.com/photo-1600867727181-432fc2cd1031?q=80&w=600" alt="Sucursal Tamaulipas">
                </div>
                <div class="sucursal-info">
                    <h3>Tamaulipas</h3>
                    <p><i class="fas fa-map-marker-alt green-text"></i> Av. Ejército Mexicano 706, Colonias Primavera, 89130 Tampico, Tamps.</p>
                    <p><i class="fas fa-phone-alt green-text"></i> 83 3333 3333</p>
                    <p><i class="fas fa-clock green-text"></i> Lun - Vie: 9:00 - 19:00 | Sáb: 10:00 - 15:00</p>
                </div>
            </div>

            <div class="sucursal-card">
                <div class="sucursal-img">
                    <img src="https://images.unsplash.com/photo-1518105779142-d975f22f1b0a?q=80&w=600" alt="Sucursal CDMX">
                </div>
                <div class="sucursal-info">
                    <span class="city-tag">Matriz</span>
                    <h3>CDMX</h3>
                    <p><i class="fas fa-map-marker-alt green-text"></i>Vasco de Quiroga 3800, Santa Fe, Contadero, Cuajimalpa de Morelos, 05348 Ciudad de México, CDMX</p>
                    <p><i class="fas fa-phone-alt green-text"></i> 55 3333 3333</p>
                    <p><i class="fas fa-clock green-text"></i> Lun - Vie: 9:00 - 20:00 | Sáb: 10:00 - 16:00</p>
                </div>
            </div>

            <div class="sucursal-card">
                <div class="sucursal-img">
                    <img src="https://images.unsplash.com/photo-1584466977773-e625c37cdd50?q=80&w=600" alt="Sucursal Querétaro">
                </div>
                <div class="sucursal-info">
                    <h3>Querétaro</h3>
                    <p><i class="fas fa-map-marker-alt green-text"></i> Blvd. Bernardo Quintana #200, Santiago de Querétaro.</p>
                    <p><i class="fas fa-phone-alt green-text"></i> 44 2333 3333</p>
                    <p><i class="fas fa-clock green-text"></i> Lun - Vie: 9:30 - 19:00 | Sáb: 10:00 - 15:00</p>
                </div>
            </div>

        </div>
    </main>

    <footer class="main-footer">
        <div class="container footer-grid">
            <div class="footer-col"><h4 class="footer-title title-green">Conoce más</h4><ul><li><a href="nosotros.php">¿Quiénes Somos?</a></li><li><a href="vende.php">Vende tu auto</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-white">Legales</h4><ul><li><a href="#">Aviso de privacidad</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-grey">Ayuda</h4><ul><li><a href="contacto.php">Contacto</a></li><li><a href="ubicaciones.php">Sucursales</a></li></ul></div>
            <div class="footer-col footer-right"><a href="../index.php" class="back-to-home">Regresa al inicio <i class="fas fa-chevron-up"></i></a></div>
        </div>
        <div class="footer-bottom container"><p>© <?php echo date('Y'); ?> <span class="white-text">CAR</span><span class="green-text">PRIX</span>. Todos los derechos reservados.</p></div>
    </footer>

    <script src="../js/ubicaciones.js"></script>
</body>
</html>