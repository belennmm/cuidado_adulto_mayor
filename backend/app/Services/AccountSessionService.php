<?php

namespace App\Services;

use App\Models\User;
use App\Support\AccountSecurityState;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AccountSessionService
{
    private function lockAccount(User $user): void
    {
        $current = User::query()->whereKey($user->id)->lockForUpdate()->first();
        abort_unless($current && hash_equals(AccountSecurityState::fingerprint($current), AccountSecurityState::fingerprint($user)), 401);
    }

    public function start(User $user, ?int $tokenId = null, ?Request $request = null): string
    {
        return DB::transaction(function () use ($user, $tokenId, $request) {
            $this->lockAccount($user);
            $id = (string) Str::uuid();
            $expires = now()->addMinutes(max(1, (int) config($tokenId ? 'sanctum.expiration' : 'session.absolute_timeout', 60)));
            if ($tokenId) {
                $token = DB::table('personal_access_tokens')->where('id', $tokenId)->first();
                if ($token->expires_at && Carbon::parse($token->expires_at)->lt($expires)) {
                    $expires = Carbon::parse($token->expires_at);
                }
            }
            DB::table('account_sessions')->insert([
                'id' => $id, 'user_id' => $user->id, 'token_id' => $tokenId,
                'browser_hash' => $tokenId ? null : hash('sha256', $request->session()->getId()),
                'security_fingerprint' => AccountSecurityState::fingerprint($user),
                'ip_address' => $request?->ip(), 'user_agent' => mb_substr($request?->userAgent() ?? '', 0, 512),
                'authenticated_at' => now(), 'last_activity' => now(),
                'expires_at' => $expires,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->limit($user, $id);

            return $id;
        });
    }

    private function remove(object $row): void
    {
        if ($row->token_id) {
            DB::table('personal_access_tokens')->where('id', $row->token_id)->delete();
        }
        DB::table('account_sessions')->where('id', $row->id)->delete();
    }

    private function limit(User $user, ?string $keep = null): void
    {
        $rows = DB::table('account_sessions')->where('user_id', $user->id)->orderBy('last_activity')->orderBy('created_at')->orderBy('id')->get();
        foreach ($rows as $row) {
            if (Carbon::parse($row->expires_at)->lte(now()) || Carbon::parse($row->last_activity)->lte(now()->subMinutes((int) config('session.idle_timeout', 15)))) {
                $this->remove($row);
            }
        }
        $rows = DB::table('account_sessions')->where('user_id', $user->id)->orderBy('last_activity')->orderBy('created_at')->orderBy('id')->get();
        $excess = $rows->count() - max(1, (int) config('session.max_per_user', 5));
        foreach ($rows as $row) {
            if ($excess <= 0) {
                break;
            }
            if ($row->id !== $keep) {
                $this->remove($row);
                $excess--;
            }
        }
    }

    public function current(Request $request): ?object
    {
        $query = DB::table('account_sessions')->where('user_id', $request->user()->id);
        if ($request->attributes->get('browser_session')) {
            return $query->where('id', (string) $request->session()->get('account_session_id'))->first();
        }
        $token = $request->user()->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $query->where('token_id', $token->id)->first() : null;
    }

    public function touch(User $user, string $id, Request $request): bool
    {
        return DB::transaction(function () use ($user, $id, $request) {
            $this->lockAccount($user);
            $this->limit($user);
            $row = DB::table('account_sessions')->where('user_id', $user->id)->where('id', $id)->first();
            if (! $row || ($row->browser_hash && ! hash_equals($row->browser_hash, hash('sha256', $request->session()->getId())))) {
                return false;
            }
            DB::table('account_sessions')->where('id', $id)->update(['last_activity' => now(), 'ip_address' => $request->ip(), 'user_agent' => mb_substr($request->userAgent() ?? '', 0, 512), 'updated_at' => now()]);

            return true;
        });
    }

    public function listing(Request $request): array
    {
        $user = $request->user();

        return DB::transaction(function () use ($user, $request) {
            $this->lockAccount($user);
            $this->limit($user);
            $current = $this->current($request)?->id;

            return DB::table('account_sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()->map(fn ($row) => [
                'id' => $row->id, 'type' => $row->token_id ? 'api' : 'browser', 'device' => $row->user_agent ?: 'Dispositivo desconocido',
                'ip_address' => $row->ip_address, 'last_activity' => $row->last_activity, 'created_at' => $row->created_at, 'current' => $row->id === $current,
            ])->all();
        });
    }

    public function close(User $user, string $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $this->lockAccount($user);
            $row = DB::table('account_sessions')->where('user_id', $user->id)->where('id', $id)->first();
            abort_unless($row, 404);
            $this->remove($row);
        });
    }

    public function confirm(Request $request, string $password): void
    {
        DB::transaction(function () use ($request, $password) {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals(AccountSecurityState::fingerprint($user), AccountSecurityState::fingerprint($request->user())), 401);
            $row = $this->current($request);
            if ($request->attributes->get('browser_session') || ($request->user()->currentAccessToken() instanceof PersonalAccessToken && $request->user()->currentAccessToken()->exists)) {
                abort_unless($row, 401);
            }
            if (! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['La contrasena actual no es correcta.']]);
            }
            if ($row) {
                DB::table('account_sessions')->where('id', $row->id)->update(['authenticated_at' => now()]);
            }
        });
    }

    public function requireRecent(Request $request): void
    {
        if ($request->filled('current_password')) {
            $this->confirm($request, $request->string('current_password')->toString());

            return;
        }
        $row = $this->current($request);
        if (! $row?->authenticated_at || Carbon::parse($row->authenticated_at)->lte(now()->subMinutes((int) config('session.reauthentication_timeout', 5)))) {
            abort(response()->json(['message' => 'Confirma tu contrasena actual para continuar.', 'code' => 'reauthentication_required'], 428));
        }
    }
}
