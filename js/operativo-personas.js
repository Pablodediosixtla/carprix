(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const state = {
        page: 1,
        timer: null,
        permissions: null,
        supervisors: [],
        currentUser: null,
        items: [],
    };

    const table = document.getElementById('person-table');
    const pagination = document.getElementById('person-pagination');
    const createDialog = document.getElementById('person-dialog');
    const createForm = document.getElementById('person-form');
    const createMessage = document.getElementById('person-message');
    const createdSummary = document.getElementById('person-created-summary');
    const createLevel = document.getElementById('person-level');
    const createSupervisor = document.getElementById('person-supervisor');
    const createSupervisorHelp = document.getElementById('person-supervisor-help');
    const createSupervisorSellsField = document.getElementById('supervisor-sells-field');
    const createSave = document.getElementById('person-save-button');

    const editDialog = document.getElementById('person-edit-dialog');
    const editForm = document.getElementById('person-edit-form');
    const editMessage = document.getElementById('person-edit-message');
    const editLevel = document.getElementById('edit-person-level');
    const editSupervisor = document.getElementById('edit-person-supervisor');
    const editSupervisorSellsField = document.getElementById('edit-supervisor-sells-field');

    const statusDialog = document.getElementById('person-status-dialog');
    const statusForm = document.getElementById('person-status-form');
    const statusMessage = document.getElementById('person-status-message');

    const passwordDialog = document.getElementById('person-password-dialog');
    const passwordForm = document.getElementById('person-password-form');
    const passwordMessage = document.getElementById('person-password-message');
    const passwordResult = document.getElementById('person-password-result');

    const levelLabel = (level) => ({
        VENDEDOR: 'Vendedor',
        SUPERVISOR: 'Supervisor',
        RESPONSABLE_INVENTARIO: 'Responsable de inventario',
        GERENTE_OPERACIONES: 'Gerente de operaciones',
        SUPER_ADMIN: 'Superadministrador',
        OTRO: 'Otro',
    }[level] || level || '—');

    const levelIcon = (level) => ({
        VENDEDOR: 'fa-user-tie',
        SUPERVISOR: 'fa-people-group',
        RESPONSABLE_INVENTARIO: 'fa-warehouse',
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

    const birthdayText = (value) => {
        if (!value) return '—';
        const date = new Date(`${value}T12:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        return new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short' }).format(date);
    };

    const fullName = (item) => [item.nombre, item.apellido_paterno, item.apellido_materno].filter(Boolean).join(' ');

    const fillLevelOptions = (select, currentLevel = '') => {
        const allowed = [...(state.permissions?.niveles_permitidos || [])];
        if (currentLevel && !allowed.some((item) => item.codigo === currentLevel)) {
            allowed.push({ codigo: currentLevel, nombre: levelLabel(currentLevel) });
        }
        select.innerHTML = allowed.map((item) =>
            `<option value="${OP.escapeHtml(item.codigo)}">${OP.escapeHtml(item.nombre)}</option>`
        ).join('');
        if (currentLevel) select.value = currentLevel;
    };

    const fillSupervisors = (select, selectedId = 0, excludeId = 0) => {
        select.innerHTML = '<option value="0">Sin supervisor</option>' + state.supervisors
            .filter((item) => Number(item.id) !== Number(excludeId))
            .map((item) => `<option value="${item.id}">${OP.escapeHtml(item.nombre_completo)} · ${OP.escapeHtml(item.username)}</option>`)
            .join('');
        select.value = String(selectedId || 0);
    };

    const configureCreateLevel = () => {
        const level = createLevel.value;
        createSupervisorSellsField.hidden = level !== 'SUPERVISOR';
        createSupervisor.required = ['VENDEDOR', 'RESPONSABLE_INVENTARIO'].includes(level);
        if (level === 'SUPERVISOR') {
            createSupervisorHelp.textContent = 'Puedes asignarlo a un gerente o nivel superior.';
        } else if (level === 'GERENTE_OPERACIONES') {
            createSupervisorHelp.textContent = 'El supervisor directo es opcional para un gerente de operaciones.';
        } else {
            createSupervisorHelp.textContent = 'Selecciona a la persona que autorizará sus solicitudes cuando aplique.';
        }
    };

    const configureEditLevel = () => {
        editSupervisorSellsField.hidden = editLevel.value !== 'SUPERVISOR';
        editSupervisor.required = ['VENDEDOR', 'RESPONSABLE_INVENTARIO'].includes(editLevel.value);
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

    const populateConfiguration = (response) => {
        state.permissions = response.data.permisos || {};
        state.supervisors = response.data.supervisores || [];
        const newButton = document.getElementById('new-person-button');
        newButton.hidden = !state.permissions.puede_crear;
        fillLevelOptions(createLevel);
        fillSupervisors(createSupervisor);
        configureCreateLevel();
    };

    const render = (items, total) => {
        state.items = items;
        document.getElementById('person-count').textContent = `${total} persona${total === 1 ? '' : 's'}`;
        if (!items.length) {
            table.innerHTML = '<tr><td colspan="8"><div class="op-empty"><div><i class="fa-solid fa-users-slash"></i>No se encontraron personas.</div></div></td></tr>';
            return;
        }

        table.innerHTML = items.map((item) => {
            const actions = [];
            if (item.puede_editar_completo) {
                actions.push(`<button class="op-icon-action" data-edit-person="${item.id}" title="Editar usuario"><i class="fa-solid fa-pen"></i></button>`);
            }
            if (item.puede_gestionar_estatus) {
                actions.push(`<button class="op-icon-action" data-status-person="${item.id}" title="Cambiar estatus"><i class="fa-solid fa-user-lock"></i></button>`);
            }
            if (item.puede_reset_password) {
                actions.push(`<button class="op-icon-action" data-password-person="${item.id}" title="Restablecer contraseña"><i class="fa-solid fa-key"></i></button>`);
            }

            return `
                <tr>
                    <td><div class="op-person-cell"><span class="op-avatar small">${OP.escapeHtml(OP.initials(item))}</span><div><strong>${OP.escapeHtml(fullName(item))}</strong><span>${OP.escapeHtml(item.username)} · ${OP.escapeHtml(item.email)}</span></div></div></td>
                    <td><span class="op-level-badge"><i class="fa-solid ${levelIcon(item.nivel)}"></i>${OP.escapeHtml(levelLabel(item.nivel))}</span></td>
                    <td>${(item.roles || []).map((role) => `<span class="op-role-chip">${OP.escapeHtml(role)}</span>`).join(' ') || '—'}</td>
                    <td>${item.supervisor_nombre ? `<strong>${OP.escapeHtml(item.supervisor_nombre)}</strong><br><span class="op-muted">${OP.escapeHtml(item.supervisor_username || '')}</span>` : '<span class="op-muted">Sin supervisor</span>'}</td>
                    <td><span class="op-status-badge ${OP.statusClass(item.estatus)}">${OP.escapeHtml(item.estatus)}</span></td>
                    <td>${OP.escapeHtml(birthdayText(item.fecha_nacimiento))}</td>
                    <td>${OP.formatDate(item.ultimo_login_at)}</td>
                    <td><div class="op-row-actions">${actions.join('') || '<span class="op-muted">Consulta</span>'}</div></td>
                </tr>`;
        }).join('');

        table.querySelectorAll('[data-edit-person]').forEach((button) => button.addEventListener('click', () => openEdit(Number(button.dataset.editPerson))));
        table.querySelectorAll('[data-status-person]').forEach((button) => button.addEventListener('click', () => openStatus(Number(button.dataset.statusPerson))));
        table.querySelectorAll('[data-password-person]').forEach((button) => button.addEventListener('click', () => openPassword(Number(button.dataset.passwordPerson))));
    };

    const load = async (page = 1) => {
        state.page = page;
        table.innerHTML = '<tr><td colspan="8"><i class="fa-solid fa-spinner fa-spin"></i> Cargando personas...</td></tr>';
        try {
            const response = await OP.request('op_c_personas.php', {
                page,
                size: 20,
                search: document.getElementById('person-search').value.trim(),
                estatus: document.getElementById('person-status').value,
            });
            populateConfiguration(response);
            render(response.data.items || [], response.data.pagination.total || 0);
            OP.pagination(pagination, response.data.pagination, load);
        } catch (error) {
            table.innerHTML = `<tr><td colspan="8">${OP.escapeHtml(error.message)}</td></tr>`;
        }
    };

    const resetCreateForm = () => {
        createForm.reset();
        OP.setMessage(createMessage);
        createdSummary.hidden = true;
        createdSummary.innerHTML = '';
        document.getElementById('person-username').dataset.manual = 'false';
        document.getElementById('person-password').value = generatePassword();
        fillLevelOptions(createLevel);
        fillSupervisors(createSupervisor);
        configureCreateLevel();
    };

    const openEdit = (id) => {
        const item = state.items.find((row) => Number(row.id) === id);
        if (!item || !item.puede_editar_completo) return;
        OP.setMessage(editMessage);
        document.getElementById('edit-person-id').value = item.id;
        fillLevelOptions(editLevel, item.nivel);
        fillSupervisors(editSupervisor, item.supervisor_id || 0, item.id);
        document.getElementById('edit-person-name').value = item.nombre || '';
        document.getElementById('edit-person-lastname').value = item.apellido_paterno || '';
        document.getElementById('edit-person-second-lastname').value = item.apellido_materno || '';
        document.getElementById('edit-person-username').value = item.username || '';
        document.getElementById('edit-person-email').value = item.email || '';
        document.getElementById('edit-person-phone').value = item.telefono || '';
        document.getElementById('edit-person-birthdate').value = item.fecha_nacimiento || '';
        document.getElementById('edit-person-status').value = item.estatus || 'Activo';
        document.getElementById('edit-supervisor-also-sells').checked = (item.roles || []).includes('VENTAS') && (item.roles || []).includes('AUTORIZADOR');
        configureEditLevel();
        editDialog.showModal();
    };

    const openStatus = (id) => {
        const item = state.items.find((row) => Number(row.id) === id);
        if (!item || !item.puede_gestionar_estatus) return;
        OP.setMessage(statusMessage);
        document.getElementById('status-person-id').value = item.id;
        document.getElementById('status-person-name').textContent = fullName(item);
        document.getElementById('status-person-value').value = item.estatus;
        statusDialog.showModal();
    };

    const openPassword = (id) => {
        const item = state.items.find((row) => Number(row.id) === id);
        if (!item || !item.puede_reset_password) return;
        OP.setMessage(passwordMessage);
        passwordResult.hidden = true;
        passwordResult.innerHTML = '';
        document.getElementById('password-person-id').value = item.id;
        document.getElementById('password-person-name').textContent = fullName(item);
        document.getElementById('password-person-value').value = generatePassword();
        passwordDialog.showModal();
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

        document.getElementById('new-person-button').addEventListener('click', () => {
            if (!state.permissions?.puede_crear) return;
            resetCreateForm();
            createDialog.showModal();
        });
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
        createLevel.addEventListener('change', configureCreateLevel);
        editLevel.addEventListener('change', configureEditLevel);

        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            OP.setMessage(createMessage);
            createdSummary.hidden = true;
            OP.buttonLoading(createSave, true, 'Agregando...');
            const password = document.getElementById('person-password').value;
            try {
                const response = await OP.request('op_i_persona.php', {
                    nivel: createLevel.value,
                    supervisor_id: Number(createSupervisor.value || 0),
                    supervisor_tambien_vende: document.getElementById('supervisor-also-sells').checked,
                    nombre: document.getElementById('person-name').value.trim(),
                    apellido_paterno: document.getElementById('person-lastname').value.trim(),
                    apellido_materno: document.getElementById('person-second-lastname').value.trim(),
                    username: document.getElementById('person-username').value.trim(),
                    email: document.getElementById('person-email').value.trim(),
                    telefono: document.getElementById('person-phone').value.trim(),
                    fecha_nacimiento: document.getElementById('person-birthdate').value,
                    password_temporal: password,
                }, { csrf: true });
                createdSummary.hidden = false;
                createdSummary.innerHTML = `<i class="fa-solid fa-circle-check"></i><div><strong>Persona agregada correctamente</strong><span>Usuario: <b>${OP.escapeHtml(document.getElementById('person-username').value)}</b></span><span>Contraseña temporal: <code>${OP.escapeHtml(password)}</code></span><small>Se solicitará cambiarla al primer acceso.</small></div>`;
                OP.toast(response.message || 'Persona agregada correctamente.');
                await load(1);
            } catch (error) {
                OP.setMessage(createMessage, error.message);
            } finally {
                OP.buttonLoading(createSave, false);
            }
        });

        editForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = document.getElementById('person-edit-save');
            OP.setMessage(editMessage);
            OP.buttonLoading(button, true, 'Guardando...');
            try {
                await OP.request('op_u_persona.php', {
                    usuario_id: Number(document.getElementById('edit-person-id').value),
                    nivel: editLevel.value,
                    supervisor_id: Number(editSupervisor.value || 0),
                    supervisor_tambien_vende: document.getElementById('edit-supervisor-also-sells').checked,
                    nombre: document.getElementById('edit-person-name').value.trim(),
                    apellido_paterno: document.getElementById('edit-person-lastname').value.trim(),
                    apellido_materno: document.getElementById('edit-person-second-lastname').value.trim(),
                    username: document.getElementById('edit-person-username').value.trim(),
                    email: document.getElementById('edit-person-email').value.trim(),
                    telefono: document.getElementById('edit-person-phone').value.trim(),
                    fecha_nacimiento: document.getElementById('edit-person-birthdate').value,
                    estatus: document.getElementById('edit-person-status').value,
                }, { csrf: true });
                editDialog.close();
                OP.toast('Usuario actualizado correctamente.');
                await load(state.page);
            } catch (error) {
                OP.setMessage(editMessage, error.message);
            } finally {
                OP.buttonLoading(button, false);
            }
        });

        statusForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = statusForm.querySelector('button[type="submit"]');
            OP.setMessage(statusMessage);
            OP.buttonLoading(button, true, 'Actualizando...');
            try {
                await OP.request('op_u_usuario_estatus.php', {
                    usuario_id: Number(document.getElementById('status-person-id').value),
                    estatus: document.getElementById('status-person-value').value,
                }, { csrf: true });
                statusDialog.close();
                OP.toast('Estatus actualizado correctamente.');
                await load(state.page);
            } catch (error) {
                OP.setMessage(statusMessage, error.message);
            } finally {
                OP.buttonLoading(button, false);
            }
        });

        document.getElementById('password-person-generate').addEventListener('click', () => {
            document.getElementById('password-person-value').value = generatePassword();
        });
        passwordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = document.getElementById('person-password-save');
            const password = document.getElementById('password-person-value').value;
            OP.setMessage(passwordMessage);
            OP.buttonLoading(button, true, 'Restableciendo...');
            try {
                await OP.request('op_u_usuario_password.php', {
                    usuario_id: Number(document.getElementById('password-person-id').value),
                    password_temporal: password,
                }, { csrf: true });
                passwordResult.hidden = false;
                passwordResult.innerHTML = `<i class="fa-solid fa-key"></i><div><strong>Contraseña restablecida</strong><span>Temporal: <code>${OP.escapeHtml(password)}</code></span><small>El usuario deberá cambiarla en su próximo acceso.</small></div>`;
                OP.toast('Contraseña temporal asignada correctamente.');
            } catch (error) {
                OP.setMessage(passwordMessage, error.message);
            } finally {
                OP.buttonLoading(button, false);
            }
        });

        await load();
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
