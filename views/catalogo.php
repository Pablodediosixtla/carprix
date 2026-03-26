<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Seminuevos | CARPRIX</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/catalogo.css">
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

    <main class="container catalogo-layout">
        <button class="btn-mobile-filters" id="btn-toggle-filters"><i class="fas fa-filter"></i> Mostrar Filtros</button>

        <aside class="filtros-sidebar scroll-custom" id="filtros-sidebar">
            <div class="filtro-header">
                <h3>Filtros</h3>
                <button id="btn-limpiar" class="btn-clear">Limpiar</button>
            </div>
            
            <div class="filtro-section active">
                <button class="btn-accordion">General <i class="fas fa-chevron-down"></i></button>
                <div class="accordion-content">
                    <div class="filtro-grupo">
                        <label>Marca</label>
                        <select id="filter-marca" class="filter-input"><option value="">Todas las marcas</option></select>
                    </div>
                    <div class="filtro-grupo">
                        <label>Tipo de Auto</label>
                        <select id="filter-tipo" class="filter-input"><option value="">Cualquier tipo</option></select>
                    </div>
                    <div class="filtro-grupo">
                        <label>Presupuesto Máximo ($)</label>
                        <input type="number" id="filter-precio" class="filter-input" placeholder="Ej. 500000">
                    </div>
                    <div class="filtro-grupo">
                        <label>Ubicación</label>
                        <select id="filter-ubicacion" class="filter-input"><option value="">Cualquier ubicación</option></select>
                    </div>
                </div>
            </div>

            <div class="filtro-section active">
                <button class="btn-accordion">Avanzado <i class="fas fa-chevron-down"></i></button>
                <div class="accordion-content">
                    <div class="filtro-grupo">
                        <label>Año</label>
                        <select id="filter-anio" class="filter-input"><option value="">Todos los años</option></select>
                    </div>
                    <div class="filtro-grupo">
                        <label>Transmisión</label>
                        <select id="filter-transmision" class="filter-input"><option value="">Cualquier transmisión</option></select>
                    </div>
                    <div class="filtro-grupo">
                        <label>Combustible</label>
                        <select id="filter-combustible" class="filter-input"><option value="">Cualquier combustible</option></select>
                    </div>
                    <div class="filtro-grupo">
                        <label>Color</label>
                        <select id="filter-color" class="filter-input"><option value="">Cualquier color</option></select>
                    </div>
                    <div class="filtro-grupo">
                        <label>Tracción</label>
                        <select id="filter-traccion" class="filter-input"><option value="">Cualquier tracción</option></select>
                    </div>
                    <div class="filtro-grupo">
                        <label>Pasajeros</label>
                        <select id="filter-pasajeros" class="filter-input"><option value="">Cualquier capacidad</option></select>
                    </div>
                </div>
            </div>
        </aside>

        <section class="catalogo-main">
            <div class="catalogo-top-bar">
                <p><span id="total-results" class="green-text font-bold">0</span> autos encontrados</p>
                <div class="view-toggles">
                    <button class="btn-view active" id="view-grid" title="Vista Mosaico"><i class="fas fa-th-large"></i></button>
                    <button class="btn-view" id="view-list" title="Vista Lista"><i class="fas fa-list"></i></button>
                </div>
            </div>

            <div id="catalogo-container" class="layout-grid">
                <p style="text-align: center; width: 100%; grid-column: 1/-1; color: var(--gray-text);">Cargando inventario...</p>
            </div>

            <div class="pagination-container" id="pagination-controls"></div>
        </section>
    </main>

    <footer class="main-footer">
        <div class="container footer-grid">
            <div class="footer-col"><h4 class="footer-title title-green">Conoce más</h4><ul><li><a href="#">¿Quiénes Somos?</a></li><li><a href="#">Vende tu auto</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-white">Legales</h4><ul><li><a href="#">Aviso de privacidad</a></li></ul></div>
            <div class="footer-col"><h4 class="footer-title title-grey">Ayuda</h4><ul><li><a href="#">Contacto</a></li></ul></div>
            <div class="footer-col footer-right"><a href="#" class="back-to-home">Regresa al inicio <i class="fas fa-chevron-up"></i></a></div>
        </div>
        <div class="footer-bottom container"><p>&copy; <?php echo date('Y'); ?> <span class="white-text">CAR</span><span class="green-text">PRIX</span>. Todos los derechos reservados.</p></div>
    </footer>

    <script src="../js/catalogo.js"></script>
</body>
</html>