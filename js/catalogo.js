document.addEventListener('DOMContentLoaded', () => {
    const mobileMenu = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');
    
    if (mobileMenu && navMenu) {
        mobileMenu.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    let allAutos = [];
    let filteredAutos = [];
    let currentPage = 1;
    const itemsPerPage = 8; 
    let currentView = 'grid'; 
    let isFiltering = false; 

    const container = document.getElementById('catalogo-container');
    const totalResults = document.getElementById('total-results');
    const paginationContainer = document.getElementById('pagination-controls');
    const btnLimpiar = document.getElementById('btn-limpiar');

    const filterIds = ['marca', 'tipo', 'precio', 'anio', 'ubicacion', 'transmision', 'combustible', 'color', 'traccion', 'pasajeros', 'duenos'];
    const filters = {};
    filterIds.forEach(id => { filters[id] = document.getElementById(`filter-${id}`); });

    const btnGrid = document.getElementById('view-grid');
    const btnList = document.getElementById('view-list');
    const btnMobileFilters = document.getElementById('btn-toggle-filters');
    const sidebar = document.getElementById('filtros-sidebar');

    const init = async () => {
        try {
            const response = await fetch('../db/web/get_autos.php');
            const result = await response.json();
            
            if (result.ok) {
                allAutos = result.data.filter(auto => auto.estatus !== 'Oculto');
                
                initAllSelects();
                
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('marca') && urlParams.get('marca') !== '') {
                    filters.marca.value = urlParams.get('marca');
                }
                if (urlParams.has('presupuesto') && urlParams.get('presupuesto') !== '') {
                    filters.precio.value = urlParams.get('presupuesto');
                }
                
                applyFilters(); 
            } else {
                container.innerHTML = `<p style="text-align:center; grid-column:1/-1;">Error: ${result.error}</p>`;
            }
        } catch (error) {
            container.innerHTML = `<p style="text-align:center; grid-column:1/-1; color: #ff5252;">Error de red al cargar el inventario.</p>`;
        }
    };

    const accordions = document.querySelectorAll('.btn-accordion');
    accordions.forEach(acc => {
        acc.addEventListener('click', function() {
            this.parentElement.classList.toggle('active');
        });
    });

    const initAllSelects = () => {
        filterIds.forEach(id => {
            if (id === 'precio') return;
            const select = filters[id];
            
            const uniqueValues = [...new Set(allAutos.map(a => String(a[id])))].filter(v => v !== 'null' && v !== '').sort();
            
            const firstOption = select.options[0].outerHTML;
            select.innerHTML = firstOption;
            uniqueValues.forEach(val => { 
                let displayVal = val;
                if(id === 'duenos') displayVal = val === "1" ? "1 Dueño" : `${val} Dueños`;
                select.innerHTML += `<option value="${val}">${displayVal}</option>`; 
            });
        });
    };

    const getAvailableOptionsFor = (filterIdToSkip) => {
        const precioMax = parseFloat(filters.precio.value) || Infinity;
        return allAutos.filter(auto => {
            if (parseFloat(auto.precio) > precioMax) return false;
            
            for (let id of filterIds) {
                if (id === 'precio' || id === filterIdToSkip) continue;
                const val = filters[id].value;
                if (val !== '' && auto[id] !== null && String(auto[id]) !== val) return false;
            }
            return true;
        });
    };

    const updateSelectOptions = () => {
        isFiltering = true; 

        filterIds.forEach(id => {
            if (id === 'precio') return;
            
            const select = filters[id];
            const currentSelectedValue = String(select.value); 
            
            const validAutos = getAvailableOptionsFor(id);
            const availableValues = [...new Set(validAutos.map(a => String(a[id])))].filter(v => v !== 'null' && v !== '').sort();

            const firstOption = select.options[0].outerHTML;
            select.innerHTML = firstOption;

            availableValues.forEach(val => {
                let displayVal = val;
                if(id === 'duenos') displayVal = val === "1" ? "1 Dueño" : `${val} Dueños`;
                select.innerHTML += `<option value="${val}">${displayVal}</option>`;
            });

            if (availableValues.includes(currentSelectedValue)) {
                select.value = currentSelectedValue;
            } else {
                select.value = '';
            }
        });

        isFiltering = false;
    };

    const applyFilters = () => {
        if (isFiltering) return;

        updateSelectOptions();

        const precioMax = parseFloat(filters.precio.value) || Infinity;

        filteredAutos = allAutos.filter(auto => {
            let match = true;
            for (let id of filterIds) {
                if (id === 'precio') {
                    if (parseFloat(auto.precio) > precioMax) match = false;
                } else {
                    const val = filters[id].value;
                    if (val !== '' && (auto[id] === null || String(auto[id]) !== val)) match = false;
                }
            }
            return match;
        });

        currentPage = 1;
        totalResults.innerText = filteredAutos.length;
        renderPage();
    };

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
            
            const tipoBadgeHtml = auto.tipo ? `<span class="type-badge">${auto.tipo}</span>` : '';
            
            let statusOverlayHtml = '';
            if (auto.estatus === 'Vendido') {
                statusOverlayHtml = `<div class="status-overlay"><span class="status-badge">Vendido</span></div>`;
            } else if (auto.estatus === 'Apartado') {
                statusOverlayHtml = `<div class="status-overlay"><span class="status-badge status-apartado">Apartado</span></div>`;
            }

            const card = `
                <div class="car-card">
                    <div class="car-img">
                        <img src="${auto.img_principal}" alt="${auto.marca}">
                        ${statusOverlayHtml}
                        <span class="year-badge">${auto.anio}</span>
                        ${tipoBadgeHtml}
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

    // ---- LÓGICA DE PAGINACIÓN INTELIGENTE ----
    const renderPaginationControls = () => {
        paginationContainer.innerHTML = '';
        const totalPages = Math.ceil(filteredAutos.length / itemsPerPage);
        
        if (totalPages <= 1) return;

        // Función auxiliar para crear botones
        const createBtn = (text, page, isActive = false, isDisabled = false) => {
            const btn = document.createElement('button');
            btn.className = `btn-page ${isActive ? 'active' : ''}`;
            btn.innerHTML = text;
            if (isDisabled) {
                btn.disabled = true;
            } else {
                btn.addEventListener('click', () => {
                    currentPage = page;
                    renderPage();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
            return btn;
        };

        // Botón Anterior
        paginationContainer.appendChild(createBtn('<i class="fas fa-chevron-left"></i>', currentPage - 1, false, currentPage === 1));

        // Calcular la ventana de botones a mostrar
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (currentPage <= 3) { endPage = Math.min(totalPages, 5); }
        if (currentPage >= totalPages - 2) { startPage = Math.max(1, totalPages - 4); }

        // Primera página y Puntos suspensivos izquierdos
        if (startPage > 1) {
            paginationContainer.appendChild(createBtn('1', 1));
            if (startPage > 2) {
                const dots = document.createElement('span');
                dots.className = 'pagination-dots';
                dots.innerText = '...';
                paginationContainer.appendChild(dots);
            }
        }

        // Botones numéricos centrales
        for (let i = startPage; i <= endPage; i++) {
            paginationContainer.appendChild(createBtn(i, i, i === currentPage));
        }

        // Puntos suspensivos derechos y Última página
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const dots = document.createElement('span');
                dots.className = 'pagination-dots';
                dots.innerText = '...';
                paginationContainer.appendChild(dots);
            }
            paginationContainer.appendChild(createBtn(totalPages, totalPages));
        }

        // Botón Siguiente
        paginationContainer.appendChild(createBtn('<i class="fas fa-chevron-right"></i>', currentPage + 1, false, currentPage === totalPages));
    };

    // 8. EVENT LISTENERS
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