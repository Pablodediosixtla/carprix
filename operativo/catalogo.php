<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Gestión de catálogo', 'catalogo');
?>
<section class="op-page-head">
    <div>
        <span class="op-kicker">INVENTARIO</span>
        <h2>Gestión de catálogo</h2>
        <p>Consulta, registra y edita la información operativa, imágenes y solicitudes de publicación.</p>
    </div>
    <button class="op-primary-button" id="new-auto-button" type="button">
        <i class="fa-solid fa-plus"></i> Agregar auto
    </button>
</section>

<nav class="op-tabs" aria-label="Secciones de catálogo">
    <button class="op-tab-button active" type="button" data-catalog-tab="autos">
        <i class="fa-solid fa-car-side"></i> Catálogo
    </button>
    <button class="op-tab-button" type="button" data-catalog-tab="requerimientos">
        <i class="fa-solid fa-clipboard-check"></i> Requerimientos catálogo
        <span class="op-tab-badge" id="catalog-request-badge" hidden>0</span>
    </button>
    <button class="op-tab-button" type="button" data-catalog-tab="destacados">
        <i class="fa-solid fa-star"></i> Autos destacados
    </button>
</nav>

<section class="op-tab-panel" id="catalog-tab-autos">
    <section class="op-filter-bar">
        <label class="op-search"><i class="fa-solid fa-magnifying-glass"></i><input id="catalog-search" placeholder="Buscar por ID, marca o modelo"></label>
        <select id="catalog-status">
            <option value="">Todos los estatus</option>
            <option>Disponible</option>
            <option>Apartado</option>
            <option>Vendido</option>
            <option>Oculto</option>
        </select>
        <select id="catalog-type"><option value="">Todos los tipos</option></select>
        <select id="catalog-location"><option value="">Todas las ubicaciones</option></select>
        <button class="op-secondary-button" id="catalog-refresh" type="button"><i class="fa-solid fa-rotate"></i></button>
    </section>

    <section class="op-catalog-grid" id="catalog-grid"><div class="op-loading">Cargando catálogo...</div></section>
    <div class="op-pagination" id="catalog-pagination"></div>
</section>

<section class="op-tab-panel" id="catalog-tab-requerimientos" hidden>
    <section class="op-filter-bar compact">
        <label class="op-search"><i class="fa-solid fa-magnifying-glass"></i><input id="catalog-request-search" placeholder="ID, auto, marca, modelo o solicitante"></label>
        <select id="catalog-request-decision">
            <option value="">Todas las decisiones</option>
            <option>Pendiente</option>
            <option>Aprobado</option>
            <option>Rechazado</option>
            <option>Cancelado</option>
        </select>
        <button class="op-secondary-button" id="catalog-request-refresh" type="button"><i class="fa-solid fa-rotate"></i></button>
    </section>
    <section class="op-approval-list" id="catalog-request-list"><div class="op-loading">Cargando requerimientos de catálogo...</div></section>
    <div class="op-pagination" id="catalog-request-pagination"></div>
</section>

<section class="op-tab-panel" id="catalog-tab-destacados" hidden>
    <section class="op-panel op-featured-panel">
        <div class="op-panel-head">
            <div>
                <span class="op-kicker">INDEX / AUTOS DESTACADOS</span>
                <h3>Gestiona los autos destacados desde la lista</h3>
                <p>La lista se ordena por visitas a la vista de detalle, de mayor a menor. Puedes mantener hasta tres autos destacados al mismo tiempo.</p>
            </div>
            <div class="op-featured-summary" aria-live="polite">
                <span class="op-featured-summary-icon"><i class="fa-solid fa-star"></i></span>
                <div>
                    <small>DESTACADOS ACTIVOS</small>
                    <strong id="featured-count">0 / 3</strong>
                </div>
            </div>
        </div>

        <div class="op-form-message" id="featured-message" hidden></div>

        <section class="op-filter-bar compact op-featured-filter">
            <label class="op-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="featured-search" type="search" autocomplete="off" placeholder="Buscar por ID, marca o modelo">
            </label>
            <button class="op-secondary-button" id="featured-refresh" type="button" title="Actualizar lista">
                <i class="fa-solid fa-rotate"></i>
            </button>
        </section>

        <div class="op-table-wrap op-featured-table-wrap">
            <table class="op-table op-featured-table">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>ID</th>
                        <th>Vehículo</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Estatus</th>
                        <th>Visitas</th>
                        <th>Destacado</th>
                    </tr>
                </thead>
                <tbody id="featured-list">
                    <tr><td colspan="8"><div class="op-loading">Cargando autos destacados...</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="op-pagination" id="featured-pagination"></div>
    </section>
</section>

