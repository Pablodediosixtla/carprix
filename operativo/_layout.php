<?php
declare(strict_types=1);

function operativoPageStart(string $title, string $page): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safePage = htmlspecialchars($page, ENT_QUOTES, 'UTF-8');
    $items = [
        ['home', 'home.php', 'fa-house', 'Inicio', ''],
        ['dashboard', 'dashboard.php', 'fa-chart-column', 'Dashboard', 'SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR,RH'],
        ['metas', 'metas.php', 'fa-bullseye', 'Metas', 'SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR'],
        ['tareas', 'tareas.php', 'fa-list-check', 'Tareas', ''],
        ['recompensas', 'recompensas.php', 'fa-award', 'Recompensas', ''],
        ['gestion_recompensas', 'gestion_recompensas.php', 'fa-gift', 'Gestión recompensas', 'SUPER_ADMIN,ADMIN_OPERATIVO,RH'],
        ['personas', 'personas.php', 'fa-users-gear', 'Personas', 'SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR,RH'],
        ['catalogo', 'catalogo.php', 'fa-car-side', 'Gestión de catálogo', 'SUPER_ADMIN,ADMIN_OPERATIVO,INVENTARIO'],
        ['requerimientos', 'requerimientos.php', 'fa-file-circle-plus', 'Requerimientos', 'SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR,INVENTARIO,VENTAS'],
        ['autorizaciones', 'autorizaciones.php', 'fa-circle-check', 'Autorizaciones', 'SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR'],
        ['jerarquia', 'jerarquia.php', 'fa-sitemap', 'Jerarquía', 'SUPER_ADMIN,ADMIN_OPERATIVO,AUTORIZADOR,INVENTARIO,VENTAS,RH'],
    ];

    echo <<<HTML
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111111">
    <title>{$safeTitle} | CARPRIX Operativo</title>
    <link rel="icon" href="../img/favicon.ico">
    <link rel="apple-touch-icon" href="../img/favicon-180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/operativo.css?v=20260904-1">
</head>
<body class="op-body" data-page="{$safePage}">
    <div class="op-shell">
        <aside class="op-sidebar" id="op-sidebar" aria-label="Navegación operativa">
            <a class="op-brand" href="home.php" aria-label="CARPRIX Operativo">
                <img src="../img/brand/logo-carprix-dark.svg" alt="CARPRIX">
                <span>OPERATIVO</span>
            </a>
            <nav class="op-nav">
HTML;

    foreach ($items as [$id, $href, $icon, $label, $roles]) {
        $active = $id === $page ? ' active' : '';
        $roleAttr = $roles !== '' ? ' data-required-roles="' . htmlspecialchars($roles, ENT_QUOTES, 'UTF-8') . '"' : '';
        echo '<a class="op-nav-link' . $active . '" href="' . $href . '"' . $roleAttr . '>';
        echo '<i class="fa-solid ' . $icon . '"></i><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        if ($id === 'autorizaciones') {
            echo '<b class="op-nav-badge" id="nav-approval-count" hidden>0</b>';
        }
        echo '</a>';
    }

    echo <<<HTML
            </nav>
            <div class="op-sidebar-user">
                <div class="op-avatar js-user-initials">CP</div>
                <div>
                    <strong class="js-user-name">Cargando...</strong>
                    <small class="js-user-role">Sesión operativa</small>
                </div>
            </div>
        </aside>

        <div class="op-main-wrap">
            <header class="op-topbar">
                <button class="op-icon-button op-menu-button" id="op-menu-button" aria-label="Abrir menú">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="op-topbar-title">
                    <small>CARPRIX / Operación</small>
                    <h1>{$safeTitle}</h1>
                </div>
                <div class="op-topbar-actions">
                    <a class="op-icon-button op-client-link" href="../index.php" title="Ir a la vista cliente" aria-label="Ir a la vista cliente">
                        <i class="fa-solid fa-store"></i>
                    </a>
                    <button class="op-user-button" id="op-user-button" type="button">
                        <span class="op-avatar small js-user-initials">CP</span>
                        <span class="op-user-button-copy">
                            <strong class="js-user-name">Cargando...</strong>
                            <small class="js-user-role">Usuario</small>
                        </span>
                    </button>
                    <button class="op-icon-button" id="op-logout-button" type="button" title="Cerrar sesión" aria-label="Cerrar sesión">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </button>
                </div>
            </header>
            <main class="op-content">
HTML;
}

function operativoPageEnd(array $scripts = []): void
{
    echo '</main></div></div><div class="op-sidebar-overlay" id="op-sidebar-overlay"></div>';
    echo '<div class="op-toast-zone" id="op-toast-zone" aria-live="polite"></div>';
    echo '<script src="../js/operativo-common.js?v=20260904-1"></script>';
    foreach ($scripts as $script) {
        echo '<script src="../js/' . htmlspecialchars($script, ENT_QUOTES, 'UTF-8') . '?v=20260904-1"></script>';
    }
    echo '</body></html>';
}
