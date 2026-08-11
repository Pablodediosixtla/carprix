(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const yearSelect = document.getElementById('reward-year');
    const grantButton = document.getElementById('reward-grant-button');
    const grantDialog = document.getElementById('reward-grant-dialog');
    const grantForm = document.getElementById('reward-grant-form');
    const grantMessage = document.getElementById('reward-grant-message');
    const targetSelect = document.getElementById('reward-target');
    const catalogSelect = document.getElementById('reward-catalog');
    const preview = document.getElementById('reward-preview');
    let assignableCatalog = [];

    const formatPoints = (value) => new Intl.NumberFormat('es-MX').format(Number(value || 0));

    const fillYears = (years, currentYear) => {
        const values = Array.isArray(years) && years.length ? years : [currentYear];
        yearSelect.innerHTML = values.map((year) => `<option value="${Number(year)}">${Number(year)}</option>`).join('');
    };

    const renderPrizes = (items, balance) => {
        const container = document.getElementById('reward-prizes');
        if (!items.length) {
            container.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-gift"></i>Aún no hay premios configurados.</div></div>';
            return;
        }
        container.innerHTML = items.map((item) => `
            <article class="op-prize-card ${item.alcanzado ? 'achieved' : ''}">
                <div class="op-prize-card-head">
                    <span class="op-prize-icon"><i class="fa-solid ${item.alcanzado ? 'fa-trophy' : 'fa-gift'}"></i></span>
                    <div><strong>${OP.escapeHtml(item.nombre)}</strong><small>${formatPoints(item.puntos_requeridos)} puntos</small></div>
                    <span class="op-status-badge ${item.alcanzado ? 'aprobado' : 'pendiente'}">${item.alcanzado ? 'Alcanzado' : `${formatPoints(item.faltantes)} faltantes`}</span>
                </div>
                ${item.descripcion ? `<p>${OP.escapeHtml(item.descripcion)}</p>` : ''}
                <div class="op-progress"><span style="width:${Math.max(0, Math.min(100, Number(item.progreso || 0)))}%"></span></div>
                <small class="op-prize-progress-copy">${formatPoints(Math.max(0, balance))} / ${formatPoints(item.puntos_requeridos)} puntos</small>
            </article>`).join('');
    };

    const renderHistory = (items) => {
        const container = document.getElementById('reward-history');
        if (!items.length) {
            container.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-star"></i>No hay movimientos en este año.</div></div>';
            return;
        }
        container.innerHTML = items.map((item) => {
            const positive = Number(item.puntos_aplicados) >= 0;
            return `
                <article class="op-reward-history-item">
                    <span class="op-reward-points ${positive ? 'positive' : 'negative'}">${positive ? '+' : ''}${formatPoints(item.puntos_aplicados)}</span>
                    <div class="op-reward-history-main">
                        <strong>${OP.escapeHtml(item.recompensa_nombre)}</strong>
                        <span>${OP.escapeHtml(item.categoria_nombre)} · ${OP.escapeHtml(item.asignado_por_nombre)}</span>
                        ${item.comentario ? `<p>${OP.escapeHtml(item.comentario)}</p>` : ''}
                    </div>
                    <time>${OP.formatDate(item.creado_en)}</time>
                </article>`;
        }).join('');
    };

    const loadRewards = async (requestedYear = null) => {
        const payload = requestedYear ? { anio: Number(requestedYear) } : {};
        const response = await OP.request('op_c_recompensas.php', payload);
        const data = response.data;
        if (!yearSelect.options.length) fillYears(data.anios_disponibles, Number(data.anio_actual));
        yearSelect.value = String(data.anio);
        document.getElementById('reward-balance').textContent = formatPoints(data.resumen.saldo);
        document.getElementById('reward-earned').textContent = formatPoints(data.resumen.ganados);
        document.getElementById('reward-deducted').textContent = formatPoints(data.resumen.descontados);
        document.getElementById('reward-movements-count').textContent = formatPoints(data.resumen.movimientos);
        renderPrizes(data.premios || [], Number(data.resumen.saldo || 0));
        renderHistory(data.movimientos || []);
        grantButton.hidden = !data.puede_asignar;
    };

    const loadAssignable = async () => {
        const response = await OP.request('op_c_recompensa_asignables.php');
        const data = response.data;
        targetSelect.innerHTML = '<option value="">Selecciona una persona</option>' + (data.personas || []).map((item) => `<option value="${item.id}">${OP.escapeHtml(item.nombre_completo)} · ${OP.escapeHtml(item.username)}</option>`).join('');
        assignableCatalog = data.catalogo || [];
        catalogSelect.innerHTML = '<option value="">Selecciona un concepto</option>' + assignableCatalog.map((item) => {
            const sign = Number(item.puntos_aplicados) >= 0 ? '+' : '';
            return `<option value="${item.id}">${OP.escapeHtml(item.categoria_nombre)} · ${OP.escapeHtml(item.nombre)} (${sign}${formatPoints(item.puntos_aplicados)})</option>`;
        }).join('');
    };

    const updatePreview = () => {
        const selected = assignableCatalog.find((item) => Number(item.id) === Number(catalogSelect.value));
        if (!selected) {
            preview.hidden = true;
            preview.innerHTML = '';
            return;
        }
        const points = Number(selected.puntos_aplicados || 0);
        preview.hidden = false;
        preview.className = `op-reward-preview ${points < 0 ? 'negative' : 'positive'}`;
        preview.innerHTML = `<i class="fa-solid ${points < 0 ? 'fa-circle-minus' : 'fa-circle-plus'}"></i><div><strong>${points > 0 ? '+' : ''}${formatPoints(points)} puntos</strong><span>${OP.escapeHtml(selected.descripcion || selected.nombre)}</span></div>`;
    };

    try {
        const user = await OP.loadSession();
        if (!user) return;
        if (user.debe_cambiar_password) {
            await OP.forcePasswordChange();
            location.reload();
            return;
        }

        await loadRewards();
        yearSelect.addEventListener('change', () => loadRewards(yearSelect.value));
        catalogSelect.addEventListener('change', updatePreview);

        grantButton.addEventListener('click', async () => {
            grantForm.reset();
            OP.setMessage(grantMessage);
            preview.hidden = true;
            await loadAssignable();
            if (!targetSelect.options.length || targetSelect.options.length === 1) {
                OP.toast('No tienes subordinados disponibles para asignar recompensas.', 'error');
                return;
            }
            grantDialog.showModal();
        });

        grantForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!grantForm.reportValidity()) return;
            const submit = document.getElementById('reward-grant-submit');
            OP.setMessage(grantMessage);
            OP.buttonLoading(submit, true, 'Aplicando...');
            try {
                const selected = assignableCatalog.find((item) => Number(item.id) === Number(catalogSelect.value));
                const comment = document.getElementById('reward-comment').value.trim();
                if (selected && Number(selected.puntos_aplicados) < 0 && !comment) {
                    OP.setMessage(grantMessage, 'Debes indicar el motivo cuando se descuentan puntos.');
                    return;
                }
                const response = await OP.request('op_i_recompensa.php', {
                    usuario_id: Number(targetSelect.value),
                    catalogo_id: Number(catalogSelect.value),
                    comentario: comment,
                }, { csrf: true });
                grantDialog.close();
                OP.toast(response.message || 'Movimiento registrado.');
                await loadRewards(yearSelect.value);
            } catch (error) {
                OP.setMessage(grantMessage, error.message);
            } finally {
                OP.buttonLoading(submit, false);
            }
        });
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
