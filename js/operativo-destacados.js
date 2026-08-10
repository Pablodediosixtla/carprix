(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const message = document.getElementById('featured-message');
    const saveButton = document.getElementById('featured-save-button');
    if (!OP || !saveButton) return;

    const state = {
        user: null,
        canEdit: false,
        slots: new Map(),
        timers: new Map(),
        requestSeq: new Map(),
    };

    const slotElements = (slot) => ({
        input: document.getElementById(`featured-auto-${slot}`),
        search: document.getElementById(`featured-search-${slot}`),
        results: document.getElementById(`featured-results-${slot}`),
        preview: document.getElementById(`featured-preview-${slot}`),
    });

    const hideResults = (slot) => {
        const { results } = slotElements(slot);
        results.hidden = true;
        results.innerHTML = '';
    };

    const renderPreview = (slot, auto = null) => {
        const { preview, input, search } = slotElements(slot);
        if (!auto?.id) {
            input.value = '';
            preview.innerHTML = '<div class="op-image-empty"><i class="fa-regular fa-star"></i><span>Selecciona un auto disponible.</span></div>';
            return;
        }

        state.slots.set(slot, auto);
        input.value = String(auto.id);
        search.value = `#${auto.id} · ${auto.marca} ${auto.modelo} ${auto.anio}`;
        const fallback = OP.imageUrl('img/hero-default.jpg');
        preview.innerHTML = `
            <img src="${OP.escapeHtml(OP.imageUrl(auto.img_principal))}" alt="${OP.escapeHtml(auto.marca)} ${OP.escapeHtml(auto.modelo)}" onerror="this.onerror=null;this.src='${OP.escapeHtml(fallback)}'">
            <div>
                <span class="op-card-label">AUTO #${auto.id}</span>
                <h4>${OP.escapeHtml(auto.marca)} ${OP.escapeHtml(auto.modelo)} ${auto.anio}</h4>
                <p>${OP.formatCurrency(auto.precio)} · ${OP.escapeHtml(auto.ubicacion || '')}</p>
                <small>${new Intl.NumberFormat('es-MX').format(Number(auto.kilometraje || 0))} km</small>
            </div>`;
    };

    const selectAuto = (slot, auto) => {
        const duplicateSlot = [...state.slots.entries()].find(([otherSlot, selected]) => otherSlot !== slot && Number(selected?.id) === Number(auto.id));
        if (duplicateSlot) {
            OP.toast(`El auto #${auto.id} ya está seleccionado en la posición ${duplicateSlot[0]}.`, 'error');
            return;
        }
        renderPreview(slot, auto);
        hideResults(slot);
        OP.setMessage(message);
    };

    const renderResults = (slot, items) => {
        const { results } = slotElements(slot);
        if (!items.length) {
            results.innerHTML = '<div class="op-autocomplete-empty"><i class="fa-solid fa-car-burst"></i> No se encontraron autos disponibles.</div>';
            results.hidden = false;
            return;
        }

        results.innerHTML = items.map((auto) => `
            <button type="button" class="op-autocomplete-option" data-featured-auto-id="${auto.id}">
                <span><strong>#${auto.id} · ${OP.escapeHtml(auto.marca)} ${OP.escapeHtml(auto.modelo)}</strong><small>${auto.anio} · ${OP.escapeHtml(auto.ubicacion)}</small></span>
                <span><strong>${OP.formatCurrency(auto.precio)}</strong><small>${new Intl.NumberFormat('es-MX').format(Number(auto.kilometraje || 0))} km</small></span>
            </button>`).join('');
        results.hidden = false;

        const byId = new Map(items.map((auto) => [Number(auto.id), auto]));
        results.querySelectorAll('[data-featured-auto-id]').forEach((button) => {
            button.addEventListener('click', () => selectAuto(slot, byId.get(Number(button.dataset.featuredAutoId))));
        });
    };

    const searchAutos = async (slot, query) => {
        const seq = (state.requestSeq.get(slot) || 0) + 1;
        state.requestSeq.set(slot, seq);
        const { results } = slotElements(slot);
        results.innerHTML = '<div class="op-autocomplete-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando autos disponibles...</div>';
        results.hidden = false;

        try {
            const response = await OP.request('op_c_catalogo.php', {
                page: 1,
                size: 15,
                estatus: 'Disponible',
                search: query,
            });
            if (state.requestSeq.get(slot) !== seq) return;
            renderResults(slot, response.data.items || []);
        } catch (error) {
            if (state.requestSeq.get(slot) !== seq) return;
            results.innerHTML = `<div class="op-autocomplete-empty error">${OP.escapeHtml(error.message)}</div>`;
            results.hidden = false;
        }
    };

    const loadFeatured = async () => {
        OP.setMessage(message);
        const response = await OP.request('op_c_destacados.php');
        state.canEdit = Boolean(response.data.permisos?.puede_editar);
        saveButton.hidden = !state.canEdit;

        state.slots.clear();
        (response.data.items || []).forEach((item) => {
            const slot = Number(item.posicion);
            if (item.id) renderPreview(slot, item);
            else renderPreview(slot, null);
        });
    };

    for (let slot = 1; slot <= 3; slot += 1) {
        const { search } = slotElements(slot);
        search.addEventListener('input', () => {
            const existing = state.slots.get(slot);
            if (existing && !search.value.includes(`#${existing.id}`)) {
                state.slots.delete(slot);
                document.getElementById(`featured-auto-${slot}`).value = '';
                renderPreview(slot, null);
                search.focus();
            }
            clearTimeout(state.timers.get(slot));
            const query = search.value.trim();
            if (query.length < 2) {
                hideResults(slot);
                return;
            }
            state.timers.set(slot, setTimeout(() => searchAutos(slot, query), 280));
        });
        search.addEventListener('focus', () => {
            const query = search.value.trim();
            if (query.length >= 2 && !state.slots.get(slot)) searchAutos(slot, query);
        });
    }

    document.addEventListener('click', (event) => {
        for (let slot = 1; slot <= 3; slot += 1) {
            const card = document.querySelector(`[data-featured-slot="${slot}"]`);
            if (card && !card.contains(event.target)) hideResults(slot);
        }
    });

    saveButton.addEventListener('click', async () => {
        const ids = [1, 2, 3].map((slot) => Number(document.getElementById(`featured-auto-${slot}`).value || 0));
        if (ids.some((id) => id <= 0)) {
            OP.setMessage(message, 'Selecciona un auto disponible para cada una de las tres posiciones.');
            return;
        }
        if (new Set(ids).size !== 3) {
            OP.setMessage(message, 'Los tres autos destacados deben ser diferentes.');
            return;
        }

        OP.setMessage(message);
        OP.buttonLoading(saveButton, true, 'Guardando...');
        try {
            await OP.request('op_u_destacados.php', { auto_ids: ids }, { csrf: true });
            OP.toast('Autos destacados actualizados.');
            await loadFeatured();
        } catch (error) {
            OP.setMessage(message, error.message);
        } finally {
            OP.buttonLoading(saveButton, false);
        }
    });

    try {
        state.user = await OP.loadSession();
        if (!state.user) return;
        await loadFeatured();
    } catch (error) {
        OP.setMessage(message, error.message);
    }
})();
