<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('token_id')->nullable()->unique()->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('browser_hash', 64)->nullable()->unique();
            $table->string('security_fingerprint', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('authenticated_at')->nullable();
            $table->timestamp('last_activity')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'last_activity']);
        });
        // Existing API credentials receive metadata without exposing their credential IDs.
        DB::table('personal_access_tokens')->where('tokenable_type', User::class)->orderBy('id')->chunkById(100, function ($tokens) {
            foreach ($tokens as $token) {
                $expires = Carbon::parse($token->created_at)->addMinutes(max(1, (int) config('sanctum.expiration', 60)));
                if ($token->expires_at && Carbon::parse($token->expires_at)->lt($expires)) {
                    $expires = Carbon::parse($token->expires_at);
                }
                DB::table('account_sessions')->insert([
                    'id' => (string) Str::uuid(), 'user_id' => $token->tokenable_id, 'token_id' => $token->id,
                    'authenticated_at' => null, 'last_activity' => $token->last_used_at ?? $token->created_at,
                    'expires_at' => $expires,
                    'created_at' => $token->created_at, 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_sessions');
    }
};
