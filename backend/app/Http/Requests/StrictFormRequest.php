<?php

namespace App\Http\Requests;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $contentType = strtolower((string) $this->header('Content-Type'));
            if ($this->getContent() !== '' && ! $this->isJson()
                && ! str_starts_with($contentType, 'application/x-www-form-urlencoded')
                && ! str_starts_with($contentType, 'multipart/form-data')) {
                $validator->errors()->add('_body', 'El cuerpo debe enviarse como JSON o formulario.');
            }
            if ($this->isJson() && $this->getContent() !== '') {
                try {
                    $decoded = json_decode($this->getContent(), false, 64, JSON_THROW_ON_ERROR);
                    if (! is_object($decoded) && $decoded !== []) {
                        $validator->errors()->add('_body', 'El cuerpo JSON debe ser un objeto.');
                    }
                } catch (JsonException) {
                    $validator->errors()->add('_body', 'El cuerpo JSON no es valido.');
                }
            }

            $tree = [];
            foreach (array_keys($this->rules()) as $field) {
                $node = &$tree;
                foreach (explode('.', $field) as $segment) {
                    $node[$segment] ??= [];
                    $node = &$node[$segment];
                }
                unset($node);
            }

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
