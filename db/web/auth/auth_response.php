<?php
declare(strict_types=1);

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function okResponse(array $data = [], string $message = 'Operación realizada correctamente.', int $status = 200): never
{
    jsonResponse([
        'ok' => true,
        'message' => $message,
        'data' => $data,
    ], $status);
}

function errorResponse(string $message, int $status = 400, string $code = 'REQUEST_ERROR', array $details = []): never
{
    $payload = [
        'ok' => false,
        'error' => $message,
        'code' => $code,
    ];

    if ($details !== []) {
        $payload['details'] = $details;
    }

    jsonResponse($payload, $status);
}
