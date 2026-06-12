<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Deployment mode helpers (single-database Docker vs multi-API hosting).
 */
function deployment_mode(): string
{
    $raw = strtolower((string) (getenv('DEPLOYMENT_MODE') ?: 'multi'));
    return $raw === 'single' ? 'single' : 'multi';
}

function is_single_deployment_mode(): bool
{
    return deployment_mode() === 'single';
}

function default_api_id(): string
{
    $fromEnv = getenv('DEFAULT_API_ID');
    return ($fromEnv !== false && $fromEnv !== '') ? $fromEnv : 'default';
}

/**
 * Database connection parameters from Docker env vars (single-mode bootstrap).
 *
 * @return array<string,mixed>|null
 */
function single_mode_connection_from_env(): ?array
{
    $host = getenv('DB_HOST');
    $database = getenv('DB_NAME');
    if ($host === false || $host === '' || $database === false || $database === '') {
        return null;
    }

    return [
        'driver' => 'mysql',
        'host' => $host,
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'username' => getenv('DB_USER') !== false ? getenv('DB_USER') : '',
        'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        'database' => $database,
    ];
}
