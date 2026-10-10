<?php

namespace App\Support;

use JsonException;

final class StrictJson
{
    public const MAX_DEPTH = 16;

    public static function decode(string $json): object|array
    {
        $decoded = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
        if (! is_object($decoded) && $decoded !== []) {
            throw new JsonException('El cuerpo JSON debe ser un objeto.');
        }

        // Tokenize valid JSON without interpreting braces or colons inside strings.
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|[{}\[\]:,]|[^\s{}\[\]:,]+/s', $json, $matches);
        $tokens = $matches[0];
        $stack = [];
        foreach ($tokens as $index => $token) {
            if (str_starts_with($token, '"') && preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/', json_decode($token, true, 64, JSON_THROW_ON_ERROR))) {
                throw new JsonException('Caracteres JSON no permitidos.');
            }
            if ($token === '{' || $token === '[') {
                $stack[] = [];
                if (count($stack) > self::MAX_DEPTH) {
                    throw new JsonException('El JSON excede el nivel de anidamiento permitido.');
                }
            } elseif ($token === '}' || $token === ']') {
                array_pop($stack);
            } elseif (str_starts_with($token, '"') && ($tokens[$index + 1] ?? null) === ':') {
                $key = 'key:'.json_decode($token, true, 64, JSON_THROW_ON_ERROR);
                $level = count($stack) - 1;
                if (isset($stack[$level][$key])) {
                    throw new JsonException('El JSON contiene claves duplicadas.');
                }
                $stack[$level][$key] = true;
            }
        }

        return $decoded;
    }
}
