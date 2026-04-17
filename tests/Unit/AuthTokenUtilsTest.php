<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthTokenUtilsTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME'], $_SERVER['HTTPS']);
    }

    public function testGenerateSecureTokenHasExpectedHexLength(): void
    {
        $token = generateSecureToken(16);

        self::assertSame(32, strlen($token));
        self::assertSame(1, preg_match('/^[a-f0-9]+$/', $token));
    }

    public function testHashAuthTokenIsDeterministicSha256(): void
    {
        $hash = hashAuthToken('abc123');

        self::assertSame(hash('sha256', 'abc123'), $hash);
        self::assertSame(64, strlen($hash));
    }

    public function testIsLocalHostEnvironmentReturnsTrueForLocalhost(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost:8080';

        self::assertTrue(isLocalHostEnvironment());
    }

    public function testBuildPublicUrlUsesHttpAndQuery(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        unset($_SERVER['HTTPS']);

        $url = buildPublicUrl('api/get_orders.php', ['page' => 2, 'per_page' => 20]);

        self::assertSame('http://example.test/api/get_orders.php?page=2&per_page=20', $url);
    }

    public function testBuildPublicUrlUsesHttpsWhenEnabled(): void
    {
        $_SERVER['HTTP_HOST'] = 'secure.example.test';
        $_SERVER['HTTPS'] = 'on';

        $url = buildPublicUrl('/pages/home.html');

        self::assertSame('https://secure.example.test/pages/home.html', $url);
    }
}
