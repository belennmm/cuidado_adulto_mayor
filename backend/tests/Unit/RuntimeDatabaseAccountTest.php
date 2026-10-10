<?php

namespace Tests\Unit;

use App\Support\RuntimeDatabaseAccount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RuntimeDatabaseAccountTest extends TestCase
{
    #[DataProvider('unsafeAccounts')]
    public function test_production_configuration_rejects_missing_or_administrative_credentials(array $configuration, string $expectedError): void
    {
        $this->assertContains($expectedError, RuntimeDatabaseAccount::errors($configuration));
    }

    public static function unsafeAccounts(): array
    {
        $adminError = 'DB_USERNAME debe usar una cuenta de ejecucion sin privilegios administrativos';

        return [
            [['driver' => 'pgsql', 'username' => '', 'password' => 'test'], 'DB_USERNAME'],
            [['driver' => 'mysql', 'username' => 'cuidado_runtime', 'password' => ''], 'DB_PASSWORD'],
            [['driver' => 'mysql', 'username' => 'ROOT', 'password' => 'test'], $adminError],
            [['driver' => 'pgsql', 'username' => 'postgres', 'password' => 'test'], $adminError],
            [['driver' => 'sqlsrv', 'username' => 'sa', 'password' => 'test'], $adminError],
            [['driver' => 'pgsql', 'username' => 'cuidado_runtime', 'password' => 'test', 'url' => 'postgresql://postgres:test@localhost/app'], $adminError],
            [['driver' => 'mysql', 'url' => 'mysql://%72oot:test@localhost/app'], $adminError],
            [['driver' => 'pgsql', 'url' => 'postgres://cuidado_runtime:test@localhost/app?username=postgres'], $adminError],
        ];
    }

    public function test_dedicated_runtime_accounts_and_sqlite_need_no_administrative_credentials(): void
    {
        $this->assertSame([], RuntimeDatabaseAccount::errors(['driver' => 'pgsql', 'url' => 'postgres://cuidado_runtime:test@localhost/app']));
        $this->assertSame([], RuntimeDatabaseAccount::errors(['driver' => 'mysql', 'username' => 'cuidado_runtime', 'password' => 'test']));
        $this->assertSame([], RuntimeDatabaseAccount::errors(['driver' => 'sqlite', 'database' => ':memory:']));
    }
}
