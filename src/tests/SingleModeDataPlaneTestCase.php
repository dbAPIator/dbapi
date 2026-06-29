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
        if (!self::probeManagementApi(self::$client)) {
            self::markTestSkipped(self::managementApiSkipMessage());
        }
        self::ensureDefaultApiSchema();
        self::$serverReady = self::probeDataPlane();
        if (!self::$serverReady) {
            self::markTestSkipped(
                'Single-mode data plane not reachable at ' . self::baseUri()
                . ' — start Docker (docker compose up -d), copy tests/test.env.single.example to tests/test.env,'
                . ' and reset MySQL volume if schema changed.'
            );
        }
    }

    protected static function ensureDefaultApiSchema(): void
    {
        try {
            $resp = self::$client->post('mgmt/v1/schema:rebuild', [
                'headers' => self::managementHeaders(),
                'http_errors' => false,
            ]);
            if ($resp->getStatusCode() >= 400) {
                return;
            }
            self::$client->post('mgmt/v1:activate', [
                'headers' => self::managementHeaders(),
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            // Not single-mode or transient error; probeDataPlane will skip.
        }
    }

    protected static function probeDataPlane(): bool
    {
        return self::probeDataPlaneFilterCasesViaHttp(self::$client, 'v1/data/filter_cases');
    }

    protected function dataUrl(
        string $resource,
        ?string $id = null,
        ?string $relation = null,
        ?string $relId = null,
        ?string $subRelation = null,
        ?string $subRelId = null
    ): string {
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
        if ($subRelation !== null) {
            $path .= '/' . $subRelation;
        }
        if ($subRelId !== null) {
            $path .= '/' . $subRelId;
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
