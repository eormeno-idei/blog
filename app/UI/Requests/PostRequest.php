<?php

namespace App\UI\Requests;

use Idei\Usim\Http\Requests\UIRequest;

class PostRequest extends UIRequest
{
    public protected(set) string $title = '';
    public protected(set) string $content = '';
    public protected(set) ?string $from = null;
    public protected(set) ?string $to = null;

    protected array $map = [
        'title' => 'post_title',
        'content' => 'post_content',
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
            'title' => trim((string) $this->input('title')),
            'content' => trim((string) $this->input('content')),
            'from' => $this->input('from') ? trim((string) $this->input('from')) : null,
            'to' => $this->input('to') ? trim((string) $this->input('to')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|min:10|max:100',
            'content' => 'sometimes|string|min:50',
            'from' => 'required|date_format:Y-m-d|before:to|after_or_equal:today',
            'to' => 'required|date_format:Y-m-d|after:from',
        ];
    }

    public function messages(): array
    {
        return [
            'title.min' => 'El título debe tener al menos 10 caracteres.',
            'title.max' => 'El título no puede exceder los 100 caracteres.',
            'content.min' => 'El contenido debe tener al menos 50 caracteres.',
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
            'title' => 'título del post',
            'content' => 'contenido del post',
            'from' => 'fecha de inicio',
            'to' => 'fecha de fin',
        ];
    }

    public function errorToastMessage(): ?string
    {
        return null;
    }
}
