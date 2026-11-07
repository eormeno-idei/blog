<?php

namespace App\UI\Requests;

use Idei\Usim\Http\Requests\UIRequest;

class InitialDataRequest extends UIRequest
{
    public protected(set) string $title = '';
    public protected(set) string $content = '';

    protected array $map = [
        'title' => 'post_title',
        'content' => 'post_content',
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
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|min:10|max:100',
            'content' => 'required|string|min:50',
        ];
    }

    public function messages(): array
    {
        return [
            'title.min' => 'El título debe tener al menos 10 caracteres.',
            'title.max' => 'El título no puede exceder los 100 caracteres.',
            'content.min' => 'El contenido debe tener al menos 50 caracteres.',
            'content.required' => 'El contenido es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título del post',
            'content' => 'contenido del post',
        ];
    }

    public function errorToastMessage(): ?string
    {
        return null;
    }
}
