<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApiEndpointsIntegrationTest extends TestCase
{
    private static $serverProcess = null;
    private static array $pipes = [];
    private static string $baseUrl = 'http://127.0.0.1:18080';

    public static function setUpBeforeClass(): void
    {
        $docRoot = realpath(__DIR__ . '/../../');
        if ($docRoot === false) {
            self::markTestSkipped('Could not resolve project root for integration server.');
        }

        $cmd = sprintf('%s -S 127.0.0.1:18080 -t %s', PHP_BINARY, escapeshellarg($docRoot));

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        self::$serverProcess = proc_open($cmd, $descriptorSpec, self::$pipes, $docRoot);

        if (!is_resource(self::$serverProcess)) {
            self::markTestSkipped('Could not start PHP built-in server for integration tests.');
        }

        $ready = false;
        for ($i = 0; $i < 20; $i++) {
            usleep(150000);
            $response = @file_get_contents(self::$baseUrl . '/api/diagnostic.php');
            if ($response !== false) {
                $ready = true;
                break;
            }
        }

        if (!$ready) {
            self::tearDownAfterClass();
            self::markTestSkipped('Integration server did not become ready in time.');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }

        foreach (self::$pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        self::$pipes = [];
        self::$serverProcess = null;
    }

    public function testDiagnosticEndpointReturnsHtmlPage(): void
    {
        [$statusCode, $body] = $this->request('GET', '/api/diagnostic.php');

        self::assertSame(200, $statusCode);
        self::assertNotEmpty($body);
        self::assertStringContainsString('BizLink CRM - Database Diagnostic', $body);
    }

    public function testGetProductsEndpointReturnsJsonEnvelope(): void
    {
        [$statusCode, $body] = $this->request('GET', '/api/get_products.php?page=1&per_page=5');

        self::assertContains($statusCode, [200, 500]);

        $decoded = json_decode($body, true);
        self::assertIsArray($decoded, 'Expected JSON response body from get_products endpoint.');
        self::assertArrayHasKey('success', $decoded);
        self::assertArrayHasKey('message', $decoded);
    }

    private function request(string $method, string $path): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $body = file_get_contents(self::$baseUrl . $path, false, $context);
        $statusCode = $this->extractStatusCode($http_response_header ?? []);

        return [$statusCode, $body === false ? '' : $body];
    }

    private function extractStatusCode(array $headers): int
    {
        if (empty($headers)) {
            return 0;
        }

        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches) === 1) {
            return (int)$matches[1];
        }

        return 0;
    }
}
