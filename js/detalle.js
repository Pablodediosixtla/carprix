document.addEventListener('DOMContentLoaded', () => {
    const mobileMenu = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');
    
    if (mobileMenu && navMenu) {
        mobileMenu.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    const loadingState = document.getElementById('loading-state');
    const detailContent = document.getElementById('detail-content');
    
    let CAR_PRICE = 0;

    const urlParams = new URLSearchParams(window.location.search);
    const carId = urlParams.get('id');

    if (!carId) {
        loadingState.innerHTML = '<span style="color:#ff5252;">Error: No se seleccionó ningún vehículo.</span>';
        return;
    }

    fetchCarDetails(carId);

    async function fetchCarDetails(id) {
        try {
            const response = await fetch('../db/web/get_autos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const result = await response.json();

            if (result.ok && result.data.length > 0) {
                const auto = result.data[0]; 
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
        CAR_PRICE = parseFloat(auto.precio);
        const priceFmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(CAR_PRICE);
        const kmFmt = new Intl.NumberFormat('es-MX').format(auto.kilometraje) + ' km';

        document.title = `${auto.marca} ${auto.modelo} | CARPRIX`;
        document.getElementById('det-name').innerText = `${auto.marca} ${auto.modelo}`;
        document.getElementById('det-price').innerText = priceFmt;
        
        const btnApartar = document.getElementById('btn-apartar');
        const statusOverlay = document.getElementById('status-overlay');
        const statusBadge = document.getElementById('status-badge');

        if(auto.estatus !== 'Disponible') {
            btnApartar.innerText = auto.estatus.toUpperCase();
            btnApartar.disabled = true;
            btnApartar.style.background = '#555';
            btnApartar.style.cursor = 'not-allowed';

            if (auto.estatus === 'Vendido') {
                statusBadge.innerText = 'Vendido';
                statusBadge.className = 'status-badge';
                statusOverlay.style.display = 'flex';
            } else if (auto.estatus === 'Apartado') {
                statusBadge.innerText = 'Apartado';
                statusBadge.className = 'status-badge status-apartado';
                statusOverlay.style.display = 'flex';
            }
        } else {
            btnApartar.innerText = 'DISPONIBLE';
            btnApartar.disabled = true;
            btnApartar.style.cursor = 'default';
        }

        // TEXTO DINÁMICO DE DUEÑOS
        const dueñosElement = document.getElementById('det-duenos');
        if (dueñosElement) {
            if (auto.duenos) {
                dueñosElement.innerText = auto.duenos == 1 ? "1 Dueño" : `${auto.duenos} Dueños`;
            } else {
                dueñosElement.innerText = "N/A Dueños";
            }
        }

        document.getElementById('badge-year').innerText = auto.anio;
        if (auto.tipo) {
            const badgeTipo = document.getElementById('badge-tipo');
            badgeTipo.innerText = auto.tipo;
            badgeTipo.style.display = 'block';
        }

        const mainImg = document.getElementById('main-view');
        mainImg.src = auto.img_principal;
        
        const thumbsContainer = document.getElementById('gallery-thumbs');
        thumbsContainer.innerHTML = `<img class="thumb-item active" src="${auto.img_principal}" alt="thumb">`;

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

        loadingState.style.display = 'none';
        detailContent.style.display = 'grid'; 

        initCotizador();
    }

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
        updateUI();

        btnCalculate.addEventListener('click', () => {
            const enganche = (CAR_PRICE * rangeEnganche.value) / 100;
            const plazo = parseInt(inputPlazo.value);
            const saldoFinanciar = CAR_PRICE - enganche;
            
            const interesAprox = 1.15; 
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