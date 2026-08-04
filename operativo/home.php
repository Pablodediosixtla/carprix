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
            <a href="requerimientos.php" class="op-text-link">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div id="latest-requirements" class="op-list"><div class="op-loading">Cargando...</div></div>
    </article>
    <article class="op-panel">
        <div class="op-panel-header"><div><small>SESIÓN</small><h3>Tu perfil operativo</h3></div></div>
        <dl class="op-profile-details">
            <div><dt>Usuario</dt><dd id="profile-username">—</dd></div>
            <div><dt>Correo</dt><dd id="profile-email">—</dd></div>
            <div><dt>Último acceso</dt><dd id="profile-last-login">—</dd></div>
            <div><dt>Roles activos</dt><dd id="profile-roles">—</dd></div>
        </dl>
        <div class="op-quick-links">
            <a href="personas.php" data-required-roles="SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR"><i class="fa-solid fa-users-gear"></i><span>Agregar personas</span></a>
            <a href="catalogo.php"><i class="fa-solid fa-car-side"></i><span>Gestionar catálogo</span></a>
            <a href="requerimientos.php"><i class="fa-solid fa-file-circle-plus"></i><span>Nuevo requerimiento</span></a>
            <a href="autorizaciones.php" data-required-roles="SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR"><i class="fa-solid fa-circle-check"></i><span>Revisar autorizaciones</span></a>
        </div>
    </article>
</section>
<?php operativoPageEnd(['operativo-home.js']); ?>
