(async () => {
    'use strict';
    const OP = window.CARPRIX_OP;
    const state = { page: 1, permissions: {}, timer: null };
    const list = document.getElementById('requirement-list');
    const pagination = document.getElementById('requirement-pagination');
    const requirementDialog = document.getElementById('requirement-dialog');
    const requirementForm = document.getElementById('requirement-form');
    const requirementMessage = document.getElementById('requirement-message');
    const statusDialog = document.getElementById('status-dialog');
    const statusForm = document.getElementById('status-form');
    const statusMessage = document.getElementById('status-message');

    const loadAvailableAutos = async () => {
        const all = [];
        let page = 1;
        let pages = 1;
        do {
            const response = await OP.request('op_c_catalogo.php', { page, size: 100, estatus: 'Disponible' });
            all.push(...response.data.items);
            pages = response.data.pagination.pages;
            page += 1;
        } while (page <= pages && page <= 20);
        const select = document.getElementById('req-auto');
        select.innerHTML = '<option value="">Selecciona un auto</option>' + all.map((auto) => `<option value="${auto.id}">#${auto.id} · ${OP.escapeHtml(auto.marca)} ${OP.escapeHtml(auto.modelo)} ${auto.anio} · ${OP.formatCurrency(auto.precio)}</option>`).join('');
    };

    const nextStatus = (status) => status === 'Solicitado' ? 'Apartado' : (status === 'Apartado' ? 'Vendido' : null);

    const render = (items) => {
        if (!items.length) {
            list.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-file-circle-question"></i>No hay requerimientos para mostrar.</div></div>';
            return;
        }
        list.innerHTML = items.map((item) => {
            const next = nextStatus(item.estatus);
            const pending = item.cambio_pendiente_id ? `<div class="op-pending-change"><i class="fa-solid fa-hourglass-half"></i> Cambio a ${OP.escapeHtml(item.cambio_pendiente_estatus)} pendiente de autorización</div>` : '';
            const changeButton = next && state.permissions.puede_solicitar_cambio && !item.cambio_pendiente_id
                ? `<button class="op-primary-button" data-status-change="${item.id}" data-next-status="${next}"><i class="fa-solid fa-arrow-trend-up"></i> Solicitar ${next}</button>` : '';
            return `
                <article class="op-requirement-card">
                    <div class="op-card-copy"><span class="op-card-label">${OP.escapeHtml(item.folio)}</span><h3>${OP.escapeHtml(item.cliente_nombre)}</h3><p>${OP.escapeHtml(item.cliente_telefono)}${item.cliente_email ? ` · ${OP.escapeHtml(item.cliente_email)}` : ''}</p></div>
                    <div class="op-card-copy"><span class="op-card-label">AUTO #${item.auto_id}</span><h3>${OP.escapeHtml(item.marca)} ${OP.escapeHtml(item.modelo)} ${item.anio}</h3><p>${OP.formatCurrency(item.precio)} · ${OP.escapeHtml(item.forma_pago)} · Responsable: ${OP.escapeHtml(item.asignado_a_nombre)}</p>${pending}</div>
                    <div class="op-card-copy"><span class="op-card-label">ESTATUS</span><p><span class="op-status-badge ${OP.statusClass(item.estatus)}">${OP.escapeHtml(item.estatus)}</span></p><p>Registro: ${OP.formatDate(item.fecha_solicitud)}</p></div>
                    <div class="op-card-actions">${changeButton}</div>
                </article>`;
        }).join('');
        list.querySelectorAll('[data-status-change]').forEach((button) => {
            button.addEventListener('click', () => {
                const requested = button.dataset.nextStatus;
                document.getElementById('status-requirement-id').value = button.dataset.statusChange;
                document.getElementById('status-requested-value').value = requested;
                document.getElementById('status-dialog-title').textContent = `Solicitar estatus ${requested}`;
                document.getElementById('status-description').textContent = `El cambio será enviado al supervisor configurado y no se aplicará hasta ser autorizado.`;
                document.getElementById('status-reason').value = '';
                OP.setMessage(statusMessage);
                statusDialog.showModal();
            });
        });
    };

    const load = async (page = 1) => {
        state.page = page;
        list.innerHTML = '<div class="op-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando requerimientos...</div>';
        try {
            const response = await OP.request('op_c_requerimientos.php', {
                page,
                size: 15,
                search: document.getElementById('requirement-search').value.trim(),
                estatus: document.getElementById('requirement-status').value,
            });
            state.permissions = response.data.permisos;
            document.getElementById('new-requirement-button').hidden = !state.permissions.puede_crear;
            render(response.data.items);
            OP.pagination(pagination, response.data.pagination, load);
        } catch (error) {
            list.innerHTML = `<div class="op-empty"><div><i class="fa-solid fa-triangle-exclamation"></i>${OP.escapeHtml(error.message)}</div></div>`;
        }
    };

    try {
        const user = await OP.loadSession();
        if (!user) return;
        if (user.debe_cambiar_password) { await OP.forcePasswordChange(); location.reload(); return; }
        document.getElementById('new-requirement-button').addEventListener('click', async () => {
            requirementForm.reset();
            OP.setMessage(requirementMessage);
            requirementDialog.showModal();
            if (document.getElementById('req-auto').options.length <= 1) await loadAvailableAutos();
        });
        document.getElementById('requirement-refresh').addEventListener('click', () => load(1));
        document.getElementById('requirement-status').addEventListener('change', () => load(1));
        document.getElementById('requirement-search').addEventListener('input', () => {
            clearTimeout(state.timer);
            state.timer = setTimeout(() => load(1), 350);
        });
        requirementForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = document.getElementById('requirement-save');
            OP.setMessage(requirementMessage);
            OP.buttonLoading(button, true, 'Registrando...');
            try {
                const response = await OP.request('op_i_requerimiento.php', {
                    auto_id: Number(document.getElementById('req-auto').value),
                    cliente_nombre: document.getElementById('req-client-name').value.trim(),
                    cliente_telefono: document.getElementById('req-client-phone').value.trim(),
                    cliente_email: document.getElementById('req-client-email').value.trim(),
                    cliente_identificacion: document.getElementById('req-client-id').value.trim(),
                    monto_propuesto: document.getElementById('req-amount').value,
                    forma_pago: document.getElementById('req-payment').value,
                    comentarios: document.getElementById('req-comments').value.trim(),
                }, { csrf: true });
                requirementDialog.close();
                OP.toast(`Requerimiento ${response.data.folio} creado.`);
                await load(1);
            } catch (error) {
                OP.setMessage(requirementMessage, error.message);
            } finally {
                OP.buttonLoading(button, false);
            }
        });
        statusForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = statusForm.querySelector('button[type="submit"]');
            OP.setMessage(statusMessage);
            OP.buttonLoading(button, true, 'Enviando...');
            try {
                await OP.request('op_i_cambio_estatus.php', {
                    requerimiento_id: Number(document.getElementById('status-requirement-id').value),
                    estatus_solicitado: document.getElementById('status-requested-value').value,
                    motivo: document.getElementById('status-reason').value.trim(),
                }, { csrf: true });
                statusDialog.close();
                OP.toast('Solicitud enviada al autorizador.');
                await load(state.page);
            } catch (error) {
                OP.setMessage(statusMessage, error.message);
            } finally {
                OP.buttonLoading(button, false);
            }
        });
        await load();
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
