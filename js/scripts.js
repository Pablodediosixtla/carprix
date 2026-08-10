document.addEventListener('DOMContentLoaded', () => {
    // 1. Carrusel de Fondo (Hero)
    const slides = document.querySelectorAll('.hero-slide');
    let currentSlide = 0;
    if (slides.length > 0) {
        setInterval(() => {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }, 5000);
    }

    // 2. Menú Móvil
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

    // 3. Cargar los tres autos destacados configurados en Operativo / Catálogo.
    loadFeaturedCars();
});

async function loadFeaturedCars() {
    const grid = document.getElementById('car-grid');
    if (!grid) return;

    try {
        const response = await fetch(`db/web/get_autos_destacados.php?ts=${Date.now()}`, {
            cache: 'no-store',
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const result = await response.json();

        if (result.ok && Array.isArray(result.data) && result.data.length > 0) {
            grid.innerHTML = '';

            result.data.slice(0, 3).forEach((auto) => {
                const priceFmt = new Intl.NumberFormat('es-MX', {
                    style: 'currency',
                    currency: 'MXN',
                }).format(auto.precio);
                const kmFmt = `${new Intl.NumberFormat('es-MX').format(auto.kilometraje)} km`;
                const rawImage = String(auto.img_principal || 'img/hero-default.jpg');
                const separator = rawImage.includes('?') ? '&' : '?';
                const imageSrc = `${rawImage}${separator}carprix_cache=${Date.now()}`;

                grid.insertAdjacentHTML('beforeend', `
                    <div class="car-card">
                        <div class="car-img">
                            <img src="${imageSrc}" alt="${auto.marca} ${auto.modelo}">
                            <span class="year-badge">${auto.anio}</span>
                        </div>
                        <div class="car-info">
                            <h3>${auto.marca} ${auto.modelo}</h3>
                            <p class="km">${kmFmt}</p>
                            <p class="price">${priceFmt}</p>
                            <a href="views/detalle.php?id=${auto.id}" class="btn-details">Ver detalles</a>
                        </div>
                    </div>
                `);
            });
        } else {
            grid.innerHTML = '<p style="text-align:center; grid-column: 1 / -1; color: var(--gray-text);">No hay autos disponibles por el momento.</p>';
        }
    } catch (error) {
        console.error('Error al cargar los autos destacados:', error);
        grid.innerHTML = '<p style="text-align:center; grid-column: 1 / -1; color: #ff5252;">Error al conectar con la base de datos.</p>';
    }
}
