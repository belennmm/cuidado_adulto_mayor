<?php

namespace App\Services;

use App\Jobs\SendPasswordRecovery;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Timebox;

class PasswordRecoveryService
{
    public function request(string $email): void
    {
        SendPasswordRecovery::dispatch(mb_strtolower(trim($email)))->onConnection('database');
    }

    public function reset(array $data): bool
    {
        $data['email'] = mb_strtolower(trim($data['email']));

        return (new Timebox)->call(fn () => DB::transaction(function () use ($data) {
            // Serializes consumption for this account, including concurrent reset requests.
            $user = User::query()->whereRaw('LOWER(email) = ?', [$data['email']])->lockForUpdate()->first();
            if (! $user) {
                return false;
            }
            $row = DB::table(config('auth.passwords.users.table'))->where('email', $user->email)->first();
            if (! $row || ! $row->created_at || now()->subMinutes((int) config('auth.passwords.users.expire'))->gte(Carbon::parse($row->created_at))) {
                return false;
            }
            if ($row->attempts >= 5) {
                return false;
            }
            DB::table(config('auth.passwords.users.table'))->where('email', $user->email)->increment('attempts');
            $data['email'] = $user->email;

            return Password::broker()->reset($data, function (User $account, string $password) {
                PasswordPolicy::validate($password, 'password', $account);
                $account->forceFill(['password' => Hash::make($password)])->save();
            }) === Password::PASSWORD_RESET;
        }), 200000);
    }
}
