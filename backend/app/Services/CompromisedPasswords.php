<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CompromisedPasswords
{
    public function contains(string $password): bool
    {
        if (! config('passwords.check_compromised')) {
            return false;
        }
        $digest = strtoupper(sha1($password));
        $prefix = substr($digest, 0, 5);
        $body = Cache::remember('pwned-range:'.$prefix, 3600, function () use ($prefix) {
            try {
                $response = Http::connectTimeout(2)->timeout(4)->withOptions(['allow_redirects' => false])
                    ->withHeaders(['Add-Padding' => 'true'])->get('https://api.pwnedpasswords.com/range/'.$prefix);
            } catch (ConnectionException) {
                abort(503);
            }
            if (! $response->successful() || strlen($response->body()) > 1000000) {
                abort(503);
            }
            $body = trim($response->body());
            if ($body === '' || ! preg_match('/\A[0-9A-F]{35}:[0-9]+(?:\r?\n[0-9A-F]{35}:[0-9]+)*\z/i', $body)) {
                abort(503);
            }

            return $body;
        });
        foreach (explode("\n", $body) as $line) {
            [$suffix, $count] = explode(':', trim($line));
            if (hash_equals(substr($digest, 5), strtoupper($suffix)) && (int) $count > 0) {
                return true;
            }
        }

        return false;
    }
}
