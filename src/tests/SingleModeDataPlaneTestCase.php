<?php

use GuzzleHttp\Client;

/**
 * Data-plane tests against Docker single-mode deployment (/v1/data/...).
 *
 * Requires a running instance with DEPLOYMENT_MODE=single and the full test
 * schema loaded (src/tests/dataplane-schema-body.sql via docker/mysql-init/).
 *
 * @see docs/data_plane_test_plan.md
 */
abstract class SingleModeDataPlaneTestCase extends IntegrationTestCase
{
    protected static Client $client;
    protected static bool $serverReady = false;

    public static function setUpBeforeClass(): void
    {
        self::$client = self::createHttpClient();
        self::ensureDefaultApiSchema();
        self::$serverReady = self::probeDataPlane();
        if (!self::$serverReady) {
            self::markTestSkipped(
                'Single-mode data plane not reachable at ' . self::baseUri()
                . ' — start Docker (docker compose up -d) and reset MySQL volume if schema changed.'
            );
        }
    }

    protected static function ensureDefaultApiSchema(): void
    {
        $apiId = getenv('DEFAULT_API_ID') ?: 'default';
        $resp = self::$client->post('mgmt/v1/apis/' . $apiId . '/schema:rebuild', [
            'headers' => self::managementHeaders(),
            'http_errors' => false,
        ]);
        if ($resp->getStatusCode() >= 400) {
            return;
        }
        self::$client->post('mgmt/v1/apis/' . $apiId . ':activate', [
            'headers' => self::managementHeaders(),
            'http_errors' => false,
        ]);
    }

    protected static function probeDataPlane(): bool
    {
        return self::probeDataPlaneFilterCasesViaHttp(self::$client, 'v1/data/filter_cases');
    }

    protected function dataUrl(string $resource, ?string $id = null, ?string $relation = null, ?string $relId = null): string
    {
        $path = 'v1/data/' . $resource;
        if ($id !== null) {
            $path .= '/' . $id;
        }
        if ($relation !== null) {
            $path .= '/' . $relation;
        }
        if ($relId !== null) {
            $path .= '/' . $relId;
        }
        return $path;
    }

    protected function dataRequest(string $method, string $uri, array $options = [])
    {
        $options['headers'] = array_merge(
            ['Accept' => 'application/vnd.api+json'],
            $options['headers'] ?? []
        );
        return self::$client->request($method, $uri, array_merge($options, ['http_errors' => false]));
    }

    protected function uniqueEmail(string $prefix = 'test'): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4)) . '@single.test';
    }
}
