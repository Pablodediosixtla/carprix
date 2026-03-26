document.addEventListener('DOMContentLoaded', () => {
    // ---- INICIO FIX MENÚ MÓVIL ----
    const mobileMenu = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');
    
    if (mobileMenu && navMenu) {
        mobileMenu.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }
    // ---- FIN FIX MENÚ MÓVIL ----

    const loadingState = document.getElementById('loading-state');
    const detailContent = document.getElementById('detail-content');
    
    // Variables globales para el cotizador
    let CAR_PRICE = 0;

    // 1. Obtener el ID de la URL
    const urlParams = new URLSearchParams(window.location.search);
    const carId = urlParams.get('id');

    if (!carId) {
        loadingState.innerHTML = '<span style="color:#ff5252;">Error: No se seleccionó ningún vehículo.</span>';
        return;
    }

    // 2. Consumir la API
    fetchCarDetails(carId);

    async function fetchCarDetails(id) {
        try {
            // Mandamos un POST con el ID en el body (como lo configuramos en get_autos.php)
            const response = await fetch('../db/web/get_autos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const result = await response.json();

            if (result.ok && result.data.length > 0) {
                const auto = result.data[0]; // El primer auto del array
                renderCarData(auto);
            } else {
                loadingState.innerHTML = '<span style="color:#ff5252;">Vehículo no encontrado o no disponible.</span>';
            }
        } catch (error) {
            console.error("Error fetching car:", error);
            loadingState.innerHTML = '<span style="color:#ff5252;">Error de conexión con la base de datos.</span>';
        }
    }

    function renderCarData(auto) {
        // Formateos
        CAR_PRICE = parseFloat(auto.precio);
        const priceFmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(CAR_PRICE);
        const kmFmt = new Intl.NumberFormat('es-MX').format(auto.kilometraje) + ' km';

        // Llenar Textos Principales
        document.title = `${auto.marca} ${auto.modelo} | CARPRIX`;
        document.getElementById('det-name').innerText = `${auto.marca} ${auto.modelo}`;
        document.getElementById('det-price').innerText = priceFmt;
        
        if(auto.estatus !== 'Disponible') {
            const btnApartar = document.getElementById('btn-apartar');
            btnApartar.innerText = auto.estatus.toUpperCase();
            btnApartar.disabled = true;
            btnApartar.style.background = '#555';
            btnApartar.style.cursor = 'not-allowed';
        }

        // Llenar Galería
        const mainImg = document.getElementById('main-view');
        mainImg.src = auto.img_principal;
        
        const thumbsContainer = document.getElementById('gallery-thumbs');
        thumbsContainer.innerHTML = `<img class="thumb-item active" src="${auto.img_principal}" alt="thumb">`;

        // Llenar Especificaciones (Las 9 cajas, agregado TIPO y ajustado PASAJEROS)
        const specsGrid = document.getElementById('specs-grid');
        const specsData = [
            { label: "Tipo", val: auto.tipo || 'N/A', icon: "fa-car" },
            { label: "Año", val: auto.anio, icon: "fa-calendar-alt" },
            { label: "Kilometraje", val: kmFmt, icon: "fa-road" },
            { label: "Transmisión", val: auto.transmision, icon: "fa-cogs" },
            { label: "Ubicación", val: auto.ubicacion, icon: "fa-map-marker-alt" },
            { label: "Motor", val: auto.motor || 'N/A', icon: "fa-car-side" },
            { label: "Combustible", val: auto.combustible || 'N/A', icon: "fa-gas-pump" },
            { label: "Pasajeros", val: auto.pasajeros || 'N/A', icon: "fa-users" },
            { label: "Tracción", val: auto.traccion || 'N/A', icon: "fa-dharmachakra" }
        ];

        specsData.forEach(spec => {
            specsGrid.innerHTML += `
                <div class="spec-card">
                    <i class="fas ${spec.icon} green-text"></i>
                    <div>
                        <small>${spec.label}</small>
                        <span>${spec.val}</span>
                    </div>
                </div>
            `;
        });

        // Ocultar loader y mostrar contenido
        loadingState.style.display = 'none';
        detailContent.style.display = 'grid'; 

        // Inicializar Cotizador
        initCotizador();
    }

    // 3. LÓGICA DEL COTIZADOR
    function initCotizador() {
        const rangeEnganche = document.getElementById('range-enganche');
        const displayPercent = document.getElementById('display-percent');
        const displayTotalEnganche = document.getElementById('display-total-enganche');
        const inputPlazo = document.getElementById('input-plazo');
        const btnCalculate = document.getElementById('btn-calculate');
        const tablaBody = document.getElementById('tabla-body');
        const cotizacionResult = document.getElementById('resultado-cotizacion');

        const formatMXN = (val) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(val);

        const updateUI = () => {
            const percent = rangeEnganche.value;
            const total = (CAR_PRICE * percent) / 100;
            displayPercent.innerText = `${percent}%`;
            displayTotalEnganche.innerText = formatMXN(total);
        };

        rangeEnganche.addEventListener('input', updateUI);
        updateUI(); // Carga inicial

        btnCalculate.addEventListener('click', () => {
            const enganche = (CAR_PRICE * rangeEnganche.value) / 100;
            const plazo = parseInt(inputPlazo.value);
            const saldoFinanciar = CAR_PRICE - enganche;
            
            // Formula básica de simulación (Saldo a financiar / meses) + interés ficticio
            const interesAprox = 1.15; // 15% interés total en el periodo como ejemplo
            const saldoTotalConInteres = saldoFinanciar * interesAprox;
            const pagoMensual = saldoTotalConInteres / plazo;

            tablaBody.innerHTML = '';
            let saldoActual = saldoTotalConInteres;

            for (let i = 1; i <= plazo; i++) {
                saldoActual -= pagoMensual;
                if (saldoActual < 0) saldoActual = 0;

                const row = `<tr><td>${i}</td><td>${formatMXN(pagoMensual)}</td><td>${formatMXN(saldoActual)}</td></tr>`;
                tablaBody.innerHTML += row;
            }

            cotizacionResult.style.display = 'block';
            cotizacionResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
});