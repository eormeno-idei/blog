<?php

namespace App\DTOs\Posts;

use App\DTOs\Posts\WorkflowStepDTO;

readonly class WorkflowStateDTO
{
    /**
     * @param list<WorkflowStepDTO> $steps
     */
    public function __construct(
        public array $steps,
        public int $currentIndex,
        public bool $canAdvance,
        public bool $canGoBack,
    ) {}

    public function currentStep(): WorkflowStepDTO
    {
        return $this->steps[$this->currentIndex];
    }
}
