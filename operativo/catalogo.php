<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Gestión de catálogo', 'catalogo');
?>
<section class="op-page-head">
    <div><span class="op-kicker">INVENTARIO</span><h2>Autos publicados</h2><p>Consulta, registra y edita la información operativa del catálogo.</p></div>
    <button class="op-primary-button" id="new-auto-button" type="button"><i class="fa-solid fa-plus"></i> Agregar auto</button>
</section>
<section class="op-filter-bar">
    <label class="op-search"><i class="fa-solid fa-magnifying-glass"></i><input id="catalog-search" placeholder="Buscar por ID, marca o modelo"></label>
    <select id="catalog-status"><option value="">Todos los estatus</option><option>Disponible</option><option>Vendido</option><option>Oculto</option></select>
    <select id="catalog-type"><option value="">Todos los tipos</option></select>
    <select id="catalog-location"><option value="">Todas las ubicaciones</option></select>
    <button class="op-secondary-button" id="catalog-refresh" type="button"><i class="fa-solid fa-rotate"></i></button>
</section>
<section class="op-catalog-grid" id="catalog-grid"><div class="op-loading">Cargando catálogo...</div></section>
<div class="op-pagination" id="catalog-pagination"></div>

<dialog class="op-dialog wide" id="auto-dialog">
    <form method="dialog" class="op-dialog-card" id="auto-form">
        <div class="op-dialog-header"><div><small>CATÁLOGO</small><h3 id="auto-dialog-title">Agregar auto</h3></div><button class="op-dialog-close" value="cancel" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
        <input type="hidden" id="auto-id">
        <div class="op-form-grid three">
            <label class="op-field"><span>Marca *</span><input id="auto-marca" required maxlength="50"></label>
            <label class="op-field span-2"><span>Modelo *</span><input id="auto-modelo" required maxlength="100"></label>
            <label class="op-field"><span>Tipo</span><input id="auto-tipo" maxlength="50"></label>
            <label class="op-field"><span>Año *</span><input id="auto-anio" type="number" min="1950" max="2100" required></label>
            <label class="op-field"><span>Estatus *</span><select id="auto-estatus"><option>Disponible</option><option>Vendido</option><option>Oculto</option></select></label>
            <label class="op-field"><span>Precio *</span><input id="auto-precio" type="number" min="1" step="0.01" required></label>
            <label class="op-field"><span>Mensualidad</span><input id="auto-mensualidad" type="number" min="0" step="0.01"></label>
            <label class="op-field"><span>Kilometraje</span><input id="auto-kilometraje" type="number" min="0"></label>
            <label class="op-field"><span>Ubicación *</span><input id="auto-ubicacion" required maxlength="100"></label>
            <label class="op-field"><span>Transmisión *</span><select id="auto-transmision"><option>Automatico</option><option>Manual</option></select></label>
            <label class="op-field"><span>Color</span><input id="auto-color" maxlength="50"></label>
            <label class="op-field"><span>Motor</span><input id="auto-motor" maxlength="50"></label>
            <label class="op-field"><span>Combustible</span><input id="auto-combustible" maxlength="50" value="Gasolina"></label>
            <label class="op-field"><span>Pasajeros</span><input id="auto-pasajeros" type="number" min="1" value="5"></label>
            <label class="op-field"><span>Tracción</span><input id="auto-traccion" maxlength="50" value="Delantera"></label>
            <label class="op-field"><span>Dueños</span><input id="auto-duenos" type="number" min="1" value="1"></label>
            <label class="op-field span-3"><span>Imagen principal</span><input id="auto-imagen" maxlength="500" placeholder="Catalogo/123/Img01.jpg"></label>
        </div>
        <div class="op-form-message" id="auto-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" value="cancel" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" id="auto-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button></div>
    </form>
</dialog>
<?php operativoPageEnd(['operativo-catalogo.js']); ?>
