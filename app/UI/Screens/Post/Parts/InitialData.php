<?php

namespace App\UI\Screens\Post\Parts;

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
use Idei\Usim\Events\UsimEvent;

class InitialData extends Screen
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

    protected function buildBaseUI(Container $container, int $post_id): void
    {
        $post = $this->postService->findById($post_id);

        $container
            ->width(Size::full())
            ->height(Size::full())
            ->justifyContent(JustifyContent::START)
            ->add(
                $this->buildDataStepCard($post)
            );
    }

    protected function postLoadUI(int $post_id): void
    {
        $post = $this->postService->findById($post_id);
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
            ->onInput('on_title_changed');

        $this->post_content = UI::textarea('post_content')
            ->label('Información textual (Notas base para el Post y el Reel)')
            ->placeholder(t('post.default_content'))
            ->value($initialContent)
            ->plainText()
            ->required(true)
            ->width(Size::full())
            ->height(Size::px(400))
            ->helpText('La IA utilizará estas notas para redactar las escenas del Reel.')
            ->onInput('on_content_changed');

        $card->add($this->post_title);
        $card->add($this->post_content);

        return $card;
    }

    /**
     * @param int $post_id
     * @param string $post_title
     * @return void
     */
    public function onTitleChanged(int $post_id, string $post_title = ''): void
    {
        $this->postService->updatePost($post_id, ['title' => $post_title,], Auth::user());
        event(new UsimEvent('post_changed', [
            'postId' => $post_id,
            'stepIndex' => 0,
        ]));
    }

    /**
     * @param int $post_id
     * @param string $post_content
     * @return void
     */
    public function onContentChanged(int $post_id, string $post_content = ''): void
    {
        $this->postService->updatePost($post_id, ['content' => $post_content,], Auth::user());
        event(new UsimEvent('post_changed', [
            'postId' => $post_id,
            'stepIndex' => 0,
        ]));
    }
}
