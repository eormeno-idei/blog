<?php

namespace App\DTOs\Posts;
use Illuminate\Support\Collection;

readonly class Workflow
{
    /**
     * @param Collection<int, WorkflowStep> $steps
     * @param WorkflowStep $current
     */
    public function __construct(
        public Collection $steps,
        public WorkflowStep $current,
    ) {}
}
