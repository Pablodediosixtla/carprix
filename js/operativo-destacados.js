(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const list = document.getElementById('featured-list');
    const pagination = document.getElementById('featured-pagination');
    const search = document.getElementById('featured-search');
    const refresh = document.getElementById('featured-refresh');
    const message = document.getElementById('featured-message');
    const count = document.getElementById('featured-count');
    if (!OP || !list || !search) return;

    const state = {
        page: 1,
        size: 25,
        canEdit: false,
        selectedCount: 0,
        maxFeatured: 3,
        timer: null,
        requestSeq: 0,
    };

    const statusClass = (status) => String(status || '').toLowerCase().replaceAll(' ', '-');

    const renderLoading = () => {
        list.innerHTML = '<tr><td colspan="8"><div class="op-loading">Cargando vehículos...</div></td></tr>';
    };

    const renderEmpty = () => {
        list.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="op-empty compact">
                        <div><i class="fa-solid fa-car-side"></i><strong>No se encontraron vehículos.</strong></div>
                    </div>
                </td>
            </tr>`;
    };

    const renderRows = (items) => {
        if (!items.length) {
            renderEmpty();
            return;
        }

        const fallback = OP.imageUrl('img/hero-default.jpg');
        list.innerHTML = items.map((auto) => {
            const featured = Boolean(auto.destacado);
            const available = String(auto.estatus) === 'Disponible';
            const disabled = !state.canEdit || (!featured && !available);
            const title = featured
                ? 'Quitar de los autos destacados'
                : (available ? 'Agregar a los autos destacados' : 'Solo los autos disponibles pueden destacarse');
            const vehicleName = `${auto.marca || ''} ${auto.modelo || ''} ${auto.anio || ''}`.trim();

            return `
                <tr data-featured-row="${auto.id}">
                    <td>
                        <img class="op-featured-thumb"
                             src="${OP.escapeHtml(OP.imageUrl(auto.img_principal || 'img/hero-default.jpg'))}"
                             alt="${OP.escapeHtml(vehicleName)}"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='${OP.escapeHtml(fallback)}'">
                    </td>
                    <td><strong>#${auto.id}</strong></td>
                    <td>
                        <strong class="op-featured-vehicle-name">${OP.escapeHtml(vehicleName)}</strong>
                        <small class="op-featured-vehicle-meta">${OP.escapeHtml(auto.ubicacion || '')}</small>
                    </td>
                    <td>${OP.escapeHtml(auto.marca || '—')}</td>
                    <td>${OP.escapeHtml(auto.modelo || '—')}</td>
                    <td><span class="op-status-badge ${statusClass(auto.estatus)}">${OP.escapeHtml(auto.estatus || '—')}</span></td>
                    <td>
                        <span class="op-featured-visits">
                            <i class="fa-regular fa-eye"></i>
                            <strong>${new Intl.NumberFormat('es-MX').format(Number(auto.total_visitas || 0))}</strong>
                        </span>
                    </td>
                    <td>
                        <button type="button"
                                class="op-featured-star ${featured ? 'active' : ''}"
                                data-featured-toggle="${auto.id}"
                                data-featured-value="${featured ? '0' : '1'}"
                                title="${OP.escapeHtml(title)}"
                                aria-label="${OP.escapeHtml(title)}"
                                ${disabled ? 'disabled' : ''}>
                            <i class="fa-${featured ? 'solid' : 'regular'} fa-star"></i>
                        </button>
                    </td>
                </tr>`;
        }).join('');

        list.querySelectorAll('[data-featured-toggle]').forEach((button) => {
            button.addEventListener('click', () => toggleFeatured(button));
        });
    };

    const updateSummary = () => {
        if (count) count.textContent = `${state.selectedCount} / ${state.maxFeatured}`;
    };

    const load = async (page = 1) => {
        state.page = page;
        const seq = ++state.requestSeq;
        renderLoading();
        OP.setMessage(message);

        try {
            const response = await OP.request('op_c_destacados.php', {
                page: state.page,
                size: state.size,
                search: search.value.trim(),
            });
            if (seq !== state.requestSeq) return;

            state.canEdit = Boolean(response.data.permisos?.puede_editar);
            state.selectedCount = Number(response.data.destacados?.seleccionados || 0);
            state.maxFeatured = Number(response.data.destacados?.maximo || 3);
            updateSummary();
            renderRows(response.data.items || []);
            OP.pagination(pagination, response.data.pagination, load);
        } catch (error) {
            if (seq !== state.requestSeq) return;
            list.innerHTML = `<tr><td colspan="8"><div class="op-empty compact"><div><i class="fa-solid fa-triangle-exclamation"></i><strong>${OP.escapeHtml(error.message)}</strong></div></div></td></tr>`;
            pagination.innerHTML = '';
        }
    };

    const toggleFeatured = async (button) => {
        const autoId = Number(button.dataset.featuredToggle || 0);
        const makeFeatured = button.dataset.featuredValue === '1';
        if (!autoId || button.disabled) return;

        if (makeFeatured && state.selectedCount >= state.maxFeatured) {
            OP.toast('Ya hay tres autos destacados. Retira una estrella antes de seleccionar otro.', 'error');
            return;
        }

        OP.buttonLoading(button, true, '');
        try {
            const response = await OP.request('op_u_destacados.php', {
                auto_id: autoId,
                destacado: makeFeatured,
            }, { csrf: true });
            state.selectedCount = Number(response.data.seleccionados ?? state.selectedCount);
            updateSummary();
            OP.toast(makeFeatured ? `Auto #${autoId} agregado a destacados.` : `Auto #${autoId} retirado de destacados.`);
            await load(state.page);
        } catch (error) {
            OP.toast(error.message, 'error');
            OP.setMessage(message, error.message);
        } finally {
            OP.buttonLoading(button, false);
        }
    };

    search.addEventListener('input', () => {
        clearTimeout(state.timer);
        state.timer = setTimeout(() => load(1), 280);
    });

    refresh?.addEventListener('click', () => load(state.page));

    try {
        const user = await OP.loadSession();
        if (!user) return;
        await load(1);
    } catch (error) {
        OP.setMessage(message, error.message);
    }
})();
