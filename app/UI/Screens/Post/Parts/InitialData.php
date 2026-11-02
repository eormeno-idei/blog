<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostWorkflowStepInterface;
use Idei\Usim\Screen;
use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;
use Idei\Usim\Enums\JustifyContent;


class InitialData extends Screen implements PostWorkflowStepInterface
{
    protected Input $post_title;
    protected Textarea $post_content;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
        parent::__construct();
    }

    public static function authorize(): bool
    {
        return self::requireAuth();
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $post = $this->resolveQueryPost();
        $container
            ->width(Size::full())
            ->height(Size::full())
            ->justifyContent(JustifyContent::START)
            ->add(
                $this->buildDataStepCard($post)
            );
    }

    protected function postLoadUI(): void
    {
        $post = $this->resolveQueryPost();
        if ($post !== null) {
            $this->post_title->value($post->title);
            $this->post_content->value($post->content);
        }
    }

    protected function buildDataStepCard(?PostDTO $post = null): Container
    {
        $card = UI::container('step_data_card')
            ->width(Size::full())
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(5))
            ->gap(Spacing::px(5));

        $initialTitle = $post !== null ? $post->title : '';
        $initialContent = $post !== null ? $post->content : '';

        $this->post_title = UI::input('post_title')
            ->label('Título del Post')
            ->placeholder('Escribe un título claro y atractivo...')
            ->value($initialTitle)
            ->required(true)
            ->width(Size::full())
            ->onChange('on_title_changed');

        $this->post_content = UI::textarea('post_content')
            ->label('Información textual (Notas base para el Post y el Reel)')
            ->placeholder('Escribe la información del evento, convocatoria o proyecto…')
            ->value($initialContent)
            ->plainText()
            ->width(Size::full())
            ->height(Size::px(400))
            ->helpText('La IA utilizará estas notas para redactar las escenas del Reel.')
            ->onChange('on_content_changed');

        $card->add($this->post_title);
        $card->add($this->post_content);

        return $card;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onTitleChanged(array $params): void
    {
        $post = $this->resolveQueryPost();
        if ($post === null) {
            return;
        }
        $postId = $post->id;
        $rawValue = $params['value'] ?? $params['post_title'] ?? '';
        $newTitle = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        if ($newTitle === '') {
            return;
        }

        $this->post_title->value($newTitle);

        $this->postService->updatePost($postId, [
            'title' => $newTitle,
        ], Auth::user());
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onContentChanged(array $params): void
    {
        $post = $this->resolveQueryPost();
        if ($post === null) {
            return;
        }
        $postId = $post->id;
        $rawValue = $params['value'] ?? $params['post_content'] ?? '';
        $value = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        $this->post_content->value($value);

        $this->postService->updatePost($postId, [
            'content' => $value,
        ], Auth::user());
    }

    public static function getName(): string
    {
        return "Initial Data";
    }

    public function getPrevious(): ?string
    {
        return null;
    }

    public function getNext(): ?string
    {
        return DateTimeData::class;
    }

    public function isNextEnabled(): bool
    {
        return false;
    }

    public function isPreviousEnabled(): bool
    {
        return false;
    }

    private function resolveQueryPost(): ?PostDTO
    {
        $queryParams = $this->getQueryParams();
        $postId = $queryParams['post_id'] ?? null;
        if ($postId === null) {
            return null;
        }

        return $this->postService->findById((int) $postId);
    }
}
