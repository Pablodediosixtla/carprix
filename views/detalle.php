<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a1a1a">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="../img/favicon-180.png">
    <script src="../js/theme.js?v=20260803-3"></script>
    <title>Detalle del Auto | CARPRIX</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css?v=20260803-3">
    <link rel="stylesheet" href="../css/detalle.css?v=20260904-1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-dark">

    <header class="main-header">
        <nav class="container nav-flex">
            <div class="logo">
                <a href="../index.php" class="brand-logo-link" aria-label="CARPRIX - Inicio">
                    <img class="brand-logo brand-logo-full theme-logo theme-logo-dark" src="../img/brand/logo-carprix-dark.svg" alt="CARPRIX - Confianza que te mueve">
                    <img class="brand-logo brand-logo-full theme-logo theme-logo-light" src="../img/brand/logo-carprix-light.svg" alt="CARPRIX - Confianza que te mueve">
                    <img class="brand-logo brand-logo-mobile theme-logo theme-logo-dark" src="../img/brand/logo-carprix-wordmark-dark.svg" alt="CARPRIX">
                    <img class="brand-logo brand-logo-mobile theme-logo theme-logo-light" src="../img/brand/logo-carprix-wordmark-light.svg" alt="CARPRIX">
                </a>
            </div>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="catalogo.php">Compra</a></li>
                <li><a href="vende.php">Vende</a></li>
                <li><a href="nosotros.php">Nosotros</a></li>
                <li><a href="contacto.php">Contacto</a></li>
                <li><a href="../operativo/login.php" class="btn-outline" data-operativo-access>Iniciar Sesión</a></li>
            </ul>
            <div class="nav-actions">
                <button type="button" class="theme-toggle" id="theme-toggle" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
                    <i class="fas fa-sun" aria-hidden="true"></i>
                    <span class="sr-only">Cambiar tema</span>
                </button>
                <div class="menu-toggle" id="mobile-menu"><i class="fas fa-bars"></i></div>
            </div>
        </nav>
    </header>

    <main class="container detail-container-main">
        <div id="loading-state" style="text-align: center; padding: 100px 0; color: var(--green-accent); font-size: 20px; font-weight: bold;">
            <i class="fas fa-circle-notch fa-spin"></i> Cargando información del vehículo...
        </div>

        <div class="detail-wrapper" id="detail-content" style="display: none;">
            <div class="detail-content-left">
                <section class="mobile-vehicle-card" aria-live="polite" aria-label="Resumen del vehículo">
                    <p class="mobile-card-eyebrow">AUTO CERTIFICADO</p>
                    <div class="mobile-card-heading">
                        <h1 class="mobile-card-name" id="mobile-det-name"></h1>
                        <span class="mobile-card-id" id="mobile-det-id"></span>
                    </div>
                    <p class="mobile-card-price" id="mobile-det-price"></p>
                </section>

                <div class="gallery-container">
                    <div class="main-img-wrapper">
                        <div class="image-stage">
                            <div id="status-overlay" class="status-overlay" style="display: none;">
                                <span id="status-badge" class="status-badge"></span>
                            </div>

                            <img id="main-view" src="" alt="Auto Principal" tabindex="0">
                            <button type="button" id="open-lightbox" class="image-zoom-trigger" aria-label="Ver fotografía completa">
                                <i class="fas fa-expand" aria-hidden="true"></i>
                            </button>

                            <span id="badge-id" class="detail-id-badge"></span>
                            <span id="badge-year" class="year-badge"></span>
                            <span id="badge-tipo" class="type-badge" style="display:none;"></span>
                        </div>
                    </div>

                    <div class="gallery-thumbs" id="gallery-thumbs">
                        </div>
                </div>

                <div class="technical-info">
                    <h2 class="title-spec">Especificaciones técnicas</h2>
                    <div class="specs-box-grid" id="specs-grid">
                        </div>
                </div>

                <div class="cotizador-container web-only">
                    <h2 class="title-spec">Cotizador de Financiamiento</h2>
                    <div class="cotizador-card">
                        <div class="cotizador-controls">
                            <div class="input-group">
                                <label>Porcentaje de Enganche</label>
                                <input type="range" id="range-enganche" min="10" max="80" step="5" value="20">
                                <div class="enganche-info">
                                    <p>Seleccionado: <span id="display-percent" class="green-text">20%</span></p>
                                    <p>Monto: <span id="display-total-enganche" class="white-text">$0</span></p>
                                </div>
                            </div>
                            <div class="input-group">
                                <label>Plazo (Meses)</label>
                                <select id="input-plazo" class="input-select-dark">
                                    <option value="12">12 meses</option>
                                    <option value="24">24 meses</option>
                                    <option value="36">36 meses</option>
                                    <option value="48">48 meses</option>
                                    <option value="60" selected>60 meses</option>
                                    <option value="72">72 meses</option>
                                </select>
                            </div>
                            <button id="btn-calculate" class="btn-calculate">CALCULAR</button>
                        </div>

                        <div id="resultado-cotizacion" class="cotizacion-tabla-wrapper">
                            <h3 class="table-title">Plan de pagos estimado</h3>
                            <div class="table-scroll">
                                <table class="tabla-finanzas">
                                    <thead><tr><th>Mes</th><th>Pago Mensual</th><th>Saldo Restante</th></tr></thead>
                                    <tbody id="tabla-body"></tbody>
                                </table>
                            </div>
                        </div>
                        <p class="disclaimer-cotizador"><i class="fas fa-exclamation-triangle"></i> Cotización de carácter informativo. Los precios y tarifas están sujetos a cambios sin previo aviso.</p>
                    </div>
                </div>
            </div>

            <aside class="detail-sidebar">
                <div class="action-card">
                    <span class="label-new" id="det-badge">RECIÉN PUBLICADO</span>
                    <h1 class="car-name" id="det-name">Cargando...</h1>
                    <div class="price-section">
                        <p class="price-tag" id="det-price">$0</p>
                        <small class="price-info">Precio de lista final</small>
                    </div>
                    
                    <button class="btn-buy" id="btn-apartar" type="button" disabled style="cursor: default;" aria-disabled="true" title="Inicia sesión operativa para solicitar el apartado.">DISPONIBLE</button>
                    
                    <div class="safety-badges">
                        <p><i class="fas fa-check-circle green-text"></i> Auto inspeccionado</p>
                        <p><i class="fas fa-file-invoice-dollar green-text"></i> Sin adeudos</p>
                        <p><i class="fas fa-shield-alt green-text"></i> Garantía Mecánica</p>
                        <p><i class="fas fa-users green-text"></i> <span id="det-duenos">Cargando...</span></p>
                    </div>
                </div>
            </aside>
        </div>
    </main>


    <div class="image-lightbox" id="image-lightbox" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-label="Galería de fotografías">
        <button type="button" class="lightbox-close" id="lightbox-close" aria-label="Cerrar galería">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
        <button type="button" class="lightbox-nav lightbox-prev" id="lightbox-prev" aria-label="Fotografía anterior">
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>
        <figure class="lightbox-figure">
            <img id="lightbox-image" src="" alt="Fotografía ampliada del vehículo">
            <figcaption id="lightbox-counter"></figcaption>
        </figure>
        <button type="button" class="lightbox-nav lightbox-next" id="lightbox-next" aria-label="Fotografía siguiente">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
    </div>

    <footer class="main-footer">
        <div class="container footer-grid">
            <div class="footer-col"><h4 class="footer-title title-green">Conoce más</h4><ul><li><a href="#">¿Quiénes Somos?</a></li><li><a href="vende.php">Vende tu auto</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-white">Legales</h4><ul><li><a href="#">Aviso de privacidad</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-grey">Ayuda</h4><ul><li><a href="contacto.php">Contacto</a></li><li><a href="nosotros.php">Preguntas frecuentes</a></li></ul></div>
            <div class="footer-col footer-right"><a href="#" class="back-to-home">Regresa al inicio <i class="fas fa-chevron-up"></i></a></div>
        </div>
        <div class="footer-bottom container">
            <a href="../index.php" class="footer-brand-link" aria-label="CARPRIX - Inicio">
                <img src="../img/brand/logo-carprix-dark.svg" alt="CARPRIX - Confianza que te mueve" class="footer-brand-logo">
            </a>
            <p>&copy; <?php echo date('Y'); ?> CARPRIX. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="../js/public-operativo-session.js?v=20260810-3"></script>
    <script src="../js/detalle.js?v=20260904-1"></script>
</body>
</html>
