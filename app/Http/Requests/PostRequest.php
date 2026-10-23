<?php

namespace App\Http\Requests;

use App\UI\Screens\PublishPost;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;

class PostRequest extends UIRequest
{
    /**
     * Mapping from clean attribute names to component keys.
     *
     * @var array<string, string>
     */
    protected array $map = [
        'title' => 'post_title',
        'content' => 'post_content',
    ];

    /**
     * Toast notification shown on validation failure.
     */
    protected ?string $errorToast = 'Por favor completa los campos obligatorios.';

    /**
     * Determine if the user is authorized to perform this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        if (! isset($this->parameters['post_title']) && isset($this->parameters['title'])) {
            $this->parameters['post_title'] = $this->parameters['title'];
        }

        if (! isset($this->parameters['post_content']) && isset($this->parameters['content'])) {
            $this->parameters['post_content'] = $this->parameters['content'];
        }

        if (isset($this->parameters['post_title']) && is_string($this->parameters['post_title'])) {
            $this->parameters['post_title'] = trim($this->parameters['post_title']);
        }

        if (isset($this->parameters['post_content']) && is_string($this->parameters['post_content'])) {
            $this->parameters['post_content'] = trim($this->parameters['post_content']);
        }
    }

    /**
     * Validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'post_title' => ['required', 'string'],
            'post_content' => ['required', 'string'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'post_title.required' => 'El título es obligatorio.',
            'post_content.required' => 'El contenido es obligatorio.',
        ];
    }

    /**
     * Get the trimmed post title.
     */
    public function title(): string
    {
        $title = $this->validated('post_title') ?? $this->input('post_title') ?? $this->input('title');

        return is_string($title) ? trim($title) : '';
    }

    /**
     * Get the trimmed post content.
     */
    public function content(): string
    {
        $content = $this->validated('post_content') ?? $this->input('post_content') ?? $this->input('content');

        return is_string($content) ? trim($content) : '';
    }

    /**
     * Factory helper to create and initialize a PostRequest instance.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function fromArray(array $parameters, ?Screen $screen = null): static
    {
        /** @var static $instance */
        $instance = app(static::class);
        $instance->initialize(
            screen: $screen ?? Screen::make(PublishPost::class),
            parameters: $parameters
        );

        return $instance;
    }
}
