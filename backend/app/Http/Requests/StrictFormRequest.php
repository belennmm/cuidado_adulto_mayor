<?php

namespace App\Http\Requests;

use App\Support\StrictJson;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use JsonException;

abstract class StrictFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->allFiles() !== []) {
            $this->rejectBody('Esta API no admite archivos adjuntos.');
        }
        if ($this->isJson() && $this->getContent() !== '') {
            try {
                $decoded = StrictJson::decode($this->getContent());
                $this->checkJsonContainers($decoded, $this->ruleTree());
            } catch (JsonException) {
                $this->rejectBody('JSON no valido: revise claves, estructura y anidamiento.');
            }
        }
        // Keep query and body separate, including GET requests with JSON headers.
        $this->query->replace($this->normalizeInput($this->query->all()));
        $this->request->replace($this->normalizeInput($this->request->all()));
        if ($this->isJson()) {
            $this->json()->replace($this->normalizeInput($this->json()->all()));
        }
    }

    private function normalizeInput(array $input, int $depth = 0): array
    {
        if ($depth > StrictJson::MAX_DEPTH) {
            $this->rejectBody('La entrada excede el nivel de anidamiento permitido.');
        }
        foreach ($input as $key => &$value) {
            if (is_array($value)) {
                $value = $this->normalizeInput($value, $depth + 1);
            } elseif (is_string($value)) {
                if (! mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                    $this->rejectBody('La entrada contiene caracteres no permitidos.');
                }
                // Credentials are opaque: never trim, decode or normalize passwords.
                if (! str_contains((string) $key, 'password')) {
                    $value = \Normalizer::normalize($value, \Normalizer::FORM_C);
                    $value = preg_replace('/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u', '', $value);
                }
            }
        }
        unset($value);

        return $input;
    }

    private function rejectBody(string $message, string $field = '_body'): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => [$field => [$message]],
        ], 422));
    }

    private function ruleTree(): array
    {
        $tree = [];
        foreach (array_keys($this->rules()) as $field) {
            $node = &$tree;
            foreach (explode('.', $field) as $segment) {
                $node[$segment] ??= [];
                $node = &$node[$segment];
            }
            unset($node);
        }

        return $tree;
    }

    private function checkJsonContainers(mixed $value, array $tree, string $prefix = ''): void
    {
        if (isset($tree['*']) && is_object($value)) {
            $this->rejectBody('Se esperaba una lista JSON.', $prefix ?: '_body');
        }
        if (is_array($value) || is_object($value)) {
            foreach ($value as $key => $child) {
                $this->checkJsonContainers($child, $tree[$key] ?? $tree['*'] ?? [], ltrim($prefix.'.'.$key, '.'));
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $contentType = strtolower((string) $this->header('Content-Type'));
            if ($this->getContent() !== '' && ! $this->isJson()
                && ! str_starts_with($contentType, 'application/x-www-form-urlencoded')
                && ! str_starts_with($contentType, 'multipart/form-data')) {
                $validator->errors()->add('_body', 'El cuerpo debe enviarse como JSON o formulario.');
            }
            $tree = $this->ruleTree();

            $input = $this->all();
            // Laravel supports form method override as transport metadata, not business input.
            if (array_key_exists('_method', $input) && $this->getRealMethod() === 'POST'
                && is_string($input['_method']) && in_array(strtoupper($input['_method']), ['PUT', 'PATCH', 'DELETE'], true)
                && strtoupper($input['_method']) === $this->method()) {
                unset($input['_method']);
            }
            $this->rejectUnknown($input, $tree, $validator);

            if (! in_array($this->method(), ['GET', 'HEAD'], true)) {
                foreach (array_keys($this->query->all()) as $field) {
                    $validator->errors()->add((string) $field, 'Las operaciones de escritura reciben campos en el cuerpo, no en la query.');
                }
            } elseif ($this->request->count() || ($this->isJson() && $this->json()->count())) {
                $validator->errors()->add('_body', 'Las consultas reciben filtros en la query, no en el cuerpo.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }

    private function rejectUnknown(array $input, array $tree, Validator $validator, string $prefix = ''): void
    {
        foreach ($input as $key => $value) {
            $path = $prefix.(string) $key;
            $branch = $tree[$key] ?? $tree['*'] ?? null;
            if ($branch === null) {
                $validator->errors()->add($path, 'Este campo no esta permitido.');
            } elseif (is_array($value)) {
                $this->rejectUnknown($value, $branch, $validator, $path.'.');
            }
        }
    }
}
