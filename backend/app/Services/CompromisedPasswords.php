<?php

namespace App\Services;

use App\Support\OutboundRequestException;
use Illuminate\Support\Facades\Cache;

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
                $response = app(SecureExternalHttpClient::class)->passwordRange($prefix);
            } catch (OutboundRequestException) {
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
