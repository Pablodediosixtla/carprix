<?php
// detalle.php - Ubicado en /views/
$auto = [
    "marca" => "MG",
    "modelo" => "MG5 1.5 Excite Sedan",
    "precio" => "226,999",
    "año" => 2024,
    "km" => "56,641 km",
    "ubicacion" => "Querétaro",
    "transmision" => "Manual",
    "color" => "Negro",
    "img_principal" => "https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=800",
    "imagenes" => [
        "https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=800",
        "https://images.unsplash.com/photo-1494905998402-395d579af36f?q=80&w=800",
        "https://images.unsplash.com/photo-1583121274602-3e2820c69888?q=80&w=800",
        "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?q=80&w=800",
        "https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?q=80&w=800",
        "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?q=80&w=800",
        "https://images.unsplash.com/photo-1526726538690-5cbf227eabef?q=80&w=800",
        "https://images.unsplash.com/photo-1502877338535-766e1452684a?q=80&w=800"
    ],
    "specs" => [
        ["label" => "Año", "val" => "2024", "icon" => "fa-calendar-alt"],
        ["label" => "Kilometraje", "val" => "56,641 km", "icon" => "fa-road"],
        ["label" => "Transmisión", "val" => "Manual", "icon" => "fa-cogs"],
        ["label" => "Ubicación", "val" => "Querétaro", "icon" => "fa-map-marker-alt"],
        ["label" => "Motor", "val" => "1.5L 4 Cil", "icon" => "fa-engine"],
        ["label" => "Combustible", "val" => "Gasolina", "icon" => "fa-gas-pump"],
        ["label" => "Pasajeros", "val" => "5 Adultos", "icon" => "fa-users"],
        ["label" => "Tracción", "val" => "Delantera", "icon" => "fa-car-side"]
    ]
];

$precioNumerico = str_replace(',', '', $auto['precio']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $auto['marca'] . " " . $auto['modelo']; ?> | CARPRIX</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/detalle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-dark">

    <header class="main-header">
        <nav class="container nav-flex">
            <div class="logo"><span class="white-text">CAR</span><span class="green-text">PRIX</span></div>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="../index.php">Compra</a></li>
                <li><a href="#">Vende</a></li>
                <li><a href="#">Nosotros</a></li>
                <li><a href="#">Contacto</a></li>
                <li><a href="#" class="btn-outline">Iniciar Sesión</a></li>
            </ul>
            <div class="menu-toggle" id="mobile-menu"><i class="fas fa-bars"></i></div>
        </nav>
    </header>

    <main class="container detail-container-main">
        <div class="detail-wrapper">
            <div class="detail-content">
                <div class="gallery-container">
                    <img id="main-view" src="<?php echo $auto['img_principal']; ?>" alt="Auto Principal">
                    <div class="gallery-thumbs">
                        <?php foreach($auto['imagenes'] as $index => $img): ?>
                            <img class="thumb-item <?php echo $index === 0 ? 'active' : ''; ?>" src="<?php echo $img; ?>" alt="Miniatura">
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="technical-info">
                    <h2 class="title-spec">Especificaciones técnicas</h2>
                    <div class="specs-box-grid">
                        <?php foreach($auto['specs'] as $spec): ?>
                        <div class="spec-card">
                            <i class="fas <?php echo $spec['icon']; ?> green-text"></i>
                            <div>
                                <small><?php echo $spec['label']; ?></small>
                                <span><?php echo $spec['val']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
                                <input type="number" id="input-plazo" value="60" min="12" max="72">
                            </div>
                            <button id="btn-calculate" class="btn-calculate">CALCULAR</button>
                        </div>

                        <div id="resultado-cotizacion" class="cotizacion-tabla-wrapper">
                            <h3 class="table-title">Plan de pagos estimado</h3>
                            <div class="table-scroll">
                                <table class="tabla-finanzas">
                                    <thead>
                                        <tr>
                                            <th>Mes</th>
                                            <th>Pago Mensual</th>
                                            <th>Saldo Restante</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabla-body"></tbody>
                                </table>
                            </div>
                        </div>
                        <p class="disclaimer-cotizador">
                            <i class="fas fa-exclamation-triangle"></i> Cotización de carácter informativo. Los precios y tarifas están sujetos a cambios sin previo aviso.
                        </p>
                    </div>
                </div>
            </div>

            <aside class="detail-sidebar">
                <div class="action-card">
                    <span class="label-new">RECIÉN PUBLICADO</span>
                    <h1 class="car-name"><?php echo $auto['marca'] . " " . $auto['modelo']; ?></h1>
                    <div class="price-section">
                        <p class="price-tag">$<?php echo $auto['precio']; ?></p>
                        <small class="price-info">Precio de lista final</small>
                    </div>
                    <button class="btn-buy">APARTAR AHORA</button>
                    <div class="safety-badges">
                        <p><i class="fas fa-check-circle green-text"></i> Auto inspeccionado en 240 puntos</p>
                        <p><i class="fas fa-undo green-text"></i> 7 días o 300 km de prueba</p>
                    </div>
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
                <a href="../index.php" class="back-to-home">Regresa al inicio <i class="fas fa-chevron-up"></i></a>
            </div>
        </div>
        <div class="footer-bottom container">
            <p>&copy; <?php echo date('Y'); ?> <span class="white-text">CAR</span><span class="green-text">PRIX</span>. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>const CAR_PRICE = <?php echo $precioNumerico; ?>;</script>
    <script src="../js/scripts.js"></script>
    <script src="../js/detalle.js"></script>
</body>
</html>