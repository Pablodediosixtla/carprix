<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Requerimientos de compra', 'requerimientos');
?>
<section class="op-page-head">
    <div><span class="op-kicker">GESTIÓN COMERCIAL</span><h2>Solicitudes de clientes</h2><p>Registra el interés de compra y solicita cambios de estatus para autorización.</p></div>
    <button class="op-primary-button" id="new-requirement-button" type="button"><i class="fa-solid fa-plus"></i> Nuevo requerimiento</button>
</section>
<section class="op-filter-bar compact">
    <label class="op-search"><i class="fa-solid fa-magnifying-glass"></i><input id="requirement-search" placeholder="Folio, cliente, teléfono o auto"></label>
    <select id="requirement-status"><option value="">Todos los estatus</option><option>Solicitado</option><option>Apartado</option><option>Vendido</option></select>
    <button class="op-secondary-button" id="requirement-refresh" type="button"><i class="fa-solid fa-rotate"></i></button>
</section>
<section class="op-requirement-list" id="requirement-list"><div class="op-loading">Cargando requerimientos...</div></section>
<div class="op-pagination" id="requirement-pagination"></div>

<dialog class="op-dialog wide" id="requirement-dialog">
    <form class="op-dialog-card" id="requirement-form">
        <div class="op-dialog-header"><div><small>NUEVA OPORTUNIDAD</small><h3>Registrar requerimiento</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <div class="op-form-grid two">
            <label class="op-field span-2"><span>Auto disponible *</span><select id="req-auto" required><option value="">Selecciona un auto</option></select></label>
            <label class="op-field"><span>Nombre del cliente *</span><input id="req-client-name" maxlength="150" required></label>
            <label class="op-field"><span>Teléfono *</span><input id="req-client-phone" maxlength="20" required></label>
            <label class="op-field"><span>Correo</span><input id="req-client-email" type="email" maxlength="150"></label>
            <label class="op-field"><span>Identificación / referencia</span><input id="req-client-id" maxlength="100"></label>
            <label class="op-field"><span>Monto propuesto</span><input id="req-amount" type="number" min="1" step="0.01"></label>
            <label class="op-field"><span>Forma de pago</span><select id="req-payment"><option>Contado</option><option>Financiamiento</option><option>Otro</option></select></label>
            <label class="op-field span-2"><span>Comentarios</span><textarea id="req-comments" rows="4" maxlength="3000"></textarea></label>
        </div>
        <div class="op-form-message" id="requirement-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" id="requirement-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Registrar</button></div>
    </form>
</dialog>

<dialog class="op-dialog" id="status-dialog">
    <form class="op-dialog-card" id="status-form">
        <div class="op-dialog-header"><div><small>AUTORIZACIÓN REQUERIDA</small><h3 id="status-dialog-title">Solicitar cambio</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <input type="hidden" id="status-requirement-id">
        <input type="hidden" id="status-requested-value">
        <p class="op-dialog-description" id="status-description"></p>
        <label class="op-field"><span>Motivo del cambio *</span><textarea id="status-reason" rows="5" maxlength="500" required></textarea></label>
        <div class="op-form-message" id="status-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar a autorización</button></div>
    </form>
</dialog>
<?php operativoPageEnd(['operativo-requerimientos.js']); ?>
