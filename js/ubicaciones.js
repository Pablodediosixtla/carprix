document.addEventListener('DOMContentLoaded', () => {
    // Menú móvil (Hamburguesa)
    const mobileMenu = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');
    
    if (mobileMenu && navMenu) {
        mobileMenu.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    // Efecto de scroll en header (igual al index)
    window.addEventListener('scroll', () => {
        const header = document.querySelector('.main-header');
        if (window.scrollY > 50) {
            header.style.background = "rgba(26, 26, 26, 0.98)";
        } else {
            header.style.background = "rgba(26, 26, 26, 0.9)";
        }
    });
});