<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto | CARPRIX</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/contacto.css">
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
                <li><a href="contacto.php" class="green-text">Contacto</a></li>
                <li><a href="#" class="btn-outline">Iniciar Sesión</a></li>
            </ul>

            <div class="menu-toggle" id="mobile-menu">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
    </header>

    <main class="container contacto-main">
        <section class="contacto-header">
            <h1>Estamos para <span class="green-text">ayudarte</span></h1>
            <p>¿Tienes dudas sobre un vehículo o el proceso de compra? Escríbenos.</p>
        </section>

        <div class="contacto-grid">
            <aside class="contacto-info">
                <div class="info-item-card">
                    <i class="fas fa-phone-alt"></i>
                    <div>
                        <h4>Llámanos o WhatsApp</h4>
                        <p><a href="tel:3333333333" class="link-info">33 3333 3333</a></p>
                    </div>
                </div>

                <div class="info-item-card">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h4>Correo Electrónico</h4>
                        <p><a href="mailto:atcontacto@carprix.com.mx" class="link-info">atcontacto@carprix.com.mx</a></p>
                    </div>
                </div>

                <div class="info-item-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <h4>Visítanos</h4>
                        <p>Av. Principal #123, Guadalajara, Jalisco.</p>
                    </div>
                </div>

                <div class="map-placeholder">
                    <img src="https://images.unsplash.com/photo-1526778548025-fa2f459cd5c1?q=80&w=600" alt="Mapa Ubicación">
                </div>
            </aside>

            <div class="contacto-form-container">
                <form id="form-contacto" class="contacto-card">
                    <h3>Envíanos un mensaje</h3>
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" id="c-nombre" placeholder="Tu nombre" required>
                    </div>
                    <div class="form-group">
                        <label>Correo Electrónico</label>
                        <input type="email" id="c-email" placeholder="correo@ejemplo.com" required>
                    </div>
                    <div class="form-group">
                        <label>Asunto</label>
                        <select id="c-asunto" class="input-select-dark">
                            <option value="Informes">Informes sobre comprar un auto</option>
                            <option value="Venta">Duda sobre vender mi auto</option>
                            <option value="Queja">Sugerencia</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mensaje</label>
                        <textarea id="c-mensaje" rows="5" placeholder="¿En qué podemos apoyarte?" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit-contacto" id="btn-contacto">ENVIAR MENSAJE</button>
                    <div id="status-contacto" class="form-status"></div>
                </form>
            </div>
        </div>
    </main>

    <footer class="main-footer">
        <div class="container footer-grid">
            <div class="footer-col"><h4 class="footer-title title-green">Conoce más</h4><ul><li><a href="#">¿Quiénes Somos?</a></li><li><a href="vende.php">Vende tu auto</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-white">Legales</h4><ul><li><a href="#">Aviso de privacidad</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-grey">Ayuda</h4><ul><li><a href="contacto.php">Contacto</a></li><li><a href="nosotros.php">Preguntas frecuentes</a></li></ul></div>
            <div class="footer-col footer-right"><a href="#" class="back-to-home">Regresa al inicio <i class="fas fa-chevron-up"></i></a></div>
        </div>
        <div class="footer-bottom container"><p>&copy; <?php echo date('Y'); ?> <span class="white-text">CAR</span><span class="green-text">PRIX</span>. Todos los derechos reservados.</p></div>
    </footer>

    <script src="../js/contacto.js"></script>
</body>
</html>