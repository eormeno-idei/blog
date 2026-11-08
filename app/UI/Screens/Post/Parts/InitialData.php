<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\UI\Requests\InitialDataRequest;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class InitialData extends Screen
{
    protected Input $post_title;
    protected Textarea $post_content;
    protected Button $confirm_button;

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

    protected function buildDataStepCard(?PostDTO $post): Container
    {
        $card = UI::container('step_data_card')
            ->width(Size::full())
            ->centerHorizontal()
            ->gap(Spacing::px(20))
            ->layout(LayoutType::VERTICAL)
            ->justifyContent(JustifyContent::START)
            ->padding(Spacing::px(5));

        $initialTitle = $post !== null ? $post->title : '';
        $initialContent = $post !== null ? $post->content : '';

        $this->post_title = UI::input('post_title')
            ->label('Título del Post')
            ->placeholder('Escribe un título claro y atractivo...')
            ->helpText('El título debe ser conciso y reflejar el contenido del post. Mínimo 10 caracteres, máximo 100.')
            ->value($initialTitle)
            ->required(true)
            ->width(Size::full());

        $this->post_content = UI::textarea('post_content')
            ->label('Contexto para generar el Reel')
            ->placeholder(t('post.default_content'))
            ->value($initialContent)
            ->plainText()
            ->required(true)
            ->width(Size::full())
            ->helpText('La IA utilizará estas notas para generar las escenas del Reel.');

        $this->confirm_button = UI::button('confirm_button')
            ->label('Confirmar datos iniciales')
            ->action('on_confirm_initial_data')
            ->style('primary')
            ->width(Size::full());

        $card->add($this->post_title);
        $card->add($this->post_content);
        $card->add($this->confirm_button);

        return $card;
    }

    public function onConfirmInitialData(int $post_id, InitialDataRequest $postRequest): void
    {
        $post_title = $postRequest->title;
        $post_content = $postRequest->content;

        $this->postService->updatePost($post_id, [
            'title' => $post_title,
            'content' => $post_content
        ], Auth::user());

        event(new UsimEvent('post_changed', [
            'postId' => $post_id,
            'stepIndex' => 0,
        ]));
    }

}
