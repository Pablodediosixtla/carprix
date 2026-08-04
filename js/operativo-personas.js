(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const state = {
        page: 1,
        timer: null,
        permissions: null,
        supervisors: [],
        currentUser: null,
    };

    const table = document.getElementById('person-table');
    const pagination = document.getElementById('person-pagination');
    const dialog = document.getElementById('person-dialog');
    const form = document.getElementById('person-form');
    const message = document.getElementById('person-message');
    const createdSummary = document.getElementById('person-created-summary');
    const levelSelect = document.getElementById('person-level');
    const supervisorSelect = document.getElementById('person-supervisor');
    const supervisorField = document.getElementById('person-supervisor-field');
    const supervisorHelp = document.getElementById('person-supervisor-help');
    const supervisorSellsField = document.getElementById('supervisor-sells-field');
    const saveButton = document.getElementById('person-save-button');

    const levelLabel = (level) => ({
        VENDEDOR: 'Vendedor',
        SUPERVISOR: 'Supervisor',
        GERENTE_OPERACIONES: 'Gerente de operaciones',
        SUPER_ADMIN: 'Superadministrador',
        OTRO: 'Otro',
    }[level] || level || '—');

    const levelIcon = (level) => ({
        VENDEDOR: 'fa-user-tie',
        SUPERVISOR: 'fa-people-group',
        GERENTE_OPERACIONES: 'fa-user-gear',
        SUPER_ADMIN: 'fa-shield-halved',
    }[level] || 'fa-user');

    const generatePassword = () => {
        const lower = 'abcdefghijkmnopqrstuvwxyz';
        const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const numbers = '23456789';
        const symbols = '!@#$%&*?';
        const all = lower + upper + numbers + symbols;
        const pick = (chars) => chars[crypto.getRandomValues(new Uint32Array(1))[0] % chars.length];
        const chars = [pick(lower), pick(upper), pick(numbers), pick(symbols)];
        while (chars.length < 14) chars.push(pick(all));
        for (let i = chars.length - 1; i > 0; i -= 1) {
            const j = crypto.getRandomValues(new Uint32Array(1))[0] % (i + 1);
            [chars[i], chars[j]] = [chars[j], chars[i]];
        }
        return chars.join('');
    };

    const buildUsername = () => {
        const name = document.getElementById('person-name').value.trim();
        const lastname = document.getElementById('person-lastname').value.trim();
        const target = document.getElementById('person-username');
        if (target.dataset.manual === 'true' || !name || !lastname) return;
        const normalize = (value) => value.normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^A-Za-z0-9]/g, '')
            .toLowerCase();
        target.value = `${normalize(name.split(/\s+/)[0])}.${normalize(lastname.split(/\s+/)[0])}`;
    };

    const configureLevel = () => {
        const level = levelSelect.value;
        const forcedSupervisorId = Number(state.permissions?.supervisor_forzado_id || 0);
        supervisorSellsField.hidden = level !== 'SUPERVISOR';

        if (level === 'GERENTE_OPERACIONES') {
            supervisorField.hidden = false;
            supervisorSelect.required = false;
            supervisorHelp.textContent = 'Puedes dejarlo sin supervisor o asignarlo a un nivel superior.';
            supervisorSelect.value = supervisorSelect.value || '0';
            return;
        }

        supervisorField.hidden = false;
        supervisorSelect.required = level === 'VENDEDOR';

        if (forcedSupervisorId > 0) {
            supervisorSelect.value = String(forcedSupervisorId);
            supervisorSelect.disabled = true;
            supervisorHelp.textContent = 'El vendedor quedará asignado automáticamente a tu línea jerárquica.';
            return;
        }

        supervisorSelect.disabled = false;
        if (level === 'VENDEDOR') {
            supervisorHelp.textContent = 'Selecciona al responsable que autorizará sus cambios de estatus.';
        } else if (level === 'SUPERVISOR') {
            const currentId = Number(state.currentUser?.id || 0);
            if (state.permissions?.es_gerente && currentId > 0) {
                supervisorSelect.value = String(currentId);
                supervisorHelp.textContent = 'El supervisor nuevo quedará dentro de tu línea jerárquica.';
            } else {
                supervisorSelect.required = false;
                supervisorHelp.textContent = 'Puedes asignar un responsable superior o dejarlo sin supervisor.';
            }
        }
    };

    const populateConfiguration = (response) => {
        state.permissions = response.data.permisos;
        state.supervisors = response.data.supervisores || [];

        const levels = state.permissions.niveles_permitidos || [];
        levelSelect.innerHTML = levels.map((item) =>
            `<option value="${OP.escapeHtml(item.codigo)}">${OP.escapeHtml(item.nombre)}</option>`
        ).join('');

        supervisorSelect.innerHTML = '<option value="0">Sin supervisor</option>' + state.supervisors.map((item) =>
            `<option value="${item.id}">${OP.escapeHtml(item.nombre_completo)} · ${OP.escapeHtml(item.username)}</option>`
        ).join('');

        configureLevel();
    };

    const render = (items, total) => {
        document.getElementById('person-count').textContent = `${total} persona${total === 1 ? '' : 's'}`;
        if (!items.length) {
            table.innerHTML = '<tr><td colspan="6"><div class="op-empty"><div><i class="fa-solid fa-users-slash"></i>No se encontraron personas.</div></div></td></tr>';
            return;
        }

        table.innerHTML = items.map((item) => {
            const fullName = [item.nombre, item.apellido_paterno, item.apellido_materno].filter(Boolean).join(' ');
            return `
                <tr>
                    <td>
                        <div class="op-person-cell">
                            <span class="op-avatar small">${OP.escapeHtml(OP.initials(item))}</span>
                            <div>
                                <strong>${OP.escapeHtml(fullName)}</strong>
                                <span>${OP.escapeHtml(item.username)} · ${OP.escapeHtml(item.email)}</span>
                            </div>
                        </div>
                    </td>
                    <td><span class="op-level-badge"><i class="fa-solid ${levelIcon(item.nivel)}"></i>${OP.escapeHtml(levelLabel(item.nivel))}</span></td>
                    <td>${(item.roles || []).map((role) => `<span class="op-role-chip">${OP.escapeHtml(role)}</span>`).join(' ') || '—'}</td>
                    <td>${item.supervisor_nombre
                        ? `<strong>${OP.escapeHtml(item.supervisor_nombre)}</strong><br><span class="op-muted">${OP.escapeHtml(item.supervisor_username || '')}</span>`
                        : '<span class="op-muted">Sin supervisor</span>'}</td>
                    <td><span class="op-status-badge ${OP.statusClass(item.estatus)}">${OP.escapeHtml(item.estatus)}</span></td>
                    <td>${OP.formatDate(item.ultimo_login_at)}</td>
                </tr>`;
        }).join('');
    };

    const load = async (page = 1) => {
        state.page = page;
        table.innerHTML = '<tr><td colspan="6"><i class="fa-solid fa-spinner fa-spin"></i> Cargando personas...</td></tr>';
        try {
            const response = await OP.request('op_c_personas.php', {
                page,
                size: 20,
                search: document.getElementById('person-search').value.trim(),
                estatus: document.getElementById('person-status').value,
            });
            populateConfiguration(response);
            render(response.data.items, response.data.pagination.total);
            OP.pagination(pagination, response.data.pagination, load);
        } catch (error) {
            table.innerHTML = `<tr><td colspan="6">${OP.escapeHtml(error.message)}</td></tr>`;
        }
    };

    const resetForm = () => {
        form.reset();
        OP.setMessage(message);
        createdSummary.hidden = true;
        createdSummary.innerHTML = '';
        document.getElementById('person-username').dataset.manual = 'false';
        document.getElementById('person-password').value = generatePassword();
        if (levelSelect.options.length) levelSelect.selectedIndex = 0;
        configureLevel();
    };

    const openDialog = () => {
        resetForm();
        dialog.showModal();
    };

    try {
        const user = await OP.loadSession();
        if (!user) return;
        state.currentUser = user;
        if (user.debe_cambiar_password) {
            await OP.forcePasswordChange();
            location.reload();
            return;
        }
        if (!OP.hasAnyRole(user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR'])) {
            location.href = 'home.php';
            return;
        }

        document.getElementById('new-person-button').addEventListener('click', openDialog);
        document.getElementById('person-refresh').addEventListener('click', () => load(1));
        document.getElementById('person-status').addEventListener('change', () => load(1));
        document.getElementById('person-search').addEventListener('input', () => {
            clearTimeout(state.timer);
            state.timer = setTimeout(() => load(1), 350);
        });
        document.getElementById('generate-password-button').addEventListener('click', () => {
            document.getElementById('person-password').value = generatePassword();
        });
        document.getElementById('person-name').addEventListener('input', buildUsername);
        document.getElementById('person-lastname').addEventListener('input', buildUsername);
        document.getElementById('person-username').addEventListener('input', (event) => {
            event.currentTarget.dataset.manual = event.currentTarget.value.trim() ? 'true' : 'false';
        });
        levelSelect.addEventListener('change', configureLevel);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            OP.setMessage(message);
            createdSummary.hidden = true;
            OP.buttonLoading(saveButton, true, 'Agregando...');

            const selectedSupervisorId = Number(supervisorSelect.value || 0);
            if (levelSelect.value === 'VENDEDOR' && selectedSupervisorId <= 0) {
                OP.setMessage(message, 'Selecciona al supervisor directo del vendedor.');
                OP.buttonLoading(saveButton, false);
                return;
            }

            const password = document.getElementById('person-password').value;
            const payload = {
                nivel: levelSelect.value,
                supervisor_id: selectedSupervisorId,
                supervisor_tambien_vende: document.getElementById('supervisor-also-sells').checked,
                nombre: document.getElementById('person-name').value.trim(),
                apellido_paterno: document.getElementById('person-lastname').value.trim(),
                apellido_materno: document.getElementById('person-second-lastname').value.trim(),
                username: document.getElementById('person-username').value.trim(),
                email: document.getElementById('person-email').value.trim(),
                telefono: document.getElementById('person-phone').value.trim(),
                password_temporal: password,
            };

            try {
                const response = await OP.request('op_i_persona.php', payload, { csrf: true });
                createdSummary.hidden = false;
                createdSummary.innerHTML = `
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>Persona agregada correctamente</strong>
                        <span>Usuario: <b>${OP.escapeHtml(payload.username)}</b></span>
                        <span>Contraseña temporal: <code>${OP.escapeHtml(password)}</code></span>
                        <small>Comparte la contraseña por un medio seguro. Se solicitará cambiarla al primer acceso.</small>
                    </div>`;
                OP.toast(response.message || 'Persona agregada correctamente.');
                await load(1);
                form.querySelectorAll('input:not([type="checkbox"])').forEach((input) => {
                    if (!['person-password'].includes(input.id)) input.value = '';
                });
                document.getElementById('person-password').value = generatePassword();
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
