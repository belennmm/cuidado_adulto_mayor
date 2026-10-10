<?php

namespace App\Support;

use Illuminate\Database\ConfigurationUrlParser;

final class RuntimeDatabaseAccount
{
    public static function errors(array $configuration): array
    {
        $connection = (new ConfigurationUrlParser)->parseConfiguration($configuration);
        if (($connection['driver'] ?? null) === 'sqlite') {
            return [];
        }

        $errors = [];
        $username = strtolower(trim((string) ($connection['username'] ?? '')));
        if ($username === '') {
            $errors[] = 'DB_USERNAME';
        } elseif (in_array($username, ['root', 'postgres', 'sa'], true)) {
            $errors[] = 'DB_USERNAME debe usar una cuenta de ejecucion sin privilegios administrativos';
        }
        if (($connection['password'] ?? '') === '' || ($connection['password'] ?? null) === null) {
            $errors[] = 'DB_PASSWORD';
        }

        return $errors;
    }
}
