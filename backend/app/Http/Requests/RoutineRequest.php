<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class RoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la rutina es obligatorio.',
            'nombre.string' => 'El nombre de la rutina debe ser texto.',
            'nombre.max' => 'El nombre de la rutina no puede superar 255 caracteres.',
            'horario.required' => 'El horario de la rutina es obligatorio.',
            'horario.date_format' => 'El horario debe tener el formato HH:MM.',
            'actividades.required' => 'Debes registrar al menos una actividad.',
            'actividades.array' => 'Las actividades deben enviarse como una lista.',
            'actividades.min' => 'Debes registrar al menos una actividad.',
            'actividades.*.required' => 'Cada actividad debe tener contenido.',
            'actividades.*.string' => 'Cada actividad debe ser texto.',
            'actividades.*.max' => 'Cada actividad no puede superar 255 caracteres.',
            'adulto_mayor_id.required_without' => 'Debes seleccionar un adulto mayor.',
            'adulto_mayor_id.integer' => 'El adulto mayor seleccionado no es valido.',
            'older_adult_id.required_without' => 'Debes seleccionar un adulto mayor.',
            'older_adult_id.integer' => 'El adulto mayor seleccionado no es valido.',
            'actividad.required_without' => 'Debes seleccionar una actividad para completar.',
            'actividad.string' => 'La actividad seleccionada debe ser texto.',
            'actividad.max' => 'La actividad seleccionada no puede superar 255 caracteres.',
            'actividad_index.required_without' => 'Debes seleccionar una actividad para completar.',
            'actividad_index.integer' => 'La actividad seleccionada no es valida.',
            'actividad_index.min' => 'La actividad seleccionada no es valida.',
        ];
    }
}
