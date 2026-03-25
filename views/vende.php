<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vende tu Auto | CARPRIX</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/vende.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-dark">

    <header class="main-header">
        <nav class="container nav-flex">
            <div class="logo"><a href="../index.php" style="text-decoration:none;"><span class="white-text">CAR</span><span class="green-text">PRIX</span></a></div>
            
            <ul class="nav-menu" id="nav-menu">
                <li><a href="catalogo.php">Compra</a></li>
                <li><a href="vende.php" class="green-text">Vende</a></li>
                <li><a href="nosotros.php">Nosotros</a></li>
                <li><a href="contacto.php">Contacto</a></li>
                <li><a href="#" class="btn-outline">Iniciar Sesión</a></li>
            </ul>

            <div class="menu-toggle" id="mobile-menu">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
    </header>

    <main class="container vende-main">
        <section class="vende-header">
            <h1>Vender tu auto <span class="green-text">nunca fue tan fácil</span></h1>
            <p>Obtén una oferta justa y segura por tu vehículo hoy mismo.</p>
        </section>

        <div class="vende-container">
            <form id="form-vende" class="vende-card">
                <div class="form-section">
                    <h3><i class="fas fa-car green-text"></i> Datos del Vehículo</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Marca</label>
                            <input type="text" id="v-marca" placeholder="Ej. Toyota" required>
                        </div>
                        <div class="form-group">
                            <label>Modelo y Versión</label>
                            <input type="text" id="v-modelo" placeholder="Ej. Camry SE" required>
                        </div>
                        <div class="form-group">
                            <label>Año</label>
                            <input type="number" id="v-anio" placeholder="2022" required>
                        </div>
                        <div class="form-group">
                            <label>Kilometraje</label>
                            <input type="number" id="v-km" placeholder="45000" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-user green-text"></i> Datos de Contacto</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre Completo</label>
                            <input type="text" id="v-nombre" placeholder="Tu nombre" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono (WhatsApp)</label>
                            <input type="tel" id="v-tel" placeholder="33 1234 5678" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Comentarios Adicionales (Estado, choques, adeudos)</label>
                    <textarea id="v-comentarios" rows="4" placeholder="Cuéntanos más sobre el estado de tu auto..."></textarea>
                </div>

                <button type="submit" class="btn-submit-vende" id="btn-vende">ENVIAR SOLICITUD</button>
                <div id="vende-mensaje" class="form-status"></div>
            </form>

            <aside class="vende-info">
                <div class="info-card">
                    <h4>La experiencia CARPRIX</h4>
                    <ul>
                        <li><i class="fas fa-bolt green-text"></i> Valuación rápida y profesional</li>
                        <li><i class="fas fa-shield-alt green-text"></i> Transacción 100% segura</li>
                        <li><i class="fas fa-file-contract green-text"></i> Sin trámites complicados</li>
                    </ul>
                </div>
                <div class="info-img">
                    <img src="https://images.unsplash.com/photo-1552519507-da3b142c6e3d?q=80&w=600" alt="Vende tu auto">
                </div>
            </aside>
        </div>
    </main>

    <footer class="main-footer">
        <div class="container footer-grid">
            <div class="footer-col">
                <h4 class="footer-title title-green">Conoce más</h4>
                <ul>
                    <li><a href="#">¿Quiénes Somos?</a></li>
                    <li><a href="vende.php">Vende tu auto</a></li>
                    <li><a href="ubicaciones.php">Sucursales</a></li>
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

    <script src="../js/vende.js"></script>
</body>
</html>