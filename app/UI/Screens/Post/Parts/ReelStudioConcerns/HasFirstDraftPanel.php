<?php

namespace App\UI\Screens\Post\Parts\ReelStudioConcerns;

use App\DTOs\Posts\PostDTO;
use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use App\Jobs\GenerateReelStructureJob;
use Idei\Usim\Components\Button;
use Idei\Usim\Events\UsimEvent;
use Illuminate\Support\Facades\Auth;

trait HasFirstDraftPanel
{
    protected Button $btn_generate_first_structure;

    public function onGenerateFirstStructure(int $postId, ?string $txt_initial_instructions = null): void
    {
        $post = $this->postService->findById($postId);
        $this->btn_generate_first_structure->enabled(false);
        $this->startReelGenerationJob($post, $txt_initial_instructions);
    }

    protected function startReelGenerationJob(PostDTO $post, ?string $initialInstructions): void
    {
        $new_generating_event = $this->postHistoryService->requestGenerateReelScript(
            user: Auth::user(),
            postId: $post->id,
            extra_instructions: $initialInstructions
        );

        GenerateReelStructureJob::dispatch($new_generating_event, $post->content);

        $this->startTimer('tmr_reel_poll');
    }

    public function onCheckReelStatus(int $post_id): void
    {
        $status = $this->postHistoryService->getLatestReelEvent($post_id);

        if ($status === null) {
            return;
        }

        match ($status->event_type) {
            PostHistoryEventType::GENERATING_SCRIPT => $this->handleGeneratingScriptStatus($status),
            PostHistoryEventType::VERSION_GENERATED => $this->handleCompletedStatus($status),
            PostHistoryEventType::GENERATION_FAILED => $this->handleFailedStatus($status),
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
        $this->stopTimer('tmr_reel_poll');
        event(new UsimEvent('ai_first_structure_completed', [
            'postId' => $status->post_id,
            'script' => $status->reel_script,
        ]));
    }

    private function handleFailedStatus(PostHistoryEventDTO $status): void
    {
        $this->stopTimer('tmr_reel_poll');
        $errorMessage = $status->summary ?? $this->t('.toast.unknown_error');
        $this->toast($this->t('.toast.generation_failed', ['message' => $errorMessage]), 'error');
    }
}
