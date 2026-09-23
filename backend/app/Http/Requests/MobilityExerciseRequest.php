<?php

namespace App\Http\Requests;

use App\Models\MobilityExercise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobilityExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $exercise = $this->route('mobilityExercise');

        return [
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('mobility_exercises', 'slug')->ignore($exercise instanceof MobilityExercise ? $exercise->id : null)],
            'title' => ['required', 'string', 'max:255'], 'focus' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'], 'repetitions' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'array', 'min:1', 'max:20'], 'instructions.*' => ['required', 'string', 'max:1000'],
            'precaution' => ['required', 'string', 'max:2000'], 'is_active' => ['sometimes', 'boolean'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return ['title.required' => 'El título del ejercicio es obligatorio.', 'focus.required' => 'El área de enfoque es obligatoria.', 'duration_minutes.required' => 'La duración es obligatoria.', 'duration_minutes.min' => 'La duración debe ser de al menos un minuto.', 'repetitions.required' => 'Las repeticiones son obligatorias.', 'instructions.required' => 'Debes registrar las instrucciones.', 'instructions.min' => 'Debes registrar al menos una instrucción.', 'instructions.*.required' => 'Las instrucciones no pueden estar vacías.', 'precaution.required' => 'La precaución es obligatoria.', 'slug.unique' => 'Ya existe un ejercicio con este identificador.'];
    }
}
