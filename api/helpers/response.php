<?php
declare(strict_types=1);

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function successResponse(array $data = [], string $message = 'OK', int $status = 200): void
{
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data,
    ], $status);
}

function errorResponse(string $message, int $status = 400, array $errors = []): void
{
    $payload = [
        'success' => false,
        'message' => $message,
    ];

    if ($errors !== []) {
        $payload['errors'] = $errors;
    }

    jsonResponse($payload, $status);
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        errorResponse('Invalid JSON body.', 400);
    }

    return $data;
}
