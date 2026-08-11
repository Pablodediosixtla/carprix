(async () => {
    'use strict';

    const OP = window.CARPRIX_OP;
    const state = {
        user: null,
        page: 1,
        timer: null,
        tasks: new Map(),
        destinatarios: [],
        detailTaskId: null,
    };

    const list = document.getElementById('task-list');
    const pagination = document.getElementById('task-pagination');
    const taskDialog = document.getElementById('task-dialog');
    const taskForm = document.getElementById('task-form');
    const taskMessage = document.getElementById('task-message');
    const detailDialog = document.getElementById('task-detail-dialog');
    const actionDialog = document.getElementById('task-action-dialog');
    const approvalDialog = document.getElementById('task-approval-dialog');

    const displayStatus = (status) => status === 'En revision' ? 'En revisión' : status;

    const toLocalInput = (date) => {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const dueLabel = (value) => {
        if (!value) return 'Sin fecha límite';
        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return value;
        const now = new Date();
        const late = parsed.getTime() < now.getTime();
        return `${late ? 'Vencida · ' : ''}${OP.formatDate(value)}`;
    };

    const loadRecipients = async () => {
        const response = await OP.request('op_c_tarea_destinatarios.php');
        state.destinatarios = response.data.items || [];
        const select = document.getElementById('task-assignee');
        select.innerHTML = '<option value="">Selecciona una persona</option>' + state.destinatarios.map((item) => {
            const isSelf = Number(item.id) === Number(state.user.id);
            const manager = item.supervisor_nombre ? ` · Manager: ${item.supervisor_nombre}` : '';
            return `<option value="${item.id}">${OP.escapeHtml(item.nombre_completo)}${isSelf ? ' (Tú)' : ''}${OP.escapeHtml(manager)}</option>`;
        }).join('');
    };

    const openNewTask = () => {
        taskForm.reset();
        document.getElementById('task-priority-form').value = 'Media';
        document.getElementById('task-requires-approval').checked = true;
        const start = new Date();
        start.setMinutes(Math.ceil(start.getMinutes() / 15) * 15, 0, 0);
        const end = new Date(start.getTime() + (24 * 60 * 60 * 1000));
        document.getElementById('task-start').value = toLocalInput(start);
        document.getElementById('task-end').value = toLocalInput(end);
        const self = state.destinatarios.find((item) => Number(item.id) === Number(state.user.id));
        document.getElementById('task-assignee').value = self ? String(self.id) : '';
        OP.setMessage(taskMessage);
        taskDialog.showModal();
    };

    const renderMetrics = (summary) => {
        document.querySelectorAll('[data-task-metric]').forEach((node) => {
            node.textContent = new Intl.NumberFormat('es-MX').format(Number(summary?.[node.dataset.taskMetric] || 0));
        });
    };

    const renderActions = (item) => {
        const p = item.permisos || {};
        const actions = [`<button class="op-secondary-button" data-task-detail="${item.id}"><i class="fa-regular fa-eye"></i> Detalle</button>`];
        if (p.puede_iniciar) actions.push(`<button class="op-primary-button" data-task-action="iniciar" data-task-id="${item.id}"><i class="fa-solid fa-play"></i> Iniciar</button>`);
        if (p.puede_completar) actions.push(`<button class="op-success-button" data-task-action="completar" data-task-id="${item.id}"><i class="fa-solid fa-check"></i> ${item.requiere_aprobacion ? 'Enviar a revisión' : 'Completar'}</button>`);
        if (p.puede_aprobar) {
            actions.push(`<button class="op-success-button" data-task-approval="Aprobado" data-task-id="${item.id}"><i class="fa-solid fa-check-double"></i> Aprobar</button>`);
            actions.push(`<button class="op-danger-button" data-task-approval="Rechazado" data-task-id="${item.id}"><i class="fa-solid fa-rotate-left"></i> Rechazar</button>`);
        }
        if (p.puede_cancelar) actions.push(`<button class="op-danger-button" data-task-action="cancelar" data-task-id="${item.id}"><i class="fa-solid fa-ban"></i> Cancelar</button>`);
        return actions.join('');
    };

    const renderTasks = (items) => {
        state.tasks.clear();
        items.forEach((item) => state.tasks.set(Number(item.id), item));
        if (!items.length) {
            list.innerHTML = '<div class="op-empty"><div><i class="fa-solid fa-list-check"></i>No hay tareas con estos filtros.</div></div>';
            return;
        }

        list.innerHTML = items.map((item) => `
            <article class="op-task-card priority-${String(item.prioridad || '').toLowerCase()}">
                <div class="op-task-main">
                    <div class="op-task-card-head">
                        <span class="op-card-label">${OP.escapeHtml(item.folio)}</span>
                        <span class="op-status-badge ${OP.statusClass(item.estatus)}">${OP.escapeHtml(displayStatus(item.estatus))}</span>
                    </div>
                    <h3>${OP.escapeHtml(item.titulo)}</h3>
                    <p>${OP.escapeHtml(item.descripcion || 'Sin descripción.')}</p>
                    <div class="op-task-meta">
                        <span><i class="fa-solid fa-user"></i> ${OP.escapeHtml(item.asignado_a_nombre)}</span>
                        <span><i class="fa-solid fa-user-pen"></i> Creó: ${OP.escapeHtml(item.creado_por_nombre)}</span>
                        <span><i class="fa-solid fa-flag"></i> ${OP.escapeHtml(item.prioridad)}</span>
                        <span><i class="fa-regular fa-message"></i> ${item.comentarios_total}</span>
                    </div>
                </div>
                <div class="op-task-dates">
                    <small>INICIO</small><strong>${OP.formatDate(item.fecha_inicio)}</strong>
                    <small>FECHA FIN</small><strong>${OP.escapeHtml(dueLabel(item.fecha_fin))}</strong>
                    <small>APROBADOR</small><strong>${OP.escapeHtml(item.aprobador_nombre || (item.requiere_aprobacion ? 'Por jerarquía' : 'No requerido'))}</strong>
                </div>
                <div class="op-task-actions">${renderActions(item)}</div>
            </article>`).join('');

        list.querySelectorAll('[data-task-detail]').forEach((button) => {
            button.addEventListener('click', () => openDetail(Number(button.dataset.taskDetail)));
        });
        list.querySelectorAll('[data-task-action]').forEach((button) => {
            button.addEventListener('click', () => openAction(Number(button.dataset.taskId), button.dataset.taskAction));
        });
        list.querySelectorAll('[data-task-approval]').forEach((button) => {
            button.addEventListener('click', () => openApproval(Number(button.dataset.taskId), button.dataset.taskApproval));
        });
    };

    const load = async (page = 1) => {
        state.page = page;
        list.innerHTML = '<div class="op-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando tareas...</div>';
        try {
            const response = await OP.request('op_c_tareas.php', {
                page,
                size: 15,
                search: document.getElementById('task-search').value.trim(),
                estatus: document.getElementById('task-status').value,
                prioridad: document.getElementById('task-priority').value,
            });
            renderTasks(response.data.items || []);
            renderMetrics(response.data.resumen || {});
            OP.pagination(pagination, response.data.pagination, load);
        } catch (error) {
            list.innerHTML = `<div class="op-empty"><div><i class="fa-solid fa-triangle-exclamation"></i>${OP.escapeHtml(error.message)}</div></div>`;
        }
    };

    const openAction = (taskId, action) => {
        const task = state.tasks.get(taskId);
        if (!task) return;
        const title = document.getElementById('task-action-title');
        const description = document.getElementById('task-action-description');
        const comment = document.getElementById('task-action-comment');
        const submit = document.getElementById('task-action-submit');
        document.getElementById('task-action-id').value = String(taskId);
        document.getElementById('task-action-value').value = action;
        comment.value = '';
        OP.setMessage(document.getElementById('task-action-message'));

        if (action === 'iniciar') {
            title.textContent = 'Iniciar tarea';
            description.textContent = `La tarea ${task.folio} pasará a En progreso.`;
            submit.className = 'op-primary-button';
            submit.innerHTML = '<i class="fa-solid fa-play"></i> Iniciar';
            comment.required = false;
        } else if (action === 'completar') {
            title.textContent = task.requiere_aprobacion ? 'Enviar tarea a revisión' : 'Completar tarea';
            description.textContent = task.requiere_aprobacion
                ? 'La tarea se enviará al manager directo de la persona asignada para su aprobación.'
                : 'La tarea quedará marcada como Completada.';
            submit.className = 'op-success-button';
            submit.innerHTML = '<i class="fa-solid fa-check"></i> Continuar';
            comment.required = false;
        } else {
            title.textContent = 'Cancelar tarea';
            description.textContent = 'La tarea quedará cancelada y cualquier aprobación pendiente también se cerrará.';
            submit.className = 'op-danger-button';
            submit.innerHTML = '<i class="fa-solid fa-ban"></i> Cancelar tarea';
            comment.required = true;
        }
        actionDialog.showModal();
    };

    const openApproval = (taskId, decision) => {
        const task = state.tasks.get(taskId);
        if (!task) return;
        document.getElementById('task-approval-id').value = String(taskId);
        document.getElementById('task-approval-decision').value = decision;
        document.getElementById('task-approval-comment').value = '';
        const title = document.getElementById('task-approval-title');
        const description = document.getElementById('task-approval-description');
        const submit = document.getElementById('task-approval-submit');
        title.textContent = decision === 'Aprobado' ? 'Aprobar tarea' : 'Rechazar tarea';
        description.textContent = decision === 'Aprobado'
            ? `${task.folio} quedará Completada.`
            : `${task.folio} regresará a En progreso para que la persona asignada continúe trabajando.`;
        submit.className = decision === 'Aprobado' ? 'op-success-button' : 'op-danger-button';
        submit.innerHTML = decision === 'Aprobado' ? '<i class="fa-solid fa-check-double"></i> Aprobar' : '<i class="fa-solid fa-rotate-left"></i> Rechazar';
        OP.setMessage(document.getElementById('task-approval-message'));
        approvalDialog.showModal();
    };

    const detailActionButtons = (task) => {
        const p = task.permisos || {};
        const buttons = [];
        if (p.puede_iniciar) buttons.push(`<button class="op-primary-button" data-detail-action="iniciar"><i class="fa-solid fa-play"></i> Iniciar</button>`);
        if (p.puede_completar) buttons.push(`<button class="op-success-button" data-detail-action="completar"><i class="fa-solid fa-check"></i> ${task.requiere_aprobacion ? 'Enviar a revisión' : 'Completar'}</button>`);
        if (p.puede_aprobar) {
            buttons.push('<button class="op-success-button" data-detail-approval="Aprobado"><i class="fa-solid fa-check-double"></i> Aprobar</button>');
            buttons.push('<button class="op-danger-button" data-detail-approval="Rechazado"><i class="fa-solid fa-rotate-left"></i> Rechazar</button>');
        }
        if (p.puede_cancelar) buttons.push('<button class="op-danger-button" data-detail-action="cancelar"><i class="fa-solid fa-ban"></i> Cancelar</button>');
        return buttons.join('');
    };

    const openDetail = async (taskId) => {
        state.detailTaskId = taskId;
        document.getElementById('task-detail-body').innerHTML = '<div class="op-loading">Cargando...</div>';
        document.getElementById('task-comments-list').innerHTML = '';
        document.getElementById('task-approval-history').innerHTML = '';
        detailDialog.showModal();
        try {
            const response = await OP.request('op_c_tarea.php', { tarea_id: taskId });
            const task = response.data.tarea;
            document.getElementById('task-detail-folio').textContent = task.folio;
            document.getElementById('task-detail-title').textContent = task.titulo;
            document.getElementById('task-comment-id').value = String(task.id);
            document.getElementById('task-detail-body').innerHTML = `
                <div class="op-task-detail-grid">
                    <div><small>ESTATUS</small><strong><span class="op-status-badge ${OP.statusClass(task.estatus)}">${OP.escapeHtml(displayStatus(task.estatus))}</span></strong></div>
                    <div><small>PRIORIDAD</small><strong>${OP.escapeHtml(task.prioridad)}</strong></div>
                    <div><small>ASIGNADA A</small><strong>${OP.escapeHtml(task.asignado_a_nombre)}</strong></div>
                    <div><small>CREADA POR</small><strong>${OP.escapeHtml(task.creado_por_nombre)}</strong></div>
                    <div><small>FECHA INICIO</small><strong>${OP.formatDate(task.fecha_inicio)}</strong></div>
                    <div><small>FECHA FIN</small><strong>${OP.formatDate(task.fecha_fin)}</strong></div>
                    <div><small>APROBADOR</small><strong>${OP.escapeHtml(task.aprobador_nombre || (task.requiere_aprobacion ? 'Por jerarquía' : 'No requerido'))}</strong></div>
                    <div><small>APROBACIÓN</small><strong>${task.requiere_aprobacion ? 'Requerida' : 'No requerida'}</strong></div>
                </div>
                <div class="op-task-description"><small>DESCRIPCIÓN</small><p>${OP.escapeHtml(task.descripcion || 'Sin descripción.')}</p></div>`;

            const actions = document.getElementById('task-detail-actions');
            actions.innerHTML = detailActionButtons(task);
            actions.querySelectorAll('[data-detail-action]').forEach((button) => {
                button.addEventListener('click', () => { detailDialog.close(); openAction(task.id, button.dataset.detailAction); });
            });
            actions.querySelectorAll('[data-detail-approval]').forEach((button) => {
                button.addEventListener('click', () => { detailDialog.close(); openApproval(task.id, button.dataset.detailApproval); });
            });

            const comments = response.data.comentarios || [];
            document.getElementById('task-comments-list').innerHTML = comments.length ? comments.map((item) => `
                <article class="op-task-comment">
                    <div><strong>${OP.escapeHtml(item.usuario_nombre)}</strong><small>${OP.formatDate(item.creado_en)}</small></div>
                    <p>${OP.escapeHtml(item.comentario)}</p>
                </article>`).join('') : '<div class="op-empty compact"><div><i class="fa-regular fa-message"></i>Aún no hay comentarios.</div></div>';

            const approvals = response.data.aprobaciones || [];
            const history = response.data.historial || [];
            const approvalHtml = approvals.length ? approvals.map((item) => `
                <div class="op-task-history-item approval">
                    <span class="op-status-badge ${OP.statusClass(item.decision)}">${OP.escapeHtml(item.decision)}</span>
                    <div><strong>Aprobación · ${OP.escapeHtml(item.aprobador_nombre)}</strong><small>${OP.formatDate(item.fecha_solicitud)}${item.fecha_decision ? ` · Resuelta ${OP.formatDate(item.fecha_decision)}` : ''}</small>${item.comentario ? `<p>${OP.escapeHtml(item.comentario)}</p>` : ''}</div>
                </div>`).join('') : '';
            const historyHtml = history.map((item) => `
                <div class="op-task-history-item">
                    <i class="fa-solid fa-circle"></i>
                    <div><strong>${OP.escapeHtml(item.tipo_evento.replaceAll('_', ' '))} · ${OP.escapeHtml(item.usuario_nombre)}</strong><small>${OP.formatDate(item.creado_en)}</small>${item.detalle ? `<p>${OP.escapeHtml(item.detalle)}</p>` : ''}</div>
                </div>`).join('');
            document.getElementById('task-approval-history').innerHTML = approvalHtml + historyHtml || '<div class="op-empty compact"><div>Sin historial.</div></div>';
        } catch (error) {
            document.getElementById('task-detail-body').innerHTML = `<div class="op-empty"><div><i class="fa-solid fa-triangle-exclamation"></i>${OP.escapeHtml(error.message)}</div></div>`;
        }
    };

    try {
        state.user = await OP.loadSession();
        if (!state.user) return;
        if (state.user.debe_cambiar_password) {
            await OP.forcePasswordChange();
            location.reload();
            return;
        }

        await loadRecipients();

        document.getElementById('new-task-button').addEventListener('click', openNewTask);
        document.getElementById('task-refresh').addEventListener('click', () => load(1));
        document.getElementById('task-status').addEventListener('change', () => load(1));
        document.getElementById('task-priority').addEventListener('change', () => load(1));
        document.getElementById('task-search').addEventListener('input', () => {
            clearTimeout(state.timer);
            state.timer = setTimeout(() => load(1), 350);
        });

        taskForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!taskForm.reportValidity()) return;
            const save = document.getElementById('task-save');
            OP.setMessage(taskMessage);
            OP.buttonLoading(save, true, 'Creando...');
            try {
                await OP.request('op_i_tarea.php', {
                    titulo: document.getElementById('task-title').value.trim(),
                    descripcion: document.getElementById('task-description').value.trim(),
                    prioridad: document.getElementById('task-priority-form').value,
                    asignado_a: Number(document.getElementById('task-assignee').value),
                    fecha_inicio: document.getElementById('task-start').value,
                    fecha_fin: document.getElementById('task-end').value,
                    requiere_aprobacion: document.getElementById('task-requires-approval').checked,
                }, { csrf: true });
                taskDialog.close();
                OP.toast('Tarea creada correctamente.');
                await load(1);
            } catch (error) {
                OP.setMessage(taskMessage, error.message);
            } finally {
                OP.buttonLoading(save, false);
            }
        });

        document.getElementById('task-action-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = document.getElementById('task-action-submit');
            const action = document.getElementById('task-action-value').value;
            const comment = document.getElementById('task-action-comment').value.trim();
            const message = document.getElementById('task-action-message');
            if (action === 'cancelar' && !comment) {
                OP.setMessage(message, 'Debes indicar el motivo de cancelación.');
                return;
            }
            OP.setMessage(message);
            OP.buttonLoading(submit, true, 'Procesando...');
            try {
                const response = await OP.request('op_u_tarea.php', {
                    tarea_id: Number(document.getElementById('task-action-id').value),
                    accion: action,
                    comentario: comment,
                }, { csrf: true });
                actionDialog.close();
                OP.toast(response.message || 'Tarea actualizada.');
                await load(state.page);
            } catch (error) {
                OP.setMessage(message, error.message);
            } finally {
                OP.buttonLoading(submit, false);
            }
        });

        document.getElementById('task-approval-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = document.getElementById('task-approval-submit');
            const decision = document.getElementById('task-approval-decision').value;
            const comment = document.getElementById('task-approval-comment').value.trim();
            const message = document.getElementById('task-approval-message');
            if (decision === 'Rechazado' && !comment) {
                OP.setMessage(message, 'Debes indicar el motivo del rechazo.');
                return;
            }
            OP.setMessage(message);
            OP.buttonLoading(submit, true, 'Resolviendo...');
            try {
                const response = await OP.request('op_u_tarea_aprobacion.php', {
                    tarea_id: Number(document.getElementById('task-approval-id').value),
                    decision,
                    comentario: comment,
                }, { csrf: true });
                approvalDialog.close();
                OP.toast(response.message || 'Aprobación resuelta.');
                await load(state.page);
            } catch (error) {
                OP.setMessage(message, error.message);
            } finally {
                OP.buttonLoading(submit, false);
            }
        });

        document.getElementById('task-comment-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const text = document.getElementById('task-comment-text');
            const submit = document.getElementById('task-comment-save');
            const value = text.value.trim();
            if (!value) return;
            OP.buttonLoading(submit, true, 'Enviando...');
            try {
                await OP.request('op_i_tarea_comentario.php', {
                    tarea_id: Number(document.getElementById('task-comment-id').value),
                    comentario: value,
                }, { csrf: true });
                text.value = '';
                OP.toast('Comentario agregado.');
                await openDetail(state.detailTaskId);
                await load(state.page);
            } catch (error) {
                OP.toast(error.message, 'error');
            } finally {
                OP.buttonLoading(submit, false);
            }
        });

        await load();
    } catch (error) {
        OP.toast(error.message, 'error');
    }
})();
