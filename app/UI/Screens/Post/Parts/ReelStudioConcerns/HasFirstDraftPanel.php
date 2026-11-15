<?php

namespace App\UI\Screens\Post\Parts\ReelStudioConcerns;

use App\DTOs\Posts\PostDTO;
use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use App\Jobs\GenerateReelStructureJob;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Timer;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

trait HasFirstDraftPanel
{
    protected Container $context_received_card;
    protected NewLabel $context_received_title;
    protected NewLabel $context_received_content;
    protected NewLabel $context_received_images;
    protected Textarea $initial_instructions;
    protected Button $btn_generate_first_structure;
    protected Timer $tmr_reel_poll;

    /**
     * Configure the first-draft panel and attach its declarative grid layout.
     */
    protected function buildFirstDraftPanel(): Container
    {
        $this->configureContextReceivedCard();

        // Timer is non-visual and not a layout slot, so attach it explicitly.
        return $this->first_draft_panel
            ->layoutFile('reel-studio-first-draft.layout.yaml')
            ->add($this->buildReelPollTimer());
    }

    protected function buildReelPollTimer(): Timer
    {
        return $this->tmr_reel_poll
            ->action('check_reel_status')
            ->every(1000)
            ->enabled(false);
    }

    private function configureContextReceivedCard(): Container
    {
        return $this->context_received_card
            ->card()
            ->layout(LayoutType::VERTICAL)
            ->width(Size::full())
            ->padding(Spacing::px(5))
            ->gap(Spacing::px(5))
            ->justifyContent(JustifyContent::START)
            ->add($this->context_received_title->text($this->t('.context_received.title')))
            ->add($this->context_received_content->text($this->t('.context_received.content')))
            ->add($this->context_received_images->text($this->t('.context_received.images')));
    }

    public function onGenerateFirstStructure(int $postId, ?string $initialInstructions): void
    {
        $post = $this->postService->findById($postId);
        $this->btn_generate_first_structure->enabled(false);
        $this->startReelGenerationJob($post, $initialInstructions);
    }

    protected function startReelGenerationJob(PostDTO $post, ?string $initialInstructions): void
    {
        $new_generating_event = $this->postHistoryService->requestGenerateReelScript(
            user: Auth::user(),
            postId: $post->id,
            extra_instructions: $initialInstructions
        );

        GenerateReelStructureJob::dispatch($new_generating_event, $post->content);

        $this->tmr_reel_poll->start();
    }

    public function onCheckReelStatus(int $post_id): void
    {
        $status = $this->postHistoryService->getLatestReelEvent($post_id);

        if ($status === null) {
            return;
        }

        match ($status->event_type) {
                // PostHistoryEventType::GENERATE_REQUESTED => $this->handleGenerateRequestedStatus($status),
            PostHistoryEventType::GENERATING_SCRIPT => $this->handleGeneratingScriptStatus($status),
            PostHistoryEventType::VERSION_GENERATED => $this->handleCompletedStatus($status),
            default => null,
        };
    }

    private function handleGeneratingScriptStatus(PostHistoryEventDTO $status): void
    {
        event(new UsimEvent('generating_reel', [
            'postId' => $status->post_id,
        ]));
    }

    private function handleCompletedStatus(PostHistoryEventDTO $status): void
    {
        $this->tmr_reel_poll->stop();
        event(new UsimEvent('ai_first_structure_completed', [
            'postId' => $status->post_id,
            'script' => $status->reel_script
        ]));
    }

    private function handleFailedStatus(PostHistoryEventDTO $status): void
    {
        $this->tmr_reel_poll->stop();
        $errorMessage = $status->errorMessage ?? $this->t('.toast.unknown_error');
        $this->toast($this->t('.toast.generation_failed', ['message' => $errorMessage]), 'error');
    }
}
