(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const yearSelect = document.getElementById('analytics-year');
    const personSelect = document.getElementById('analytics-person');
    const rankingBody = document.getElementById('analytics-ranking');
    const chart = document.getElementById('analytics-chart');
    let initializedFilters = false;

    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const number = (value) => new Intl.NumberFormat('es-MX').format(Number(value || 0));

    const renderChart = (monthly) => {
        const requests = monthly?.solicitudes || [];
        const reserved = monthly?.apartados || [];
        const sold = monthly?.vendidos || [];
        const max = Math.max(1, ...requests, ...reserved, ...sold);

        chart.innerHTML = months.map((month, index) => {
            const r = Number(requests[index] || 0);
            const a = Number(reserved[index] || 0);
            const v = Number(sold[index] || 0);
            const height = (value) => value > 0 ? Math.max(8, Math.round((value / max) * 132)) : 2;
            return `
                <div class="op-analytics-month">
                    <div class="op-analytics-bars" title="${month}: ${r} solicitudes, ${a} apartados, ${v} vendidos">
                        <span class="requests" style="height:${height(r)}px"><b>${r || ''}</b></span>
                        <span class="reserved" style="height:${height(a)}px"><b>${a || ''}</b></span>
                        <span class="sold" style="height:${height(v)}px"><b>${v || ''}</b></span>
                    </div>
                    <small>${month}</small>
                </div>`;
        }).join('');
    };

    const renderRanking = (items) => {
        document.getElementById('analytics-ranking-count').textContent = `${items.length} persona${items.length === 1 ? '' : 's'}`;
        if (!items.length) {
            rankingBody.innerHTML = '<tr><td colspan="6"><div class="op-empty"><div><i class="fa-solid fa-chart-simple"></i>No hay datos en este alcance.</div></div></td></tr>';
            return;
        }
        rankingBody.innerHTML = items.map((item) => `
            <tr>
                <td><div class="op-person-cell"><span class="op-avatar small">${OP.escapeHtml(OP.initials(item))}</span><div><strong>${OP.escapeHtml(item.nombre_completo || item.username)}</strong><span>${OP.escapeHtml(item.username)} · ${OP.escapeHtml(item.estatus)}</span></div></div></td>
                <td><strong>${number(item.solicitudes)}</strong></td>
                <td><strong>${number(item.apartados)}</strong></td>
                <td><strong>${number(item.vendidos)}</strong></td>
                <td><span class="op-status-badge ${Number(item.conversion) >= 50 ? 'solicitado' : 'pendiente'}">${Number(item.conversion || 0).toFixed(1)}%</span></td>
                <td><strong class="${Number(item.puntos) >= 0 ? 'op-positive-text' : 'op-negative-text'}">${Number(item.puntos) > 0 ? '+' : ''}${number(item.puntos)}</strong></td>
            </tr>`).join('');
    };

    const populateFilters = (data) => {
        const selectedYear = String(data.anio || '');
        yearSelect.innerHTML = (data.anios_disponibles || []).map((year) => `<option value="${year}">${year}</option>`).join('');
        yearSelect.value = selectedYear;

        const selectedPerson = String(data.usuario_seleccionado || 0);
        personSelect.innerHTML = `<option value="0">${data.alcance?.global ? 'Organización completa' : 'Mi grupo completo'}</option>` +
            (data.personas || []).map((person) => `<option value="${person.id}">${OP.escapeHtml(person.nombre_completo)} · ${OP.escapeHtml(person.username)}</option>`).join('');
        personSelect.value = selectedPerson;
    };

    const render = (data) => {
        const summary = data.resumen || {};
        document.getElementById('metric-requests').textContent = number(summary.solicitudes);
        document.getElementById('metric-reserved').textContent = number(summary.apartados);
        document.getElementById('metric-sold').textContent = number(summary.vendidos);
        document.getElementById('metric-rewards').textContent = number(summary.reconocimientos);
        document.getElementById('metric-points').textContent = `${Number(summary.puntos_netos || 0) > 0 ? '+' : ''}${number(summary.puntos_netos)}`;
        document.getElementById('metric-conversion').textContent = `${Number(summary.conversion || 0).toFixed(1)}%`;
        document.getElementById('metric-points-positive').textContent = `+${number(summary.puntos_positivos)}`;
        document.getElementById('metric-points-negative').textContent = `-${number(summary.puntos_negativos)}`;
        document.getElementById('metric-reward-movements').textContent = number(summary.movimientos_recompensa);
        document.getElementById('metric-rejected').textContent = number(summary.rechazados);
        document.getElementById('analytics-scope-label').textContent = data.alcance?.etiqueta || '—';
        renderChart(data.mensual || {});
        renderRanking(data.ranking || []);
    };

    const load = async () => {
        chart.innerHTML = '<div class="op-loading">Cargando indicadores...</div>';
        try {
            const response = await OP.request('op_c_analytics_dashboard.php', {
                anio: Number(yearSelect.value || new Date().getFullYear()),
                usuario_id: Number(personSelect.value || 0),
            });
            if (!initializedFilters) {
                populateFilters(response.data);
                initializedFilters = true;
            } else {
                // Refresca años/personas sin perder selección actual.
                const currentYear = yearSelect.value;
                const currentPerson = personSelect.value;
                populateFilters(response.data);
                if ([...yearSelect.options].some((o) => o.value === currentYear)) yearSelect.value = currentYear;
                if ([...personSelect.options].some((o) => o.value === currentPerson)) personSelect.value = currentPerson;
            }
            render(response.data);
        } catch (error) {
            chart.innerHTML = `<div class="op-empty"><div><i class="fa-solid fa-circle-exclamation"></i>${OP.escapeHtml(error.message)}</div></div>`;
            rankingBody.innerHTML = '<tr><td colspan="6">No fue posible cargar la analítica.</td></tr>';
        }
    };

    const user = await OP.loadSession();
    if (!user) return;
    if (user.debe_cambiar_password) {
        await OP.forcePasswordChange();
        location.reload();
        return;
    }

    yearSelect.value = String(new Date().getFullYear());
    yearSelect.addEventListener('change', load);
    personSelect.addEventListener('change', load);
    document.getElementById('analytics-refresh').addEventListener('click', load);
    await load();
})();
