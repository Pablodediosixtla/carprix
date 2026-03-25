document.addEventListener('DOMContentLoaded', () => {
    const formVende = document.getElementById('form-vende');
    const msg = document.getElementById('vende-mensaje');
    const btnSubmit = document.getElementById('btn-vende');

    // 1. Manejo del Menú Móvil (Hamburguesa)
    const mobileMenu = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');
    
    if (mobileMenu && navMenu) {
        mobileMenu.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    // 2. Lógica de Envío del Formulario
    if (formVende) {
        formVende.onsubmit = async (e) => {
            e.preventDefault(); // Evita que la página se recargue

            // Bloquear botón y mostrar estado de carga
            btnSubmit.disabled = true;
            btnSubmit.innerText = 'ENVIANDO...';
            msg.innerHTML = '<span style="color: var(--green-accent)">Procesando tu solicitud...</span>';

            // Recolectar datos del formulario
            const data = {
                marca: document.getElementById('v-marca').value.trim(),
                modelo: document.getElementById('v-modelo').value.trim(),
                anio: document.getElementById('v-anio').value,
                km: document.getElementById('v-km').value,
                nombre: document.getElementById('v-nombre').value.trim(),
                tel: document.getElementById('v-tel').value.trim(),
                comentarios: document.getElementById('v-comentarios').value.trim()
            };

            try {
                // Petición al servicio PHP que creamos
                const response = await fetch('../db/web/insert_prospecto.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.ok) {
                    // Éxito: Mostrar mensaje del servidor y limpiar campos
                    msg.innerHTML = `<span style="color: var(--green-accent)"><i class="fas fa-check-circle"></i> ${result.mensaje}</span>`;
                    formVende.reset();
                } else {
                    // Error del servidor (validación o base de datos)
                    msg.innerHTML = `<span style="color: #ff5252;"><i class="fas fa-exclamation-circle"></i> Error: ${result.error}</span>`;
                }

            } catch (error) {
                // Error de red o conexión
                console.error("Error en la solicitud:", error);
                msg.innerHTML = '<span style="color: #ff5252;"><i class="fas fa-wifi"></i> Error de conexión con el servidor. Inténtalo más tarde.</span>';
            } finally {
                // Reactivar botón
                btnSubmit.disabled = false;
                btnSubmit.innerText = 'ENVIAR SOLICITUD';
            }
        };
    }
});