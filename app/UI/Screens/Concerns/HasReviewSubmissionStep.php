<?php

declare(strict_types=1);

namespace App\UI\Screens\Concerns;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Http\Requests\PostRequest;
use App\Models\User;
use App\UI\Screens\Home;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Select;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Step 4: Enviar a revisión (Selección de revisor con permisos y envío).
 *
 * @mixin PublishPost
 *
 * @property int $state_post_id
 * @property PostServiceInterface $postService
 * @property Container $ai_response
 * @property \Idei\Usim\Components\Container $ai_response
 *
 * @method void goToStep(string $tabId)
 * @method void enableStepTab(string $tabId, bool $activate = false)
 * @method int resolveCandidatePostId(array<string, mixed> $params = [])
 * @method static toast(string $message, string $type = 'info')
 * @method void navigate(string $screenClass, array<string, mixed> $params = [])
 * @method void stopActiveReelJobPolling()
 * @method string|null resolveUploadedImageFilename(array<string, mixed> $params)
 * @method array<string, mixed>|null getCurrentVersionData()
 * @method void cleanupDiscardedReelFiles(?string $keepVideoFilename)
 */
trait HasReviewSubmissionStep
{
    protected ?Select $reviewer_id = null;

    protected Button $btn_save_post;

    protected Button $btn_step4_back;

    protected function buildReviewSubmissionStepCard(?PostDTO $post = null): Container
    {
        $card = UI::container('step_review_submission_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(860))
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $card->add(
            UI::label('lbl_review_step_title')
                ->text('📤 Enviar Propuesta a Revisión')
                ->style('h3')
        );

        $card->add(
            UI::label('lbl_review_step_intro')
                ->text('Tu post y el guion del Reel están listos para ser evaluados. Selecciona el revisor institucional para enviar tu propuesta.')
                ->style('muted')
        );

        $reviewers = $this->resolveAvailableReviewers();
        $this->addReviewerSelectionToCard($card, $reviewers, $post);

        $card->add($this->buildStep4ActionsRow());

        return $card;
    }

    /**
     * @param  Collection<int, User>  $reviewers
     */
    private function addReviewerSelectionToCard(Container $card, Collection $reviewers, ?PostDTO $post): void
    {
        if ($reviewers->count() > 1) {
            $options = [];
            foreach ($reviewers as $reviewer) {
                $options[] = [
                    'value' => (string) $reviewer->id,
                    'label' => "{$reviewer->name} ({$reviewer->email})",
                ];
            }

            $firstReviewer = $reviewers->first();
            $currentReviewerId = 0;
            if ($post !== null && $post->reviewedBy !== null) {
                $currentReviewerId = $post->reviewedBy->id;
            } elseif ($firstReviewer instanceof User) {
                $currentReviewerId = (int) $firstReviewer->id;
            }

            $this->reviewer_id = UI::select('reviewer_id')
                ->label('Revisor Asignado')
                ->placeholder('Selecciona un revisor institucional...')
                ->options($options)
                ->value($currentReviewerId > 0 ? (string) $currentReviewerId : null)
                ->required(true)
                ->width(Size::full());

            $card->add($this->reviewer_id);
        } elseif ($reviewers->count() === 1) {
            /** @var User $single */
            $single = $reviewers->first();
            $card->add(
                UI::label('lbl_single_reviewer')
                    ->text("👤 Revisor asignado: **{$single->name}** ({$single->email})")
                    ->markdown(true)
                    ->style('info')
            );
        } else {
            $card->add(
                UI::label('lbl_no_reviewers')
                    ->text('ℹ️ La propuesta se enviará a la bandeja general de revisión institucional.')
                    ->style('muted')
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveAvailableReviewers(): Collection
    {
        /** @var Collection<int, User> $reviewers */
        $reviewers = User::query()
            ->whereHas('permissions', static function ($q): void {
                $q->whereIn('name', ['review_posts', 'posts.review']);
            })
            ->orWhereHas('roles', static function ($q): void {
                $q->whereIn('name', ['reviewer', 'admin']);
            })
            ->orWhereHas('roles.permissions', static function ($q): void {
                $q->whereIn('name', ['review_posts', 'posts.review']);
            })
            ->distinct()
            ->get(['id', 'name', 'email']);

        return $reviewers;
    }

    private function buildStep4ActionsRow(): Container
    {
        $actions = UI::container('step4_actions_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::zero());

        $this->btn_step4_back = UI::button('btn_step4_back')
            ->label('← Atrás: Estructura de Reel')
            ->action('step4_back')
            ->style('secondary');

        $this->btn_save_post = UI::button('btn_save_post')
            ->label('📤 Enviar a Revisión')
            ->action('save_post')
            ->style('primary')
            ->enabled(false);

        $actions->add($this->btn_step4_back);
        $actions->add($this->btn_save_post);

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep4Back(array $params): void
    {
        $this->goToStep('reel_studio_tab');
    }

    public function onSavePost(PostRequest $request): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $this->toast('Debes iniciar sesión para publicar.', 'error');

            return;
        }

        $this->stopActiveReelJobPolling();

        $imageFilename = $this->resolveUploadedImageFilename($request->all());
        $currentVersion = $this->getCurrentVersionData();
        $videoFilename = is_string($currentVersion['video_filename'] ?? null)
            ? $currentVersion['video_filename']
            : null;

        $fallbackAiScript = isset($this->ai_response) && is_string($this->ai_response->get('value')) && trim((string) $this->ai_response->get('value')) !== ''
            ? trim((string) $this->ai_response->get('value'))
            : null;

        $aiScript = is_string($currentVersion['json_script'] ?? null)
            ? $currentVersion['json_script']
            : $fallbackAiScript;

        $this->cleanupDiscardedReelFiles($videoFilename);

        $reviewerId = isset($request->all()['reviewer_id']) && is_numeric($request->all()['reviewer_id'])
            ? (int) $request->all()['reviewer_id']
            : null;

        $payload = [
            'user_id' => (int) $user->id,
            'title' => $request->title(),
            'content' => $request->content(),
            'image' => $imageFilename,
            'aspect_ratio' => '16:9',
            'from' => $request->fromDate(),
            'to' => $request->toDate(),
            'status' => 'pending_review',
            'ai_script' => $aiScript,
            'reel_video' => $videoFilename,
            'reviewed_by' => $reviewerId,
        ];

        $targetPostId = $this->resolveCandidatePostId($request->all());
        if ($targetPostId > 0) {
            $this->state_post_id = $targetPostId;
            $this->postService->updatePost($targetPostId, $payload, $user);
        } else {
            $this->state_post_id = $this->postService->createPost($payload);
        }

        $this->enableStepTab('step_reviewer_response_tab', activate: true);
        $this->toast('¡Propuesta enviada a revisión correctamente!', 'success');
        $this->navigate(Home::class);
    }
}
