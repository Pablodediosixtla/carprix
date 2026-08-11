<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Tareas', 'tareas');
?>
<section class="op-page-head">
    <div>
        <span class="op-kicker">ORGANIZACIÓN</span>
        <h2>Tareas operativas</h2>
        <p>Crea tareas para ti o para tu línea jerárquica, da seguimiento y controla su aprobación.</p>
    </div>
    <button class="op-primary-button" id="new-task-button" type="button">
        <i class="fa-solid fa-plus"></i> Nueva tarea
    </button>
</section>

<section class="op-metric-grid op-task-metrics">
    <article class="op-metric-card"><span class="op-metric-icon amber"><i class="fa-regular fa-clock"></i></span><div><small>Pendientes</small><strong data-task-metric="pendientes">0</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon blue"><i class="fa-solid fa-person-running"></i></span><div><small>En progreso</small><strong data-task-metric="en_progreso">0</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon purple"><i class="fa-solid fa-user-check"></i></span><div><small>En revisión</small><strong data-task-metric="en_revision">0</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon green"><i class="fa-solid fa-check-double"></i></span><div><small>Completadas</small><strong data-task-metric="completadas">0</strong></div></article>
</section>

<section class="op-filter-bar op-task-filter">
    <label class="op-search"><i class="fa-solid fa-magnifying-glass"></i><input id="task-search" placeholder="Folio, título, persona o descripción"></label>
    <select id="task-status">
        <option value="">Todos los estatus</option>
        <option>Pendiente</option>
        <option>En progreso</option>
        <option value="En revision">En revisión</option>
        <option>Completada</option>
        <option>Cancelada</option>
    </select>
    <select id="task-priority">
        <option value="">Todas las prioridades</option>
        <option>Baja</option>
        <option>Media</option>
        <option>Alta</option>
        <option>Urgente</option>
    </select>
    <button class="op-secondary-button" id="task-refresh" type="button"><i class="fa-solid fa-rotate"></i></button>
</section>

<section class="op-task-list" id="task-list"><div class="op-loading">Cargando tareas...</div></section>
<div class="op-pagination" id="task-pagination"></div>

<dialog class="op-dialog wide" id="task-dialog">
    <form class="op-dialog-card" id="task-form" novalidate>
        <div class="op-dialog-header">
            <div><small>TAREAS</small><h3>Nueva tarea</h3></div>
            <button class="op-dialog-close" type="button" data-close-dialog aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="op-dialog-description">Solo puedes asignar tareas a ti mismo o a personas de tu línea jerárquica descendente.</p>
        <div class="op-form-grid two">
            <label class="op-field span-2"><span>Título *</span><input id="task-title" maxlength="150" required></label>
            <label class="op-field"><span>Asignar a *</span><select id="task-assignee" required><option value="">Selecciona una persona</option></select></label>
            <label class="op-field"><span>Prioridad *</span><select id="task-priority-form" required><option>Media</option><option>Baja</option><option>Alta</option><option>Urgente</option></select></label>
            <label class="op-field"><span>Fecha inicio *</span><input id="task-start" type="datetime-local" required></label>
            <label class="op-field"><span>Fecha fin</span><input id="task-end" type="datetime-local"></label>
            <label class="op-field span-2"><span>Descripción</span><textarea id="task-description" rows="5" maxlength="4000" placeholder="Objetivo, entregables, instrucciones o contexto..."></textarea></label>
            <label class="op-check-field span-2"><input id="task-requires-approval" type="checkbox" checked><span><strong>Requiere aprobación al completar</strong><small>La tarea se enviará al manager directo de la persona asignada.</small></span></label>
        </div>
        <div class="op-form-message" id="task-message" hidden></div>
        <div class="op-dialog-actions">
            <button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button>
            <button class="op-primary-button" id="task-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Crear tarea</button>
        </div>
    </form>
</dialog>

<dialog class="op-dialog wide" id="task-detail-dialog">
    <div class="op-dialog-card op-task-detail-card">
        <div class="op-dialog-header">
            <div><small id="task-detail-folio">TAREA</small><h3 id="task-detail-title">Detalle de tarea</h3></div>
            <button class="op-dialog-close" type="button" data-close-dialog aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="task-detail-body" class="op-task-detail-body"><div class="op-loading">Cargando...</div></div>
        <div class="op-task-detail-actions" id="task-detail-actions"></div>
        <section class="op-task-comments">
            <div class="op-panel-header"><div><small>SEGUIMIENTO</small><h3>Comentarios</h3></div></div>
            <div id="task-comments-list" class="op-task-comments-list"></div>
            <form id="task-comment-form" class="op-task-comment-form">
                <input type="hidden" id="task-comment-id">
                <textarea id="task-comment-text" rows="3" maxlength="2000" placeholder="Agrega un comentario de seguimiento..." required></textarea>
                <button class="op-primary-button" id="task-comment-save" type="submit"><i class="fa-solid fa-paper-plane"></i> Comentar</button>
            </form>
        </section>
        <section class="op-task-history-wrap">
            <div class="op-panel-header"><div><small>AUDITORÍA</small><h3>Historial y aprobaciones</h3></div></div>
            <div id="task-approval-history" class="op-task-history"></div>
        </section>
    </div>
</dialog>

<dialog class="op-dialog" id="task-action-dialog">
    <form class="op-dialog-card" id="task-action-form">
        <div class="op-dialog-header">
            <div><small>TAREA</small><h3 id="task-action-title">Actualizar tarea</h3></div>
            <button class="op-dialog-close" type="button" data-close-dialog aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="op-dialog-description" id="task-action-description"></p>
        <input type="hidden" id="task-action-id">
        <input type="hidden" id="task-action-value">
        <label class="op-field"><span>Comentario</span><textarea id="task-action-comment" rows="4" maxlength="500"></textarea></label>
        <div class="op-form-message" id="task-action-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-primary-button" id="task-action-submit" type="submit">Continuar</button></div>
    </form>
</dialog>

<dialog class="op-dialog" id="task-approval-dialog">
    <form class="op-dialog-card" id="task-approval-form">
        <div class="op-dialog-header">
            <div><small>APROBACIÓN</small><h3 id="task-approval-title">Resolver tarea</h3></div>
            <button class="op-dialog-close" type="button" data-close-dialog aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="op-dialog-description" id="task-approval-description"></p>
        <input type="hidden" id="task-approval-id">
        <input type="hidden" id="task-approval-decision">
        <label class="op-field"><span>Comentario</span><textarea id="task-approval-comment" rows="4" maxlength="500"></textarea></label>
        <div class="op-form-message" id="task-approval-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-primary-button" id="task-approval-submit" type="submit">Resolver</button></div>
    </form>
</dialog>
<?php operativoPageEnd(['operativo-tareas.js']); ?>
