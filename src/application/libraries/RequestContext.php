<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Per-request correlation id for logging and error responses.
 */
class RequestContext
{
    private static ?string $requestId = null;

    /**
     * Initialize from incoming X-Request-Id or generate a new id.
     */
    public static function init(?string $fromHeader = null): string
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }

        $incoming = $fromHeader;
        if ($incoming === null) {
            $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
        }
        if (is_string($incoming) && preg_match('/^[a-zA-Z0-9._\-]{8,128}$/', $incoming)) {
            self::$requestId = $incoming;
        } else {
            self::$requestId = bin2hex(random_bytes(16));
        }

        return self::$requestId;
    }

    public static function id(): ?string
    {
        return self::$requestId;
    }

    public static function reset(): void
    {
        self::$requestId = null;
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        if (self::$requestId !== null) {
            $context['request_id'] = self::$requestId;
        }
        if (function_exists('log_message')) {
            $suffix = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
            log_message($level, $message . $suffix);
        }
    }
}
