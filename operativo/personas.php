<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Personas y jerarquía', 'personas');
?>
<section class="op-page-head">
    <div>
        <span class="op-kicker">EQUIPO OPERATIVO</span>
        <h2>Personas de la operación</h2>
        <p>Agrega vendedores y supervisores de acuerdo con tu nivel jerárquico.</p>
    </div>
    <button class="op-primary-button" id="new-person-button" type="button">
        <i class="fa-solid fa-user-plus"></i> Agregar persona
    </button>
</section>

<section class="op-filter-bar compact">
    <label class="op-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input id="person-search" placeholder="Buscar por nombre, usuario o correo">
    </label>
    <select id="person-status">
        <option value="">Todos los estatus</option>
        <option>Activo</option>
        <option>Inactivo</option>
        <option>Bloqueado</option>
    </select>
    <button class="op-secondary-button" id="person-refresh" type="button" title="Actualizar">
        <i class="fa-solid fa-rotate"></i>
    </button>
</section>

<section class="op-panel">
    <div class="op-panel-header">
        <div>
            <small>ESTRUCTURA ACTUAL</small>
            <h3>Equipo y supervisor directo</h3>
        </div>
        <span class="op-muted" id="person-count">—</span>
    </div>
    <div class="op-table-wrap">
        <table class="op-table op-person-table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Nivel</th>
                    <th>Roles</th>
                    <th>Supervisor</th>
                    <th>Estatus</th>
                    <th>Último acceso</th>
                </tr>
            </thead>
            <tbody id="person-table">
                <tr><td colspan="6">Cargando personas...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="op-pagination" id="person-pagination"></div>
</section>

<dialog class="op-dialog wide" id="person-dialog">
    <form class="op-dialog-card" id="person-form" novalidate>
        <div class="op-dialog-header">
            <div>
                <small>NUEVA PERSONA</small>
                <h3>Agregar al equipo operativo</h3>
            </div>
            <button class="op-dialog-close" type="button" data-close-dialog aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="op-hierarchy-notice" id="person-scope-notice">
            <i class="fa-solid fa-sitemap"></i>
            <div>
                <strong>Asignación jerárquica</strong>
                <span>La persona se agregará de acuerdo con los permisos de tu nivel.</span>
            </div>
        </div>

        <div class="op-form-grid three">
            <label class="op-field">
                <span>Nivel operativo *</span>
                <select id="person-level" required></select>
            </label>
            <label class="op-field span-2" id="person-supervisor-field">
                <span>Supervisor directo *</span>
                <select id="person-supervisor"></select>
                <small class="op-field-help" id="person-supervisor-help"></small>
            </label>

            <label class="op-field">
                <span>Nombre *</span>
                <input id="person-name" required maxlength="100" autocomplete="off">
            </label>
            <label class="op-field">
                <span>Apellido paterno *</span>
                <input id="person-lastname" required maxlength="100" autocomplete="off">
            </label>
            <label class="op-field">
                <span>Apellido materno</span>
                <input id="person-second-lastname" maxlength="100" autocomplete="off">
            </label>

            <label class="op-field">
                <span>Username *</span>
                <input id="person-username" required maxlength="80" autocomplete="off" placeholder="nombre.apellido">
            </label>
            <label class="op-field">
                <span>Correo *</span>
                <input id="person-email" type="email" required maxlength="150" autocomplete="off" placeholder="nombre@carprix.com.mx">
            </label>
            <label class="op-field">
                <span>Teléfono</span>
                <input id="person-phone" maxlength="20" autocomplete="off">
            </label>

            <label class="op-field span-2">
                <span>Contraseña temporal *</span>
                <div class="op-input-with-action">
                    <input id="person-password" type="text" required minlength="10" maxlength="72" autocomplete="new-password">
                    <button class="op-secondary-button" id="generate-password-button" type="button">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Generar
                    </button>
                </div>
                <small class="op-field-help">El usuario deberá cambiarla en su primer acceso.</small>
            </label>

            <label class="op-check-card" id="supervisor-sells-field" hidden>
                <input id="supervisor-also-sells" type="checkbox">
                <span>
                    <strong>También atiende ventas</strong>
                    <small>Además del rol AUTORIZADOR, tendrá el rol VENTAS.</small>
                </span>
            </label>
        </div>

        <div class="op-form-message" id="person-message" hidden></div>
        <div class="op-created-summary" id="person-created-summary" hidden></div>

        <div class="op-dialog-actions">
            <button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button>
            <button class="op-primary-button" id="person-save-button" type="submit">
                <i class="fa-solid fa-user-check"></i> Agregar persona
            </button>
        </div>
    </form>
</dialog>

<?php operativoPageEnd(['operativo-personas.js']); ?>
