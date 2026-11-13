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
    ) {
    }

    /**
     * Obtiene el estado del flujo de trabajo para un post específico.
     *
     * @param int $postId El ID del post para el cual se desea obtener el estado del flujo de trabajo.
     * @param int $requestedIndex El índice del paso solicitado. Si es -1, se determinará automáticamente el paso actual basado en el estado del post.
     * @throws ModelNotFoundException
     * @return WorkflowStateDTO
     */
    public function getWorkflowState(int $postId, ?int $requestedIndex = null): WorkflowStateDTO
    {
        $post = $this->postService->findById($postId);

        if ($post === null) {
            throw new ModelNotFoundException("Post {$postId} not found.");
        }

        $allSteps = PostWorkflowStep::cases();
        $totalSteps = \count($allSteps);

        // 1. Determinar el índice actual solicitado o por defecto
        $currentIndex = $requestedIndex == null ?
            $this->lastCompletedStepIndex($allSteps, $post)
            : $requestedIndex;

        // 2. Construir los DTOs de pasos evaluando si cumplen requisitos
        $stepDTOs = [];
        $previousStepCompleted = true; // Para el primer paso, asumimos que se puede acceder
        foreach ($allSteps as $idx => $stepEnum) {
            $isCompleted = $this->isStepCompleted($stepEnum, $post);
            $canAccess = $previousStepCompleted;

            $stepDTOs[] = new WorkflowStepDTO(
                step: $stepEnum,
                label: $stepEnum->label(),
                screenClass: $stepEnum->screenClass(),
                isCurrent: $idx === $currentIndex,
                isCompleted: $isCompleted,
                canAccess: $canAccess,
            );

            $previousStepCompleted = $isCompleted;
        }

        // 3. Evaluar si el paso actual puede avanzar al siguiente
        $currentStepEnum = $allSteps[$currentIndex];
        $canAdvance = ($currentIndex < $totalSteps - 1) && $this->isStepCompleted($currentStepEnum, $post);
        $canGoBack = $currentIndex > 0;

        $workflowState = new WorkflowStateDTO(
            steps: $stepDTOs,
            currentIndex: $currentIndex,
            canAdvance: $canAdvance,
            canGoBack: $canGoBack,
        );

        return $workflowState;
    }

    /**
     * Reglas de validación de negocio puras para cada paso.
     */
    private function isStepCompleted(PostWorkflowStep $step, PostDTO $post): bool
    {
        return match ($step) {
            PostWorkflowStep::INITIAL_DATA => $this->initialDataStepCompleted($post),
            PostWorkflowStep::DATE_TIME => $this->dateTimeStepCompleted($post),
            PostWorkflowStep::REEL_STUDIO => false,
            PostWorkflowStep::REVIEWER_CHAT => false,
            PostWorkflowStep::VIDEO_STUDIO => $post->reelVideo !== null,
            PostWorkflowStep::SUMMARY_PUBLISH => false,
        };
    }

    private function initialDataStepCompleted(PostDTO $post): bool
    {
        $titleLength = strlen(trim($post->title));
        $contentLength = strlen(trim($post->content));
        $ret = $titleLength > 10 && $contentLength > 50;
        return $ret;
    }

    private function dateTimeStepCompleted(PostDTO $post): bool
    {
        $ret = $post->from !== null && $post->to !== null;
        $ret = $ret && $post->from < $post->to;
        $ret = $ret && $post->from > now();
        return $ret;
    }

    private function lastCompletedStepIndex(array $allSteps, PostDTO $post): int
    {
        $lastCompletedIndex = -1;
        foreach ($allSteps as $idx => $stepEnum) {
            $isCompleted = $this->isStepCompleted($stepEnum, $post);
            if ($isCompleted) {
                $lastCompletedIndex = $idx;
            }
        }
        if ($lastCompletedIndex < \count($allSteps) - 1) {
            $nextStepEnum = $allSteps[$lastCompletedIndex + 1];
            if (!$this->isStepCompleted($nextStepEnum, $post)) {
                return $lastCompletedIndex + 1; // El siguiente paso no está completado, así que ese es el paso actual
            }
        }
        return $lastCompletedIndex === -1 ? 0 : $lastCompletedIndex;
    }
}
