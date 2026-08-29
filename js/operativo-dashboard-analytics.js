(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const yearSelect = document.getElementById('analytics-year');
    const teamSelect = document.getElementById('analytics-team');
    const personSelect = document.getElementById('analytics-person');
    const rankingBody = document.getElementById('analytics-ranking');
    const chart = document.getElementById('analytics-chart');
    const detailPanel = document.getElementById('analytics-detail-panel');
    const detailBody = document.getElementById('analytics-detail-body');
    const detailTitle = document.getElementById('analytics-detail-title');
    const detailCount = document.getElementById('analytics-detail-count');
    const monthDetails = document.getElementById('analytics-month-filter');
    const monthSummary = document.getElementById('analytics-month-summary');
    const monthOptions = document.getElementById('analytics-month-options');
    const monthAll = document.getElementById('analytics-month-all');

    const monthsShort = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const monthsLong = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const allMonths = Array.from({ length: 12 }, (_, index) => index + 1);
    let selectedMonths = new Set(allMonths);
    let initializedFilters = false;
    let rankingItems = [];
    let detailData = null;
    const sortState = {
        ranking: { key: 'vendidos', direction: 'desc' },
        detail: { key: 'fecha_evento', direction: 'desc' },
    };

    const number = (value) => new Intl.NumberFormat('es-MX').format(Number(value || 0));
    const selectedMonthValues = () => [...selectedMonths].sort((a, b) => a - b);
    const monthLabel = (values = selectedMonthValues()) => {
        if (values.length === 12) return 'Todos los meses';
        if (values.length === 1) return monthsLong[values[0] - 1];
        if (values.length <= 3) return values.map((month) => monthsShort[month - 1]).join(', ');
        return `${values.length} meses seleccionados`;
    };

    const syncMonthControls = () => {
        const values = selectedMonthValues();
        monthSummary.textContent = monthLabel(values);
        monthAll.checked = values.length === 12;
        monthOptions.querySelectorAll('input[data-month]').forEach((input) => { input.checked = selectedMonths.has(Number(input.dataset.month)); });
    };
    const buildMonthOptions = () => {
        monthOptions.innerHTML = monthsLong.map((name, index) => `<label class="op-multi-select-option"><input type="checkbox" data-month="${index + 1}" checked><span>${name}</span></label>`).join('');
        syncMonthControls();
    };
    const readMonthControls = () => {
        const checked = [...monthOptions.querySelectorAll('input[data-month]:checked')].map((input) => Number(input.dataset.month)).filter((month) => month >= 1 && month <= 12);
        selectedMonths = new Set(checked.length ? checked : allMonths);
        syncMonthControls();
    };
    const selectAllMonths = () => { selectedMonths = new Set(allMonths); syncMonthControls(); };
    const selectSingleMonth = (month) => { selectedMonths = new Set([Number(month)]); syncMonthControls(); };

    const renderChart = (monthly) => {
        const requests = monthly?.solicitudes || [];
        const reserved = monthly?.apartados || [];
        const sold = monthly?.vendidos || [];
        const reserveGoals = monthly?.meta_apartados || [];
        const max = Math.max(1, ...requests, ...reserved, ...sold, ...reserveGoals);
        const filtered = selectedMonths.size < 12;
        const chartHeight = 150;
        const topPad = 8;
        const usableHeight = 132;
        const goalY = (value) => chartHeight - topPad - ((Number(value || 0) / max) * usableHeight);
        const goalPoints = monthsShort.map((_, index) => `${50 + (index * 100)},${goalY(reserveGoals[index]).toFixed(2)}`).join(' ');
        const goalLabels = monthsShort.map((_, index) => {
            const value = Number(reserveGoals[index] || 0);
            const x = 50 + (index * 100);
            const y = Math.max(10, goalY(value) - 7);
            return value > 0
                ? `<text x="${x}" y="${y.toFixed(2)}" text-anchor="middle" class="op-analytics-goal-value">${value}</text>`
                : '';
        }).join('');

        const monthButtons = monthsShort.map((month, index) => {
            const monthNumber = index + 1;
            const r = Number(requests[index] || 0), a = Number(reserved[index] || 0), v = Number(sold[index] || 0);
            const selected = selectedMonths.has(monthNumber);
            const height = (value) => value > 0 ? Math.max(8, Math.round((value / max) * usableHeight)) : 2;
            return `<button class="op-analytics-month${selected ? ' selected' : ''}${filtered && !selected ? ' filtered-out' : ''}" type="button" data-month="${monthNumber}" aria-pressed="${selected ? 'true' : 'false'}" title="Filtrar por ${monthsLong[index]}">
                <div class="op-analytics-bars" title="${month}: ${r} solicitudes, ${a} apartados, ${v} vendidos, meta ${Number(reserveGoals[index] || 0)}">
                    <span class="requests" style="height:${height(r)}px"><b>${r || ''}</b></span><span class="reserved" style="height:${height(a)}px"><b>${a || ''}</b></span><span class="sold" style="height:${height(v)}px"><b>${v || ''}</b></span>
                </div><small>${month}</small></button>`;
        }).join('');

        chart.innerHTML = `<div class="op-analytics-chart-inner">
            <svg class="op-analytics-goal-overlay" viewBox="0 0 1200 ${chartHeight}" preserveAspectRatio="none" aria-hidden="true">
                <polyline points="${goalPoints}" class="op-analytics-goal-line"></polyline>
                ${goalLabels}
            </svg>
            <div class="op-analytics-month-grid">${monthButtons}</div>
        </div>`;
    };

    const sortValue = (item, key) => {
        const value = item?.[key];
        if (key === 'fecha_evento') return Date.parse(String(value || '').replace(' ', 'T')) || 0;
        if (['solicitudes','apartados','vendidos','vendidos_anio','meta_apartados','meta_ventas','cumplimiento_apartados','cumplimiento_ventas','puntos','monto_propuesto'].includes(key)) return Number(value || 0);
        return String(value || '').toLocaleLowerCase('es-MX');
    };
    const sorted = (items, state) => [...items].sort((a, b) => {
        const av = sortValue(a, state.key), bv = sortValue(b, state.key);
        let result = 0;
        if (typeof av === 'number' && typeof bv === 'number') result = av - bv;
        else result = String(av).localeCompare(String(bv), 'es-MX', { numeric: true, sensitivity: 'base' });
        return state.direction === 'asc' ? result : -result;
    });
    const updateSortIndicators = (table) => {
        document.querySelectorAll(`[data-sort-table="${table}"] .op-sort-button`).forEach((button) => {
            const active = button.dataset.sortKey === sortState[table].key;
            const icon = button.querySelector('i');
            button.classList.toggle('active', active);
            if (icon) icon.className = `fa-solid ${active ? (sortState[table].direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'}`;
        });
    };

    const renderRanking = () => {
        const items = sorted(rankingItems, sortState.ranking);
        document.getElementById('analytics-ranking-count').textContent = `${items.length} persona${items.length === 1 ? '' : 's'}`;
        updateSortIndicators('ranking');
        if (!items.length) {
            rankingBody.innerHTML = '<tr><td colspan="10"><div class="op-empty"><div><i class="fa-solid fa-chart-simple"></i>No hay actividad para este alcance. Las personas y equipos siguen disponibles con indicadores en 0.</div></div></td></tr>';
            return;
        }
        rankingBody.innerHTML = items.map((item) => `
            <tr>
                <td><div class="op-person-cell"><span class="op-avatar small">${OP.escapeHtml(OP.initials(item))}</span><div><strong>${OP.escapeHtml(item.nombre_completo || item.username)}</strong><span>${OP.escapeHtml(item.username)} · ${OP.escapeHtml(item.estatus)}</span></div></div></td>
                <td><strong>${number(item.solicitudes)}</strong></td><td><strong>${number(item.apartados)}</strong></td><td><strong>${number(item.meta_apartados)}</strong></td>
                <td><span class="op-status-badge ${Number(item.cumplimiento_apartados) >= 100 ? 'solicitado' : 'pendiente'}">${Number(item.cumplimiento_apartados || 0).toFixed(1)}%</span></td>
                <td><strong>${number(item.vendidos)}</strong></td><td><strong>${number(item.vendidos_anio)}</strong></td><td><strong>${number(item.meta_ventas)}</strong></td>
                <td><span class="op-status-badge ${Number(item.cumplimiento_ventas) >= 100 ? 'solicitado' : 'pendiente'}">${Number(item.cumplimiento_ventas || 0).toFixed(1)}%</span></td>
                <td><strong class="${Number(item.puntos) >= 0 ? 'op-positive-text' : 'op-negative-text'}">${Number(item.puntos) > 0 ? '+' : ''}${number(item.puntos)}</strong></td>
            </tr>`).join('');
    };

    const detailBadgeClass = (event) => event === 'Vendido' ? 'vendido' : (event === 'Apartado' ? 'apartado' : 'solicitado');
    const renderDetail = () => {
        const detail = detailData;
        if (!detail?.activo) { detailPanel.hidden = true; return; }
        detailPanel.hidden = false;
        detailTitle.textContent = `Detalle · ${monthLabel(detail.meses || selectedMonthValues())}`;
        detailCount.textContent = `${number(detail.total)} movimiento${Number(detail.total) === 1 ? '' : 's'}`;
        updateSortIndicators('detail');
        const items = sorted(detail.items || [], sortState.detail);
        if (!items.length) {
            detailBody.innerHTML = '<tr><td colspan="7"><div class="op-empty"><div><i class="fa-solid fa-calendar-xmark"></i>No hay movimientos en los meses seleccionados.</div></div></td></tr>';
            return;
        }
        detailBody.innerHTML = items.map((item) => `<tr>
            <td><span class="op-status-badge ${detailBadgeClass(item.movimiento)}">${OP.escapeHtml(item.movimiento)}</span></td>
            <td><strong>${OP.escapeHtml(OP.formatDate(item.fecha_evento))}</strong></td>
            <td><div class="op-analytics-detail-main"><strong>${OP.escapeHtml(item.folio)}</strong><span>#${Number(item.auto_id)} · ${OP.escapeHtml(item.marca)} ${OP.escapeHtml(item.modelo)} ${Number(item.anio || 0)}</span></div></td>
            <td>${OP.escapeHtml(item.cliente_nombre || '—')}</td><td>${OP.escapeHtml(item.responsable_nombre || item.responsable_username || '—')}</td>
            <td><strong>${OP.escapeHtml(OP.formatCurrency(item.monto_propuesto))}</strong></td><td><span class="op-status-badge ${String(item.estatus || '').toLowerCase()}">${OP.escapeHtml(item.estatus || '—')}</span></td>
        </tr>`).join('');
    };

    const setProgress = (id, value) => { document.getElementById(id).style.width = `${Math.max(0, Math.min(100, Number(value || 0)))}%`; };

    const populateFilters = (data) => {
        const selectedYear = String(data.anio || '');
        yearSelect.innerHTML = (data.anios_disponibles || []).map((year) => `<option value="${year}">${year}</option>`).join(''); yearSelect.value = selectedYear;
        const selectedTeam = String(data.equipo_seleccionado || 0);
        const teamGeneralLabel = data.alcance?.equipo_general || (data.alcance?.global ? 'Organización completa' : 'Mi equipo completo');
        teamSelect.innerHTML = `<option value="0">${OP.escapeHtml(teamGeneralLabel)}</option>` + (data.equipos || []).map((team) => `<option value="${team.id}">${OP.escapeHtml(team.etiqueta)}</option>`).join(''); teamSelect.value = selectedTeam;
        const selectedPerson = String(data.usuario_seleccionado || 0);
        const personGeneralLabel = selectedTeam !== '0' ? 'Todo el equipo' : (data.alcance?.global ? 'Todas las personas' : 'Mi grupo completo');
        personSelect.innerHTML = `<option value="0">${OP.escapeHtml(personGeneralLabel)}</option>` + (data.personas || []).map((person) => `<option value="${person.id}">${OP.escapeHtml(person.nombre_completo)} · ${OP.escapeHtml(person.username)}</option>`).join(''); personSelect.value = selectedPerson;
        if (Array.isArray(data.meses_seleccionados) && data.meses_seleccionados.length) { selectedMonths = new Set(data.meses_seleccionados.map(Number)); syncMonthControls(); }
    };

    const render = (data) => {
        const summary = data.resumen || {};
        document.getElementById('metric-requests').textContent = number(summary.solicitudes);
        document.getElementById('metric-reserved').textContent = number(summary.apartados);
        document.getElementById('metric-reserve-goal').textContent = number(summary.meta_apartados);
        document.getElementById('metric-sold').textContent = number(summary.vendidos);
        document.getElementById('metric-sale-goal').textContent = number(summary.meta_ventas);
        document.getElementById('metric-rewards').textContent = number(summary.reconocimientos);
        document.getElementById('metric-points').textContent = `${Number(summary.puntos_netos || 0) > 0 ? '+' : ''}${number(summary.puntos_netos)}`;
        document.getElementById('metric-conversion').textContent = `${Number(summary.conversion || 0).toFixed(1)}%`;
        document.getElementById('metric-points-positive').textContent = `+${number(summary.puntos_positivos)}`;
        document.getElementById('metric-points-negative').textContent = `-${number(summary.puntos_negativos)}`;
        document.getElementById('metric-reward-movements').textContent = number(summary.movimientos_recompensa);
        document.getElementById('metric-rejected').textContent = number(summary.rechazados);
        document.getElementById('analytics-scope-label').textContent = data.alcance?.etiqueta || '—';

        document.getElementById('goal-reserve-progress-label').textContent = `${Number(summary.cumplimiento_meta_apartados || 0).toFixed(1)}%`;
        document.getElementById('goal-reserve-actual').textContent = number(summary.apartados);
        document.getElementById('goal-reserve-target').textContent = number(summary.meta_apartados);
        setProgress('goal-reserve-progress', summary.cumplimiento_meta_apartados);
        document.getElementById('goal-sale-progress-label').textContent = `${Number(summary.cumplimiento_meta_ventas || 0).toFixed(1)}%`;
        document.getElementById('goal-sale-actual').textContent = number(summary.vendidos_anio);
        document.getElementById('goal-sale-target').textContent = number(summary.meta_ventas);
        setProgress('goal-sale-progress', summary.cumplimiento_meta_ventas);

        renderChart(data.mensual || {});
        detailData = data.detalle || {};
        rankingItems = data.ranking || [];
        renderDetail(); renderRanking();
    };

    const load = async () => {
        chart.innerHTML = '<div class="op-loading">Cargando indicadores...</div>';
        try {
            const response = await OP.request('op_c_analytics_dashboard.php', { anio: Number(yearSelect.value || new Date().getFullYear()), meses: selectedMonthValues(), equipo_id: Number(teamSelect.value || 0), usuario_id: Number(personSelect.value || 0) });
            populateFilters(response.data); initializedFilters = true; render(response.data);
        } catch (error) {
            chart.innerHTML = `<div class="op-empty"><div><i class="fa-solid fa-circle-exclamation"></i>${OP.escapeHtml(error.message)}</div></div>`;
            rankingBody.innerHTML = '<tr><td colspan="10">No fue posible cargar la analítica.</td></tr>'; detailPanel.hidden = true;
        }
    };

    buildMonthOptions();
    monthAll.addEventListener('change', () => monthOptions.querySelectorAll('input[data-month]').forEach((input) => { input.checked = monthAll.checked; }));
    monthOptions.addEventListener('change', () => { const boxes = [...monthOptions.querySelectorAll('input[data-month]')]; monthAll.checked = boxes.every((input) => input.checked); });
    document.getElementById('analytics-month-apply').addEventListener('click', async () => { readMonthControls(); monthDetails.removeAttribute('open'); await load(); });
    chart.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-month]'); if (!button) return;
        const month = Number(button.dataset.month); const remove = selectedMonths.size === 1 && selectedMonths.has(month);
        remove ? selectAllMonths() : selectSingleMonth(month); await load();
        if (!remove && !detailPanel.hidden) detailPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    document.getElementById('analytics-clear-months').addEventListener('click', async () => { selectAllMonths(); await load(); });
    yearSelect.addEventListener('change', load);
    teamSelect.addEventListener('change', async () => { personSelect.value = '0'; await load(); });
    personSelect.addEventListener('change', load);
    document.getElementById('analytics-refresh').addEventListener('click', load);
    document.addEventListener('click', (event) => {
        if (monthDetails.open && !monthDetails.contains(event.target)) monthDetails.removeAttribute('open');
        const sortButton = event.target.closest('.op-sort-button[data-sort-key]');
        if (!sortButton) return;
        const table = sortButton.closest('[data-sort-table]')?.dataset.sortTable;
        if (!table || !sortState[table]) return;
        const key = sortButton.dataset.sortKey;
        if (sortState[table].key === key) sortState[table].direction = sortState[table].direction === 'asc' ? 'desc' : 'asc';
        else { sortState[table].key = key; sortState[table].direction = ['nombre_completo','movimiento','folio','cliente_nombre','responsable_nombre','estatus'].includes(key) ? 'asc' : 'desc'; }
        table === 'ranking' ? renderRanking() : renderDetail();
    });

    const user = await OP.loadSession(); if (!user) return;
    if (user.debe_cambiar_password) { await OP.forcePasswordChange(); location.reload(); return; }
    yearSelect.value = String(new Date().getFullYear());
    if (!initializedFilters) { teamSelect.value = '0'; personSelect.value = '0'; }
    await load();
})();
