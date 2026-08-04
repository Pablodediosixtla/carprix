(async () => {
    'use strict';
    const OP = window.CARPRIX_OP;
    const state = { items: [] };
    const table = document.getElementById('hierarchy-table');
    const form = document.getElementById('hierarchy-form');
    const message = document.getElementById('hierarchy-message');
    const userSelect = document.getElementById('hierarchy-user');
    const supervisorSelect = document.getElementById('hierarchy-supervisor');

    const canAuthorize = (item) => (item.roles || []).some((role) => ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR'].includes(role));

    const populateSelects = () => {
        userSelect.innerHTML = '<option value="">Selecciona un trabajador</option>' + state.items.filter((item) => item.usuario_estatus === 'Activo').map((item) => `<option value="${item.usuario_id}">${OP.escapeHtml(item.usuario_nombre)} · ${OP.escapeHtml(item.username)}</option>`).join('');
        supervisorSelect.innerHTML = '<option value="0">Sin supervisor</option>' + state.items.filter((item) => item.usuario_estatus === 'Activo' && canAuthorize(item)).map((item) => `<option value="${item.usuario_id}">${OP.escapeHtml(item.usuario_nombre)} · ${OP.escapeHtml(item.username)}</option>`).join('');
    };

    const render = () => {
        table.innerHTML = state.items.map((item) => `
            <tr>
                <td><strong>${OP.escapeHtml(item.usuario_nombre)}</strong><br><span class="op-muted">${OP.escapeHtml(item.username)}</span></td>
                <td>${(item.roles || []).map((role) => `<span class="op-role-chip">${OP.escapeHtml(role)}</span>`).join(' ') || '—'}</td>
                <td>${item.supervisor_nombre ? `<strong>${OP.escapeHtml(item.supervisor_nombre)}</strong><br><span class="op-muted">${OP.escapeHtml(item.supervisor_username)}</span>` : '<span class="op-muted">Sin supervisor</span>'}</td>
                <td><span class="op-status-badge ${item.activo ? 'aprobado' : 'oculto'}">${item.activo ? 'Activa' : 'Sin asignar'}</span></td>
                <td><button class="op-secondary-button" data-edit-hierarchy="${item.usuario_id}"><i class="fa-solid fa-pen"></i></button></td>
            </tr>`).join('');
        table.querySelectorAll('[data-edit-hierarchy]').forEach((button) => {
            button.addEventListener('click', () => {
                const item = state.items.find((row) => row.usuario_id === Number(button.dataset.editHierarchy));
                userSelect.value = item.usuario_id;
                supervisorSelect.value = item.supervisor_id || 0;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    };

    const load = async () => {
        table.innerHTML = '<tr><td colspan="5">Cargando jerarquía...</td></tr>';
        try {
            const response = await OP.request('op_c_jerarquia.php');
            state.items = response.data.items;
            populateSelects();
            render();
        } catch (error) {
            table.innerHTML = `<tr><td colspan="5">${OP.escapeHtml(error.message)}</td></tr>`;
        }
    };

    try {
        const user = await OP.loadSession();
        if (!user) return;
        if (user.debe_cambiar_password) { await OP.forcePasswordChange(); location.reload(); return; }
        if (!OP.hasAnyRole(user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO'])) { location.href = 'home.php'; return; }
        document.getElementById('hierarchy-refresh').addEventListener('click', load);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            OP.setMessage(message);
            OP.buttonLoading(button, true, 'Guardando...');
            try {
                await OP.request('op_u_jerarquia.php', {
                    usuario_id: Number(userSelect.value),
                    supervisor_id: Number(supervisorSelect.value),
                }, { csrf: true });
                OP.toast('Jerarquía actualizada.');
                await load();
            } catch (error) {
                OP.setMessage(message, error.message);
            } finally {
                OP.buttonLoading(button, false);
            }
        });
        await load();
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
