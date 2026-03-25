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
                    <img src="https://images.unsplash.com/photo-1582650809081-08630013349a?q=80&w=600" alt="Sucursal Guadalajara">
                </div>
                <div class="sucursal-info">
                    <span class="city-tag">Matriz</span>
                    <h3>Guadalajara</h3>
                    <p><i class="fas fa-map-marker-alt green-text"></i> Av. López Mateos Sur #4500, Zapopan, Jalisco.</p>
                    <p><i class="fas fa-phone-alt green-text"></i> 33 3333 3333</p>
                    <p><i class="fas fa-clock green-text"></i> Lun - Vie: 9:00 - 19:00 | Sáb: 10:00 - 15:00</p>
                    <a href="https://maps.google.com" target="_blank" class="btn-map"><i class="fas fa-directions"></i> CÓMO LLEGAR</a>
                </div>
            </div>

            <div class="sucursal-card">
                <div class="sucursal-img">
                    <img src="https://images.unsplash.com/photo-1568248590623-2895f87b3281?q=80&w=600" alt="Sucursal Morelia">
                </div>
                <div class="sucursal-info">
                    <h3>Morelia</h3>
                    <p><i class="fas fa-map-marker-alt green-text"></i> Perif. Paseo de la República #1500, Morelia, Michoacán.</p>
                    <p><i class="fas fa-phone-alt green-text"></i> 44 3522 7213</p>
                    <p><i class="fas fa-clock green-text"></i> Lun - Vie: 9:00 - 18:30 | Sáb: 10:00 - 14:00</p>
                    <a href="https://maps.google.com" target="_blank" class="btn-map"><i class="fas fa-directions"></i> CÓMO LLEGAR</a>
                </div>
            </div>

            <div class="sucursal-card">
                <div class="sucursal-img">
                    <img src="https://images.unsplash.com/photo-1518780664697-55e3ad937233?q=80&w=600" alt="Sucursal CDMX">
                </div>
                <div class="sucursal-info">
                    <h3>CDMX</h3>
                    <p><i class="fas fa-map-marker-alt green-text"></i> Av. Insurgentes Sur #2450, Ciudad de México.</p>
                    <p><i class="fas fa-phone-alt green-text"></i> 55 3333 3333</p>
                    <p><i class="fas fa-clock green-text"></i> Lun - Vie: 9:00 - 20:00 | Sáb: 10:00 - 16:00</p>
                    <a href="https://maps.google.com" target="_blank" class="btn-map"><i class="fas fa-directions"></i> CÓMO LLEGAR</a>
                </div>
            </div>

            <div class="sucursal-card">
                <div class="sucursal-img">
                    <img src="https://images.unsplash.com/photo-1512917774080-9991f1c4c750?q=80&w=600" alt="Sucursal Querétaro">
                </div>
                <div class="sucursal-info">
                    <h3>Querétaro</h3>
                    <p><i class="fas fa-map-marker-alt green-text"></i> Blvd. Bernardo Quintana #200, Santiago de Querétaro.</p>
                    <p><i class="fas fa-phone-alt green-text"></i> 44 2333 3333</p>
                    <p><i class="fas fa-clock green-text"></i> Lun - Vie: 9:30 - 19:00 | Sáb: 10:00 - 15:00</p>
                    <a href="https://maps.google.com" target="_blank" class="btn-map"><i class="fas fa-directions"></i> CÓMO LLEGAR</a>
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
        <div class="footer-bottom container"><p>&copy; <?php echo date('Y'); ?> <span class="white-text">CAR</span><span class="green-text">PRIX</span>. Todos los derechos reservados.</p></div>
    </footer>

    <script src="../js/ubicaciones.js"></script>
</body>
</html>