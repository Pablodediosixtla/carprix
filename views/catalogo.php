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
                <li><a href="../index.php">Compra</a></li>
                <li><a href="#">Vende</a></li>
                <li><a href="#">Nosotros</a></li>
                <li><a href="#">Contacto</a></li>
                <li><a href="#" class="btn-outline">Iniciar Sesión</a></li>
            </ul>
            <div class="menu-toggle" id="mobile-menu"><i class="fas fa-bars"></i></div>
        </nav>
    </header>

    <main class="container catalogo-layout">
        <button class="btn-mobile-filters" id="btn-toggle-filters"><i class="fas fa-filter"></i> Mostrar Filtros</button>

        <aside class="filtros-sidebar" id="filtros-sidebar">
            <div class="filtro-header">
                <h3>Filtros</h3>
                <button id="btn-limpiar" class="btn-clear">Limpiar</button>
            </div>
            
            <div class="filtro-grupo">
                <label>Marca</label>
                <select id="filter-marca" class="filter-input">
                    <option value="">Todas las marcas</option>
                    <option value="Audi">Audi</option>
                    <option value="BMW">BMW</option>
                    <option value="Chevrolet">Chevrolet</option>
                    <option value="Ford">Ford</option>
                    <option value="Honda">Honda</option>
                    <option value="Hyundai">Hyundai</option>
                    <option value="Kia">Kia</option>
                    <option value="Mazda">Mazda</option>
                    <option value="MG">MG</option>
                    <option value="Nissan">Nissan</option>
                    <option value="Toyota">Toyota</option>
                    <option value="Volkswagen">Volkswagen</option>
                </select>
            </div>

            <div class="filtro-grupo">
                <label>Presupuesto Máximo ($)</label>
                <input type="number" id="filter-precio" class="filter-input" placeholder="Ej. 500000">
            </div>

            <div class="filtro-grupo">
                <label>Transmisión</label>
                <select id="filter-transmision" class="filter-input">
                    <option value="">Cualquiera</option>
                    <option value="Automático">Automático</option>
                    <option value="Manual">Manual</option>
                </select>
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
                <p style="text-align: center; width: 100%; grid-column: 1/-1;">Cargando inventario...</p>
            </div>

            <div class="pagination-container" id="pagination-controls">
                </div>
        </section>
    </main>

    <script src="../js/catalogo.js"></script>
</body>
</html>