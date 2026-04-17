<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApiHelpersTest extends TestCase
{
    public function testValidateEmailStrictAcceptsValidEmail(): void
    {
        self::assertTrue(validateEmailStrict('user@example.com'));
    }

    public function testValidateEmailStrictRejectsInvalidEmail(): void
    {
        self::assertFalse(validateEmailStrict('not-an-email'));
    }

    public function testValidateRoleStrictAcceptsKnownRolesOnly(): void
    {
        self::assertTrue(validateRoleStrict('admin'));
        self::assertTrue(validateRoleStrict('vendor'));
        self::assertTrue(validateRoleStrict('customer'));
        self::assertFalse(validateRoleStrict('superadmin'));
    }

    public function testSanitizeStringTrimsAndCapsLength(): void
    {
        $value = sanitizeString('  abcdef  ', 4);

        self::assertSame('abcd', $value);
    }

    public function testGetPaginationParamsNormalizesBounds(): void
    {
        $pagination = getPaginationParams(['page' => 0, 'per_page' => 500], 20, 100);

        self::assertSame(1, $pagination['page']);
        self::assertSame(100, $pagination['per_page']);
        self::assertSame(0, $pagination['offset']);
        self::assertSame(100, $pagination['limit']);
    }

    public function testValidateRequiredReturnsFieldErrors(): void
    {
        $payload = ['email' => 'john@example.com', 'password' => ''];
        $errors = validateRequired($payload, ['email', 'password', 'role']);

        self::assertCount(2, $errors);
        self::assertSame('password', $errors[0]['field']);
        self::assertSame('role', $errors[1]['field']);
    }
}
