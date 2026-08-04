<?php
declare(strict_types=1);

const OPERATIVO_SESSION_KEY = 'operativo_user';
const OPERATIVO_CSRF_KEY = 'operativo_csrf_token';
const OPERATIVO_LAST_ACTIVITY_KEY = 'operativo_last_activity';

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function startOperativoSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('carprix_operativo');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function currentSessionUser(): ?array
{
    startOperativoSession();
    $user = $_SESSION[OPERATIVO_SESSION_KEY] ?? null;
    return is_array($user) ? $user : null;
}

function setSessionUser(array $user): void
{
    startOperativoSession();
    $_SESSION[OPERATIVO_SESSION_KEY] = $user;
    $_SESSION[OPERATIVO_LAST_ACTIVITY_KEY] = time();
}

function destroyOperativoSession(): void
{
    startOperativoSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}

function enforceSessionIdleTimeout(): void
{
    startOperativoSession();

    $idleSeconds = (int) (getenv('CARPRIX_SESSION_IDLE_SECONDS') ?: 7200);
    $lastActivity = (int) ($_SESSION[OPERATIVO_LAST_ACTIVITY_KEY] ?? 0);

    if ($lastActivity > 0 && (time() - $lastActivity) > $idleSeconds) {
        destroyOperativoSession();
        errorResponse('La sesión expiró por inactividad.', 401, 'SESSION_EXPIRED');
    }

    if (isset($_SESSION[OPERATIVO_SESSION_KEY])) {
        $_SESSION[OPERATIVO_LAST_ACTIVITY_KEY] = time();
    }
}
