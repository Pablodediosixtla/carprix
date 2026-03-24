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
            header.style.background = "rgba(18, 18, 18, 0.98)";
        } else {
            header.style.boxShadow = "none";
            header.style.background = "rgba(18, 18, 18, 0.9)";
        }
    });
});

