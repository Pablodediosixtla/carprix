<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(true);
destroyOperativoSession();
okResponse([], 'Sesión cerrada correctamente.');