<dialog class="op-dialog wide" id="auto-dialog">
    <form class="op-dialog-card" id="auto-form" novalidate>
        <div class="op-dialog-header">
            <div><small>CATÁLOGO</small><h3 id="auto-dialog-title">Agregar auto</h3></div>
            <button class="op-dialog-close" type="button" data-close-dialog aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <input type="hidden" id="auto-id">
        <input type="hidden" id="auto-imagen">

        <div class="op-form-grid three">
            <label class="op-field"><span>Marca *</span><select id="auto-marca" required><option value="">Selecciona una marca</option></select></label>
            <label class="op-field span-2"><span>Modelo *</span><input id="auto-modelo" required maxlength="100"></label>
            <label class="op-field"><span>Tipo</span><select id="auto-tipo"><option value="">Sin especificar</option></select></label>
            <label class="op-field"><span>Año *</span><input id="auto-anio" type="number" min="1950" max="2100" required></label>
            <label class="op-field"><span>Estatus</span><select id="auto-estatus"><option>Disponible</option><option>Apartado</option><option>Vendido</option><option>Oculto</option></select><small id="auto-status-help" class="op-field-help"></small></label>
            <label class="op-field"><span>Precio *</span><input id="auto-precio" type="number" min="1" step="0.01" required></label>
            <label class="op-field"><span>Mensualidad</span><input id="auto-mensualidad" type="number" min="0" step="0.01"></label>
            <label class="op-field"><span>Kilometraje</span><input id="auto-kilometraje" type="number" min="0"></label>
            <label class="op-field"><span>Ubicación *</span><select id="auto-ubicacion" required><option value="">Selecciona una ubicación</option></select></label>
            <label class="op-field"><span>Transmisión *</span><select id="auto-transmision" required><option value="">Selecciona una transmisión</option></select></label>
            <label class="op-field"><span>Color</span><select id="auto-color"><option value="">Sin especificar</option></select></label>
            <label class="op-field"><span>Motor</span><select id="auto-motor"><option value="">Sin especificar</option></select></label>
            <label class="op-field"><span>Combustible</span><select id="auto-combustible"><option value="">Sin especificar</option></select></label>
            <label class="op-field"><span>Pasajeros</span><input id="auto-pasajeros" type="number" min="1" value="5"></label>
            <label class="op-field"><span>Tracción</span><select id="auto-traccion"><option value="">Sin especificar</option></select></label>
            <label class="op-field"><span>Dueños</span><input id="auto-duenos" type="number" min="1" value="1"></label>

            <section class="op-image-manager span-3" aria-labelledby="auto-images-title">
                <div class="op-image-manager-head">
                    <div>
                        <span class="op-field-label" id="auto-images-title">Imágenes del auto</span>
                        <small>JPG, PNG o WEBP. Máximo 12 imágenes y 8 MB por archivo. El servidor las comprime a máximo 1920×1440 px.</small>
                    </div>
                    <div>
                        <input id="auto-image-files" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
                        <button class="op-secondary-button" id="add-auto-images-button" type="button">
                            <i class="fa-solid fa-images"></i> Agregar imágenes
                        </button>
                    </div>
                </div>

                <div class="op-image-section" id="existing-image-section">
                    <div class="op-image-section-title">
                        <strong>Imágenes actuales</strong>
                        <span id="existing-image-count">0</span>
                    </div>
                    <div class="op-image-grid" id="auto-existing-images"></div>
                </div>

                <div class="op-image-section" id="new-image-section" hidden>
                    <div class="op-image-section-title">
                        <strong>Nuevas imágenes</strong>
                        <span id="new-image-count">0</span>
                    </div>
                    <div class="op-image-grid" id="auto-new-images"></div>
                </div>

                <div class="op-image-help">
                    <i class="fa-solid fa-star"></i>
                    Marca una imagen como principal. Las nuevas imágenes se comprimen antes de almacenarse en Catalogo/{ID_AUTO}/.
                </div>
            </section>
        </div>

        <div class="op-form-message" id="auto-message" hidden></div>
        <div class="op-dialog-actions">
            <button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button>
            <button class="op-primary-button" id="auto-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </div>
    </form>
</dialog>

<dialog class="op-dialog" id="catalog-approval-dialog">
    <form class="op-dialog-card" id="catalog-approval-form">
        <div class="op-dialog-header">
            <div><small>REQUERIMIENTO DE CATÁLOGO</small><h3 id="catalog-approval-title">Resolver publicación</h3></div>
            <button class="op-dialog-close" type="button" data-close-dialog aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <input type="hidden" id="catalog-approval-id">
        <input type="hidden" id="catalog-approval-decision-value">
        <p class="op-dialog-description" id="catalog-approval-description"></p>
        <label class="op-field"><span>Comentario</span><textarea id="catalog-approval-comment" rows="5" maxlength="500"></textarea></label>
        <div class="op-form-message" id="catalog-approval-message" hidden></div>
        <div class="op-dialog-actions">
            <button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button>
            <button class="op-primary-button" id="catalog-approval-submit" type="submit"><i class="fa-solid fa-check"></i> Confirmar</button>
        </div>
    </form>
</dialog>

<?php operativoPageEnd(['operativo-catalogo.js', 'operativo-destacados.js']); ?>
