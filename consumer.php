<?php
// Conectare la Redis
$redis = new Redis();

$redis->connect("127.0.0.1", 6379);

$stream = "dbapi_webhooks";
$group = "dbapi_webhooks_group";
$consumer = 'consumer-php-1';

// Creează consumer group dacă nu există deja
try {
    $redis->xGroup('CREATE', $stream, $group, '0');
} catch (RedisException $e) {
    if (strpos($e->getMessage(), 'BUSYGROUP') === false) {
        throw $e;
    }
    // Grupul există deja, ignorăm eroarea
}

echo "Consumer PHP pornit...\n";

// HTTP client basic (fără Guzzle, doar curl pentru simplitate)
function sendWebhook($url, $payload)
{
    $ch = curl_init($url['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $url['headers']);
    if($url['method'] == 'POST'){
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    if($url['method'] == 'GET'){
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        if ($payload && is_array($payload)) {
            $queryString = http_build_query($payload);
            $urlWithParams = $url['url'] . (strpos($url['url'], '?') === false ? '?' : '&') . $queryString;
            curl_setopt($ch, CURLOPT_URL, $urlWithParams);
        }
    }

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        throw new Exception("CURL error: $err");
    }
    if ($http_code >= 400) {
        throw new Exception("HTTP error code: $http_code");
    }

    return $response;
}

function log_message($message)
{
    file_put_contents("consumer.log", json_encode($message) . "\n", FILE_APPEND);
}

// Loop infinit
while (true) {
    try {
        $messages = $redis->xReadGroup(
            $group,
            $consumer,
            [$stream => '>'],
            10,
            5000 // timeout 5 secunde
        );

        if ($messages && isset($messages[$stream])) {
            foreach ($messages[$stream] as $messageId => $fields) {
                print_r($messages);
                $callbackUrl = json_decode($fields['callback_url'], true);
                
                
                $payload = json_decode($fields['payload'], true);

                try {
                    $result = sendWebhook($callbackUrl, $payload);
                    echo "Webhook trimis către {$fields['callback_url']}\n";
                    log_message(["payload"=>$fields['payload'],"result"=>$result]);
                    $redis->xAck($stream, $group, [$messageId]);
                } catch (Exception $e) {
                    echo "Eroare la trimiterea webhook-ului: " . $e->getMessage() . "\n";
                    // opțional: retry logic sau dead-letter
                }
            }
        }
    } catch (RedisException $e) {
        echo "Redis error: " . $e->getMessage() . "\n";
        sleep(1);
    }
}
