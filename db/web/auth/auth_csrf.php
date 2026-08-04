<?php
declare(strict_types=1);

function csrfToken(): string
{
    startOperativoSession();

    if (empty($_SESSION[OPERATIVO_CSRF_KEY])) {
        $_SESSION[OPERATIVO_CSRF_KEY] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION[OPERATIVO_CSRF_KEY];
}

function rotateCsrfToken(): string
{
    startOperativoSession();
    $_SESSION[OPERATIVO_CSRF_KEY] = bin2hex(random_bytes(32));
    return (string) $_SESSION[OPERATIVO_CSRF_KEY];
}

function validateCsrfToken(array $input): void
{
    startOperativoSession();

    $provided = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '')));
    $expected = (string) ($_SESSION[OPERATIVO_CSRF_KEY] ?? '');

    if ($provided === '' || $expected === '' || !hash_equals($expected, $provided)) {
        errorResponse('Token CSRF inválido o ausente.', 419, 'CSRF_INVALID');
    }
}
