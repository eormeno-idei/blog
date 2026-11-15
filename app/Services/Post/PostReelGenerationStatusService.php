<?php

namespace App\Services\Post;

use App\Contracts\PostHistoryServiceInterface;
use App\Contracts\PostReelGenerationStatusInterface;
use App\Contracts\PostServiceInterface;
use App\DTOs\ReelJobStatusDTO;

class PostReelGenerationStatusService implements PostReelGenerationStatusInterface
{
    public function __construct(
        protected PostServiceInterface $postService,
        protected PostHistoryServiceInterface $historyService
    ) {
    }

    public function getStatus(int $postId): ?ReelJobStatusDTO
    {
        $post = $this->postService->findById($postId);
        if ($post === null) {
            return null;
        }

        $ai_job_status = $post->aiJobStatus;
        $ai_job_payload = $post->aiJobPayload;

        $decoded = \is_string($ai_job_payload ?? null) && $ai_job_payload !== ''
            ? json_decode($ai_job_payload, true)
            : [];
        $decoded['post_id'] = $postId;

        return ReelJobStatusDTO::fromArray(
            \is_array($decoded) ? $decoded : [],
            \is_string($ai_job_status ?? null) ? $ai_job_status : null
        );

    }

    public function putStatus(ReelJobStatusDTO $jobStatus): void
    {
        $post = $this->postService->findById($jobStatus->post_id);

        $dbUpdate = [
            'ai_job_status' => $jobStatus->status,
            'ai_job_payload' => json_encode($jobStatus->toArray(), JSON_UNESCAPED_UNICODE),
        ];

        if ($jobStatus->isCompleted()) {
            $dbUpdate['ai_script'] = $jobStatus->reel_script;
        }

        $this->postService->updatePost($jobStatus->post_id, $dbUpdate);
    }
}
