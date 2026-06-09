<?php
declare(strict_types=1);

function cleanString(mixed $value): string
{
    return trim((string) $value);
}

function validateRequiredFields(array $data, array $fields): array
{
    $errors = [];

    foreach ($fields as $field) {
        if (!array_key_exists($field, $data) || cleanString($data[$field]) === '') {
            $errors[$field] = 'Field wajib diisi.';
        }
    }

    return $errors;
}

function validateMaxLength(array $data, string $field, int $max): array
{
    if (!array_key_exists($field, $data) || $data[$field] === null) {
        return [];
    }

    return strlen(cleanString($data[$field])) > $max
        ? [$field => "Maksimal {$max} karakter."]
        : [];
}

function validateEnumValue(array $data, string $field, array $allowed): array
{
    if (!array_key_exists($field, $data)) {
        return [];
    }

    return in_array(cleanString($data[$field]), $allowed, true)
        ? []
        : [$field => 'Nilai tidak valid.'];
}

function validateDateYmd(array $data, string $field): array
{
    if (!array_key_exists($field, $data)) {
        return [];
    }

    $value = cleanString($data[$field]);
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value
        ? []
        : [$field => 'Tanggal harus format YYYY-MM-DD.'];
}

function validatePositiveNumber(array $data, string $field): array
{
    if (!array_key_exists($field, $data)) {
        return [];
    }

    return is_numeric($data[$field]) && (float) $data[$field] > 0
        ? []
        : [$field => 'Nilai harus angka positif.'];
}

function validateIntId(array $data, string $field, bool $required = true): array
{
    if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
        return $required ? [$field => 'Field wajib diisi.'] : [];
    }

    return filter_var($data[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false
        ? []
        : [$field => 'ID harus berupa angka positif.'];
}

function mergeErrors(array ...$errorGroups): array
{
    return array_merge(...$errorGroups);
}

function escapeHtml(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
