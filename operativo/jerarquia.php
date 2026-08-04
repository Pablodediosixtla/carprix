<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Jerarquía operativa', 'jerarquia');
?>
<section class="op-page-head">
    <div><span class="op-kicker">CONFIGURACIÓN</span><h2>Trabajador y supervisor</h2><p>Define quién debe autorizar los cambios de estatus solicitados por cada trabajador.</p></div>
</section>
<section class="op-panel op-hierarchy-form-panel">
    <form id="hierarchy-form" class="op-form-grid two">
        <label class="op-field"><span>Trabajador *</span><select id="hierarchy-user" required><option value="">Selecciona un trabajador</option></select></label>
        <label class="op-field"><span>Supervisor / autorizador</span><select id="hierarchy-supervisor"><option value="0">Sin supervisor</option></select></label>
        <div class="op-form-message span-2" id="hierarchy-message" hidden></div>
        <div class="op-dialog-actions span-2"><button class="op-primary-button" type="submit"><i class="fa-solid fa-sitemap"></i> Guardar jerarquía</button></div>
    </form>
</section>
<section class="op-panel">
    <div class="op-panel-header"><div><small>RELACIONES ACTIVAS</small><h3>Estructura de autorización</h3></div><button class="op-secondary-button" id="hierarchy-refresh" type="button"><i class="fa-solid fa-rotate"></i></button></div>
    <div class="op-table-wrap"><table class="op-table"><thead><tr><th>Trabajador</th><th>Roles</th><th>Supervisor</th><th>Estatus</th><th></th></tr></thead><tbody id="hierarchy-table"><tr><td colspan="5">Cargando...</td></tr></tbody></table></div>
</section>
<?php operativoPageEnd(['operativo-jerarquia.js']); ?>
