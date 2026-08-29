(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const yearSelect = document.getElementById('goals-year');
    const monthSelect = document.getElementById('goals-month');
    const teamSelect = document.getElementById('goals-team');
    const body = document.getElementById('goals-body');
    const message = document.getElementById('goals-message');
    const totalPanel = document.getElementById('goals-team-total-panel');
    const saveButton = document.getElementById('goals-save-distribution');
    const months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    let current = null;

    const number = (value) => new Intl.NumberFormat('es-MX').format(Number(value || 0));

    const render = (data) => {
        current = data;
        yearSelect.innerHTML = (data.anios || []).map((year) => `<option value="${year}">${year}</option>`).join('');
        yearSelect.value = String(data.anio);
        monthSelect.innerHTML = months.map((name, index) => `<option value="${index + 1}">${name}</option>`).join('');
        monthSelect.value = String(data.mes);

        const full = Boolean(data.permisos?.cambiar_total);
        if (full) {
            teamSelect.innerHTML = `<option value="0">Organización completa</option>` +
                (data.equipos || []).map((team) => `<option value="${team.id}">${OP.escapeHtml(team.etiqueta)}</option>`).join('');
        } else {
            teamSelect.innerHTML = (data.equipos || []).map((team) => `<option value="${team.id}">${OP.escapeHtml(team.etiqueta)}</option>`).join('');
        }
        teamSelect.value = String(data.equipo_seleccionado || (data.equipos?.[0]?.id ?? 0));

        document.getElementById('goals-total-reserve').textContent = number(data.totales?.reserva);
        document.getElementById('goals-total-sale').textContent = number(data.totales?.venta);
        document.getElementById('goals-people-count').textContent = number((data.personas || []).length);
        document.getElementById('goals-team-label').textContent = data.equipo_etiqueta || '—';
        document.getElementById('goals-prorate-reserve').value = Number(data.totales?.reserva || 0);
        document.getElementById('goals-prorate-sale').value = Number(data.totales?.venta || 0);
        totalPanel.hidden = !full;

        document.getElementById('goals-distribution-note').textContent = full
            ? 'Puedes modificar la distribución o cambiar el total usando el prorrateo automático.'
            : 'Puedes mover la meta entre tus subordinados, conservando exactamente el total que recibió tu equipo.';

        const people = data.personas || [];
        if (!people.length) {
            body.innerHTML = '<tr><td colspan="3"><div class="op-empty"><div><i class="fa-solid fa-bullseye"></i>Este equipo no tiene personas comerciales elegibles. Sus metas permanecen en 0.</div></div></td></tr>';
            saveButton.disabled = true;
            return;
        }
        saveButton.disabled = false;
        body.innerHTML = people.map((person) => `
            <tr data-user-id="${person.id}">
                <td><div class="op-person-cell"><span class="op-avatar small">${OP.escapeHtml(OP.initials(person))}</span><div><strong>${OP.escapeHtml(person.nombre_completo || person.username)}</strong><span>${OP.escapeHtml(person.username)}</span></div></div></td>
                <td><input class="op-goal-input" data-goal="reserve" type="number" min="0" step="1" value="${Number(person.meta_reserva || 0)}" aria-label="Meta de reservas de ${OP.escapeHtml(person.nombre_completo || person.username)}"></td>
                <td><input class="op-goal-input" data-goal="sale" type="number" min="0" step="1" value="${Number(person.meta_venta || 0)}" aria-label="Meta anual de ventas de ${OP.escapeHtml(person.nombre_completo || person.username)}"></td>
            </tr>`).join('');
    };

    const load = async () => {
        OP.setMessage(message);
        body.innerHTML = '<tr><td colspan="3">Cargando metas...</td></tr>';
        try {
            const response = await OP.request('op_c_metas.php', {
                anio: Number(yearSelect.value || new Date().getFullYear()),
                mes: Number(monthSelect.value || (new Date().getMonth() + 1)),
                equipo_id: Number(teamSelect.value || 0),
            });
            render(response.data);
        } catch (error) {
            OP.setMessage(message, error.message);
            body.innerHTML = '<tr><td colspan="3">No fue posible cargar las metas.</td></tr>';
        }
    };

    const prorate = async (type) => {
        if (!current?.permisos?.cambiar_total) return;
        const button = document.getElementById(type === 'RESERVA' ? 'goals-prorate-reserve-button' : 'goals-prorate-sale-button');
        const input = document.getElementById(type === 'RESERVA' ? 'goals-prorate-reserve' : 'goals-prorate-sale');
        OP.setMessage(message);
        OP.buttonLoading(button, true, 'Prorrateando...');
        try {
            await OP.request('op_u_meta_equipo.php', {
                tipo: type,
                anio: Number(yearSelect.value),
                mes: Number(monthSelect.value),
                equipo_id: Number(teamSelect.value || 0),
                meta_total: Math.max(0, Number(input.value || 0)),
            }, { csrf: true });
            OP.toast('Meta prorrateada correctamente.');
            await load();
        } catch (error) {
            OP.setMessage(message, error.message);
        } finally {
            OP.buttonLoading(button, false);
        }
    };

    saveButton.addEventListener('click', async () => {
        const metas = [...body.querySelectorAll('tr[data-user-id]')].map((row) => ({
            usuario_id: Number(row.dataset.userId),
            meta_reserva: Math.max(0, Number(row.querySelector('[data-goal="reserve"]').value || 0)),
            meta_venta: Math.max(0, Number(row.querySelector('[data-goal="sale"]').value || 0)),
        }));
        OP.setMessage(message);
        OP.buttonLoading(saveButton, true, 'Guardando...');
        try {
            await OP.request('op_u_meta_distribucion.php', {
                anio: Number(yearSelect.value),
                mes: Number(monthSelect.value),
                equipo_id: Number(teamSelect.value || 0),
                metas,
            }, { csrf: true });
            OP.toast('Distribución de metas actualizada.');
            await load();
        } catch (error) {
            OP.setMessage(message, error.message);
        } finally {
            OP.buttonLoading(saveButton, false);
        }
    });

    document.getElementById('goals-prorate-reserve-button').addEventListener('click', () => prorate('RESERVA'));
    document.getElementById('goals-prorate-sale-button').addEventListener('click', () => prorate('VENTA'));
    document.getElementById('goals-refresh').addEventListener('click', load);
    yearSelect.addEventListener('change', load);
    monthSelect.addEventListener('change', load);
    teamSelect.addEventListener('change', load);

    const user = await OP.loadSession();
    if (!user) return;
    if (user.debe_cambiar_password) {
        await OP.forcePasswordChange();
        location.reload();
        return;
    }
    monthSelect.value = String(new Date().getMonth() + 1);
    await load();
})();
