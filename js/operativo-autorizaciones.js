(async () => {
    'use strict';
    const OP = window.CARPRIX_OP;
    const state = { page: 1, timer: null, changes: new Map() };
    const list = document.getElementById('approval-list');
    const pagination = document.getElementById('approval-pagination');
    const dialog = document.getElementById('decision-dialog');
    const form = document.getElementById('decision-form');
    const message = document.getElementById('decision-message');

    const openDecision = (id, decision) => {
        const item = state.changes.get(id);
        document.getElementById('decision-change-id').value = id;
        document.getElementById('decision-value').value = decision;
        document.getElementById('decision-title').textContent = decision === 'Aprobado' ? 'Autorizar cambio' : 'Rechazar cambio';
        document.getElementById('decision-description').textContent = `${item.folio}: ${item.estatus_origen} → ${item.estatus_solicitado}. Solicitante: ${item.solicitante}.`;
        document.getElementById('decision-comment').value = '';
        document.getElementById('decision-submit').className = decision === 'Aprobado' ? 'op-success-button' : 'op-danger-button';
        document.getElementById('decision-submit').textContent = decision === 'Aprobado' ? 'Autorizar' : 'Rechazar';
        OP.setMessage(message);
        dialog.showModal();
    };

    const render = (items) => {
        state.changes.clear();
        items.forEach((item) => state.changes.set(item.id, item));
        if (!items.length) {
            list.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-circle-check"></i>No hay autorizaciones con estos filtros.</div></div>';
            return;
        }
        list.innerHTML = items.map((item) => `
            <article class="op-approval-card">
                <div class="op-card-copy"><span class="op-card-label">${OP.escapeHtml(item.folio)}</span><h3>${OP.escapeHtml(item.cliente_nombre)}</h3><p>${OP.escapeHtml(item.solicitante)} · ${OP.formatDate(item.fecha_solicitud)}</p></div>
                <div class="op-card-copy"><span class="op-card-label">AUTO #${item.auto_id}</span><h3>${OP.escapeHtml(item.marca)} ${OP.escapeHtml(item.modelo)} ${item.anio}</h3><p>${OP.escapeHtml(item.motivo)}</p></div>
                <div class="op-card-copy"><span class="op-card-label">CAMBIO SOLICITADO</span><p class="op-transition"><span class="op-status-badge ${OP.statusClass(item.estatus_origen)}">${OP.escapeHtml(item.estatus_origen)}</span><i class="fa-solid fa-arrow-right"></i><span class="op-status-badge ${OP.statusClass(item.estatus_solicitado)}">${OP.escapeHtml(item.estatus_solicitado)}</span></p><p>Decisión: <span class="op-status-badge ${OP.statusClass(item.decision)}">${OP.escapeHtml(item.decision)}</span></p></div>
                <div class="op-card-actions">${item.decision === 'Pendiente' && item.puede_resolver ? `<button class="op-success-button" data-approve="${item.id}"><i class="fa-solid fa-check"></i> Aprobar</button><button class="op-danger-button" data-reject="${item.id}"><i class="fa-solid fa-xmark"></i> Rechazar</button>` : ''}</div>
            </article>`).join('');
        list.querySelectorAll('[data-approve]').forEach((button) => button.addEventListener('click', () => openDecision(Number(button.dataset.approve), 'Aprobado')));
        list.querySelectorAll('[data-reject]').forEach((button) => button.addEventListener('click', () => openDecision(Number(button.dataset.reject), 'Rechazado')));
    };

    const load = async (page = 1) => {
        state.page = page;
        list.innerHTML = '<div class="op-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando autorizaciones...</div>';
        try {
            const response = await OP.request('op_c_autorizaciones.php', {
                page,
                size: 15,
                decision: document.getElementById('approval-decision').value,
                search: document.getElementById('approval-search').value.trim(),
            });
            render(response.data.items);
            OP.pagination(pagination, response.data.pagination, load);
            const pending = response.data.items.filter((item) => item.decision === 'Pendiente').length;
            const badge = document.getElementById('nav-approval-count');
            if (badge) { badge.hidden = pending === 0; badge.textContent = pending; }
        } catch (error) {
            list.innerHTML = `<div class="op-empty"><div><i class="fa-solid fa-triangle-exclamation"></i>${OP.escapeHtml(error.message)}</div></div>`;
        }
    };

    try {
        const user = await OP.loadSession();
        if (!user) return;
        if (user.debe_cambiar_password) { await OP.forcePasswordChange(); location.reload(); return; }
        if (!OP.hasAnyRole(user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR'])) { location.href = 'home.php'; return; }
        document.getElementById('approval-refresh').addEventListener('click', () => load(1));
        document.getElementById('approval-decision').addEventListener('change', () => load(1));
        document.getElementById('approval-search').addEventListener('input', () => {
            clearTimeout(state.timer);
            state.timer = setTimeout(() => load(1), 350);
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = document.getElementById('decision-submit');
            const decision = document.getElementById('decision-value').value;
            OP.setMessage(message);
            OP.buttonLoading(button, true, 'Resolviendo...');
            try {
                await OP.request('op_u_autorizacion.php', {
                    solicitud_cambio_id: Number(document.getElementById('decision-change-id').value),
                    decision,
                    comentario: document.getElementById('decision-comment').value.trim(),
                }, { csrf: true });
                dialog.close();
                OP.toast(decision === 'Aprobado' ? 'Cambio autorizado.' : 'Solicitud rechazada.');
                await load(state.page);
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
