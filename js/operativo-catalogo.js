(async () => {
    'use strict';
    const OP = window.CARPRIX_OP;
    const state = { page: 1, canEdit: false, items: new Map(), filtersReady: false, timer: null };
    const grid = document.getElementById('catalog-grid');
    const pagination = document.getElementById('catalog-pagination');
    const dialog = document.getElementById('auto-dialog');
    const form = document.getElementById('auto-form');
    const message = document.getElementById('auto-message');
    const saveButton = document.getElementById('auto-save');

    const values = () => ({
        id: Number(document.getElementById('auto-id').value || 0),
        marca: document.getElementById('auto-marca').value.trim(),
        modelo: document.getElementById('auto-modelo').value.trim(),
        tipo: document.getElementById('auto-tipo').value.trim(),
        anio: Number(document.getElementById('auto-anio').value),
        precio: Number(document.getElementById('auto-precio').value),
        mensualidad: Number(document.getElementById('auto-mensualidad').value || 0),
        ubicacion: document.getElementById('auto-ubicacion').value.trim(),
        kilometraje: Number(document.getElementById('auto-kilometraje').value || 0),
        transmision: document.getElementById('auto-transmision').value,
        color: document.getElementById('auto-color').value.trim(),
        motor: document.getElementById('auto-motor').value.trim(),
        combustible: document.getElementById('auto-combustible').value.trim(),
        pasajeros: Number(document.getElementById('auto-pasajeros').value || 5),
        traccion: document.getElementById('auto-traccion').value.trim(),
        duenos: Number(document.getElementById('auto-duenos').value || 1),
        img_principal: document.getElementById('auto-imagen').value.trim(),
        estatus: document.getElementById('auto-estatus').value,
    });

    const fillForm = (auto = null) => {
        form.reset();
        OP.setMessage(message);
        document.getElementById('auto-dialog-title').textContent = auto ? `Editar auto #${auto.id}` : 'Agregar auto';
        document.getElementById('auto-id').value = auto?.id || '';
        const mapping = {
            'auto-marca': auto?.marca || '', 'auto-modelo': auto?.modelo || '', 'auto-tipo': auto?.tipo || '',
            'auto-anio': auto?.anio || new Date().getFullYear(), 'auto-precio': auto?.precio || '',
            'auto-mensualidad': auto?.mensualidad || 0, 'auto-ubicacion': auto?.ubicacion || '',
            'auto-kilometraje': auto?.kilometraje || 0, 'auto-transmision': auto?.transmision || 'Automatico',
            'auto-color': auto?.color || '', 'auto-motor': auto?.motor || '',
            'auto-combustible': auto?.combustible || 'Gasolina', 'auto-pasajeros': auto?.pasajeros || 5,
            'auto-traccion': auto?.traccion || 'Delantera', 'auto-duenos': auto?.duenos || 1,
            'auto-imagen': auto?.img_principal || '', 'auto-estatus': auto?.estatus || 'Disponible',
        };
        Object.entries(mapping).forEach(([id, value]) => { document.getElementById(id).value = value; });
        dialog.showModal();
    };

    const renderFilters = (filters) => {
        if (state.filtersReady) return;
        const typeSelect = document.getElementById('catalog-type');
        const locationSelect = document.getElementById('catalog-location');
        typeSelect.innerHTML += (filters.tipos || []).map((value) => `<option value="${OP.escapeHtml(value)}">${OP.escapeHtml(value)}</option>`).join('');
        locationSelect.innerHTML += (filters.ubicaciones || []).map((value) => `<option value="${OP.escapeHtml(value)}">${OP.escapeHtml(value)}</option>`).join('');
        state.filtersReady = true;
    };

    const render = (items) => {
        state.items.clear();
        items.forEach((item) => state.items.set(item.id, item));
        if (!items.length) {
            grid.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-car-burst"></i>No se encontraron autos con estos filtros.</div></div>';
            return;
        }
        grid.innerHTML = items.map((auto) => `
            <article class="op-car-card">
                <div class="op-car-image">
                    <img src="${OP.escapeHtml(OP.imageUrl(auto.img_principal))}" alt="${OP.escapeHtml(`${auto.marca} ${auto.modelo}`)}" loading="lazy" onerror="this.src='../img/hero-default.jpg'">
                    <span class="op-status-badge ${OP.statusClass(auto.estatus)}">${OP.escapeHtml(auto.estatus)}</span>
                </div>
                <div class="op-car-card-body">
                    <span class="op-card-label">ID #${auto.id} · ${OP.escapeHtml(auto.tipo || 'Sin tipo')}</span>
                    <h3>${OP.escapeHtml(auto.marca)} ${OP.escapeHtml(auto.modelo)}</h3>
                    <div class="op-car-meta"><span><i class="fa-regular fa-calendar"></i> ${auto.anio}</span><span><i class="fa-solid fa-gauge-high"></i> ${new Intl.NumberFormat('es-MX').format(auto.kilometraje)} km</span><span><i class="fa-solid fa-location-dot"></i> ${OP.escapeHtml(auto.ubicacion)}</span></div>
                    <div class="op-car-price"><div><small>Precio</small><strong>${OP.formatCurrency(auto.precio)}</strong></div>${state.canEdit ? `<div class="op-car-actions"><button class="op-secondary-button" data-edit-auto="${auto.id}" title="Editar"><i class="fa-solid fa-pen"></i></button></div>` : ''}</div>
                </div>
            </article>`).join('');
        grid.querySelectorAll('[data-edit-auto]').forEach((button) => button.addEventListener('click', () => fillForm(state.items.get(Number(button.dataset.editAuto)))));
    };

    const load = async (page = 1) => {
        state.page = page;
        grid.innerHTML = '<div class="op-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando catálogo...</div>';
        try {
            const response = await OP.request('op_c_catalogo.php', {
                page,
                size: 12,
                search: document.getElementById('catalog-search').value.trim(),
                estatus: document.getElementById('catalog-status').value,
                tipo: document.getElementById('catalog-type').value,
                ubicacion: document.getElementById('catalog-location').value,
            });
            state.canEdit = Boolean(response.data.permisos.puede_editar);
            document.getElementById('new-auto-button').hidden = !state.canEdit;
            renderFilters(response.data.filtros);
            render(response.data.items);
            OP.pagination(pagination, response.data.pagination, load);
        } catch (error) {
            grid.innerHTML = `<div class="op-empty"><div><i class="fa-solid fa-triangle-exclamation"></i>${OP.escapeHtml(error.message)}</div></div>`;
        }
    };

    try {
        const user = await OP.loadSession();
        if (!user) return;
        if (user.debe_cambiar_password) { await OP.forcePasswordChange(); location.reload(); return; }
        document.getElementById('new-auto-button').addEventListener('click', () => fillForm());
        document.getElementById('catalog-refresh').addEventListener('click', () => load(1));
        ['catalog-status', 'catalog-type', 'catalog-location'].forEach((id) => document.getElementById(id).addEventListener('change', () => load(1)));
        document.getElementById('catalog-search').addEventListener('input', () => {
            clearTimeout(state.timer);
            state.timer = setTimeout(() => load(1), 350);
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            OP.setMessage(message);
            OP.buttonLoading(saveButton, true, 'Guardando...');
            const payload = values();
            try {
                await OP.request(payload.id ? 'op_u_auto.php' : 'op_i_auto.php', payload, { csrf: true });
                dialog.close();
                OP.toast(payload.id ? 'Auto actualizado.' : 'Auto agregado.');
                await load(state.page);
            } catch (error) {
                OP.setMessage(message, error.message);
            } finally {
                OP.buttonLoading(saveButton, false);
            }
        });
        await load();
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
