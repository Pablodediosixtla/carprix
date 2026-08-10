(async () => {
    'use strict';
    const OP = window.CARPRIX_OP;
    const state = {
        page: 1,
        permissions: {},
        timer: null,
        autoTimer: null,
        autoRequestSeq: 0,
    };
    const list = document.getElementById('requirement-list');
    const pagination = document.getElementById('requirement-pagination');
    const requirementDialog = document.getElementById('requirement-dialog');
    const requirementForm = document.getElementById('requirement-form');
    const requirementMessage = document.getElementById('requirement-message');
    const statusDialog = document.getElementById('status-dialog');
    const statusForm = document.getElementById('status-form');
    const statusMessage = document.getElementById('status-message');
    const autoSearch = document.getElementById('req-auto-search');
    const autoIdInput = document.getElementById('req-auto');
    const autoResults = document.getElementById('req-auto-results');
    const autoSelected = document.getElementById('req-auto-selected');

    const clearAutoSelection = () => {
        autoIdInput.value = '';
        autoSelected.textContent = 'Escribe al menos 2 caracteres. Solo se consultan autos con estatus Disponible.';
    };

    const hideAutoResults = () => {
        autoResults.hidden = true;
        autoResults.innerHTML = '';
    };

    const selectAuto = (auto) => {
        autoIdInput.value = String(auto.id);
        autoSearch.value = `#${auto.id} · ${auto.marca} ${auto.modelo} ${auto.anio}`;
        autoSelected.textContent = `${OP.formatCurrency(auto.precio)} · ${auto.ubicacion} · ${new Intl.NumberFormat('es-MX').format(auto.kilometraje)} km`;
        hideAutoResults();
    };

    const renderAutoResults = (items) => {
        if (!items.length) {
            autoResults.innerHTML = '<div class="op-autocomplete-empty"><i class="fa-solid fa-car-burst"></i> No se encontraron autos disponibles.</div>';
            autoResults.hidden = false;
            return;
        }

        autoResults.innerHTML = items.map((auto) => `
            <button type="button" class="op-autocomplete-option" data-auto-id="${auto.id}" role="option">
                <span><strong>#${auto.id} · ${OP.escapeHtml(auto.marca)} ${OP.escapeHtml(auto.modelo)}</strong><small>${auto.anio} · ${OP.escapeHtml(auto.ubicacion)}</small></span>
                <span><strong>${OP.formatCurrency(auto.precio)}</strong><small>${new Intl.NumberFormat('es-MX').format(auto.kilometraje)} km</small></span>
            </button>`).join('');
        autoResults.hidden = false;

        const byId = new Map(items.map((auto) => [Number(auto.id), auto]));
        autoResults.querySelectorAll('[data-auto-id]').forEach((button) => {
            button.addEventListener('click', () => selectAuto(byId.get(Number(button.dataset.autoId))));
        });
    };

    const searchAvailableAutos = async (query = '') => {
        const seq = ++state.autoRequestSeq;
        autoResults.innerHTML = '<div class="op-autocomplete-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando autos disponibles...</div>';
        autoResults.hidden = false;

        try {
            const response = await OP.request('op_c_catalogo.php', {
                page: 1,
                size: 15,
                estatus: 'Disponible',
                search: query,
            });
            if (seq !== state.autoRequestSeq) return;
            renderAutoResults(response.data.items || []);
        } catch (error) {
            if (seq !== state.autoRequestSeq) return;
            autoResults.innerHTML = `<div class="op-autocomplete-empty error">${OP.escapeHtml(error.message)}</div>`;
            autoResults.hidden = false;
        }
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
                document.getElementById('status-description').textContent = 'El cambio será enviado al supervisor configurado y no se aplicará hasta ser autorizado.';
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

        document.getElementById('new-requirement-button').addEventListener('click', () => {
            requirementForm.reset();
            clearAutoSelection();
            hideAutoResults();
            OP.setMessage(requirementMessage);
            requirementDialog.showModal();
            window.setTimeout(() => autoSearch.focus(), 60);
        });

        autoSearch.addEventListener('input', () => {
            clearAutoSelection();
            clearTimeout(state.autoTimer);
            const query = autoSearch.value.trim();
            if (query.length < 2) {
                hideAutoResults();
                return;
            }
            state.autoTimer = setTimeout(() => searchAvailableAutos(query), 280);
        });
        autoSearch.addEventListener('focus', () => {
            const query = autoSearch.value.trim();
            if (query.length >= 2) searchAvailableAutos(query);
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.op-auto-search-box') && !event.target.closest('#req-auto-results')) {
                hideAutoResults();
            }
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
            if (!Number(autoIdInput.value || 0)) {
                OP.setMessage(requirementMessage, 'Selecciona un auto disponible desde el buscador.');
                autoSearch.focus();
                return;
            }

            OP.buttonLoading(button, true, 'Registrando...');
            try {
                const response = await OP.request('op_i_requerimiento.php', {
                    auto_id: Number(autoIdInput.value),
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
