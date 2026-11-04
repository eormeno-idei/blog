<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\DTOs\Posts\WorkflowStateDTO;
use App\DTOs\Posts\WorkflowStepDTO;
use App\Enums\PostWorkflowStep;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PostWorkflowService
{
    public function __construct(
        private readonly PostServiceInterface $postService
    ) {}

    public function getWorkflowState(int $postId, ?int $requestedIndex = null): WorkflowStateDTO
    {
        $post = $this->postService->findById($postId);
        if ($post === null) {
            throw new ModelNotFoundException("Post {$postId} not found.");
        }

        $allSteps = PostWorkflowStep::cases();
        $totalSteps = count($allSteps);

        // 1. Determinar el índice actual solicitado o por defecto
        $currentIndex = $requestedIndex !== null
            ? max(0, min($requestedIndex, $totalSteps - 1))
            : $this->resolveCurrentIndexFromPost($post);

        // 2. Construir los DTOs de pasos evaluando si cumplen requisitos
        $stepDTOs = [];
        foreach ($allSteps as $idx => $stepEnum) {
            $isCompleted = $this->isStepCompleted($stepEnum, $post);
            $canAccess = $idx <= $currentIndex || $isCompleted;

            $stepDTOs[] = new WorkflowStepDTO(
                step: $stepEnum,
                label: $stepEnum->label(),
                screenClass: $stepEnum->screenClass(),
                isCurrent: $idx === $currentIndex,
                isCompleted: $isCompleted,
                canAccess: $canAccess,
            );
        }

        // 3. Evaluar si el paso actual puede avanzar al siguiente
        $currentStepEnum = $allSteps[$currentIndex];
        $canAdvance = ($currentIndex < $totalSteps - 1) && $this->isStepCompleted($currentStepEnum, $post);
        $canGoBack = $currentIndex > 0;

        return new WorkflowStateDTO(
            steps: $stepDTOs,
            currentIndex: $currentIndex,
            canAdvance: $canAdvance,
            canGoBack: $canGoBack,
        );
    }

    /**
     * Reglas de validación de negocio puras para cada paso.
     */
    private function isStepCompleted(PostWorkflowStep $step, PostDTO $post): bool
    {
        return match ($step) {
            PostWorkflowStep::INITIAL_DATA => trim($post->title) !== '' && trim($post->content) !== '',
            PostWorkflowStep::DATE_TIME => $post->from !== null && $post->to !== null,
            PostWorkflowStep::REEL_STUDIO => $post->aiScript !== null || $post->reelVideo !== null,
            PostWorkflowStep::REVIEWER_CHAT => true,
            PostWorkflowStep::VIDEO_STUDIO => $post->reelVideo !== null,
            PostWorkflowStep::SUMMARY_PUBLISH => true,
        };
    }

    private function resolveCurrentIndexFromPost(PostDTO $post): int
    {
        $workFlowStep = $post->workflowStep;
        return 0;
    }
}
