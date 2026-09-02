<?php

use GuzzleHttp\Client;

/**
 * Liveness probe GET /health.
 */
class TestHealth extends IntegrationTestCase
{
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createHttpClient();
        try {
            $resp = $this->httpRequest($this->client, 'GET', 'health', [
                'connect_timeout' => 2.0,
                'timeout' => 5.0,
            ]);
            if ($resp->getStatusCode() === 0) {
                $this->markTestSkipped(self::managementApiSkipMessage());
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped(self::managementApiSkipMessage());
        }
    }

    public function testHealthReturnsOkWithoutAuth(): void
    {
        $resp = $this->httpRequest($this->client, 'GET', 'health');
        $body = $this->assertHttpStatus($resp, 200, 'health');
        $this->assertSame('ok', $body['status'] ?? null);
        $this->assertSame('dbAPI', $body['service'] ?? null);
    }
}
