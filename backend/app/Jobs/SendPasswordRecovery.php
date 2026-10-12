<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class SendPasswordRecovery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $email) {}

    public function handle(): void
    {
        $delivery = DB::transaction(function () {
            // Token issuance and consumption use the same account lock.
            $user = User::query()->whereRaw('LOWER(email) = ?', [$this->email])->lockForUpdate()->first();
            if (! $user) {
                return null;
            }
            $token = Password::broker()->sendResetLink(['email' => $user->email], fn ($account, $token) => $token);

            return preg_match('/\A[a-f0-9]{64}\z/i', $token) ? [$user, $token] : null;
        });
        if ($delivery) {
            [$user, $token] = $delivery;
            $user->sendPasswordResetNotification($token);
        }
    }
}
