<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Autorizaciones', 'autorizaciones');
?>
<section class="op-page-head">
    <div><span class="op-kicker">CONTROL JERÁRQUICO</span><h2>Cambios de estatus</h2><p>Aprueba o rechaza los movimientos solicitados por tu equipo.</p></div>
</section>
<section class="op-filter-bar compact">
    <label class="op-search"><i class="fa-solid fa-magnifying-glass"></i><input id="approval-search" placeholder="Folio, cliente, marca o modelo"></label>
    <select id="approval-decision"><option>Pendiente</option><option value="">Todas</option><option>Aprobado</option><option>Rechazado</option><option>Cancelado</option></select>
    <button class="op-secondary-button" id="approval-refresh" type="button"><i class="fa-solid fa-rotate"></i></button>
</section>
<section class="op-approval-list" id="approval-list"><div class="op-loading">Cargando autorizaciones...</div></section>
<div class="op-pagination" id="approval-pagination"></div>

<dialog class="op-dialog" id="decision-dialog">
    <form class="op-dialog-card" id="decision-form">
        <div class="op-dialog-header"><div><small>RESOLVER SOLICITUD</small><h3 id="decision-title">Autorizar cambio</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <input type="hidden" id="decision-change-id">
        <input type="hidden" id="decision-value">
        <p class="op-dialog-description" id="decision-description"></p>
        <label class="op-field"><span>Comentario de decisión</span><textarea id="decision-comment" rows="5" maxlength="500"></textarea></label>
        <div class="op-form-message" id="decision-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" id="decision-submit" type="submit">Confirmar</button></div>
    </form>
</dialog>
<?php operativoPageEnd(['operativo-autorizaciones.js']); ?>
