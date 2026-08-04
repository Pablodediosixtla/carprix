(() => {
    'use strict';
    const OP = window.CARPRIX_OP;
    const form = document.getElementById('login-form');
    const button = document.getElementById('login-button');
    const message = document.getElementById('login-message');

    const checkExistingSession = async () => {
        try {
            const user = await OP.loadSession({ redirect: false });
            if (!user) return;
            if (user.debe_cambiar_password) {
                await OP.forcePasswordChange('');
            }
            location.href = 'home.php';
        } catch (error) {
            console.warn(error);
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        OP.setMessage(message);
        OP.buttonLoading(button, true, 'Validando...');
        const password = document.getElementById('password').value;
        try {
            const response = await OP.request('op_login.php', {
                login: document.getElementById('login').value.trim(),
                password,
            }, { redirectOnAuth: false });
            const user = response.data.usuario;
            OP.setToken(response.data.csrf_token);
            if (user.debe_cambiar_password) {
                await OP.forcePasswordChange(password);
            }
            location.href = 'home.php';
        } catch (error) {
            OP.setMessage(message, error.message);
            OP.buttonLoading(button, false);
        }
    });

    checkExistingSession();
})();
