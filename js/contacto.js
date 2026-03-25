document.addEventListener('DOMContentLoaded', () => {
    const formContacto = document.getElementById('form-contacto');
    const msgStatus = document.getElementById('status-contacto');
    const btnSubmit = document.getElementById('btn-contacto');

    // Menú móvil (Hamburguesa)
    const mobileMenu = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');
    
    if (mobileMenu && navMenu) {
        mobileMenu.onclick = () => navMenu.classList.toggle('active');
    }

    // Envío del formulario de contacto
    if (formContacto) {
        formContacto.onsubmit = async (e) => {
            e.preventDefault();

            // Estado visual de carga
            btnSubmit.disabled = true;
            btnSubmit.innerText = 'ENVIANDO...';
            msgStatus.innerHTML = '<span style="color: var(--green-accent)">Enviando tu mensaje...</span>';

            const data = {
                nombre: document.getElementById('c-nombre').value.trim(),
                email: document.getElementById('c-email').value.trim(),
                asunto: document.getElementById('c-asunto').value,
                mensaje: document.getElementById('c-mensaje').value.trim()
            };

            try {
                const response = await fetch('../db/web/insert_contacto.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.ok) {
                    msgStatus.innerHTML = `<span style="color: var(--green-accent)"><i class="fas fa-check-circle"></i> ${result.mensaje}</span>`;
                    formContacto.reset();
                } else {
                    msgStatus.innerHTML = `<span style="color: #ff5252;"><i class="fas fa-exclamation-triangle"></i> ${result.error}</span>`;
                }

            } catch (error) {
                console.error("Error en contacto:", error);
                msgStatus.innerHTML = '<span style="color: #ff5252;">Error de conexión. Inténtalo más tarde.</span>';
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.innerText = 'ENVIAR MENSAJE';
            }
        };
    }
});