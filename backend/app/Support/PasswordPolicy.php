<?php

namespace App\Support;

use App\Models\User;
use App\Rules\SafePassword;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    public static function rules(?User $user = null): array
    {
        return ['bail', 'string', 'max:128', Password::min(15)->mixedCase()->letters()->numbers()->symbols(), new SafePassword($user)];
    }

    public static function validate(string $password, string $field = 'password', ?User $user = null): void
    {
        Validator::make([$field => $password], [$field => ['required', ...self::rules($user)]])->validate();
    }
}
