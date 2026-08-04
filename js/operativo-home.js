(async () => {
    'use strict';
    const OP = window.CARPRIX_OP;
    try {
        const user = await OP.loadSession();
        if (!user) return;
        if (user.debe_cambiar_password) {
            await OP.forcePasswordChange();
            location.reload();
            return;
        }

        const fullName = [user.nombre, user.apellido_paterno].filter(Boolean).join(' ');
        document.getElementById('welcome-name').textContent = `Hola, ${fullName}`;
        document.getElementById('home-user-email').textContent = user.email || 'Sin correo';
        document.getElementById('profile-username').textContent = user.username;
        document.getElementById('profile-email').textContent = user.email || '—';
        document.getElementById('profile-last-login').textContent = OP.formatDate(user.ultimo_login_at);
        document.getElementById('profile-roles').textContent = (user.roles || []).join(', ').replaceAll('_', ' ');
        document.getElementById('home-user-roles').innerHTML = (user.roles || []).map((role) => `<span class="op-role-chip">${OP.escapeHtml(role.replaceAll('_', ' '))}</span>`).join('');

        const response = await OP.request('op_dashboard.php');
        const summary = response.data.resumen;
        document.querySelectorAll('[data-metric]').forEach((element) => {
            element.textContent = new Intl.NumberFormat('es-MX').format(summary[element.dataset.metric] || 0);
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
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
