<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Centro operativo', 'home');
?>
<section class="op-welcome-card">
    <div>
        <span class="op-kicker">BIENVENIDO</span>
        <h2 id="welcome-name">Cargando tu información...</h2>
        <p>Consulta la operación comercial y atiende tus tareas prioritarias.</p>
    </div>
    <div class="op-welcome-profile">
        <div class="op-avatar xl js-user-initials">CP</div>
        <div>
            <strong class="js-user-name">Usuario</strong>
            <span id="home-user-email">—</span>
            <div class="op-role-list" id="home-user-roles"></div>
        </div>
    </div>
</section>

<section class="op-metric-grid" id="dashboard-metrics">
    <article class="op-metric-card"><span class="op-metric-icon green"><i class="fa-solid fa-car"></i></span><div><small>Autos en catálogo</small><strong data-metric="catalogo_total">—</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon blue"><i class="fa-solid fa-circle-check"></i></span><div><small>Disponibles</small><strong data-metric="catalogo_disponibles">—</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon amber"><i class="fa-solid fa-file-circle-plus"></i></span><div><small>Solicitados</small><strong data-metric="requerimientos_solicitados">—</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon purple"><i class="fa-solid fa-handshake"></i></span><div><small>Apartados</small><strong data-metric="requerimientos_apartados">—</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon dark"><i class="fa-solid fa-tag"></i></span><div><small>Vendidos</small><strong data-metric="requerimientos_vendidos">—</strong></div></article>
    <article class="op-metric-card"><span class="op-metric-icon red"><i class="fa-solid fa-stamp"></i></span><div><small>Por autorizar</small><strong data-metric="autorizaciones_pendientes">—</strong></div></article>
</section>

<section class="op-grid-two">
    <article class="op-panel">
        <div class="op-panel-header">
            <div><small>ACTIVIDAD RECIENTE</small><h3>Últimos requerimientos</h3></div>
            <a href="requerimientos.php" class="op-text-link" data-required-roles="SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR,INVENTARIO,VENTAS">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div id="latest-requirements" class="op-list"><div class="op-loading">Cargando...</div></div>
    </article>
    <article class="op-panel">
        <div class="op-panel-header"><div><small>SESIÓN</small><h3>Tu perfil operativo</h3></div></div>
        <dl class="op-profile-details">
            <div><dt>Usuario</dt><dd id="profile-username">—</dd></div>
            <div><dt>Correo</dt><dd id="profile-email">—</dd></div>
            <div><dt>Teléfono</dt><dd id="profile-phone">—</dd></div>
            <div><dt>Último acceso</dt><dd id="profile-last-login">—</dd></div>
            <div><dt>Roles activos</dt><dd id="profile-roles">—</dd></div>
        </dl>
        <div class="op-profile-actions">
            <button class="op-secondary-button" id="edit-my-profile" type="button"><i class="fa-solid fa-address-card"></i> Actualizar correo / teléfono</button>
            <button class="op-secondary-button" id="change-my-password" type="button"><i class="fa-solid fa-key"></i> Cambiar contraseña</button>
        </div>
        <button class="op-birthday-summary" id="birthday-summary-button" type="button">
            <span class="op-birthday-icon"><i class="fa-solid fa-cake-candles"></i></span>
            <span><small>CUMPLEAÑOS DEL MES</small><strong><b id="birthday-count">—</b> personas</strong><em id="birthday-month-label">Ver calendario</em></span>
            <i class="fa-solid fa-chevron-right"></i>
        </button>
        <div class="op-quick-links">
            <a href="tareas.php"><i class="fa-solid fa-list-check"></i><span>Gestionar tareas</span></a>
            <a href="personas.php" data-required-roles="SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR,RH"><i class="fa-solid fa-users-gear"></i><span>Personas</span></a>
            <a href="catalogo.php" data-required-roles="SUPER_ADMIN,ADMIN_OPERATIVO,INVENTARIO"><i class="fa-solid fa-car-side"></i><span>Gestionar catálogo</span></a>
            <a href="requerimientos.php" data-required-roles="SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR,INVENTARIO,VENTAS"><i class="fa-solid fa-file-circle-plus"></i><span>Requerimientos</span></a>
            <a href="autorizaciones.php" data-required-roles="SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR"><i class="fa-solid fa-circle-check"></i><span>Revisar autorizaciones</span></a>
            <a href="jerarquia.php" data-required-roles="SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR,INVENTARIO,VENTAS,RH"><i class="fa-solid fa-sitemap"></i><span>Jerarquía</span></a>
        </div>
    </article>
</section>

<dialog class="op-dialog" id="my-profile-dialog">
    <form class="op-dialog-card" id="my-profile-form">
        <div class="op-dialog-header"><div><small>MI CUENTA</small><h3>Actualizar información</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <p class="op-dialog-description">Puedes actualizar únicamente tu correo electrónico y teléfono.</p>
        <label class="op-field"><span>Correo electrónico *</span><input id="my-profile-email" type="email" required maxlength="150"></label>
        <label class="op-field"><span>Teléfono</span><input id="my-profile-phone" maxlength="20"></label>
        <div class="op-form-message" id="my-profile-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button></div>
    </form>
</dialog>

<dialog class="op-dialog" id="my-password-dialog">
    <form class="op-dialog-card" id="my-password-form">
        <div class="op-dialog-header"><div><small>SEGURIDAD</small><h3>Cambiar contraseña</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <label class="op-field"><span>Contraseña actual *</span><input id="my-password-current" type="password" required></label>
        <label class="op-field"><span>Nueva contraseña *</span><input id="my-password-new" type="password" required minlength="10" maxlength="72"></label>
        <label class="op-field"><span>Confirmar nueva contraseña *</span><input id="my-password-confirm" type="password" required minlength="10" maxlength="72"></label>
        <small class="op-field-help">Mínimo 10 caracteres con mayúscula, minúscula, número y carácter especial.</small>
        <div class="op-form-message" id="my-password-message" hidden></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cancelar</button><button class="op-primary-button" type="submit"><i class="fa-solid fa-key"></i> Cambiar contraseña</button></div>
    </form>
</dialog>

<dialog class="op-dialog wide" id="birthday-dialog">
    <div class="op-dialog-card">
        <div class="op-dialog-header"><div><small>EQUIPO CARPRIX</small><h3 id="birthday-dialog-title">Cumpleaños del mes</h3></div><button class="op-dialog-close" type="button" data-close-dialog><i class="fa-solid fa-xmark"></i></button></div>
        <div class="op-birthday-list" id="birthday-list"><div class="op-loading">Cargando...</div></div>
        <div class="op-dialog-actions"><button class="op-secondary-button" type="button" data-close-dialog>Cerrar</button></div>
    </div>
</dialog>

<?php operativoPageEnd(['operativo-home.js']); ?>
