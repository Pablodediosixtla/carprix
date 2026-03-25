document.addEventListener('DOMContentLoaded', () => {
    // ESTADO GLOBAL
    let allAutos = [];
    let filteredAutos = [];
    let currentPage = 1;
    const itemsPerPage = 9; 
    let currentView = 'grid'; 

    // ELEMENTOS DEL DOM
    const container = document.getElementById('catalogo-container');
    const totalResults = document.getElementById('total-results');
    const paginationContainer = document.getElementById('pagination-controls');
    const btnLimpiar = document.getElementById('btn-limpiar');

    // FILTROS DOM
    const filterIds = ['marca', 'precio', 'anio', 'ubicacion', 'transmision', 'combustible', 'color', 'traccion', 'pasajeros'];
    const filters = {};
    filterIds.forEach(id => { filters[id] = document.getElementById(`filter-${id}`); });

    const btnGrid = document.getElementById('view-grid');
    const btnList = document.getElementById('view-list');
    const btnMobileFilters = document.getElementById('btn-toggle-filters');
    const sidebar = document.getElementById('filtros-sidebar');

    // 1. INICIALIZACIÓN
    const init = async () => {
        try {
            const response = await fetch('../db/web/get_autos.php');
            const result = await response.json();
            
            if (result.ok) {
                // Solo guardamos autos disponibles
                allAutos = result.data.filter(auto => auto.estatus === 'Disponible');
                
                populateSelects(); // Autocompletar filtros basados en la BD
                
                // Leer URL por si vienen parámetros del inicio (index.php)
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('marca') && urlParams.get('marca') !== '') {
                    setTimeout(() => { filters.marca.value = urlParams.get('marca'); applyFilters(); }, 50);
                } else if (urlParams.has('presupuesto') && urlParams.get('presupuesto') !== '') {
                    setTimeout(() => { filters.precio.value = urlParams.get('presupuesto'); applyFilters(); }, 50);
                } else {
                    applyFilters(); 
                }
            } else {
                container.innerHTML = `<p style="text-align:center; grid-column:1/-1;">Error: ${result.error}</p>`;
            }
        } catch (error) {
            container.innerHTML = `<p style="text-align:center; grid-column:1/-1; color: #ff5252;">Error de red al cargar el inventario.</p>`;
        }
    };

    // 2. LLENAR SELECTS DINÁMICAMENTE CON DATOS REALES
    const populateSelects = () => {
        const buildOptions = (key, selectElement) => {
            if (!selectElement) return;
            // Extraer valores únicos, limpiar vacíos y ordenar
            const uniqueValues = [...new Set(allAutos.map(auto => auto[key]))].filter(val => val !== null && val !== '').sort();
            
            // Guardamos la primera opción ("Todos...") y borramos el resto
            const firstOption = selectElement.options[0].outerHTML;
            selectElement.innerHTML = firstOption;
            
            // Inyectamos opciones reales
            uniqueValues.forEach(val => {
                selectElement.innerHTML += `<option value="${val}">${val}</option>`;
            });
        };

        buildOptions('marca', filters.marca);
        buildOptions('anio', filters.anio);
        buildOptions('ubicacion', filters.ubicacion);
        buildOptions('transmision', filters.transmision);
        buildOptions('combustible', filters.combustible);
        buildOptions('color', filters.color);
        buildOptions('traccion', filters.traccion);
        buildOptions('pasajeros', filters.pasajeros);
    };

    // 3. LÓGICA DE FILTRADO MULTIPLE
    const applyFilters = () => {
        const precioMax = parseFloat(filters.precio.value) || Infinity;

        filteredAutos = allAutos.filter(auto => {
            let match = true;
            
            if (filters.marca.value !== '' && auto.marca !== filters.marca.value) match = false;
            if (filters.anio.value !== '' && auto.anio.toString() !== filters.anio.value) match = false;
            if (filters.ubicacion.value !== '' && auto.ubicacion !== filters.ubicacion.value) match = false;
            if (filters.transmision.value !== '' && auto.transmision !== filters.transmision.value) match = false;
            if (filters.combustible.value !== '' && auto.combustible !== filters.combustible.value) match = false;
            if (filters.color.value !== '' && auto.color !== filters.color.value) match = false;
            if (filters.traccion.value !== '' && auto.traccion !== filters.traccion.value) match = false;
            if (filters.pasajeros.value !== '' && auto.pasajeros.toString() !== filters.pasajeros.value) match = false;
            
            if (parseFloat(auto.precio) > precioMax) match = false;

            return match;
        });

        currentPage = 1;
        totalResults.innerText = filteredAutos.length;
        renderPage();
    };

    // 4. RENDERIZADO DE TARJETAS
    const renderPage = () => {
        container.innerHTML = '';
        container.className = currentView === 'grid' ? 'layout-grid' : 'layout-list';

        if (filteredAutos.length === 0) {
            container.innerHTML = `<p style="text-align:center; grid-column:1/-1; margin-top:40px; color: var(--gray-text);">No encontramos autos con esas características.</p>`;
            paginationContainer.innerHTML = '';
            return;
        }

        const startIndex = (currentPage - 1) * itemsPerPage;
        const autosToShow = filteredAutos.slice(startIndex, startIndex + itemsPerPage);

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

    // 5. RENDERIZADO DE PAGINACIÓN
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

    // 6. EVENT LISTENERS
    filterIds.forEach(id => {
        if(filters[id]) {
            filters[id].addEventListener(id === 'precio' ? 'input' : 'change', applyFilters);
        }
    });

    btnLimpiar.addEventListener('click', () => {
        filterIds.forEach(id => { if(filters[id]) filters[id].value = ''; });
        window.history.replaceState({}, document.title, window.location.pathname);
        applyFilters();
    });

    btnGrid.addEventListener('click', () => { currentView = 'grid'; btnGrid.classList.add('active'); btnList.classList.remove('active'); renderPage(); });
    btnList.addEventListener('click', () => { currentView = 'list'; btnList.classList.add('active'); btnGrid.classList.remove('active'); renderPage(); });

    btnMobileFilters.addEventListener('click', () => { sidebar.classList.toggle('show'); });

    // Arrancar la app
    init();
});