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
 * Public base URL for links and OpenAPI servers (no trailing slash).
 *
 * Prefers BASE_URL env, then the incoming HTTP Host (incl. X-Forwarded-*), then CI base_url.
 */
function api_public_base_url($config = null): string
{
    $fromEnv = getenv('BASE_URL');
    if ($fromEnv !== false && $fromEnv !== '') {
        return rtrim($fromEnv, '/');
    }

    if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
        }
        $scheme = 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
            $scheme = (string) $_SERVER['REQUEST_SCHEME'];
        }
        return $scheme . '://' . $host;
    }

    if ($config === null && function_exists('get_instance')) {
        $ci = get_instance();
        $config = $ci->config ?? null;
    }
    if ($config !== null) {
        $configured = $config->item('base_url');
        if ($configured !== null && $configured !== '') {
            return rtrim($configured, '/');
        }
    }

    return 'http://localhost';
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
