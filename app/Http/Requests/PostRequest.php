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
        'from' => 'post_from',
        'to' => 'post_to',
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
        $this->mapAliasIfMissing('title', 'post_title');
        $this->mapAliasIfMissing('content', 'post_content');
        $this->mapAliasIfMissing('from', 'post_from');
        $this->mapAliasIfMissing('to', 'post_to');

        $this->trimParameter('post_title');
        $this->trimParameter('post_content');
        $this->trimParameter('post_from');
        $this->trimParameter('post_to');
    }

    private function mapAliasIfMissing(string $alias, string $target): void
    {
        if (! isset($this->parameters[$target]) && isset($this->parameters[$alias])) {
            $this->parameters[$target] = $this->parameters[$alias];
        }
    }

    private function trimParameter(string $key): void
    {
        if (isset($this->parameters[$key]) && is_string($this->parameters[$key])) {
            $this->parameters[$key] = trim($this->parameters[$key]);
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
            'post_from' => ['nullable', 'date'],
            'post_to' => ['nullable', 'date', 'after_or_equal:post_from'],
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
            'post_from.date' => 'La fecha de inicio no es válida.',
            'post_to.date' => 'La fecha de finalización no es válida.',
            'post_to.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
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
     * Get the optional start date.
     */
    public function fromDate(): ?string
    {
        $from = $this->validated('post_from') ?? $this->input('post_from') ?? $this->input('from');

        return is_string($from) && trim($from) !== '' ? trim($from) : null;
    }

    /**
     * Get the optional end date.
     */
    public function toDate(): ?string
    {
        $to = $this->validated('post_to') ?? $this->input('post_to') ?? $this->input('to');

        return is_string($to) && trim($to) !== '' ? trim($to) : null;
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
