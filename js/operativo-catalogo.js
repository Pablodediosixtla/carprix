(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const state = {
        page: 1,
        canEdit: false,
        items: new Map(),
        filtersReady: false,
        timer: null,
        gallery: [],
        newImages: [],
        primary: null,
        originalPrimaryPath: '',
        imageDirty: false,
    };

    const grid = document.getElementById('catalog-grid');
    const pagination = document.getElementById('catalog-pagination');
    const dialog = document.getElementById('auto-dialog');
    const form = document.getElementById('auto-form');
    const message = document.getElementById('auto-message');
    const saveButton = document.getElementById('auto-save');
    const existingGrid = document.getElementById('auto-existing-images');
    const newGrid = document.getElementById('auto-new-images');
    const fileInput = document.getElementById('auto-image-files');

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
        img_principal: state.primary?.type === 'existing' ? state.primary.path : '',
        estatus: document.getElementById('auto-estatus').value,
    });

    const revokeNewImageUrls = () => {
        state.newImages.forEach((item) => URL.revokeObjectURL(item.url));
    };

    const activeExisting = () => state.gallery.filter((image) => !image.removed);

    const ensurePrimary = () => {
        if (state.primary?.type === 'existing') {
            const exists = activeExisting().some((image) => image.path === state.primary.path);
            if (exists) return;
        }
        if (state.primary?.type === 'new') {
            const exists = state.newImages.some((image) => image.key === state.primary.key);
            if (exists) return;
        }

        const firstExisting = activeExisting()[0];
        if (firstExisting) {
            state.primary = { type: 'existing', path: firstExisting.path };
            return;
        }
        const firstNew = state.newImages[0];
        state.primary = firstNew ? { type: 'new', key: firstNew.key } : null;
    };

    const recomputeImageDirty = () => {
        const primaryChanged = state.primary?.type === 'new'
            || (state.primary?.type === 'existing' ? state.primary.path : '') !== state.originalPrimaryPath;
        state.imageDirty = state.newImages.length > 0
            || state.gallery.some((image) => image.removed)
            || primaryChanged;
    };

    const renderImages = () => {
        ensurePrimary();
        const existing = activeExisting();
        document.getElementById('existing-image-count').textContent = String(existing.length);
        document.getElementById('new-image-count').textContent = String(state.newImages.length);
        document.getElementById('new-image-section').hidden = state.newImages.length === 0;

        existingGrid.innerHTML = existing.length ? existing.map((image) => {
            const isPrimary = state.primary?.type === 'existing' && state.primary.path === image.path;
            return `
                <article class="op-image-tile ${isPrimary ? 'is-primary' : ''}">
                    <img src="${OP.escapeHtml(OP.imageUrl(image.path))}" alt="Imagen del auto" onerror="this.src='../img/hero-default.jpg'">
                    <div class="op-image-tile-overlay">
                        <button type="button" class="op-image-action ${isPrimary ? 'active' : ''}" data-primary-existing="${OP.escapeHtml(image.path)}" title="Usar como principal">
                            <i class="fa-${isPrimary ? 'solid' : 'regular'} fa-star"></i>
                        </button>
                        <button type="button" class="op-image-action danger" data-remove-existing="${OP.escapeHtml(image.path)}" title="Retirar imagen">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                    ${isPrimary ? '<span class="op-image-primary-label"><i class="fa-solid fa-star"></i> Principal</span>' : ''}
                    <small>${OP.escapeHtml(image.path.split('/').pop() || image.path)}</small>
                </article>`;
        }).join('') : '<div class="op-image-empty"><i class="fa-regular fa-images"></i><span>Este auto todavía no tiene imágenes.</span></div>';

        newGrid.innerHTML = state.newImages.map((image) => {
            const isPrimary = state.primary?.type === 'new' && state.primary.key === image.key;
            return `
                <article class="op-image-tile ${isPrimary ? 'is-primary' : ''}">
                    <img src="${OP.escapeHtml(image.url)}" alt="Nueva imagen del auto">
                    <div class="op-image-tile-overlay">
                        <button type="button" class="op-image-action ${isPrimary ? 'active' : ''}" data-primary-new="${OP.escapeHtml(image.key)}" title="Usar como principal">
                            <i class="fa-${isPrimary ? 'solid' : 'regular'} fa-star"></i>
                        </button>
                        <button type="button" class="op-image-action danger" data-remove-new="${OP.escapeHtml(image.key)}" title="Quitar archivo">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                    ${isPrimary ? '<span class="op-image-primary-label"><i class="fa-solid fa-star"></i> Principal</span>' : '<span class="op-image-new-label">Nueva</span>'}
                    <small>${OP.escapeHtml(image.file.name)}</small>
                </article>`;
        }).join('');

        document.getElementById('auto-imagen').value = state.primary?.type === 'existing' ? state.primary.path : '';
    };

    const resetImages = (auto = null) => {
        revokeNewImageUrls();
        state.newImages = [];
        state.gallery = (auto?.imagenes || []).map((image) => ({
            id: image.id === null ? null : Number(image.id),
            path: String(image.ruta_imagen || ''),
            removed: false,
        })).filter((image) => image.path);
        state.originalPrimaryPath = String(auto?.img_principal || '');
        state.primary = state.originalPrimaryPath
            ? { type: 'existing', path: state.originalPrimaryPath }
            : null;
        state.imageDirty = false;
        fileInput.value = '';
        renderImages();
    };

    const fillForm = (auto = null) => {
        form.reset();
        OP.setMessage(message);
        document.getElementById('auto-dialog-title').textContent = auto ? `Editar auto #${auto.id}` : 'Agregar auto';
        document.getElementById('auto-id').value = auto?.id || '';
        const mapping = {
            'auto-marca': auto?.marca || '',
            'auto-modelo': auto?.modelo || '',
            'auto-tipo': auto?.tipo || '',
            'auto-anio': auto?.anio || new Date().getFullYear(),
            'auto-precio': auto?.precio || '',
            'auto-mensualidad': auto?.mensualidad || 0,
            'auto-ubicacion': auto?.ubicacion || '',
            'auto-kilometraje': auto?.kilometraje || 0,
            'auto-transmision': auto?.transmision || 'Automatico',
            'auto-color': auto?.color || '',
            'auto-motor': auto?.motor || '',
            'auto-combustible': auto?.combustible || 'Gasolina',
            'auto-pasajeros': auto?.pasajeros || 5,
            'auto-traccion': auto?.traccion || 'Delantera',
            'auto-duenos': auto?.duenos || 1,
            'auto-estatus': auto?.estatus || 'Disponible',
        };
        Object.entries(mapping).forEach(([id, value]) => {
            document.getElementById(id).value = value;
        });
        resetImages(auto);
        dialog.showModal();
    };

    const openEdit = async (autoId, button) => {
        OP.buttonLoading(button, true, '');
        try {
            const response = await OP.request('op_c_auto.php', { id: autoId });
            fillForm(response.data.auto);
        } catch (error) {
            OP.toast(error.message, 'error');
        } finally {
            OP.buttonLoading(button, false);
        }
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
                    <div class="op-car-meta">
                        <span><i class="fa-regular fa-calendar"></i> ${auto.anio}</span>
                        <span><i class="fa-solid fa-gauge-high"></i> ${new Intl.NumberFormat('es-MX').format(auto.kilometraje)} km</span>
                        <span><i class="fa-solid fa-location-dot"></i> ${OP.escapeHtml(auto.ubicacion)}</span>
                    </div>
                    <div class="op-car-price">
                        <div><small>Precio</small><strong>${OP.formatCurrency(auto.precio)}</strong></div>
                        ${state.canEdit ? `<div class="op-car-actions"><button class="op-secondary-button" data-edit-auto="${auto.id}" title="Editar"><i class="fa-solid fa-pen"></i></button></div>` : ''}
                    </div>
                </div>
            </article>`).join('');

        grid.querySelectorAll('[data-edit-auto]').forEach((button) => {
            button.addEventListener('click', () => openEdit(Number(button.dataset.editAuto), button));
        });
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

    const addFiles = (fileList) => {
        const accepted = ['image/jpeg', 'image/png', 'image/webp'];
        const maxBytes = 8 * 1024 * 1024;
        const currentTotal = activeExisting().length + state.newImages.length;
        const candidates = Array.from(fileList || []);

        if ((currentTotal + candidates.length) > 12) {
            OP.toast('El auto puede tener máximo 12 imágenes.', 'error');
            return;
        }

        for (const file of candidates) {
            if (!accepted.includes(file.type)) {
                OP.toast(`${file.name}: formato no permitido.`, 'error');
                continue;
            }
            if (file.size <= 0 || file.size > maxBytes) {
                OP.toast(`${file.name}: debe pesar máximo 8 MB.`, 'error');
                continue;
            }
            state.newImages.push({
                key: `${Date.now()}-${crypto.getRandomValues(new Uint32Array(1))[0]}`,
                file,
                url: URL.createObjectURL(file),
            });
        }
        ensurePrimary();
        recomputeImageDirty();
        renderImages();
    };

    existingGrid.addEventListener('click', (event) => {
        const primaryButton = event.target.closest('[data-primary-existing]');
        if (primaryButton) {
            state.primary = { type: 'existing', path: primaryButton.dataset.primaryExisting };
            recomputeImageDirty();
            renderImages();
            return;
        }

        const removeButton = event.target.closest('[data-remove-existing]');
        if (removeButton) {
            const image = state.gallery.find((item) => item.path === removeButton.dataset.removeExisting);
            if (image) {
                image.removed = true;
                ensurePrimary();
                recomputeImageDirty();
                renderImages();
            }
        }
    });

    newGrid.addEventListener('click', (event) => {
        const primaryButton = event.target.closest('[data-primary-new]');
        if (primaryButton) {
            state.primary = { type: 'new', key: primaryButton.dataset.primaryNew };
            recomputeImageDirty();
            renderImages();
            return;
        }

        const removeButton = event.target.closest('[data-remove-new]');
        if (removeButton) {
            const index = state.newImages.findIndex((item) => item.key === removeButton.dataset.removeNew);
            if (index >= 0) {
                URL.revokeObjectURL(state.newImages[index].url);
                state.newImages.splice(index, 1);
                ensurePrimary();
                recomputeImageDirty();
                renderImages();
            }
        }
    });

    try {
        const user = await OP.loadSession();
        if (!user) return;
        if (user.debe_cambiar_password) {
            await OP.forcePasswordChange();
            location.reload();
            return;
        }

        document.getElementById('new-auto-button').addEventListener('click', () => fillForm());
        document.getElementById('catalog-refresh').addEventListener('click', () => load(1));
        ['catalog-status', 'catalog-type', 'catalog-location'].forEach((id) => {
            document.getElementById(id).addEventListener('change', () => load(1));
        });
        document.getElementById('catalog-search').addEventListener('input', () => {
            clearTimeout(state.timer);
            state.timer = setTimeout(() => load(1), 350);
        });
        document.getElementById('add-auto-images-button').addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            addFiles(fileInput.files);
            fileInput.value = '';
        });
        dialog.addEventListener('close', revokeNewImageUrls);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;

            OP.setMessage(message);
            OP.buttonLoading(saveButton, true, 'Guardando...');
            const payload = values();

            try {
                const response = await OP.request(
                    payload.id ? 'op_u_auto.php' : 'op_i_auto.php',
                    payload,
                    { csrf: true }
                );
                const autoId = payload.id || Number(response.data.id);

                if (state.imageDirty || state.newImages.length > 0) {
                    const removed = state.gallery.filter((image) => image.removed);
                    const formData = new FormData();
                    formData.append('auto_id', String(autoId));
                    formData.append('remove_image_ids', JSON.stringify(removed.filter((image) => image.id).map((image) => image.id)));
                    formData.append('remove_image_paths', JSON.stringify(removed.map((image) => image.path)));
                    formData.append('primary_existing_path', state.primary?.type === 'existing' ? state.primary.path : '');
                    formData.append('primary_new_index', state.primary?.type === 'new'
                        ? String(state.newImages.findIndex((image) => image.key === state.primary.key))
                        : '-1');
                    state.newImages.forEach((image) => formData.append('imagenes[]', image.file, image.file.name));
                    await OP.upload('op_upload_auto_images.php', formData);
                }

                dialog.close();
                OP.toast(payload.id ? 'Auto actualizado correctamente.' : 'Auto agregado correctamente.');
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
