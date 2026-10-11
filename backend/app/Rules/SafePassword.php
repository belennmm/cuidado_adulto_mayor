<?php

namespace App\Rules;

use App\Models\User;
use App\Services\CompromisedPasswords;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class SafePassword implements ValidationRule
{
    public function __construct(private readonly ?User $user = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }
        if (str_contains($value, "\0")) {
            $fail('La contrasena no puede contener caracteres nulos.');

            return;
        }
        if (config('hashing.driver') === 'bcrypt' && strlen($value) > 72) {
            $fail('La contrasena excede los 72 bytes admitidos por el algoritmo de este servidor.');

            return;
        }
        $simple = preg_replace('/[^a-z0-9]/', '', mb_strtolower($value));
        if (preg_match('/^(?:password|contrasena|qwerty|admin|administrador|letmein|welcome|bienvenido|iloveyou|abc|1234567890)+[0-9]*$/', $simple)
            || preg_match('/^(.)\1{7,}$/u', $value)) {
            $fail('Elige una contrasena menos comun.');

            return;
        }
        if ($this->user && Hash::check($value, $this->user->password)) {
            $fail('La nueva contrasena debe ser diferente de la actual.');

            return;
        }
        if (app(CompromisedPasswords::class)->contains($value)) {
            $fail('Esta contrasena aparece en filtraciones. Elige otra.');
        }
    }
}
