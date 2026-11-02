<?php

namespace App\DTOs\Posts;

class WorkflowStep
{
    public ?WorkflowStep $next;
    public bool $enabledNext = false;
    public bool $enabledPrevious = false;

    /**
     * @param WorkflowStep|null $previous
     */
    public function __construct(
        public string $name,
        public ?WorkflowStep $previous = null,
    ) {
        if ($previous !== null) {
            $previous->next = $this;
        }
    }
}
