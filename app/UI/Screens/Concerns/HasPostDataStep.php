<?php

declare(strict_types=1);

namespace App\UI\Screens\Concerns;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Http\Requests\PostRequest;
use App\Models\User;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

/**
 * Step 1: Datos de la publicación (Título, contenido/notas).
 *
 * @mixin PublishPost
 *
 * @property int $state_post_id
 * @property PostServiceInterface $postService
 *
 * @method void enableStepTab(string $tabId, bool $activate = false)
 * @method int resolveCandidatePostId(array<string, mixed> $params = [])
 * @method static toast(string $message, string $type = 'info')
 */
trait HasPostDataStep
{
    protected Input $post_id;

    protected Input $post_title;

    protected Textarea $post_content;

    protected Button $btn_step1_next;

    protected function buildDataStepCard(?PostDTO $post = null): Container
    {
        $card = UI::container('step_data_card')
            ->width(Size::full())
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(10))
            ->gap(Spacing::px(8));

        $initialTitle = $post !== null ? $post->title : '';
        $initialContent = $post !== null ? $post->content : '';

        $this->post_id = UI::input('post_id')
            ->type('hidden')
            ->value((string) $this->state_post_id);

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
            ->height(Size::px(220))
            ->helpText('La IA utilizará estas notas para redactar las escenas del Reel.')
            ->onChange('on_content_changed');

        $card->add($this->post_id);
        $card->add($this->post_title);
        $card->add($this->post_content);
        $card->add($this->buildStep1ActionsRow());

        return $card;
    }

    private function buildStep1ActionsRow(): Container
    {
        $actions = UI::container('step1_actions_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->padding(Spacing::zero());

        $this->btn_step1_next = UI::button('btn_step1_next')
            ->label('Siguiente: Imagen de Portada →')
            ->action('step1_next')
            ->style('primary');

        $actions->add($this->btn_step1_next);

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep1Next(array $params): void
    {
        $request = PostRequest::fromArray($params, $this);
        $request->validate();

        $user = Auth::user();
        if (! $user instanceof User) {
            $this->toast('Debes iniciar sesión para continuar.', 'error');

            return;
        }

        $title = $request->title();
        $content = $request->content();

        $this->post_title->value($title);
        $this->post_content->value($content);

        $payload = [
            'user_id' => (int) $user->id,
            'title' => $title,
            'content' => $content,
        ];

        if ($this->state_post_id > 0) {
            $this->postService->updatePost($this->state_post_id, $payload, $user);
        } else {
            $payload['status'] = 'draft';
            $this->state_post_id = $this->postService->createPost($payload);
            $this->post_id->value((string) $this->state_post_id);
        }

        $this->enableStepTab('step_image_tab', activate: true);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onTitleChanged(array $params): void
    {
        $rawValue = $params['value'] ?? $params['post_title'] ?? '';
        $newTitle = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        if ($newTitle === '') {
            return;
        }

        $this->post_title->value($newTitle);

        if ($this->state_post_id <= 0) {
            return;
        }

        $post = $this->postService->findById($this->state_post_id);
        if ($post === null) {
            return;
        }

        $this->postService->updatePost($this->state_post_id, [
            'title' => $newTitle,
        ], Auth::user());
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onContentChanged(array $params): void
    {
        $rawValue = $params['value'] ?? $params['post_content'] ?? '';
        $value = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        $this->post_content->value($value);

        $targetPostId = $this->resolveCandidatePostId($params);
        if ($targetPostId > 0 && $value !== '') {
            $this->state_post_id = $targetPostId;
            $this->postService->updatePost($targetPostId, [
                'content' => $value,
            ], Auth::user());
        }
    }
}
