(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const state = {
        page: 1,
        requestPage: 1,
        activeTab: 'autos',
        canEdit: false,
        canAuthorizeCatalog: false,
        user: null,
        items: new Map(),
        timer: null,
        requestTimer: null,
        optionsLoaded: false,
        options: {},
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
    const newAutoButton = document.getElementById('new-auto-button');

    const requestList = document.getElementById('catalog-request-list');
    const requestPagination = document.getElementById('catalog-request-pagination');
    const requestBadge = document.getElementById('catalog-request-badge');
    const approvalDialog = document.getElementById('catalog-approval-dialog');
    const approvalForm = document.getElementById('catalog-approval-form');
    const approvalMessage = document.getElementById('catalog-approval-message');
    const approvalSubmit = document.getElementById('catalog-approval-submit');

    const comboConfig = {
        'auto-marca': ['marcas', 'Selecciona una marca'],
        'auto-tipo': ['tipos', 'Sin especificar'],
        'auto-ubicacion': ['ubicaciones', 'Selecciona una ubicación'],
        'auto-transmision': ['transmisiones', 'Selecciona una transmisión'],
        'auto-color': ['colores', 'Sin especificar'],
        'auto-motor': ['motores', 'Sin especificar'],
        'auto-combustible': ['combustibles', 'Sin especificar'],
        'auto-traccion': ['tracciones', 'Sin especificar'],
    };

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
        transmision: document.getElementById('auto-transmision').value.trim(),
        color: document.getElementById('auto-color').value.trim(),
        motor: document.getElementById('auto-motor').value.trim(),
        combustible: document.getElementById('auto-combustible').value.trim(),
        pasajeros: Number(document.getElementById('auto-pasajeros').value || 5),
        traccion: document.getElementById('auto-traccion').value.trim(),
        duenos: Number(document.getElementById('auto-duenos').value || 1),
        img_principal: state.primary?.type === 'existing' ? state.primary.path : '',
        estatus: document.getElementById('auto-estatus').value,
    });

    const setSelectOptions = (select, options, placeholder, currentValue = '') => {
        const normalized = Array.from(new Set((options || [])
            .map((value) => String(value || '').trim())
            .filter(Boolean)));

        if (currentValue && !normalized.some((value) => value === currentValue)) {
            normalized.unshift(currentValue);
        }

        select.innerHTML = `<option value="">${OP.escapeHtml(placeholder)}</option>`
            + normalized.map((value) => `<option value="${OP.escapeHtml(value)}">${OP.escapeHtml(value)}</option>`).join('');
        select.value = currentValue || '';
    };

    const loadOptions = async () => {
        if (state.optionsLoaded) return;
        const response = await OP.request('op_c_catalogo_opciones.php');
        state.options = response.data.opciones || {};

        Object.entries(comboConfig).forEach(([selectId, [key, placeholder]]) => {
            setSelectOptions(document.getElementById(selectId), state.options[key] || [], placeholder);
        });

        setSelectOptions(
            document.getElementById('catalog-type'),
            state.options.tipos || [],
            'Todos los tipos'
        );
        setSelectOptions(
            document.getElementById('catalog-location'),
            state.options.ubicaciones || [],
            'Todas las ubicaciones'
        );

        state.optionsLoaded = true;
    };

    const setComboValue = (id, value) => {
        const select = document.getElementById(id);
        const [key, placeholder] = comboConfig[id];
        setSelectOptions(select, state.options[key] || [], placeholder, String(value || ''));
    };

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
        const fallback = OP.imageUrl('img/hero-default.jpg');
        document.getElementById('existing-image-count').textContent = String(existing.length);
        document.getElementById('new-image-count').textContent = String(state.newImages.length);
        document.getElementById('new-image-section').hidden = state.newImages.length === 0;

        existingGrid.innerHTML = existing.length ? existing.map((image) => {
            const isPrimary = state.primary?.type === 'existing' && state.primary.path === image.path;
            return `
                <article class="op-image-tile ${isPrimary ? 'is-primary' : ''}">
                    <img src="${OP.escapeHtml(OP.imageUrl(image.path))}" alt="Imagen del auto" onerror="this.onerror=null;this.src='${OP.escapeHtml(fallback)}'">
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

    const configureStatusField = (auto = null) => {
        const statusSelect = document.getElementById('auto-estatus');
        const statusHelp = document.getElementById('auto-status-help');
        if (!auto) {
            statusSelect.value = 'Oculto';
            statusSelect.disabled = true;
            statusHelp.textContent = 'Los autos nuevos se guardan como Ocultos y generan automáticamente un requerimiento para pasar a Disponible.';
            return;
        }

        statusSelect.value = auto.estatus || 'Oculto';
        const hiddenAuto = auto.estatus === 'Oculto';
        statusSelect.disabled = hiddenAuto;
        statusHelp.textContent = hiddenAuto
            ? 'La publicación de un auto oculto se gestiona desde la pestaña Requerimientos catálogo.'
            : 'Los cambios operativos del estatus actual se guardan directamente; un auto Oculto no puede publicarse sin autorización.';
    };

    const fillForm = (auto = null) => {
        form.reset();
        OP.setMessage(message);
        document.getElementById('auto-dialog-title').textContent = auto ? `Editar auto #${auto.id}` : 'Agregar auto';
        document.getElementById('auto-id').value = auto?.id || '';

        setComboValue('auto-marca', auto?.marca || '');
        setComboValue('auto-tipo', auto?.tipo || '');
        setComboValue('auto-ubicacion', auto?.ubicacion || '');
        setComboValue('auto-transmision', auto?.transmision || 'Automatico');
        setComboValue('auto-color', auto?.color || '');
        setComboValue('auto-motor', auto?.motor || '');
        setComboValue('auto-combustible', auto?.combustible || 'Gasolina');
        setComboValue('auto-traccion', auto?.traccion || 'Delantera');

        const mapping = {
            'auto-modelo': auto?.modelo || '',
            'auto-anio': auto?.anio || new Date().getFullYear(),
            'auto-precio': auto?.precio || '',
            'auto-mensualidad': auto?.mensualidad || 0,
            'auto-kilometraje': auto?.kilometraje || 0,
            'auto-pasajeros': auto?.pasajeros || 5,
            'auto-duenos': auto?.duenos || 1,
        };
        Object.entries(mapping).forEach(([id, value]) => {
            document.getElementById(id).value = value;
        });

        configureStatusField(auto);
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

    const updateNewAutoButton = () => {
        newAutoButton.hidden = !state.canEdit || state.activeTab !== 'autos';
    };

    const render = (items) => {
        state.items.clear();
        items.forEach((item) => state.items.set(item.id, item));
        if (!items.length) {
            grid.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-car-burst"></i>No se encontraron autos con estos filtros.</div></div>';
            return;
        }

        const fallback = OP.imageUrl('img/hero-default.jpg');
        grid.innerHTML = items.map((auto) => {
            const pending = auto.requerimiento_catalogo_pendiente_id
                ? '<span class="op-publication-note pending"><i class="fa-solid fa-hourglass-half"></i> Publicación pendiente</span>'
                : '';
            const requestButton = state.canEdit && auto.estatus === 'Oculto' && !auto.requerimiento_catalogo_pendiente_id
                ? `<button class="op-secondary-button op-publish-button" data-request-publish="${auto.id}"><i class="fa-solid fa-paper-plane"></i> Solicitar publicación</button>`
                : '';

            return `
                <article class="op-car-card">
                    <div class="op-car-image">
                        <img src="${OP.escapeHtml(OP.imageUrl(auto.img_principal))}" alt="${OP.escapeHtml(`${auto.marca} ${auto.modelo}`)}" loading="lazy" onerror="this.onerror=null;this.src='${OP.escapeHtml(fallback)}'">
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
                        ${pending}
                        <div class="op-car-price">
                            <div><small>Precio</small><strong>${OP.formatCurrency(auto.precio)}</strong></div>
                            ${state.canEdit ? `<div class="op-car-actions"><button class="op-secondary-button" data-edit-auto="${auto.id}" title="Editar"><i class="fa-solid fa-pen"></i></button></div>` : ''}
                        </div>
                        ${requestButton}
                    </div>
                </article>`;
        }).join('');

        grid.querySelectorAll('[data-edit-auto]').forEach((button) => {
            button.addEventListener('click', () => openEdit(Number(button.dataset.editAuto), button));
        });
        grid.querySelectorAll('[data-request-publish]').forEach((button) => {
            button.addEventListener('click', async () => {
                const autoId = Number(button.dataset.requestPublish);
                if (!window.confirm(`¿Enviar el auto #${autoId} a autorización para publicarlo como Disponible?`)) return;
                OP.buttonLoading(button, true, 'Enviando...');
                try {
                    await OP.request('op_i_catalogo_requerimiento.php', {
                        auto_id: autoId,
                        motivo: 'Se solicita publicación del auto en el catálogo.',
                    }, { csrf: true });
                    OP.toast('Requerimiento de publicación generado.');
                    await Promise.all([load(state.page), refreshPendingBadge()]);
                } catch (error) {
                    OP.toast(error.message, 'error');
                } finally {
                    OP.buttonLoading(button, false);
                }
            });
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
            state.canAuthorizeCatalog = Boolean(response.data.permisos.puede_autorizar_catalogo);
            updateNewAutoButton();
            render(response.data.items);
            OP.pagination(pagination, response.data.pagination, load);
        } catch (error) {
            grid.innerHTML = `<div class="op-empty"><div><i class="fa-solid fa-triangle-exclamation"></i>${OP.escapeHtml(error.message)}</div></div>`;
        }
    };

    const compressClientImage = async (file) => {
        if (!file || !file.type.startsWith('image/')) return file;

        let drawable = null;
        let width = 0;
        let height = 0;
        let cleanup = () => {};

        try {
            if ('createImageBitmap' in window) {
                const bitmap = await createImageBitmap(file);
                drawable = bitmap;
                width = bitmap.width;
                height = bitmap.height;
                cleanup = () => bitmap.close?.();
            } else {
                const url = URL.createObjectURL(file);
                const image = await new Promise((resolve, reject) => {
                    const img = new Image();
                    img.onload = () => resolve(img);
                    img.onerror = () => reject(new Error('No fue posible leer la imagen.'));
                    img.src = url;
                });
                drawable = image;
                width = image.naturalWidth || image.width;
                height = image.naturalHeight || image.height;
                cleanup = () => URL.revokeObjectURL(url);
            }

            const maxWidth = 1920;
            const maxHeight = 1440;
            const ratio = Math.min(1, maxWidth / width, maxHeight / height);
            const targetWidth = Math.max(1, Math.round(width * ratio));
            const targetHeight = Math.max(1, Math.round(height * ratio));
            const canvas = document.createElement('canvas');
            canvas.width = targetWidth;
            canvas.height = targetHeight;
            const ctx = canvas.getContext('2d', { alpha: true });
            if (!ctx) return file;
            ctx.drawImage(drawable, 0, 0, targetWidth, targetHeight);

            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', 0.82));
            if (!blob) return file;

            const resized = targetWidth < width || targetHeight < height;
            if (!resized && blob.size >= file.size) return file;

            const baseName = file.name.replace(/\.[^.]+$/, '') || 'imagen';
            return new File([blob], `${baseName}.webp`, {
                type: 'image/webp',
                lastModified: Date.now(),
            });
        } catch (error) {
            console.warn('No fue posible comprimir la imagen en navegador:', error);
            return file;
        } finally {
            cleanup();
        }
    };

    const addFiles = async (fileList) => {
        const accepted = ['image/jpeg', 'image/png', 'image/webp'];
        const maxBytes = 8 * 1024 * 1024;
        const currentTotal = activeExisting().length + state.newImages.length;
        const candidates = Array.from(fileList || []);

        if ((currentTotal + candidates.length) > 12) {
            OP.toast('El auto puede tener máximo 12 imágenes.', 'error');
            return;
        }

        for (const originalFile of candidates) {
            if (!accepted.includes(originalFile.type)) {
                OP.toast(`${originalFile.name}: formato no permitido.`, 'error');
                continue;
            }
            if (originalFile.size <= 0 || originalFile.size > maxBytes) {
                OP.toast(`${originalFile.name}: debe pesar máximo 8 MB.`, 'error');
                continue;
            }

            const file = await compressClientImage(originalFile);
            state.newImages.push({
                key: `${Date.now()}-${crypto.getRandomValues(new Uint32Array(1))[0]}`,
                file,
                originalName: originalFile.name,
                originalSize: originalFile.size,
                url: URL.createObjectURL(file),
            });
        }
        ensurePrimary();
        recomputeImageDirty();
        renderImages();
    };

    const canResolveCatalogRequest = (item) => {
        if (item.decision !== 'Pendiente' || !state.canAuthorizeCatalog || !state.user) return false;
        return Boolean(item.puede_resolver);
    };

    const openApproval = (item, decision) => {
        document.getElementById('catalog-approval-id').value = item.id;
        document.getElementById('catalog-approval-decision-value').value = decision;
        document.getElementById('catalog-approval-comment').value = '';
        document.getElementById('catalog-approval-title').textContent = decision === 'Aprobado'
            ? `Autorizar publicación del auto #${item.auto_id}`
            : `Rechazar publicación del auto #${item.auto_id}`;
        document.getElementById('catalog-approval-description').textContent = decision === 'Aprobado'
            ? 'Al aprobar, el auto cambiará de Oculto a Disponible y será visible para los procesos comerciales.'
            : 'El auto permanecerá Oculto. Para rechazar debes indicar el motivo.';
        approvalSubmit.innerHTML = decision === 'Aprobado'
            ? '<i class="fa-solid fa-check"></i> Aprobar publicación'
            : '<i class="fa-solid fa-xmark"></i> Rechazar publicación';
        approvalSubmit.classList.toggle('danger', decision === 'Rechazado');
        OP.setMessage(approvalMessage);
        approvalDialog.showModal();
    };

    const renderCatalogRequests = (items) => {
        if (!items.length) {
            requestList.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-clipboard-check"></i>No hay requerimientos de catálogo para mostrar.</div></div>';
            return;
        }

        const fallback = OP.imageUrl('img/hero-default.jpg');
        requestList.innerHTML = items.map((item) => {
            const canResolve = canResolveCatalogRequest(item);
            const actions = canResolve ? `
                <button class="op-primary-button" data-catalog-approve="${item.id}"><i class="fa-solid fa-check"></i> Aprobar</button>
                <button class="op-secondary-button" data-catalog-reject="${item.id}"><i class="fa-solid fa-xmark"></i> Rechazar</button>` : '';
            const approver = item.manager_actual_nombre || item.aprobador_nombre || (item.aprobador_id ? `Usuario #${item.aprobador_id}` : 'Sin manager directo');
            return `
                <article class="op-approval-card op-catalog-request-card">
                    <div class="op-catalog-request-auto">
                        <img src="${OP.escapeHtml(OP.imageUrl(item.img_principal))}" alt="Auto #${item.auto_id}" onerror="this.onerror=null;this.src='${OP.escapeHtml(fallback)}'">
                        <div>
                            <span class="op-card-label">REQUERIMIENTO #${item.id} · AUTO #${item.auto_id}</span>
                            <h3>${OP.escapeHtml(item.marca)} ${OP.escapeHtml(item.modelo)} ${item.anio}</h3>
                            <p>${OP.formatCurrency(item.precio)} · Actual: <strong>${OP.escapeHtml(item.auto_estatus)}</strong></p>
                        </div>
                    </div>
                    <div class="op-card-copy">
                        <span class="op-card-label">SOLICITUD</span>
                        <h3>${OP.escapeHtml(item.estatus_origen)} → ${OP.escapeHtml(item.estatus_solicitado)}</h3>
                        <p>${OP.escapeHtml(item.motivo)}</p>
                        <small>Solicitó: ${OP.escapeHtml(item.solicitado_por_nombre)} · ${OP.formatDate(item.fecha_solicitud)}</small>
                    </div>
                    <div class="op-card-copy">
                        <span class="op-card-label">AUTORIZACIÓN</span>
                        <p><span class="op-status-badge ${OP.statusClass(item.decision)}">${OP.escapeHtml(item.decision)}</span></p>
                        <p>Autorizador: ${OP.escapeHtml(approver)}</p>
                        ${item.comentario_decision ? `<small>${OP.escapeHtml(item.comentario_decision)}</small>` : ''}
                    </div>
                    <div class="op-card-actions">${actions}</div>
                </article>`;
        }).join('');

        const byId = new Map(items.map((item) => [Number(item.id), item]));
        requestList.querySelectorAll('[data-catalog-approve]').forEach((button) => {
            button.addEventListener('click', () => openApproval(byId.get(Number(button.dataset.catalogApprove)), 'Aprobado'));
        });
        requestList.querySelectorAll('[data-catalog-reject]').forEach((button) => {
            button.addEventListener('click', () => openApproval(byId.get(Number(button.dataset.catalogReject)), 'Rechazado'));
        });
    };

    const loadCatalogRequests = async (page = 1) => {
        state.requestPage = page;
        requestList.innerHTML = '<div class="op-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando requerimientos de catálogo...</div>';
        try {
            const response = await OP.request('op_c_catalogo_requerimientos.php', {
                page,
                size: 15,
                search: document.getElementById('catalog-request-search').value.trim(),
                decision: document.getElementById('catalog-request-decision').value,
            });
            state.canAuthorizeCatalog = Boolean(response.data.permisos.puede_autorizar);
            renderCatalogRequests(response.data.items);
            OP.pagination(requestPagination, response.data.pagination, loadCatalogRequests);
        } catch (error) {
            requestList.innerHTML = `<div class="op-empty"><div><i class="fa-solid fa-triangle-exclamation"></i>${OP.escapeHtml(error.message)}</div></div>`;
        }
    };

    const refreshPendingBadge = async () => {
        try {
            const response = await OP.request('op_c_catalogo_requerimientos.php', {
                page: 1,
                size: 1,
                decision: 'Pendiente',
            });
            const total = Number(response.data.pagination.total || 0);
            requestBadge.textContent = String(total);
            requestBadge.hidden = total === 0;
        } catch (error) {
            requestBadge.hidden = true;
        }
    };

    const switchTab = async (tabName) => {
        state.activeTab = tabName;
        document.querySelectorAll('[data-catalog-tab]').forEach((button) => {
            button.classList.toggle('active', button.dataset.catalogTab === tabName);
        });
        document.getElementById('catalog-tab-autos').hidden = tabName !== 'autos';
        document.getElementById('catalog-tab-requerimientos').hidden = tabName !== 'requerimientos';
        const featuredPanel = document.getElementById('catalog-tab-destacados');
        if (featuredPanel) featuredPanel.hidden = tabName !== 'destacados';
        updateNewAutoButton();
        if (tabName === 'requerimientos') {
            await loadCatalogRequests(1);
        }
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
        state.user = await OP.loadSession();
        if (!state.user) return;
        if (state.user.debe_cambiar_password) {
            await OP.forcePasswordChange();
            location.reload();
            return;
        }

        await loadOptions();

        newAutoButton.addEventListener('click', () => fillForm());
        document.getElementById('catalog-refresh').addEventListener('click', () => load(1));
        ['catalog-status', 'catalog-type', 'catalog-location'].forEach((id) => {
            document.getElementById(id).addEventListener('change', () => load(1));
        });
        document.getElementById('catalog-search').addEventListener('input', () => {
            clearTimeout(state.timer);
            state.timer = setTimeout(() => load(1), 350);
        });

        document.querySelectorAll('[data-catalog-tab]').forEach((button) => {
            button.addEventListener('click', () => switchTab(button.dataset.catalogTab));
        });
        document.getElementById('catalog-request-refresh').addEventListener('click', () => loadCatalogRequests(1));
        document.getElementById('catalog-request-decision').addEventListener('change', () => loadCatalogRequests(1));
        document.getElementById('catalog-request-search').addEventListener('input', () => {
            clearTimeout(state.requestTimer);
            state.requestTimer = setTimeout(() => loadCatalogRequests(1), 350);
        });

        document.getElementById('add-auto-images-button').addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', async () => {
            const selectedFiles = Array.from(fileInput.files || []);
            fileInput.value = '';
            await addFiles(selectedFiles);
        });
        dialog.addEventListener('close', revokeNewImageUrls);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;

            OP.setMessage(message);
            OP.buttonLoading(saveButton, true, 'Guardando...');
            const payload = values();

            try {
                const isExisting = payload.id > 0;
                const response = await OP.request(
                    isExisting ? 'op_u_auto.php' : 'op_i_auto.php',
                    payload,
                    { csrf: true }
                );
                const autoId = isExisting ? payload.id : Number(response.data.id);

                let uploadResponse = null;
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
                    uploadResponse = await OP.upload('op_upload_auto_images.php', formData);
                }

                dialog.close();
                if (isExisting) {
                    OP.toast('Auto actualizado correctamente.');
                } else {
                    OP.toast('Auto guardado como Oculto y enviado a autorización de catálogo.');
                }

                if (uploadResponse?.data?.compresion?.bytes_originales > 0) {
                    const original = uploadResponse.data.compresion.bytes_originales;
                    const final = uploadResponse.data.compresion.bytes_finales;
                    const percent = original > 0 ? Math.max(0, Math.round((1 - (final / original)) * 100)) : 0;
                    OP.toast(`Imágenes comprimidas correctamente (${percent}% de reducción).`);
                }

                await Promise.all([load(state.page), refreshPendingBadge()]);
            } catch (error) {
                OP.setMessage(message, error.message);
            } finally {
                OP.buttonLoading(saveButton, false);
            }
        });

        approvalForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const decision = document.getElementById('catalog-approval-decision-value').value;
            const comment = document.getElementById('catalog-approval-comment').value.trim();
            if (decision === 'Rechazado' && !comment) {
                OP.setMessage(approvalMessage, 'Debes indicar el motivo del rechazo.');
                return;
            }

            OP.setMessage(approvalMessage);
            OP.buttonLoading(approvalSubmit, true, 'Procesando...');
            try {
                await OP.request('op_u_catalogo_requerimiento.php', {
                    requerimiento_id: Number(document.getElementById('catalog-approval-id').value),
                    decision,
                    comentario: comment,
                }, { csrf: true });
                approvalDialog.close();
                OP.toast(decision === 'Aprobado' ? 'Publicación autorizada.' : 'Publicación rechazada.');
                await Promise.all([
                    loadCatalogRequests(state.requestPage),
                    load(state.page),
                    refreshPendingBadge(),
                ]);
            } catch (error) {
                OP.setMessage(approvalMessage, error.message);
            } finally {
                OP.buttonLoading(approvalSubmit, false);
            }
        });

        await Promise.all([load(), refreshPendingBadge()]);
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
