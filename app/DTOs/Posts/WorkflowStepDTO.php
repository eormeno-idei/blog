<?php
namespace App\DTOs\Posts;

use App\Enums\PostWorkflowStep;
use Idei\Usim\Screen;

readonly class WorkflowStepDTO
{
    /**
     * @param class-string<Screen> $screenClass
     */
    public function __construct(
        public PostWorkflowStep $step,
        public string $label,
        public string $screenClass,
        public bool $isCurrent,
        public bool $isCompleted,
        public bool $canAccess,
    ) {}
}
