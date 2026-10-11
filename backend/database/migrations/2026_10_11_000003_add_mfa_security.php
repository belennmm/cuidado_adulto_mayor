<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('mfa_secret')->nullable();
            $t->text('mfa_pending_secret')->nullable();
            $t->timestamp('mfa_enabled_at')->nullable();
            $t->timestamp('mfa_pending_expires_at')->nullable();
            $t->bigInteger('mfa_last_step')->default(-1);
            $t->unsignedTinyInteger('mfa_setup_attempts')->default(0);
        });
        Schema::table('password_reset_tokens', fn (Blueprint $t) => $t->unsignedTinyInteger('attempts')->default(0));
        Schema::create('mfa_challenges', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('security_fingerprint', 64);
            $t->string('browser_hash', 64)->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('created_at');
        });
        Schema::create('mfa_recovery_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('code_hash', 64);
            $t->timestamp('expires_at');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_recovery_codes');
        Schema::dropIfExists('mfa_challenges');
        Schema::table('password_reset_tokens', fn (Blueprint $t) => $t->dropColumn('attempts'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['mfa_secret', 'mfa_pending_secret', 'mfa_enabled_at', 'mfa_pending_expires_at', 'mfa_last_step', 'mfa_setup_attempts']));
    }
};
