<?php

namespace App\Support;

final class Totp
{
    public static function secret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bytes = random_bytes(20);
        $bits = '';
        foreach (unpack('C*', $bytes) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $part) {
            $out .= $alphabet[bindec($part)];
        }

        return $out;
    }

    public static function code(string $secret, int $step, int $digits = 6): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                throw new \InvalidArgumentException('Invalid authenticator key');
            } $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $part) {
            if (strlen($part) === 8) {
                $key .= chr(bindec($part));
            }
        }
        $hash = hash_hmac('sha1', pack('N2', intdiv($step, 4294967296), $step % 4294967296), $key, true);
        $offset = ord($hash[19]) & 15;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public static function match(string $secret, string $code, int $lastStep = -1): ?int
    {
        $current = intdiv(now()->timestamp, 30);
        $found = null;
        // Fixed three-window comparison; do not return early on a matching digit.
        foreach ([$current - 1, $current, $current + 1] as $step) {
            $matches = hash_equals(self::code($secret, $step), $code);
            if ($matches && $step > $lastStep) {
                $found = $step;
            }
        }

        return $found;
    }
}
