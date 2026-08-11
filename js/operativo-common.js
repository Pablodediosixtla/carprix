(() => {
    'use strict';

    const API_BASE = '../db/web/operativo';
    const TOKEN_KEY = 'carprix-operativo-csrf';

    const getToken = () => sessionStorage.getItem(TOKEN_KEY) || '';
    const setToken = (token) => {
        if (token) sessionStorage.setItem(TOKEN_KEY, token);
        else sessionStorage.removeItem(TOKEN_KEY);
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const formatCurrency = (value) => {
        if (value === null || value === undefined || value === '') return '—';
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            maximumFractionDigits: 2,
        }).format(Number(value));
    };

    const formatDate = (value, includeTime = true) => {
        if (!value) return '—';
        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return String(value);
        return new Intl.DateTimeFormat('es-MX', {
            dateStyle: 'medium',
            ...(includeTime ? { timeStyle: 'short' } : {}),
        }).format(parsed);
    };

    const IMAGE_CACHE_VERSION = Date.now().toString(36);

    const imageUrl = (value) => {
        const path = String(value || '').trim();
        if (path.startsWith('data:') || path.startsWith('blob:')) return path;

        let url;
        if (!path) url = '../img/hero-default.jpg';
        else if (/^https?:\/\//i.test(path)) url = path;
        else if (path.startsWith('/')) url = `..${path}`;
        else url = `../${path.replace(/^\.\//, '')}`;

        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}carprix_cache=${IMAGE_CACHE_VERSION}`;
    };

    const request = async (endpoint, payload = {}, options = {}) => {
        const { csrf = false, redirectOnAuth = true } = options;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };
        if (csrf) headers['X-CSRF-Token'] = getToken();

        let response;
        try {
            response = await fetch(`${API_BASE}/${endpoint}`, {
                method: 'POST',
                credentials: 'include',
                headers,
                body: JSON.stringify(payload),
            });
        } catch (error) {
            throw new Error('No fue posible conectar con el servidor.');
        }

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Respuesta no JSON:', text.slice(0, 800));
            throw new Error(`El servidor respondió con formato no válido (${response.status}).`);
        }

        const body = await response.json();
        if (!response.ok || body.ok === false) {
            const error = new Error(body.error || body.message || 'La operación no pudo completarse.');
            error.code = body.code || 'REQUEST_ERROR';
            error.status = response.status;
            error.details = body.details || null;

            if (redirectOnAuth && (response.status === 401 || error.code === 'SESSION_EXPIRED' || error.code === 'SESSION_INVALID')) {
                setToken('');
                if (!location.pathname.endsWith('/login.php')) location.href = 'login.php';
            }
            throw error;
        }

        if (body.data?.csrf_token) setToken(body.data.csrf_token);
        return body;
    };

    const upload = async (endpoint, formData, options = {}) => {
        const { redirectOnAuth = true } = options;
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-Token': getToken(),
        };

        let response;
        try {
            response = await fetch(`${API_BASE}/${endpoint}`, {
                method: 'POST',
                credentials: 'include',
                headers,
                body: formData,
            });
        } catch (error) {
            throw new Error('No fue posible cargar los archivos al servidor.');
        }

        /*
         * Algunos errores de PHP/FPM pueden llegar a través de Nginx con un
         * Content-Type distinto aunque el cuerpo siga siendo JSON. Leemos el
         * cuerpo primero e intentamos interpretarlo antes de declarar que la
         * respuesta no es válida. Esto además conserva el código real de la API.
         */
        const raw = await response.text();
        let body = null;
        try {
            body = raw ? JSON.parse(raw) : null;
        } catch (parseError) {
            console.error('Respuesta de carga no JSON:', {
                status: response.status,
                contentType: response.headers.get('content-type') || '',
                body: raw.slice(0, 1200),
            });
            throw new Error(`La carga de imágenes falló en el servidor (HTTP ${response.status}).`);
        }

        if (!body || typeof body !== 'object') {
            throw new Error(`La carga de imágenes devolvió una respuesta vacía o inválida (HTTP ${response.status}).`);
        }

        if (!response.ok || body.ok === false) {
            const error = new Error(body.error || body.message || 'La carga no pudo completarse.');
            error.code = body.code || 'UPLOAD_ERROR';
            error.status = response.status;
            error.details = body.details || null;

            if (redirectOnAuth && (response.status === 401 || error.code === 'SESSION_EXPIRED' || error.code === 'SESSION_INVALID')) {
                setToken('');
                if (!location.pathname.endsWith('/login.php')) location.href = 'login.php';
            }
            throw error;
        }

        if (body.data?.csrf_token) setToken(body.data.csrf_token);
        return body;
    };

    const initials = (user) => {
        const parts = [user?.nombre, user?.apellido_paterno].filter(Boolean);
        return parts.map((part) => String(part).trim().charAt(0)).join('').slice(0, 2).toUpperCase() || 'CP';
    };

    const hasAnyRole = (user, allowed) => {
        const roles = new Set((user?.roles || []).map((role) => String(role).toUpperCase()));
        return allowed.some((role) => roles.has(String(role).toUpperCase()));
    };

    const PAGE_ROLES = {
        personas: ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR'],
        gestion_recompensas: ['SUPER_ADMIN', 'ADMIN_OPERATIVO'],
        catalogo: ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'INVENTARIO'],
        requerimientos: ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR', 'INVENTARIO', 'VENTAS'],
        autorizaciones: ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR'],
        jerarquia: ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR', 'INVENTARIO', 'VENTAS'],
    };

    const guardCurrentPage = (user) => {
        const page = document.body?.dataset?.page || '';
        const allowed = PAGE_ROLES[page];
        if (Array.isArray(allowed) && allowed.length > 0 && !hasAnyRole(user, allowed)) {
            location.href = 'home.php';
            return false;
        }
        return true;
    };

    const applyRoleVisibility = (user) => {
        document.querySelectorAll('[data-required-roles]').forEach((element) => {
            const required = (element.dataset.requiredRoles || '').split(',').map((item) => item.trim()).filter(Boolean);
            element.hidden = required.length > 0 && !hasAnyRole(user, required);
        });
    };

    const applyUser = (user) => {
        const fullName = [user?.nombre, user?.apellido_paterno].filter(Boolean).join(' ') || user?.username || 'Usuario';
        const primaryRole = (user?.roles || [])[0] || 'Operativo';
        document.querySelectorAll('.js-user-name').forEach((el) => { el.textContent = fullName; });
        document.querySelectorAll('.js-user-role').forEach((el) => { el.textContent = primaryRole.replaceAll('_', ' '); });
        document.querySelectorAll('.js-user-initials').forEach((el) => { el.textContent = initials(user); });
        applyRoleVisibility(user);
    };

    const loadSession = async ({ redirect = true } = {}) => {
        const response = await request('op_session.php', {}, { redirectOnAuth: false });
        const { authenticated, usuario, csrf_token: token } = response.data;
        if (!authenticated || !usuario) {
            setToken('');
            if (redirect && !location.pathname.endsWith('/login.php')) location.href = 'login.php';
            return null;
        }
        setToken(token);
        applyUser(usuario);
        if (!guardCurrentPage(usuario)) return null;
        return usuario;
    };

    const toast = (message, type = 'success', duration = 4200) => {
        const zone = document.getElementById('op-toast-zone');
        if (!zone) return;
        const item = document.createElement('div');
        item.className = `op-toast ${type}`;
        item.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i><span>${escapeHtml(message)}</span>`;
        zone.appendChild(item);
        window.setTimeout(() => item.remove(), duration);
    };

    const setMessage = (element, message = '', type = 'error') => {
        if (!element) return;
        if (!message) {
            element.hidden = true;
            element.textContent = '';
            element.className = 'op-form-message';
            return;
        }
        element.hidden = false;
        element.textContent = message;
        element.className = `op-form-message ${type}`;
    };

    const buttonLoading = (button, loading, loadingText = 'Procesando...') => {
        if (!button) return;
        if (loading) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${escapeHtml(loadingText)}`;
        } else {
            button.disabled = false;
            if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
        }
    };

    const pagination = (container, data, callback) => {
        if (!container) return;
        const { page = 1, pages = 1 } = data || {};
        if (pages <= 1) {
            container.innerHTML = '';
            return;
        }
        const start = Math.max(1, page - 2);
        const end = Math.min(pages, page + 2);
        const buttons = [];
        if (page > 1) buttons.push(`<button class="op-page-button" data-page="${page - 1}" aria-label="Anterior"><i class="fa-solid fa-chevron-left"></i></button>`);
        for (let current = start; current <= end; current += 1) {
            buttons.push(`<button class="op-page-button ${current === page ? 'active' : ''}" data-page="${current}">${current}</button>`);
        }
        if (page < pages) buttons.push(`<button class="op-page-button" data-page="${page + 1}" aria-label="Siguiente"><i class="fa-solid fa-chevron-right"></i></button>`);
        container.innerHTML = buttons.join('');
        container.querySelectorAll('[data-page]').forEach((button) => {
            button.addEventListener('click', () => callback(Number(button.dataset.page)));
        });
    };

    const bindDialogs = () => {
        document.addEventListener('click', (event) => {
            const closeButton = event.target.closest('[data-close-dialog]');
            if (closeButton) closeButton.closest('dialog')?.close();
        });
        document.querySelectorAll('dialog').forEach((dialog) => {
            /*
             * En un <select> nativo, el navegador puede reportar el clic de una
             * opción fuera del rectángulo visual del <dialog>. La validación por
             * coordenadas cerraba el modal aunque el usuario estuviera usando un
             * control interno. El backdrop del diálogo tiene como target al propio
             * elemento <dialog>, por lo que esta comprobación es más segura.
             */
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        });
    };

    const bindPasswordToggles = () => {
        document.querySelectorAll('[data-password-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordTarget);
                if (!input) return;
                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                button.querySelector('i').className = visible ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
            });
        });
    };

    const forcePasswordChange = (currentPassword = '') => new Promise((resolve, reject) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'op-dialog';
        dialog.innerHTML = `
            <form class="op-dialog-card" id="forced-password-form">
                <div class="op-dialog-header"><div><small>SEGURIDAD</small><h3>Cambia tu contraseña temporal</h3></div></div>
                <p class="op-dialog-description">Antes de continuar debes establecer una contraseña personal.</p>
                <label class="op-field"><span>Contraseña actual</span><input id="forced-current" type="password" required value="${escapeHtml(currentPassword)}"></label>
                <label class="op-field"><span>Nueva contraseña</span><input id="forced-new" type="password" required minlength="10"></label>
                <label class="op-field"><span>Confirmar contraseña</span><input id="forced-confirm" type="password" required minlength="10"></label>
                <div class="op-form-message" id="forced-message" hidden></div>
                <div class="op-dialog-actions"><button class="op-primary-button" id="forced-submit" type="submit">Actualizar contraseña</button></div>
            </form>`;
        document.body.appendChild(dialog);
        dialog.showModal();
        const form = dialog.querySelector('form');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = dialog.querySelector('#forced-submit');
            const message = dialog.querySelector('#forced-message');
            setMessage(message);
            buttonLoading(button, true, 'Actualizando...');
            try {
                const response = await request('op_change_password.php', {
                    password_actual: dialog.querySelector('#forced-current').value,
                    password_nuevo: dialog.querySelector('#forced-new').value,
                    password_confirmacion: dialog.querySelector('#forced-confirm').value,
                }, { csrf: true });
                setToken(response.data.csrf_token);
                toast('Contraseña actualizada correctamente.');
                dialog.close();
                dialog.remove();
                resolve(response);
            } catch (error) {
                setMessage(message, error.message);
                buttonLoading(button, false);
            }
        });
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            reject(new Error('Debes cambiar tu contraseña para continuar.'));
        });
    });

    const bindShell = () => {
        const sidebar = document.getElementById('op-sidebar');
        const overlay = document.getElementById('op-sidebar-overlay');
        const open = () => { sidebar?.classList.add('open'); overlay?.classList.add('open'); };
        const close = () => { sidebar?.classList.remove('open'); overlay?.classList.remove('open'); };
        document.getElementById('op-menu-button')?.addEventListener('click', open);
        overlay?.addEventListener('click', close);
        document.querySelectorAll('.op-nav-link').forEach((link) => link.addEventListener('click', close));

        document.getElementById('op-logout-button')?.addEventListener('click', async () => {
            try {
                await request('op_logout.php', {}, { csrf: true, redirectOnAuth: false });
            } catch (error) {
                console.warn(error);
            } finally {
                setToken('');
                location.href = 'login.php';
            }
        });
    };

    const statusClass = (status) => String(status || '').toLowerCase().replaceAll(' ', '-');

    document.addEventListener('DOMContentLoaded', () => {
        bindDialogs();
        bindPasswordToggles();
        bindShell();
    });

    window.CARPRIX_OP = {
        request,
        upload,
        getToken,
        setToken,
        loadSession,
        applyUser,
        hasAnyRole,
        initials,
        escapeHtml,
        formatCurrency,
        formatDate,
        imageUrl,
        toast,
        setMessage,
        buttonLoading,
        pagination,
        forcePasswordChange,
        statusClass,
    };
})();
