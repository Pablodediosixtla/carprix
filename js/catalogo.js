document.addEventListener('DOMContentLoaded', () => {
    // ESTADO GLOBAL
    let allAutos = [];
    let filteredAutos = [];
    let currentPage = 1;
    const itemsPerPage = 9; // Autos por página
    let currentView = 'grid'; // 'grid' o 'list'

    // ELEMENTOS DEL DOM
    const container = document.getElementById('catalogo-container');
    const totalResults = document.getElementById('total-results');
    const paginationContainer = document.getElementById('pagination-controls');
    
    // INPUTS DE FILTRO
    const filterMarca = document.getElementById('filter-marca');
    const filterPrecio = document.getElementById('filter-precio');
    const filterTransmision = document.getElementById('filter-transmision');
    const btnLimpiar = document.getElementById('btn-limpiar');

    // BOTONES DE VISTA Y MÓVIL
    const btnGrid = document.getElementById('view-grid');
    const btnList = document.getElementById('view-list');
    const btnMobileFilters = document.getElementById('btn-toggle-filters');
    const sidebar = document.getElementById('filtros-sidebar');

    // 1. INICIALIZACIÓN Y LECTURA DE URL
    const init = async () => {
        // Leer variables que vienen del index.php
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('marca')) filterMarca.value = urlParams.get('marca');
        if (urlParams.has('presupuesto') && urlParams.get('presupuesto') !== '') filterPrecio.value = urlParams.get('presupuesto');

        // Consumir API
        try {
            const response = await fetch('../db/web/get_autos.php');
            const result = await response.json();
            if (result.ok) {
                allAutos = result.data;
                applyFilters(); // Aplica filtros de URL y dibuja
            } else {
                container.innerHTML = `<p style="text-align:center; grid-column:1/-1;">Error: ${result.error}</p>`;
            }
        } catch (error) {
            container.innerHTML = `<p style="text-align:center; grid-column:1/-1;">Error de red al cargar el inventario.</p>`;
        }
    };

    // 2. LÓGICA DE FILTRADO
    const applyFilters = () => {
        const marcaVal = filterMarca.value.toLowerCase();
        const precioMax = parseFloat(filterPrecio.value) || Infinity;
        const transVal = filterTransmision.value.toLowerCase();

        filteredAutos = allAutos.filter(auto => {
            let matchMarca = marcaVal === '' || auto.marca.toLowerCase().includes(marcaVal);
            let matchPrecio = parseFloat(auto.precio) <= precioMax;
            let matchTrans = transVal === '' || auto.transmision.toLowerCase().includes(transVal);
            
            return matchMarca && matchPrecio && matchTrans;
        });

        currentPage = 1; // Regresar a la página 1 al filtrar
        totalResults.innerText = filteredAutos.length;
        renderPage();
    };

    // 3. RENDERIZADO Y PAGINACIÓN
    const renderPage = () => {
        container.innerHTML = '';
        container.className = currentView === 'grid' ? 'layout-grid' : 'layout-list';

        if (filteredAutos.length === 0) {
            container.innerHTML = `<p style="text-align:center; grid-column:1/-1; margin-top:40px;">No encontramos autos con esas características.</p>`;
            paginationContainer.innerHTML = '';
            return;
        }

        // Calcular qué autos mostrar
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const autosToShow = filteredAutos.slice(startIndex, endIndex);

        // Generar Tarjetas
        autosToShow.forEach(auto => {
            const priceFmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(auto.precio);
            const kmFmt = new Intl.NumberFormat('es-MX').format(auto.kilometraje) + ' km';

            const card = `
                <div class="car-card">
                    <div class="car-img">
                        <img src="${auto.img_principal}" alt="${auto.marca}">
                        <span class="year-badge">${auto.anio}</span>
                    </div>
                    <div class="car-info">
                        <h3>${auto.marca} ${auto.modelo}</h3>
                        <p class="km">${kmFmt} • ${auto.transmision}</p>
                        <p class="price">${priceFmt}</p>
                        <a href="detalle.php?id=${auto.id}" class="btn-details">Ver detalles</a>
                    </div>
                </div>
            `;
            container.innerHTML += card;
        });

        renderPaginationControls();
    };

    const renderPaginationControls = () => {
        paginationContainer.innerHTML = '';
        const totalPages = Math.ceil(filteredAutos.length / itemsPerPage);
        
        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.className = `btn-page ${i === currentPage ? 'active' : ''}`;
            btn.innerText = i;
            btn.addEventListener('click', () => {
                currentPage = i;
                renderPage();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            paginationContainer.appendChild(btn);
        }
    };

    // 4. EVENT LISTENERS
    filterMarca.addEventListener('change', applyFilters);
    filterPrecio.addEventListener('input', applyFilters);
    filterTransmision.addEventListener('change', applyFilters);

    btnLimpiar.addEventListener('click', () => {
        filterMarca.value = '';
        filterPrecio.value = '';
        filterTransmision.value = '';
        // Limpiar URL params
        window.history.replaceState({}, document.title, window.location.pathname);
        applyFilters();
    });

    btnGrid.addEventListener('click', () => { currentView = 'grid'; btnGrid.classList.add('active'); btnList.classList.remove('active'); renderPage(); });
    btnList.addEventListener('click', () => { currentView = 'list'; btnList.classList.add('active'); btnGrid.classList.remove('active'); renderPage(); });

    btnMobileFilters.addEventListener('click', () => {
        sidebar.classList.toggle('show');
    });

    // Arrancar la app
    init();
});