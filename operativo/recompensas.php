<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Recompensas', 'recompensas');
?>
<section class="op-page-head">
    <div>
        <span class="op-kicker">RECONOCIMIENTO</span>
        <h2>Mis recompensas</h2>
        <p>Consulta tus puntos del año, movimientos y las metas disponibles para alcanzar premios.</p>
    </div>
    <div class="op-reward-head-actions">
        <label class="op-field op-year-field"><span>Año</span><select id="reward-year"></select></label>
        <button class="op-primary-button" id="reward-grant-button" type="button" hidden><i class="fa-solid fa-award"></i> Asignar recompensa</button>
    </div>
</section>

<div class="op-year-notice"><i class="fa-solid fa-calendar-check"></i><span>Los puntos se contabilizan por año calendario. Al iniciar un nuevo año comienza automáticamente un nuevo recorrido de metas sin borrar el historial anterior.</span></div>

<section class="op-metric-grid op-reward-metrics">
    <article class="op-metric-card"><span class="op-metric-icon green"><i class="fa-solid fa-star"></i></span><div><small>Puntos actuales</small><strong id="reward-balance">0</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon blue"><i class="fa-solid fa-arrow-trend-up"></i></span><div><small>Puntos ganados</small><strong id="reward-earned">0</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon red"><i class="fa-solid fa-arrow-trend-down"></i></span><div><small>Puntos descontados</small><strong id="reward-deducted">0</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon purple"><i class="fa-solid fa-receipt"></i></span><div><small>Movimientos</small><strong id="reward-movements-count">0</strong></div></article>
</section>

<section class="op-grid-two op-reward-grid">
    <article class="op-panel">
        <div class="op-panel-header"><div><small>METAS</small><h3>Premios disponibles</h3></div></div>
        <div class="op-prize-list" id="reward-prizes"><div class="op-loading">Cargando metas...</div></div>
    </article>
    <article class="op-panel">
        <div class="op-panel-header"><div><small>HISTORIAL</small><h3>Mis movimientos</h3></div></div>
        <div class="op-reward-history" id="reward-history"><div class="op-loading">Cargando movimientos...</div></div>
    </article>
</section>

<dialog class="op-dialog" id="reward-grant-dialog">
    <form class="op-dialog-card" id="reward-grant-form">
        <div class="op-dialog-header">
            <div><small>RECONOCIMIENTO</small><h3>Asignar recompensa</h3></div>
            <button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="op-dialog-description">Solo puedes asignar movimientos a subordinados de tu línea jerárquica. No puedes asignarte puntos a ti mismo.</p>
        <label class="op-field"><span>Persona *</span><select id="reward-target" required><option value="">Selecciona una persona</option></select></label>
        <label class="op-field"><span>Recompensa / incidencia *</span><select id="reward-catalog" required><option value="">Selecciona un concepto</option></select></label>
        <div class="op-reward-preview" id="reward-preview" hidden></div>
        <label class="op-field"><span>Comentario</span><textarea id="reward-comment" rows="4" maxlength="700" placeholder="Contexto o motivo del movimiento..."></textarea></label>
        <div class="op-form-message" id="reward-grant-message" hidden></div>
        <div class="op-dialog-actions">
            <button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button>
            <button class="op-primary-button" id="reward-grant-submit" type="submit"><i class="fa-solid fa-check"></i> Aplicar</button>
        </div>
    </form>
</dialog>

<?php operativoPageEnd(['operativo-recompensas.js']); ?>
