document.addEventListener('DOMContentLoaded', () => {
    const formVende = document.getElementById('form-vende');
    const msg = document.getElementById('vende-mensaje');
    const btnSubmit = document.getElementById('btn-vende');

    const mobileMenu = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');
    
    if (mobileMenu && navMenu) {
        mobileMenu.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    if (formVende) {
        formVende.onsubmit = async (e) => {
            e.preventDefault(); 

            btnSubmit.disabled = true;
            btnSubmit.innerText = 'ENVIANDO...';
            msg.innerHTML = '<span style="color: var(--green-accent)">Procesando tu solicitud...</span>';

            const data = {
                marca: document.getElementById('v-marca').value.trim(),
                modelo: document.getElementById('v-modelo').value.trim(),
                version: document.getElementById('v-version').value.trim(),
                anio: document.getElementById('v-anio').value,
                km: document.getElementById('v-km').value,
                color: document.getElementById('v-color').value.trim(),
                transmision: document.getElementById('v-transmision').value,
                tipo_factura: document.getElementById('v-factura').value,
                propietarios: document.getElementById('v-propietarios').value,
                nombre: document.getElementById('v-nombre').value.trim(),
                tel: document.getElementById('v-tel').value.trim(),
                comentarios: document.getElementById('v-comentarios').value.trim()
            };

            try {
                const response = await fetch('../db/web/insert_prospecto.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.ok) {
                    msg.innerHTML = `<span style="color: var(--green-accent)"><i class="fas fa-check-circle"></i> ${result.mensaje}</span>`;
                    formVende.reset();
                } else {
                    msg.innerHTML = `<span style="color: #ff5252;"><i class="fas fa-exclamation-circle"></i> Error: ${result.error}</span>`;
                }

            } catch (error) {
                console.error("Error en la solicitud:", error);
                msg.innerHTML = '<span style="color: #ff5252;"><i class="fas fa-wifi"></i> Error de conexión con el servidor. Inténtalo más tarde.</span>';
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.innerText = 'ENVIAR SOLICITUD';
            }
        };
    }
});