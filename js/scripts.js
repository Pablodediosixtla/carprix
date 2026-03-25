document.addEventListener('DOMContentLoaded', () => {
    // 1. Carrusel de Fondo (Hero)
    const slides = document.querySelectorAll('.hero-slide');
    let currentSlide = 0;
    if(slides.length > 0) {
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
    }

    // Cerrar menú al hacer clic en un enlace
    const navLinks = document.querySelectorAll('.nav-menu a');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
        });
    });

    // 3. Efecto de scroll en header
    window.addEventListener('scroll', () => {
        const header = document.querySelector('.main-header');
        if (window.scrollY > 50) {
            header.style.boxShadow = "0 5px 20px rgba(0,0,0,0.8)";
            header.style.background = "rgba(26, 26, 26, 0.98)";
        } else {
            header.style.boxShadow = "none";
            header.style.background = "rgba(26, 26, 26, 0.9)";
        }
    });

    // 4. Cargar Autos Destacados desde la API
    loadFeaturedCars();
});

// FUNCIÓN PARA CONSUMIR EL SERVICIO API
async function loadFeaturedCars() {
    const grid = document.getElementById('car-grid');
    if (!grid) return;

    try {
        // Hacemos la petición a tu servicio
        const response = await fetch('db/web/get_autos.php');
        const result = await response.json();

        if (result.ok && result.data.length > 0) {
            grid.innerHTML = ''; // Limpiamos el mensaje de "Cargando..."
            
            // Tomamos solo los primeros 3 para el index
            const autosDestacados = result.data.slice(0, 3);

            autosDestacados.forEach(auto => {
                // Formateamos precio y kilometraje
                const priceFmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(auto.precio);
                const kmFmt = new Intl.NumberFormat('es-MX').format(auto.kilometraje) + ' km';

                const cardHTML = `
                    <div class="car-card">
                        <div class="car-img">
                            <img src="${auto.img_principal}" alt="${auto.marca} ${auto.modelo}">
                            <span class="year-badge">${auto.anio}</span>
                        </div>
                        <div class="car-info">
                            <h3>${auto.marca} ${auto.modelo}</h3>
                            <p class="km">${kmFmt}</p>
                            <p class="price">${priceFmt}</p>
                            <a href="views/detalle.php?id=${auto.id}" class="btn-details">Ver detalles</a>
                        </div>
                    </div>
                `;
                grid.innerHTML += cardHTML;
            });
        } else {
            grid.innerHTML = '<p style="text-align:center; grid-column: 1 / -1; color: var(--gray-text);">No hay autos disponibles por el momento.</p>';
        }
    } catch (error) {
        console.error("Error al cargar los autos:", error);
        grid.innerHTML = '<p style="text-align:center; grid-column: 1 / -1; color: #ff5252;">Error al conectar con la base de datos.</p>';
    }
}