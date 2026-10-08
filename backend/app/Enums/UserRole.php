<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum UserRole: string
{
    case ADMIN = 'admin';
    case FAMILY = 'familiar';
    case PROFESSIONAL = 'profesional';

    public static function fromValue(mixed $value): ?self
    {
        $normalized = Str::of((string) $value)->ascii()->lower()->trim()->toString();

        return match ($normalized) {
            'admin', 'administrador' => self::ADMIN,
            'familiar', 'cuidador_familiar' => self::FAMILY,
            'profesional', 'cuidador_profesional' => self::PROFESSIONAL,
            default => null,
        };
    }

    public static function canonicalValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function acceptedValues(): array
    {
        return [
            'admin',
            'administrador',
            'familiar',
            'cuidador_familiar',
            'profesional',
            'cuidador_profesional',
        ];
    }

    public function databaseValues(): array
    {
        return match ($this) {
            self::ADMIN => ['admin', 'administrador'],
            self::FAMILY => ['familiar', 'cuidador_familiar'],
            self::PROFESSIONAL => ['profesional', 'cuidador_profesional'],
        };
    }
}
