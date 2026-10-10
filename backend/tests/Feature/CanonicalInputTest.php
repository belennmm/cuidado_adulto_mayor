<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CanonicalInputTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('ambiguousJson')]
    public function test_ambiguous_json_is_rejected_before_any_write(string $body, string $field = '_body'): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $this->call('POST', '/api/admin/older-adults', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$admin->createToken('test')->plainTextToken,
        ], $body)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('older_adults', 0);
    }

    public static function ambiguousJson(): array
    {
        return [
            ['{"full_name":"Ana","full_name":"Otra"}'],
            ['{"full_name":"Ana","full_\u006eame":"Otra"}'],
            ['{"full_name":"Ana","medications":[{"name":"A","name":"B"}]}'],
            ['{"full_name":"Ana","medications":{"0":{"name":"A"}}}', 'medications'],
            ['{"full_name":"Ana","medications":{}}', 'medications'],
            ['{"full_name":"Ana","medications":[{"name":"A","days":{}}]}', 'medications.0.days'],
            ['{"full_name":"Ana","unexpected":'.str_repeat('[', 17).'0'.str_repeat(']', 17).'}'],
            ['{"full_name":"Ana\u0000"}'],
            ['["Ana"]'],
            ['{"full_name":'],
        ];
    }

    public function test_unicode_is_normalized_before_validation_and_persistence_without_decoding_markup(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/admin/older-adults', ['full_name' => "\u{00a0}Jose\u{0301}\u{00a0}", 'room' => '%253Cscript%253E'])
            ->assertCreated()->assertJsonPath('older_adult.full_name', 'José');
        $this->assertDatabaseHas('older_adults', ['full_name' => 'José', 'room' => '%253Cscript%253E']);
    }

    public function test_password_bytes_are_not_normalized_or_trimmed(): void
    {
        $password = "  Abcdef123!e\u{0301}  ";
        $user = User::factory()->create(['password' => $password, 'is_approved' => true]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => $password])->assertOk();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => \Normalizer::normalize($password)])->assertUnauthorized();
    }

    public function test_even_a_valid_looking_file_in_a_known_field_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $this->withToken($admin->createToken('test')->plainTextToken)
            ->post('/api/admin/older-adults', ['full_name' => UploadedFile::fake()->create('photo.png', 1, 'image/png')])
            ->assertUnprocessable()->assertJsonValidationErrors('_body');
        $this->assertDatabaseCount('older_adults', 0);
    }

    public function test_body_size_limit_cannot_be_bypassed_with_a_small_content_length(): void
    {
        config(['app.max_request_bytes' => 32]);
        $this->call('POST', '/api/login', [], [], [], ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => 1], str_repeat('x', 64))
            ->assertStatus(413);
    }
}
