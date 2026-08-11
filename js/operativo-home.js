(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    let currentUser = null;
    let birthdays = [];

    const profileDialog = document.getElementById('my-profile-dialog');
    const profileForm = document.getElementById('my-profile-form');
    const profileMessage = document.getElementById('my-profile-message');
    const passwordDialog = document.getElementById('my-password-dialog');
    const passwordForm = document.getElementById('my-password-form');
    const passwordMessage = document.getElementById('my-password-message');
    const birthdayDialog = document.getElementById('birthday-dialog');
    const birthdayList = document.getElementById('birthday-list');

    const monthName = () => new Intl.DateTimeFormat('es-MX', { month: 'long' }).format(new Date());

    const applyProfile = (user) => {
        currentUser = user;
        OP.applyUser(user);
        const fullName = [user.nombre, user.apellido_paterno].filter(Boolean).join(' ');
        document.getElementById('welcome-name').textContent = `Hola, ${fullName}`;
        document.getElementById('home-user-email').textContent = user.email || 'Sin correo';
        document.getElementById('profile-username').textContent = user.username || '—';
        document.getElementById('profile-email').textContent = user.email || '—';
        document.getElementById('profile-phone').textContent = user.telefono || '—';
        document.getElementById('profile-last-login').textContent = OP.formatDate(user.ultimo_login_at);
        document.getElementById('profile-roles').textContent = (user.roles || []).join(', ').replaceAll('_', ' ') || '—';
        document.getElementById('home-user-roles').innerHTML = (user.roles || []).map((role) => `<span class="op-role-chip">${OP.escapeHtml(role.replaceAll('_', ' '))}</span>`).join('');
    };

    const renderBirthdays = () => {
        const month = monthName();
        document.getElementById('birthday-count').textContent = birthdays.length;
        document.getElementById('birthday-month-label').textContent = `Ver ${month}`;
        document.getElementById('birthday-dialog-title').textContent = `Cumpleaños de ${month}`;

        if (!birthdays.length) {
            birthdayList.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-cake-candles"></i>No hay cumpleaños registrados este mes.</div></div>';
            return;
        }

        birthdayList.innerHTML = birthdays.map((item) => {
            const date = new Date(`${item.fecha_nacimiento}T12:00:00`);
            const label = Number.isNaN(date.getTime())
                ? `Día ${item.dia}`
                : new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'long' }).format(date);
            const roles = (item.roles || []).map((role) => role.replaceAll('_', ' ')).join(', ');
            return `
                <article class="op-birthday-item ${item.es_hoy ? 'today' : ''}">
                    <span class="op-birthday-date"><b>${item.dia}</b><small>${OP.escapeHtml(month.slice(0, 3))}</small></span>
                    <div>
                        <strong>${OP.escapeHtml(item.nombre_completo)}</strong>
                        <span>${OP.escapeHtml(label)}${roles ? ` · ${OP.escapeHtml(roles)}` : ''}</span>
                        ${item.es_hoy ? '<em><i class="fa-solid fa-star"></i> Cumpleaños hoy</em>' : ''}
                    </div>
                </article>`;
        }).join('');
    };

    const loadDashboard = async () => {
        const response = await OP.request('op_dashboard.php');
        const summary = response.data.resumen;
        const visibleMetrics = new Set(response.data.metricas_visibles || Object.keys(summary || {}));
        document.querySelectorAll('[data-metric]').forEach((element) => {
            const metric = element.dataset.metric;
            const card = element.closest('.op-metric-card');
            const isVisible = visibleMetrics.has(metric);
            if (card) card.hidden = !isVisible;
            if (isVisible) {
                const value = summary?.[metric] ?? 0;
                element.textContent = new Intl.NumberFormat('es-MX').format(value);
            }
        });

        const badge = document.getElementById('nav-approval-count');
        if (badge && summary.autorizaciones_pendientes > 0) {
            badge.hidden = false;
            badge.textContent = summary.autorizaciones_pendientes > 99 ? '99+' : summary.autorizaciones_pendientes;
        }

        const latest = document.getElementById('latest-requirements');
        const items = response.data.ultimos_requerimientos || [];
        latest.innerHTML = items.length ? items.map((item) => `
            <div class="op-list-item">
                <span class="op-status-badge ${OP.statusClass(item.estatus)}">${OP.escapeHtml(item.estatus)}</span>
                <div class="op-list-item-main">
                    <strong>${OP.escapeHtml(item.folio)} · ${OP.escapeHtml(item.cliente_nombre)}</strong>
                    <span>${OP.escapeHtml(item.marca)} ${OP.escapeHtml(item.modelo)} ${item.anio} · ${OP.escapeHtml(item.responsable)}</span>
                </div>
                <div class="op-list-item-amount"><strong>${OP.formatCurrency(item.monto_propuesto)}</strong><span>${OP.formatDate(item.fecha_solicitud, false)}</span></div>
            </div>`).join('') : '<div class="op-empty"><div><i class="fa-solid fa-inbox"></i>No hay requerimientos registrados.</div></div>';
    };

    const loadBirthdays = async () => {
        const response = await OP.request('op_c_cumpleanios.php');
        birthdays = response.data.items || [];
        renderBirthdays();
    };

    try {
        const user = await OP.loadSession();
        if (!user) return;
        if (user.debe_cambiar_password) {
            await OP.forcePasswordChange();
            location.reload();
            return;
        }
        applyProfile(user);

        document.getElementById('edit-my-profile').addEventListener('click', () => {
            OP.setMessage(profileMessage);
            document.getElementById('my-profile-email').value = currentUser?.email || '';
            document.getElementById('my-profile-phone').value = currentUser?.telefono || '';
            profileDialog.showModal();
        });

        document.getElementById('change-my-password').addEventListener('click', () => {
            passwordForm.reset();
            OP.setMessage(passwordMessage);
            passwordDialog.showModal();
        });

        document.getElementById('birthday-summary-button').addEventListener('click', () => {
            renderBirthdays();
            birthdayDialog.showModal();
        });

        profileForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = profileForm.querySelector('button[type="submit"]');
            OP.setMessage(profileMessage);
            OP.buttonLoading(button, true, 'Guardando...');
            try {
                const response = await OP.request('op_u_mi_perfil.php', {
                    email: document.getElementById('my-profile-email').value.trim(),
                    telefono: document.getElementById('my-profile-phone').value.trim(),
                }, { csrf: true });
                applyProfile(response.data.usuario);
                profileDialog.close();
                OP.toast('Tu información fue actualizada correctamente.');
            } catch (error) {
                OP.setMessage(profileMessage, error.message);
            } finally {
                OP.buttonLoading(button, false);
            }
        });

        passwordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = passwordForm.querySelector('button[type="submit"]');
            OP.setMessage(passwordMessage);
            OP.buttonLoading(button, true, 'Actualizando...');
            try {
                await OP.request('op_change_password.php', {
                    password_actual: document.getElementById('my-password-current').value,
                    password_nuevo: document.getElementById('my-password-new').value,
                    password_confirmacion: document.getElementById('my-password-confirm').value,
                }, { csrf: true });
                passwordForm.reset();
                passwordDialog.close();
                OP.toast('Contraseña actualizada correctamente.');
            } catch (error) {
                OP.setMessage(passwordMessage, error.message);
            } finally {
                OP.buttonLoading(button, false);
            }
        });

        await Promise.all([loadDashboard(), loadBirthdays()]);
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
