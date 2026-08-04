<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111111">
    <title>Acceso operativo | CARPRIX</title>
    <link rel="icon" href="../img/favicon.ico">
    <link rel="apple-touch-icon" href="../img/favicon-180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/operativo.css?v=20260803-1">
</head>
<body class="op-login-body">
    <main class="op-login-shell">
        <section class="op-login-brand-panel">
            <img src="../img/brand/logo-carprix-dark.svg" alt="CARPRIX" class="op-login-logo">
            <div class="op-login-brand-copy">
                <span class="op-kicker">PLATAFORMA OPERATIVA</span>
                <h1>Control comercial con trazabilidad y autorización.</h1>
                <p>Gestiona inventario, requerimientos de clientes y decisiones de estatus en un solo lugar.</p>
            </div>
            <div class="op-login-features">
                <div><i class="fa-solid fa-car-side"></i><span>Catálogo controlado</span></div>
                <div><i class="fa-solid fa-file-signature"></i><span>Requerimientos comerciales</span></div>
                <div><i class="fa-solid fa-shield-halved"></i><span>Autorización jerárquica</span></div>
            </div>
        </section>

        <section class="op-login-form-panel">
            <div class="op-login-card">
                <div class="op-login-card-header">
                    <span class="op-icon-mark"><i class="fa-solid fa-lock"></i></span>
                    <div>
                        <small>ACCESO SEGURO</small>
                        <h2>Iniciar sesión</h2>
                    </div>
                </div>
                <p class="op-muted">Usa tu usuario operativo o correo corporativo.</p>
                <form id="login-form" novalidate>
                    <label class="op-field">
                        <span>Usuario o correo</span>
                        <div class="op-input-icon">
                            <i class="fa-regular fa-user"></i>
                            <input id="login" name="login" autocomplete="username" required maxlength="150" placeholder="admin.carprix">
                        </div>
                    </label>
                    <label class="op-field">
                        <span>Contraseña</span>
                        <div class="op-input-icon">
                            <i class="fa-solid fa-key"></i>
                            <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••••••">
                            <button class="op-password-toggle" type="button" data-password-target="password" aria-label="Mostrar contraseña">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </label>
                    <div class="op-form-message" id="login-message" hidden></div>
                    <button class="op-primary-button full" id="login-button" type="submit">
                        <span>Entrar a la operación</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
                <p class="op-login-help"><i class="fa-solid fa-circle-info"></i> Si tu cuenta está bloqueada, contacta al administrador operativo.</p>
            </div>
        </section>
    </main>
    <div class="op-toast-zone" id="op-toast-zone" aria-live="polite"></div>
    <script src="../js/operativo-common.js?v=20260803-1"></script>
    <script src="../js/operativo-login.js?v=20260803-1"></script>
</body>
</html>
