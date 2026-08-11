(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const state = {
        categorias: [],
        catalogo: [],
        premios: [],
        anio_actual: new Date().getFullYear(),
        anios_disponibles: [],
        awardYear: new Date().getFullYear(),
        selectedPrizeId: 0,
        ranking: null,
        awardCandidate: null,
    };

    const categoryDialog = document.getElementById('reward-category-dialog');
    const ruleDialog = document.getElementById('reward-rule-dialog');
    const prizeDialog = document.getElementById('reward-prize-dialog');
    const awardDialog = document.getElementById('reward-award-dialog');

    const fmt = new Intl.NumberFormat('es-MX');
    const boolLabel = (active) => `<span class="op-status-badge ${active ? 'activo' : 'inactivo'}">${active ? 'Activo' : 'Inactivo'}</span>`;
    const pointsLabel = (item) => `${item.categoria_tipo === 'RESTA' ? '-' : '+'}${fmt.format(item.puntos)} pts`;
    const fullName = (item) => [item.nombre, item.apellido_paterno, item.apellido_materno].filter(Boolean).join(' ');

    const fillCategorySelect = () => {
        const select = document.getElementById('reward-rule-category');
        select.innerHTML = state.categorias.map((item) => `<option value="${item.id}">${OP.escapeHtml(item.nombre)} · ${item.tipo === 'RESTA' ? 'Resta' : 'Suma'}</option>`).join('');
    };

    const fillAwardYears = () => {
        const select = document.getElementById('reward-award-year');
        if (!select) return;
        const years = Array.from(new Set([state.anio_actual, ...(state.anios_disponibles || [])]))
            .filter((year) => Number(year) > 0)
            .sort((a, b) => Number(b) - Number(a));
        select.innerHTML = years.map((year) => `<option value="${Number(year)}">${Number(year)}</option>`).join('');
        state.awardYear = Number(select.value || state.anio_actual);
    };

    const renderAwardPrizes = () => {
        const container = document.getElementById('reward-award-prize-list');
        if (!container) return;

        if (!state.premios.length) {
            container.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-gift"></i>No hay premios configurados.</div></div>';
            return;
        }

        container.innerHTML = state.premios.map((item) => {
            const selected = Number(item.id) === Number(state.selectedPrizeId);
            return `
                <button class="op-award-prize-card${selected ? ' selected' : ''}${item.activo ? '' : ' inactive'}" type="button" data-rank-prize="${item.id}">
                    <span class="op-award-prize-icon"><i class="fa-solid fa-trophy"></i></span>
                    <span class="op-award-prize-copy">
                        <strong>${OP.escapeHtml(item.nombre)}</strong>
                        <small>${OP.escapeHtml(item.descripcion || 'Sin descripción')}</small>
                    </span>
                    <span class="op-award-prize-points">${fmt.format(item.puntos_requeridos)} pts</span>
                    <span class="op-status-badge ${item.activo ? 'activo' : 'inactivo'}">${item.activo ? 'Activo' : 'Inactivo'}</span>
                </button>`;
        }).join('');

        container.querySelectorAll('[data-rank-prize]').forEach((button) => {
            button.addEventListener('click', () => loadRanking(Number(button.dataset.rankPrize)));
        });
    };

    const renderRanking = () => {
        const shell = document.getElementById('reward-award-ranking-shell');
        const list = document.getElementById('reward-award-ranking-list');
        const summary = document.getElementById('reward-award-ranking-summary');
        const title = document.getElementById('reward-award-ranking-title');
        const copy = document.getElementById('reward-award-ranking-copy');
        const kicker = document.getElementById('reward-award-ranking-kicker');

        if (!state.ranking) {
            shell.hidden = true;
            return;
        }

        const data = state.ranking;
        shell.hidden = false;
        kicker.textContent = `RANKING ${data.anio}`;
        title.textContent = data.premio.nombre;
        copy.textContent = `${fmt.format(data.premio.puntos_requeridos)} puntos requeridos · usuarios activos ordenados de mayor a menor puntaje.`;
        summary.innerHTML = `
            <span><b>${fmt.format(data.resumen.elegibles)}</b><small>Ganadores</small></span>
            <span><b>${fmt.format(data.resumen.pendientes_entrega)}</b><small>Por entregar</small></span>
            <span><b>${fmt.format(data.resumen.otorgados)}</b><small>Otorgados</small></span>`;

        if (!data.usuarios.length) {
            list.innerHTML = '<div class="op-empty"><div>No hay usuarios activos para este año.</div></div>';
            return;
        }

        list.innerHTML = data.usuarios.map((item, index) => {
            const name = fullName(item) || item.username || `Usuario #${item.id}`;
            const roles = Array.isArray(item.roles) && item.roles.length ? item.roles.join(' · ').replaceAll('_', ' ') : 'Sin rol';
            let action = '';
            if (item.otorgado) {
                action = `
                    <div class="op-award-delivered">
                        <span class="op-status-badge activo"><i class="fa-solid fa-check"></i> Otorgado</span>
                        <small>${OP.formatDate(item.otorgado_en)}${item.otorgado_por_nombre ? ` · ${OP.escapeHtml(item.otorgado_por_nombre)}` : ''}</small>
                    </div>`;
            } else if (item.elegible) {
                action = `<button class="op-primary-button op-award-grant-prize" type="button" data-award-user="${item.id}"><i class="fa-solid fa-gift"></i> Otorgar</button>`;
            } else {
                action = `<span class="op-award-pending">Faltan ${fmt.format(item.puntos_faltantes)} pts</span>`;
            }

            return `
                <article class="op-award-ranking-row${item.elegible ? ' eligible' : ''}${item.otorgado ? ' awarded' : ''}">
                    <span class="op-ranking-position">#${index + 1}</span>
                    <div class="op-ranking-person">
                        <strong>${OP.escapeHtml(name)}</strong>
                        <span>${OP.escapeHtml(roles)} · ${OP.escapeHtml(item.email || item.username || '')}</span>
                        ${item.otorgado && item.comentario_otorgamiento ? `<p>${OP.escapeHtml(item.comentario_otorgamiento)}</p>` : ''}
                    </div>
                    <strong class="op-ranking-points">${fmt.format(item.puntos)} <small>pts</small></strong>
                    <div class="op-ranking-action">${action}</div>
                </article>`;
        }).join('');

        list.querySelectorAll('[data-award-user]').forEach((button) => {
            button.addEventListener('click', () => openAwardDialog(Number(button.dataset.awardUser)));
        });
    };

    const loadRanking = async (prizeId = state.selectedPrizeId) => {
        if (!prizeId) return;
        state.selectedPrizeId = Number(prizeId);
        renderAwardPrizes();

        const shell = document.getElementById('reward-award-ranking-shell');
        const list = document.getElementById('reward-award-ranking-list');
        shell.hidden = false;
        list.innerHTML = '<div class="op-loading">Cargando ranking...</div>';

        try {
            const response = await OP.request('op_c_premios_ranking.php', {
                premio_id: state.selectedPrizeId,
                anio: state.awardYear,
            });
            state.ranking = response.data;
            renderRanking();
        } catch (error) {
            state.ranking = null;
            shell.hidden = false;
            list.innerHTML = `<div class="op-empty"><div>${OP.escapeHtml(error.message)}</div></div>`;
        }
    };

    const openAwardDialog = (userId) => {
        if (!state.ranking) return;
        const candidate = state.ranking.usuarios.find((item) => Number(item.id) === Number(userId));
        if (!candidate || !candidate.elegible || candidate.otorgado) return;

        state.awardCandidate = candidate;
        const name = fullName(candidate) || candidate.username || `Usuario #${candidate.id}`;
        document.getElementById('reward-award-prize-id').value = state.ranking.premio.id;
        document.getElementById('reward-award-user-id').value = candidate.id;
        document.getElementById('reward-award-comment').value = '';
        OP.setMessage(document.getElementById('reward-award-message'));
        document.getElementById('reward-award-confirm').innerHTML = `
            <span class="op-prize-award-confirm-icon"><i class="fa-solid fa-trophy"></i></span>
            <div>
                <small>${state.ranking.anio}</small>
                <strong>${OP.escapeHtml(state.ranking.premio.nombre)}</strong>
                <span>${OP.escapeHtml(name)} · ${fmt.format(candidate.puntos)} puntos acumulados</span>
            </div>`;
        awardDialog.showModal();
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
                <strong class="op-points-value positive">${fmt.format(item.puntos_requeridos)} pts</strong>
                ${boolLabel(item.activo)}
                <button class="op-secondary-button" type="button" data-edit-prize="${item.id}"><i class="fa-solid fa-pen"></i> Editar</button>
            </article>`).join('') : '<div class="op-empty"><div><i class="fa-solid fa-gift"></i>No hay premios configurados.</div></div>';

        fillCategorySelect();
        fillAwardYears();
        renderAwardPrizes();
        bindEditButtons();
    };

    const load = async () => {
        const response = await OP.request('op_c_gestion_recompensas.php');
        Object.assign(state, response.data);
        state.awardYear = Number(state.anio_actual || new Date().getFullYear());
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
            if (state.selectedPrizeId) await loadRanking(state.selectedPrizeId);
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

        document.getElementById('reward-award-year').addEventListener('change', async (event) => {
            state.awardYear = Number(event.target.value || state.anio_actual);
            if (state.selectedPrizeId) await loadRanking(state.selectedPrizeId);
        });

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

        document.getElementById('reward-award-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!state.ranking || !state.awardCandidate) return;
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const message = document.getElementById('reward-award-message');
            OP.setMessage(message);
            OP.buttonLoading(button, true, 'Otorgando...');
            try {
                const response = await OP.request('op_i_premio_otorgado.php', {
                    premio_id: Number(document.getElementById('reward-award-prize-id').value),
                    usuario_id: Number(document.getElementById('reward-award-user-id').value),
                    anio: state.awardYear,
                    comentario: document.getElementById('reward-award-comment').value.trim(),
                }, { csrf: true });
                awardDialog.close();
                state.awardCandidate = null;
                OP.toast(response.message || 'Premio otorgado correctamente.');
                await loadRanking(state.selectedPrizeId);
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
