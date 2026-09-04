document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const mobileMenu = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');

    if (mobileMenu && navMenu) {
        mobileMenu.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });

        navMenu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => navMenu.classList.remove('active'));
        });
    }

    const loadingState = document.getElementById('loading-state');
    const detailContent = document.getElementById('detail-content');
    const mainImg = document.getElementById('main-view');
    const thumbsContainer = document.getElementById('gallery-thumbs');

    const lightbox = document.getElementById('image-lightbox');
    const lightboxImage = document.getElementById('lightbox-image');
    const lightboxCounter = document.getElementById('lightbox-counter');
    const lightboxClose = document.getElementById('lightbox-close');
    const lightboxPrev = document.getElementById('lightbox-prev');
    const lightboxNext = document.getElementById('lightbox-next');
    const openLightboxButton = document.getElementById('open-lightbox');

    let CAR_PRICE = 0;
    let galleryImages = [];
    let currentImageIndex = 0;
    let vehicleNameForAlt = 'vehículo';
    let currentAuto = null;
    let visitRegisteredFor = null;
    let operativoSession = { authenticated: false, usuario: null };

    const operativoBridge = window.CARPRIX_PUBLIC_OPERATIVO;
    const operativoSessionPromise = operativoBridge?.getSession
        ? operativoBridge.getSession()
        : Promise.resolve({ authenticated: false, usuario: null });

    operativoSessionPromise.then((session) => {
        operativoSession = session || { authenticated: false, usuario: null };
        if (currentAuto) renderStatus(currentAuto);
    });

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
                body: JSON.stringify({ id })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.ok && Array.isArray(result.data) && result.data.length > 0) {
                renderCarData(result.data[0]);
            } else {
                loadingState.innerHTML = '<span style="color:#ff5252;">Vehículo no encontrado o no disponible.</span>';
            }
        } catch (error) {
            console.error('Error fetching car:', error);
            loadingState.innerHTML = '<span style="color:#ff5252;">Error de conexión con la base de datos.</span>';
        }
    }

    function formatCurrency(value, maximumFractionDigits = 2) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            maximumFractionDigits
        }).format(Number(value) || 0);
    }

    function renderCarData(auto) {
        currentAuto = auto;
        CAR_PRICE = Number.parseFloat(auto.precio) || 0;
        const priceFmt = formatCurrency(CAR_PRICE);
        const kmFmt = `${new Intl.NumberFormat('es-MX').format(Number(auto.kilometraje) || 0)} km`;
        const vehicleName = `${auto.marca || ''} ${auto.modelo || ''}`.trim();
        vehicleNameForAlt = vehicleName || 'vehículo';

        document.title = `${vehicleNameForAlt} | CARPRIX`;
        document.getElementById('det-name').textContent = vehicleNameForAlt;
        document.getElementById('det-price').textContent = priceFmt;

        const mobileName = document.getElementById('mobile-det-name');
        const mobilePrice = document.getElementById('mobile-det-price');
        const mobileId = document.getElementById('mobile-det-id');
        const monthlyPayment = Number.parseFloat(auto.mensualidad) || 0;

        if (mobileName) mobileName.textContent = vehicleNameForAlt;
        if (mobileId) mobileId.textContent = auto.id ? `#${auto.id}` : '';
        if (mobilePrice) {
            mobilePrice.textContent = monthlyPayment > 0
                ? `DESDE ${formatCurrency(monthlyPayment, 0)}/mes | ${priceFmt}`
                : priceFmt;
        }

        renderStatus(auto);
        renderOwnership(auto);
        renderBadges(auto);
        renderGallery(auto);
        renderSpecifications(auto, kmFmt);
        registerDetailVisit(auto.id);

        loadingState.style.display = 'none';
        detailContent.style.display = 'grid';

        initCotizador();
    }

    function renderStatus(auto) {
        const btnApartar = document.getElementById('btn-apartar');
        const statusOverlay = document.getElementById('status-overlay');
        const statusBadge = document.getElementById('status-badge');
        const status = auto.estatus || 'Disponible';
        const roles = new Set((operativoSession.usuario?.roles || []).map((role) => String(role).toUpperCase()));
        const canCreateRequirement = operativoSession.authenticated
            && ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'VENTAS'].some((role) => roles.has(role));

        btnApartar.disabled = true;
        btnApartar.setAttribute('aria-disabled', 'true');
        btnApartar.dataset.canRequest = '0';
        statusOverlay.style.display = 'none';
        statusBadge.className = 'status-badge';

        if (status !== 'Disponible') {
            btnApartar.textContent = status.toUpperCase();
            btnApartar.style.background = '#555';
            btnApartar.style.cursor = 'not-allowed';
            btnApartar.title = `Este vehículo se encuentra ${status}.`;

            if (status === 'Vendido' || status === 'Apartado') {
                statusBadge.textContent = status;
                statusBadge.classList.toggle('status-apartado', status === 'Apartado');
                statusOverlay.style.display = 'flex';
            }
            return;
        }

        btnApartar.textContent = 'DISPONIBLE';
        btnApartar.style.background = '';

        if (canCreateRequirement) {
            btnApartar.disabled = false;
            btnApartar.setAttribute('aria-disabled', 'false');
            btnApartar.dataset.canRequest = '1';
            btnApartar.style.cursor = 'pointer';
            btnApartar.title = 'Crear un requerimiento de apartado para este auto.';
        } else if (operativoSession.authenticated) {
            btnApartar.style.cursor = 'not-allowed';
            btnApartar.title = 'Tu rol operativo no permite crear requerimientos de compra.';
        } else {
            btnApartar.style.cursor = 'default';
            btnApartar.title = 'Inicia sesión operativa para solicitar el apartado.';
        }
    }

    document.getElementById('btn-apartar')?.addEventListener('click', () => {
        if (!currentAuto || currentAuto.estatus !== 'Disponible') return;
        const button = document.getElementById('btn-apartar');
        if (button?.dataset.canRequest !== '1') return;
        window.location.href = `../operativo/requerimientos.php?auto_id=${encodeURIComponent(currentAuto.id)}`;
    });

    async function registerDetailVisit(autoId) {
        const normalizedId = Number(autoId || 0);
        if (normalizedId <= 0 || visitRegisteredFor === normalizedId) return;
        visitRegisteredFor = normalizedId;

        try {
            await fetch('../db/web/registrar_auto_visita.php', {
                method: 'POST',
                cache: 'no-store',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ auto_id: normalizedId })
            });
        } catch (error) {
            console.warn('No fue posible registrar la visita del vehículo:', error);
        }
    }

    function renderOwnership(auto) {
        const ownersElement = document.getElementById('det-duenos');
        if (!ownersElement) return;

        const owners = Number(auto.duenos) || 0;
        ownersElement.textContent = owners > 0
            ? `${owners} ${owners === 1 ? 'Dueño' : 'Dueños'}`
            : 'N/A Dueños';
    }

    function renderBadges(auto) {
        const idBadge = document.getElementById('badge-id');
        if (idBadge) idBadge.textContent = auto.id ? `#${auto.id}` : '';
        document.getElementById('badge-year').textContent = auto.anio || '';
        const typeBadge = document.getElementById('badge-tipo');

        if (auto.tipo) {
            typeBadge.textContent = auto.tipo;
            typeBadge.style.display = 'block';
        } else {
            typeBadge.style.display = 'none';
        }
    }

    function renderGallery(auto) {
        const sourceImages = [auto.img_principal];
        if (Array.isArray(auto.imagenes)) {
            sourceImages.push(...auto.imagenes);
        }

        galleryImages = [...new Set(sourceImages.filter(Boolean))];
        currentImageIndex = 0;
        thumbsContainer.innerHTML = '';

        if (galleryImages.length === 0) {
            mainImg.removeAttribute('src');
            mainImg.alt = 'Fotografía no disponible';
            return;
        }

        setCurrentImage(0, false);

        galleryImages.forEach((imgUrl, index) => {
            const thumb = document.createElement('img');
            thumb.className = `thumb-item ${index === 0 ? 'active' : ''}`;
            thumb.src = imgUrl;
            thumb.alt = `${vehicleNameForAlt}, vista ${index + 1}`;
            thumb.loading = index > 2 ? 'lazy' : 'eager';
            thumb.decoding = 'async';
            thumb.dataset.galleryIndex = String(index);

            thumb.addEventListener('click', () => setCurrentImage(index, true));
            thumbsContainer.appendChild(thumb);
        });

        updateLightboxControls();
    }

    function setCurrentImage(index, animate = true) {
        if (!galleryImages[index]) return;
        currentImageIndex = index;

        const update = () => {
            mainImg.src = galleryImages[index];
            mainImg.alt = `${vehicleNameForAlt}, vista ${index + 1}`;
            mainImg.style.opacity = '1';
            updateActiveThumbnail();
            updateLightboxImage();
        };

        if (animate) {
            mainImg.style.opacity = '0';
            window.setTimeout(update, 140);
        } else {
            update();
        }
    }

    function updateActiveThumbnail() {
        thumbsContainer.querySelectorAll('.thumb-item').forEach((thumb) => {
            thumb.classList.toggle(
                'active',
                Number(thumb.dataset.galleryIndex) === currentImageIndex
            );
        });
    }

    function openLightbox() {
        if (!lightbox || galleryImages.length === 0) return;
        lightbox.hidden = false;
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lightbox-open');
        updateLightboxImage();
        lightboxClose.focus();
    }

    function closeLightbox() {
        if (!lightbox) return;
        lightbox.hidden = true;
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lightbox-open');
        openLightboxButton?.focus();
    }

    function moveLightbox(direction) {
        if (galleryImages.length < 2) return;
        const nextIndex = (currentImageIndex + direction + galleryImages.length) % galleryImages.length;
        setCurrentImage(nextIndex, false);
    }

    function updateLightboxImage() {
        if (!lightboxImage || galleryImages.length === 0) return;
        lightboxImage.src = galleryImages[currentImageIndex];
        lightboxImage.alt = `${vehicleNameForAlt}, fotografía ${currentImageIndex + 1}`;
        lightboxCounter.textContent = `${currentImageIndex + 1} / ${galleryImages.length}`;
        updateLightboxControls();
    }

    function updateLightboxControls() {
        const hasMultipleImages = galleryImages.length > 1;
        if (lightboxPrev) lightboxPrev.hidden = !hasMultipleImages;
        if (lightboxNext) lightboxNext.hidden = !hasMultipleImages;
    }

    openLightboxButton?.addEventListener('click', openLightbox);
    mainImg?.addEventListener('click', openLightbox);
    mainImg?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openLightbox();
        }
    });
    lightboxClose?.addEventListener('click', closeLightbox);
    lightboxPrev?.addEventListener('click', () => moveLightbox(-1));
    lightboxNext?.addEventListener('click', () => moveLightbox(1));
    lightbox?.addEventListener('click', (event) => {
        if (event.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', (event) => {
        if (!lightbox || lightbox.hidden) return;
        if (event.key === 'Escape') closeLightbox();
        if (event.key === 'ArrowLeft') moveLightbox(-1);
        if (event.key === 'ArrowRight') moveLightbox(1);
    });

    function renderSpecifications(auto, kmFmt) {
        const specsGrid = document.getElementById('specs-grid');
        const specsData = [
            { label: 'Tipo', val: auto.tipo || 'N/A', icon: 'fa-car' },
            { label: 'Año', val: auto.anio || 'N/A', icon: 'fa-calendar-alt' },
            { label: 'Kilometraje', val: kmFmt, icon: 'fa-road' },
            { label: 'Transmisión', val: auto.transmision || 'N/A', icon: 'fa-cogs' },
            { label: 'Ubicación', val: auto.ubicacion || 'N/A', icon: 'fa-map-marker-alt' },
            { label: 'Motor', val: auto.motor || 'N/A', icon: 'fa-car-side' },
            { label: 'Combustible', val: auto.combustible || 'N/A', icon: 'fa-gas-pump' },
            { label: 'Pasajeros', val: auto.pasajeros || 'N/A', icon: 'fa-users' },
            { label: 'Tracción', val: auto.traccion || 'N/A', icon: 'fa-dharmachakra' }
        ];

        specsGrid.innerHTML = '';
        specsData.forEach((spec) => {
            const card = document.createElement('div');
            card.className = 'spec-card';
            card.innerHTML = `
                <i class="fas ${spec.icon} green-text" aria-hidden="true"></i>
                <div>
                    <small>${spec.label}</small>
                    <span>${spec.val}</span>
                </div>
            `;
            specsGrid.appendChild(card);
        });
    }

    function initCotizador() {
        const rangeEnganche = document.getElementById('range-enganche');
        const displayPercent = document.getElementById('display-percent');
        const displayTotalEnganche = document.getElementById('display-total-enganche');
        const inputPlazo = document.getElementById('input-plazo');
        const btnCalculate = document.getElementById('btn-calculate');
        const tablaBody = document.getElementById('tabla-body');
        const cotizacionResult = document.getElementById('resultado-cotizacion');

        if (!rangeEnganche || !btnCalculate) return;

        const updateUI = () => {
            const percent = Number(rangeEnganche.value) || 0;
            const total = (CAR_PRICE * percent) / 100;
            displayPercent.textContent = `${percent}%`;
            displayTotalEnganche.textContent = formatCurrency(total);
        };

        rangeEnganche.addEventListener('input', updateUI);
        updateUI();

        btnCalculate.addEventListener('click', () => {
            const downPayment = (CAR_PRICE * Number(rangeEnganche.value)) / 100;
            const term = Number.parseInt(inputPlazo.value, 10);
            const balance = CAR_PRICE - downPayment;
            const estimatedInterest = 1.15;
            const totalWithInterest = balance * estimatedInterest;
            const monthlyPayment = totalWithInterest / term;

            tablaBody.innerHTML = '';
            let currentBalance = totalWithInterest;

            for (let month = 1; month <= term; month += 1) {
                currentBalance = Math.max(0, currentBalance - monthlyPayment);
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${month}</td>
                    <td>${formatCurrency(monthlyPayment)}</td>
                    <td>${formatCurrency(currentBalance)}</td>
                `;
                tablaBody.appendChild(row);
            }

            cotizacionResult.style.display = 'block';
            cotizacionResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
});
