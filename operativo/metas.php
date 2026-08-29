<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Metas', 'metas');
?>
<section class="op-page-head op-goals-head">
    <div>
        <span class="op-kicker">GESTIÓN COMERCIAL</span>
        <h2>Metas por equipo y persona</h2>
        <p>Las reservas se administran por mes y las ventas por año. Sin una meta asignada, el valor operativo es 0.</p>
    </div>
    <div class="op-goals-filters">
        <label class="op-field compact"><span>Año</span><select id="goals-year"></select></label>
        <label class="op-field compact"><span>Mes de reserva</span><select id="goals-month"></select></label>
        <label class="op-field compact op-goals-team-field"><span>Equipo</span><select id="goals-team"></select></label>
        <button class="op-secondary-button" id="goals-refresh" type="button" title="Actualizar" aria-label="Actualizar metas"><i class="fa-solid fa-rotate"></i></button>
    </div>
</section>

<section class="op-metric-grid op-goals-metrics">
    <article class="op-metric-card"><div class="op-metric-icon purple"><i class="fa-solid fa-handshake"></i></div><div><span>Meta reservas del mes</span><strong id="goals-total-reserve">0</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon green"><i class="fa-solid fa-tag"></i></div><div><span>Meta ventas del año</span><strong id="goals-total-sale">0</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon blue"><i class="fa-solid fa-users"></i></div><div><span>Personas con alcance</span><strong id="goals-people-count">0</strong></div></article>
</section>

<section class="op-panel op-goal-team-panel" id="goals-team-total-panel" hidden>
    <div class="op-panel-header">
        <div><small>PRORRATEO AUTOMÁTICO</small><h3>Meta total del equipo</h3></div>
        <span class="op-muted" id="goals-team-label">—</span>
    </div>
    <p class="op-panel-description">La meta indicada se distribuye automáticamente, en números enteros, entre las personas activas con rol VENTAS dentro del equipo seleccionado.</p>
    <div class="op-goal-total-grid">
        <div class="op-goal-total-card">
            <label class="op-field"><span>Meta de reservas del mes</span><input id="goals-prorate-reserve" type="number" min="0" step="1" value="0"></label>
            <button class="op-primary-button" id="goals-prorate-reserve-button" type="button"><i class="fa-solid fa-divide"></i> Prorratear reservas</button>
        </div>
        <div class="op-goal-total-card">
            <label class="op-field"><span>Meta anual de ventas</span><input id="goals-prorate-sale" type="number" min="0" step="1" value="0"></label>
            <button class="op-primary-button" id="goals-prorate-sale-button" type="button"><i class="fa-solid fa-divide"></i> Prorratear ventas</button>
        </div>
    </div>
</section>

<section class="op-panel">
    <div class="op-panel-header op-goals-distribution-head">
        <div>
            <small>DISTRIBUCIÓN POR PERSONA</small>
            <h3>Metas asignadas</h3>
            <p id="goals-distribution-note" class="op-muted">—</p>
        </div>
        <button class="op-primary-button" id="goals-save-distribution" type="button"><i class="fa-solid fa-floppy-disk"></i> Guardar distribución</button>
    </div>
    <div class="op-form-message" id="goals-message" hidden></div>
    <div class="op-table-wrap">
        <table class="op-table op-goals-table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Meta reservas del mes</th>
                    <th>Meta ventas del año</th>
                </tr>
            </thead>
            <tbody id="goals-body"><tr><td colspan="3">Cargando metas...</td></tr></tbody>
        </table>
    </div>
</section>
<?php operativoPageEnd(['operativo-metas.js']); ?>
