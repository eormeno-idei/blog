<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\Workflow;
use App\DTOs\Posts\WorkflowStep;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PostWorkflowService
{
    public function __construct(
        private readonly PostServiceInterface $postRepository
    ) {}

    /**
     * @return Workflow
     */
    public function currentWorkflowStatus(int $post_id, ?Authenticatable $user = null): Workflow
    {
        $user = $user ?? Auth::user();

        $post = $this->postRepository->findById($post_id);

        if (! $post) {
            throw new ModelNotFoundException("Post with ID {$post_id} not found.");
        }

        if (! Gate::forUser($user)->allows('update', $post)) {
            throw new AuthorizationException("User does not have permission to update this post.");
        }

        $initial_data = new WorkflowStep('Data Entry');
        $date_time = new WorkflowStep('Date & Time', $initial_data);
        $reel_studio = new WorkflowStep('Reel Studio', $date_time);
        $reviewer_chat = new WorkflowStep('Review Submission', $reel_studio);
        $video_generation = new WorkflowStep('Video Generation', $reviewer_chat);
        $summary_publish = new WorkflowStep('Summary & Publish', $video_generation);

        $steps = collect([]);
        $steps->push($initial_data);
        $steps->push($date_time);
        $steps->push($reel_studio);
        $steps->push($reviewer_chat);
        $steps->push($video_generation);
        $steps->push($summary_publish);

        return new Workflow(steps: $steps, current: $steps[$post->status_value]);
    }
}
