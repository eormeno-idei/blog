<?php

namespace App\UI\Requests;

use Idei\Usim\Http\Requests\UIRequest;

class DateTimeDataRequest extends UIRequest
{
    public protected(set) ?string $from = null;
    public protected(set) ?string $to = null;

    protected array $map = [
        'from' => 'post_from',
        'to' => 'post_to',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ? trim((string) $this->input('from')) : null,
            'to' => $this->input('to') ? trim((string) $this->input('to')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'from' => 'required|date_format:Y-m-d|before:to|after_or_equal:today',
            'to' => 'required|date_format:Y-m-d|after:from',
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'La fecha de inicio es obligatoria.',
            'to.required' => 'La fecha de fin es obligatoria.',
            'from.date_format' => 'La fecha de inicio debe tener el formato YYYY-MM-DD.',
            'to.date_format' => 'La fecha de fin debe tener el formato YYYY-MM-DD.',
            'from.before' => 'La fecha de inicio no puede ser mayor que la fecha de fin.',
            'to.after' => 'La fecha de fin no puede ser menor que la fecha de inicio.',
            'from.after_or_equal' => 'La fecha de inicio no puede ser menor que la fecha de hoy.',
        ];
    }

    public function attributes(): array
    {
        return [
            'from' => 'fecha de inicio',
            'to' => 'fecha de fin',
        ];
    }

    public function errorToastMessage(): ?string
    {
        return null;
    }
}
