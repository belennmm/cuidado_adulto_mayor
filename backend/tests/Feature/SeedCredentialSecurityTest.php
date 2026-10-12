<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SeedCredentials;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\PerformanceTestSeeder;
use Database\Seeders\SecurityScanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeedCredentialSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function blockedSeeders(): array
    {
        $cases = [];
        foreach (['production', 'staging'] as $environment) {
            foreach ([DatabaseSeeder::class, InitialDataSeeder::class, PerformanceTestSeeder::class, SecurityScanSeeder::class] as $seeder) {
                $cases[$environment.' '.$seeder] = [$environment, $seeder];
            }
        }

        return $cases;
    }

    #[DataProvider('blockedSeeders')]
    public function test_seeders_refuse_non_development_environments_before_writing(string $environment, string $seeder): void
    {
        $this->app->instance('env', $environment);
        try {
            (new $seeder)->run();
            $this->fail('Seeder must refuse this environment');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('local o testing', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_random_passwords_are_distinct_and_explicit_passwords_must_meet_policy(): void
    {
        $first = SeedCredentials::password('demo');
        $this->assertNotSame($first, SeedCredentials::password('demo'));
        $this->assertGreaterThanOrEqual(32, strlen($first));
        config(['seeding.passwords.demo' => 'weak']);
        $this->expectException(ValidationException::class);
        SeedCredentials::password('demo');
    }

    public function test_rerunning_test_seeder_rotates_credentials_and_revokes_tokens(): void
    {
        (new SecurityScanSeeder)->run();
        $admin = User::where('email', 'zap.admin@example.test')->sole();
        $oldHash = $admin->password;
        $admin->createToken('old-device');
        (new SecurityScanSeeder)->run();
        $this->assertNotSame($oldHash, $admin->fresh()->password);
        $this->assertFalse(Hash::check('ZapAdmin-2026!', $admin->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
