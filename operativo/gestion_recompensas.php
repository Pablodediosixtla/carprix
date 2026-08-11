<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Gestión de recompensas', 'gestion_recompensas');
?>
<section class="op-page-head">
    <div>
        <span class="op-kicker">CONFIGURACIÓN</span>
        <h2>Gestión de recompensas</h2>
        <p>Administra categorías, valores de puntos, reglas automáticas de Apartado/Vendido y premios por alcanzar.</p>
    </div>
</section>

<div class="op-tabs" id="reward-management-tabs">
    <button class="op-tab-button active" type="button" data-reward-tab="categorias"><i class="fa-solid fa-layer-group"></i> Categorías</button>
    <button class="op-tab-button" type="button" data-reward-tab="catalogo"><i class="fa-solid fa-star"></i> Catálogo de puntos</button>
    <button class="op-tab-button" type="button" data-reward-tab="premios"><i class="fa-solid fa-gift"></i> Premios y metas</button>
</div>

<section class="op-tab-panel" data-reward-panel="categorias">
    <div class="op-panel">
        <div class="op-panel-header"><div><small>CLASIFICACIÓN</small><h3>Categorías</h3></div><button class="op-primary-button" id="new-reward-category" type="button"><i class="fa-solid fa-plus"></i> Nueva categoría</button></div>
        <div class="op-management-list" id="reward-category-list"><div class="op-loading">Cargando...</div></div>
    </div>
</section>

<section class="op-tab-panel" data-reward-panel="catalogo" hidden>
    <div class="op-panel">
        <div class="op-panel-header"><div><small>VALORES</small><h3>Catálogo de recompensas e incidencias</h3></div><button class="op-primary-button" id="new-reward-rule" type="button"><i class="fa-solid fa-plus"></i> Nuevo concepto</button></div>
        <div class="op-year-notice"><i class="fa-solid fa-circle-info"></i><span><b>AUTO_APARTADO</b> y <b>AUTO_VENDIDO</b> son reglas del sistema. Puedes cambiar su categoría, nombre, puntos y vigencia; su código y origen se mantienen protegidos.</span></div>
        <div class="op-management-list" id="reward-rule-list"><div class="op-loading">Cargando...</div></div>
    </div>
</section>

<section class="op-tab-panel" data-reward-panel="premios" hidden>
    <div class="op-panel">
        <div class="op-panel-header"><div><small>METAS</small><h3>Premios por puntos</h3></div><button class="op-primary-button" id="new-reward-prize" type="button"><i class="fa-solid fa-plus"></i> Nuevo premio</button></div>
        <div class="op-management-list" id="reward-prize-list"><div class="op-loading">Cargando...</div></div>
    </div>
</section>

<dialog class="op-dialog" id="reward-category-dialog">
    <form class="op-dialog-card" id="reward-category-form">
        <div class="op-dialog-header"><div><small>CATEGORÍA</small><h3 id="reward-category-title">Nueva categoría</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <input id="reward-category-id" type="hidden">
        <label class="op-field"><span>Nombre *</span><input id="reward-category-name" maxlength="100" required></label>
        <label class="op-field"><span>Tipo *</span><select id="reward-category-type" required><option value="SUMA">Suma puntos</option><option value="RESTA">Resta puntos</option></select></label>
        <label class="op-field"><span>Descripción</span><textarea id="reward-category-description" rows="3" maxlength="500"></textarea></label>
        <div class="op-form-grid two"><label class="op-field"><span>Orden</span><input id="reward-category-order" type="number" min="0" value="0"></label><label class="op-check-field"><input id="reward-category-active" type="checkbox" checked><span><strong>Activa</strong><small>Disponible para conceptos nuevos y activos.</small></span></label></div>
        <div class="op-form-message" id="reward-category-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" type="submit">Guardar</button></div>
    </form>
</dialog>

<dialog class="op-dialog" id="reward-rule-dialog">
    <form class="op-dialog-card" id="reward-rule-form">
        <div class="op-dialog-header"><div><small>CATÁLOGO</small><h3 id="reward-rule-title">Nuevo concepto</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <input id="reward-rule-id" type="hidden">
        <label class="op-field"><span>Categoría *</span><select id="reward-rule-category" required></select></label>
        <label class="op-field" id="reward-rule-code-field"><span>Código *</span><input id="reward-rule-code" maxlength="80" placeholder="EJEMPLO_RECOMPENSA" required></label>
        <label class="op-field"><span>Nombre *</span><input id="reward-rule-name" maxlength="140" required></label>
        <label class="op-field"><span>Puntos *</span><input id="reward-rule-points" type="number" min="1" required></label>
        <label class="op-field"><span>Descripción</span><textarea id="reward-rule-description" rows="3" maxlength="500"></textarea></label>
        <label class="op-check-field"><input id="reward-rule-active" type="checkbox" checked><span><strong>Activo</strong><small>Si se desactiva deja de poder aplicarse. Las reglas automáticas desactivadas no generan puntos.</small></span></label>
        <div class="op-form-message" id="reward-rule-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" type="submit">Guardar</button></div>
    </form>
</dialog>

<dialog class="op-dialog" id="reward-prize-dialog">
    <form class="op-dialog-card" id="reward-prize-form">
        <div class="op-dialog-header"><div><small>PREMIO</small><h3 id="reward-prize-title">Nuevo premio</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <input id="reward-prize-id" type="hidden">
        <label class="op-field"><span>Nombre *</span><input id="reward-prize-name" maxlength="140" required></label>
        <label class="op-field"><span>Puntos requeridos *</span><input id="reward-prize-points" type="number" min="1" required></label>
        <label class="op-field"><span>Descripción</span><textarea id="reward-prize-description" rows="3" maxlength="700"></textarea></label>
        <div class="op-form-grid two"><label class="op-field"><span>Orden</span><input id="reward-prize-order" type="number" min="0" value="0"></label><label class="op-check-field"><input id="reward-prize-active" type="checkbox" checked><span><strong>Activo</strong><small>Visible como meta para las personas.</small></span></label></div>
        <div class="op-form-message" id="reward-prize-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" type="submit">Guardar</button></div>
    </form>
</dialog>

<?php operativoPageEnd(['operativo-gestion-recompensas.js']); ?>
