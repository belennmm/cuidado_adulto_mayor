<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    #[DataProvider('roleAliases')]
    public function test_it_normalizes_canonical_and_legacy_roles(string $value, UserRole $expected): void
    {
        $this->assertSame($expected, UserRole::fromValue($value));
    }

    public function test_it_rejects_an_unknown_role(): void
    {
        $this->assertNull(UserRole::fromValue('supervisor'));
    }

    public static function roleAliases(): array
    {
        return [
            'admin' => ['admin', UserRole::ADMIN],
            'administrador' => ['administrador', UserRole::ADMIN],
            'familiar' => ['familiar', UserRole::FAMILY],
            'cuidador familiar' => ['cuidador_familiar', UserRole::FAMILY],
            'profesional' => ['profesional', UserRole::PROFESSIONAL],
            'cuidador profesional' => ['cuidador_profesional', UserRole::PROFESSIONAL],
        ];
    }
}
