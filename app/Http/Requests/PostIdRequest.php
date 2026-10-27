<?php

namespace App\Http\Requests;

use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use App\Contracts\PostServiceInterface;
use App\Services\User\UserService;

class PostIdRequest extends UIRequest
{
    /** Cache local para evitar consultar la BD múltiples veces */
    protected ?object $post = null;

    public function __construct(
        protected UserService $userService,
        protected PostServiceInterface $postService
    ) {}

    /**
     * Determine if the user is authorized to perform this request.
     */
    public function authorize(): bool
    {
        $post = $this->getPost();
        if (! $post) {
            return false;
        }
        return $this->postService->isCurrentUserOwner($post)
            || $this->userService->currentUserHasRole('admin');
    }

    /**
     * Validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'post_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->getPost()) {
                        $fail('El post con el ID proporcionado no existe.');
                    }
                },
            ],
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
            'post_id.required' => 'El ID del post es obligatorio.',
            'post_id.integer' => 'El ID del post debe ser un número entero.',
            'post_id.exists' => 'El post con el ID proporcionado no existe.',
        ];
    }

    /**
     * Recupera el post asociado al ID validado (con memoización).
     */
    public function getPost(): ?object
    {
        $id = $this->getPostId();
        if ($this->post === null && $id > 0) {
            $this->post = $this->postService->findById($id);
        }
        return $this->post;
    }

    /**
     * Get the post ID.
     */
    public function getPostId(): int
    {
        return (int) $this->input('post_id');
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
            screen: $screen,
            parameters: $parameters
        );

        return $instance;
    }
}
