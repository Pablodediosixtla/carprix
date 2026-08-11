(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const state = { categorias: [], catalogo: [], premios: [] };

    const categoryDialog = document.getElementById('reward-category-dialog');
    const ruleDialog = document.getElementById('reward-rule-dialog');
    const prizeDialog = document.getElementById('reward-prize-dialog');

    const boolLabel = (active) => `<span class="op-status-badge ${active ? 'activo' : 'inactivo'}">${active ? 'Activo' : 'Inactivo'}</span>`;
    const pointsLabel = (item) => `${item.categoria_tipo === 'RESTA' ? '-' : '+'}${new Intl.NumberFormat('es-MX').format(item.puntos)} pts`;

    const fillCategorySelect = () => {
        const select = document.getElementById('reward-rule-category');
        select.innerHTML = state.categorias.map((item) => `<option value="${item.id}">${OP.escapeHtml(item.nombre)} · ${item.tipo === 'RESTA' ? 'Resta' : 'Suma'}</option>`).join('');
    };

    const render = () => {
        document.getElementById('reward-category-list').innerHTML = state.categorias.length ? state.categorias.map((item) => `
            <article class="op-management-row">
                <div class="op-management-main"><strong>${OP.escapeHtml(item.nombre)}</strong><span>${OP.escapeHtml(item.descripcion || 'Sin descripción')}</span></div>
                <span class="op-reward-type ${item.tipo.toLowerCase()}">${item.tipo === 'RESTA' ? 'Resta puntos' : 'Suma puntos'}</span>
                ${boolLabel(item.activo)}
                <button class="op-secondary-button" type="button" data-edit-category="${item.id}"><i class="fa-solid fa-pen"></i> Editar</button>
            </article>`).join('') : '<div class="op-empty"><div>Sin categorías.</div></div>';

        document.getElementById('reward-rule-list').innerHTML = state.catalogo.length ? state.catalogo.map((item) => `
            <article class="op-management-row reward-rule">
                <div class="op-management-main"><strong>${OP.escapeHtml(item.nombre)} ${item.es_sistema ? '<i class="fa-solid fa-lock op-system-lock" title="Regla del sistema"></i>' : ''}</strong><span>${OP.escapeHtml(item.categoria_nombre)} · ${OP.escapeHtml(item.codigo)} · ${OP.escapeHtml(item.descripcion || 'Sin descripción')}</span></div>
                <strong class="op-points-value ${item.categoria_tipo === 'RESTA' ? 'negative' : 'positive'}">${pointsLabel(item)}</strong>
                ${boolLabel(item.activo)}
                <button class="op-secondary-button" type="button" data-edit-rule="${item.id}"><i class="fa-solid fa-pen"></i> Editar</button>
            </article>`).join('') : '<div class="op-empty"><div>Sin conceptos.</div></div>';

        document.getElementById('reward-prize-list').innerHTML = state.premios.length ? state.premios.map((item) => `
            <article class="op-management-row">
                <div class="op-management-main"><strong>${OP.escapeHtml(item.nombre)}</strong><span>${OP.escapeHtml(item.descripcion || 'Sin descripción')}</span></div>
                <strong class="op-points-value positive">${new Intl.NumberFormat('es-MX').format(item.puntos_requeridos)} pts</strong>
                ${boolLabel(item.activo)}
                <button class="op-secondary-button" type="button" data-edit-prize="${item.id}"><i class="fa-solid fa-pen"></i> Editar</button>
            </article>`).join('') : '<div class="op-empty"><div><i class="fa-solid fa-gift"></i>No hay premios configurados.</div></div>';

        fillCategorySelect();
        bindEditButtons();
    };

    const load = async () => {
        const response = await OP.request('op_c_gestion_recompensas.php');
        Object.assign(state, response.data);
        render();
    };

    const bindEditButtons = () => {
        document.querySelectorAll('[data-edit-category]').forEach((button) => button.addEventListener('click', () => openCategory(Number(button.dataset.editCategory))));
        document.querySelectorAll('[data-edit-rule]').forEach((button) => button.addEventListener('click', () => openRule(Number(button.dataset.editRule))));
        document.querySelectorAll('[data-edit-prize]').forEach((button) => button.addEventListener('click', () => openPrize(Number(button.dataset.editPrize))));
    };

    const openCategory = (id = 0) => {
        const item = state.categorias.find((row) => Number(row.id) === id) || null;
        document.getElementById('reward-category-form').reset();
        document.getElementById('reward-category-id').value = item?.id || '';
        document.getElementById('reward-category-title').textContent = item ? 'Editar categoría' : 'Nueva categoría';
        document.getElementById('reward-category-name').value = item?.nombre || '';
        document.getElementById('reward-category-type').value = item?.tipo || 'SUMA';
        document.getElementById('reward-category-description').value = item?.descripcion || '';
        document.getElementById('reward-category-order').value = item?.orden ?? 0;
        document.getElementById('reward-category-active').checked = item ? Boolean(item.activo) : true;
        OP.setMessage(document.getElementById('reward-category-message'));
        categoryDialog.showModal();
    };

    const openRule = (id = 0) => {
        const item = state.catalogo.find((row) => Number(row.id) === id) || null;
        document.getElementById('reward-rule-form').reset();
        fillCategorySelect();
        document.getElementById('reward-rule-id').value = item?.id || '';
        document.getElementById('reward-rule-title').textContent = item ? 'Editar concepto' : 'Nuevo concepto';
        document.getElementById('reward-rule-category').value = item?.categoria_id || state.categorias[0]?.id || '';
        document.getElementById('reward-rule-code').value = item?.codigo || '';
        document.getElementById('reward-rule-name').value = item?.nombre || '';
        document.getElementById('reward-rule-points').value = item?.puntos || 1;
        document.getElementById('reward-rule-description').value = item?.descripcion || '';
        document.getElementById('reward-rule-active').checked = item ? Boolean(item.activo) : true;
        const codeInput = document.getElementById('reward-rule-code');
        const codeField = document.getElementById('reward-rule-code-field');
        codeInput.disabled = Boolean(item?.es_sistema);
        codeField.querySelector('span').textContent = item?.es_sistema ? 'Código protegido' : 'Código *';
        OP.setMessage(document.getElementById('reward-rule-message'));
        ruleDialog.showModal();
    };

    const openPrize = (id = 0) => {
        const item = state.premios.find((row) => Number(row.id) === id) || null;
        document.getElementById('reward-prize-form').reset();
        document.getElementById('reward-prize-id').value = item?.id || '';
        document.getElementById('reward-prize-title').textContent = item ? 'Editar premio' : 'Nuevo premio';
        document.getElementById('reward-prize-name').value = item?.nombre || '';
        document.getElementById('reward-prize-points').value = item?.puntos_requeridos || 1;
        document.getElementById('reward-prize-description').value = item?.descripcion || '';
        document.getElementById('reward-prize-order').value = item?.orden ?? 0;
        document.getElementById('reward-prize-active').checked = item ? Boolean(item.activo) : true;
        OP.setMessage(document.getElementById('reward-prize-message'));
        prizeDialog.showModal();
    };

    const save = async (payload, messageElement, dialog, button) => {
        OP.setMessage(messageElement);
        OP.buttonLoading(button, true, 'Guardando...');
        try {
            const response = await OP.request('op_u_gestion_recompensas.php', payload, { csrf: true });
            dialog.close();
            OP.toast(response.message || 'Configuración guardada.');
            await load();
        } catch (error) {
            OP.setMessage(messageElement, error.message);
        } finally {
            OP.buttonLoading(button, false);
        }
    };

    try {
        const user = await OP.loadSession();
        if (!user) return;
        if (user.debe_cambiar_password) {
            await OP.forcePasswordChange();
            location.reload();
            return;
        }

        document.querySelectorAll('[data-reward-tab]').forEach((button) => button.addEventListener('click', () => {
            document.querySelectorAll('[data-reward-tab]').forEach((item) => item.classList.toggle('active', item === button));
            document.querySelectorAll('[data-reward-panel]').forEach((panel) => { panel.hidden = panel.dataset.rewardPanel !== button.dataset.rewardTab; });
        }));

        document.getElementById('new-reward-category').addEventListener('click', () => openCategory());
        document.getElementById('new-reward-rule').addEventListener('click', () => openRule());
        document.getElementById('new-reward-prize').addEventListener('click', () => openPrize());

        document.getElementById('reward-category-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            if (!form.reportValidity()) return;
            await save({
                entidad: 'CATEGORIA',
                id: Number(document.getElementById('reward-category-id').value || 0),
                nombre: document.getElementById('reward-category-name').value.trim(),
                tipo: document.getElementById('reward-category-type').value,
                descripcion: document.getElementById('reward-category-description').value.trim(),
                orden: Number(document.getElementById('reward-category-order').value || 0),
                activo: document.getElementById('reward-category-active').checked,
            }, document.getElementById('reward-category-message'), categoryDialog, form.querySelector('button[type="submit"]'));
        });

        document.getElementById('reward-rule-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            if (!form.reportValidity()) return;
            await save({
                entidad: 'CATALOGO',
                id: Number(document.getElementById('reward-rule-id').value || 0),
                categoria_id: Number(document.getElementById('reward-rule-category').value),
                codigo: document.getElementById('reward-rule-code').value.trim(),
                nombre: document.getElementById('reward-rule-name').value.trim(),
                puntos: Number(document.getElementById('reward-rule-points').value),
                descripcion: document.getElementById('reward-rule-description').value.trim(),
                activo: document.getElementById('reward-rule-active').checked,
            }, document.getElementById('reward-rule-message'), ruleDialog, form.querySelector('button[type="submit"]'));
        });

        document.getElementById('reward-prize-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            if (!form.reportValidity()) return;
            await save({
                entidad: 'PREMIO',
                id: Number(document.getElementById('reward-prize-id').value || 0),
                nombre: document.getElementById('reward-prize-name').value.trim(),
                puntos_requeridos: Number(document.getElementById('reward-prize-points').value),
                descripcion: document.getElementById('reward-prize-description').value.trim(),
                orden: Number(document.getElementById('reward-prize-order').value || 0),
                activo: document.getElementById('reward-prize-active').checked,
            }, document.getElementById('reward-prize-message'), prizeDialog, form.querySelector('button[type="submit"]'));
        });

        await load();
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
