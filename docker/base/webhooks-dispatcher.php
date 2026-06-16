<?php
/**
 * Reads webhook jobs from a Redis stream and delivers HTTP callbacks.
 * Message fields match publishWebhookEvent() in DbapiMiscTrait.
 */

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    return $default;
}

$redisHost = env('REDIS_HOST', 'redis');
$redisPort = (int) env('REDIS_PORT', '6379');
$redisUser = env('REDIS_USER');
$redisPassword = env('REDIS_PASSWORD');
$stream = env('REDIS_STREAM', 'dbapi_webhooks');
$group = env('REDIS_GROUP', 'dbapi_webhooks_group');
$consumer = env('WEBHOOKS_CONSUMER_NAME', gethostname() ?: 'webhooks-dispatcher');

$redis = new Redis();
$redis->connect($redisHost, $redisPort);
if ($redisUser && $redisPassword) {
    $redis->auth($redisUser, $redisPassword);
} elseif ($redisPassword) {
    $redis->auth($redisPassword);
}

try {
    $redis->xGroup('CREATE', $stream, $group, '0');
} catch (RedisException $e) {
    if (strpos($e->getMessage(), 'BUSYGROUP') === false) {
        throw $e;
    }
}

fwrite(STDOUT, "Webhook dispatcher started (stream={$stream}, group={$group}, consumer={$consumer})\n");

function curlHeaders(array $headers): array
{
    $out = [];
    foreach ($headers as $name => $value) {
        $out[] = "{$name}: {$value}";
    }
    return $out;
}

function sendWebhook(string $url, string $method, array $headers, string $payload): string
{
    $method = strtoupper($method);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, curlHeaders($headers));
    }

    switch ($method) {
        case 'GET':
            if ($payload !== '') {
                $decoded = json_decode($payload, true);
                if (is_array($decoded)) {
                    $sep = strpos($url, '?') === false ? '?' : '&';
                    $url .= $sep . http_build_query($decoded);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
            }
            break;
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            break;
        case 'PUT':
        case 'PATCH':
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            break;
        default:
            throw new InvalidArgumentException("Unsupported method: {$method}");
    }

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("CURL error: {$err}");
    }
    if ($httpCode >= 400) {
        throw new RuntimeException("HTTP {$httpCode}");
    }

    return is_string($response) ? $response : '';
}

while (true) {
    try {
        $messages = $redis->xReadGroup($group, $consumer, [$stream => '>'], 10, 5000);
        if (!$messages || !isset($messages[$stream])) {
            continue;
        }

        foreach ($messages[$stream] as $messageId => $fields) {
            $url = $fields['callback_url'] ?? '';
            $method = $fields['method'] ?? 'POST';
            $headers = json_decode($fields['headers'] ?? '{}', true);
            $payload = $fields['payload'] ?? '';

            if ($url === '') {
                fwrite(STDERR, "Skipping {$messageId}: missing callback_url\n");
                $redis->xAck($stream, $group, [$messageId]);
                continue;
            }
            if (!is_array($headers)) {
                $headers = [];
            }

            try {
                sendWebhook($url, $method, $headers, $payload);
                fwrite(STDOUT, "Delivered webhook to {$url}\n");
                $redis->xAck($stream, $group, [$messageId]);
            } catch (Throwable $e) {
                fwrite(STDERR, "Webhook failed for {$url}: {$e->getMessage()}\n");
            }
        }
    } catch (RedisException $e) {
        fwrite(STDERR, "Redis error: {$e->getMessage()}\n");
        sleep(1);
    }
}
