<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Personas y jerarquía', 'personas');
?>
<section class="op-page-head">
    <div>
        <span class="op-kicker">EQUIPO OPERATIVO</span>
        <h2>Personas de la operación</h2>
        <p>Consulta tu estructura y administra usuarios de acuerdo con tu nivel jerárquico.</p>
    </div>
    <button class="op-primary-button" id="new-person-button" type="button" hidden>
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
                    <th>Cumpleaños</th>
                    <th>Último acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="person-table">
                <tr><td colspan="8">Cargando personas...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="op-pagination" id="person-pagination"></div>
</section>

<dialog class="op-dialog wide" id="person-dialog">
    <form class="op-dialog-card" id="person-form" novalidate>
        <div class="op-dialog-header">
            <div><small>NUEVA PERSONA</small><h3>Agregar usuario operativo</h3></div>
            <button class="op-dialog-close" type="button" data-close-dialog aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="op-form-grid three">
            <label class="op-field"><span>Nivel operativo *</span><select id="person-level" required></select></label>
            <label class="op-field span-2" id="person-supervisor-field"><span>Supervisor directo</span><select id="person-supervisor"></select><small class="op-field-help" id="person-supervisor-help"></small></label>
            <label class="op-field"><span>Nombre *</span><input id="person-name" required maxlength="100" autocomplete="off"></label>
            <label class="op-field"><span>Apellido paterno *</span><input id="person-lastname" required maxlength="100" autocomplete="off"></label>
            <label class="op-field"><span>Apellido materno</span><input id="person-second-lastname" maxlength="100" autocomplete="off"></label>
            <label class="op-field"><span>Username *</span><input id="person-username" required maxlength="80" autocomplete="off" placeholder="nombre.apellido"></label>
            <label class="op-field"><span>Correo *</span><input id="person-email" type="email" required maxlength="150" autocomplete="off"></label>
            <label class="op-field"><span>Teléfono</span><input id="person-phone" maxlength="20" autocomplete="off"></label>
            <label class="op-field"><span>Fecha de nacimiento</span><input id="person-birthdate" type="date"></label>
            <label class="op-field span-2"><span>Contraseña temporal *</span><div class="op-input-with-action"><input id="person-password" type="text" required minlength="10" maxlength="72" autocomplete="new-password"><button class="op-secondary-button" id="generate-password-button" type="button"><i class="fa-solid fa-wand-magic-sparkles"></i> Generar</button></div><small class="op-field-help">El usuario deberá cambiarla en su primer acceso.</small></label>
            <label class="op-check-card" id="supervisor-sells-field" hidden><input id="supervisor-also-sells" type="checkbox"><span><strong>También atiende ventas</strong><small>Además del rol AUTORIZADOR, tendrá VENTAS.</small></span></label>
        </div>
        <div class="op-form-message" id="person-message" hidden></div>
        <div class="op-created-summary" id="person-created-summary" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" id="person-save-button" type="submit"><i class="fa-solid fa-user-check"></i> Agregar persona</button></div>
    </form>
</dialog>

<dialog class="op-dialog wide" id="person-edit-dialog">
    <form class="op-dialog-card" id="person-edit-form" novalidate>
        <div class="op-dialog-header">
            <div><small>ADMINISTRACIÓN</small><h3>Editar usuario</h3></div>
            <button class="op-dialog-close" type="button" data-close-dialog aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <input type="hidden" id="edit-person-id">
        <div class="op-form-grid three">
            <label class="op-field"><span>Nivel operativo *</span><select id="edit-person-level" required></select></label>
            <label class="op-field span-2"><span>Supervisor directo</span><select id="edit-person-supervisor"></select><small class="op-field-help">Vendedores e inventario requieren supervisor para sus flujos de autorización.</small></label>
            <label class="op-field"><span>Nombre *</span><input id="edit-person-name" required maxlength="100"></label>
            <label class="op-field"><span>Apellido paterno *</span><input id="edit-person-lastname" required maxlength="100"></label>
            <label class="op-field"><span>Apellido materno</span><input id="edit-person-second-lastname" maxlength="100"></label>
            <label class="op-field"><span>Username *</span><input id="edit-person-username" required maxlength="80"></label>
            <label class="op-field"><span>Correo *</span><input id="edit-person-email" type="email" required maxlength="150"></label>
            <label class="op-field"><span>Teléfono</span><input id="edit-person-phone" maxlength="20"></label>
            <label class="op-field"><span>Fecha de nacimiento</span><input id="edit-person-birthdate" type="date"></label>
            <label class="op-field"><span>Estatus *</span><select id="edit-person-status" required><option>Activo</option><option>Inactivo</option><option>Bloqueado</option></select></label>
            <label class="op-check-card" id="edit-supervisor-sells-field" hidden><input id="edit-supervisor-also-sells" type="checkbox"><span><strong>También atiende ventas</strong><small>Mantiene AUTORIZADOR + VENTAS.</small></span></label>
        </div>
        <div class="op-form-message" id="person-edit-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" id="person-edit-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button></div>
    </form>
</dialog>

<dialog class="op-dialog" id="person-status-dialog">
    <form class="op-dialog-card" id="person-status-form">
        <div class="op-dialog-header"><div><small>GESTIÓN DE PERSONA</small><h3>Cambiar estatus</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <input type="hidden" id="status-person-id">
        <p class="op-dialog-description" id="status-person-name">—</p>
        <label class="op-field"><span>Estatus</span><select id="status-person-value" required><option>Activo</option><option>Inactivo</option><option>Bloqueado</option></select></label>
        <div class="op-form-message" id="person-status-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" type="submit">Actualizar estatus</button></div>
    </form>
</dialog>

<dialog class="op-dialog" id="person-password-dialog">
    <form class="op-dialog-card" id="person-password-form">
        <div class="op-dialog-header"><div><small>SEGURIDAD</small><h3>Restablecer contraseña</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <input type="hidden" id="password-person-id">
        <p class="op-dialog-description" id="password-person-name">—</p>
        <label class="op-field"><span>Contraseña temporal</span><div class="op-input-with-action"><input id="password-person-value" type="text" required minlength="10" maxlength="72"><button class="op-secondary-button" id="password-person-generate" type="button"><i class="fa-solid fa-wand-magic-sparkles"></i> Generar</button></div></label>
        <div class="op-form-message" id="person-password-message" hidden></div>
        <div class="op-created-summary" id="person-password-result" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cerrar</button><button class="op-primary-button" id="person-password-save" type="submit">Restablecer</button></div>
    </form>
</dialog>

<?php operativoPageEnd(['operativo-personas.js']); ?>
