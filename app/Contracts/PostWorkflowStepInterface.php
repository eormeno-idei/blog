<?php

namespace App\Contracts;

interface PostWorkflowStepInterface
{
    /**
     * @return string 
     * The name of the workflow step.
     */
    public static function getName(): string;

    /**
     * @return class-string<PostWorkflowStepInterface>|null 
     * The class name of the previous step, or null if this is the first step.
     */
    public function getPrevious(): ?string;

    /**
     * @return class-string<PostWorkflowStepInterface>|null 
     * The class name of the next step, or null if this is the last step.
     */
    public function getNext(): ?string;

    /**
     * @return bool 
     * Whether the next step is enabled or not.
     */
    public function isNextEnabled(): bool;

    /**
     * @return bool 
     * Whether the previous step is enabled or not.
     */
    public function isPreviousEnabled(): bool;
}
