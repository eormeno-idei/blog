<?php

namespace App\Services\Post;

use App\Contracts\PostReelGenerationStatusInterface;
use App\Contracts\PostServiceInterface;
use App\DTOs\ReelStructureGenerationStatus;

class PostReelGenerationStatusService implements PostReelGenerationStatusInterface
{
    public function __construct(
        protected PostServiceInterface $postService,
    ) {
    }

    public function getStatus(int $postId): ?ReelStructureGenerationStatus
    {
        $post = $this->postService->findById($postId);
        if ($post === null) {
            return null;
        }

        $ai_job_status = $post->aiJobStatus;
        $ai_job_payload = $post->aiJobPayload;

        $decoded = is_string($ai_job_payload ?? null) && $ai_job_payload !== ''
            ? json_decode($ai_job_payload, true)
            : [];
        $decoded['post_id'] = $postId;

        return ReelStructureGenerationStatus::fromArray(
            is_array($decoded) ? $decoded : [],
            is_string($ai_job_status ?? null) ? $ai_job_status : null
        );

    }

    public function putStatus(ReelStructureGenerationStatus $payload): void
    {
        $post = $this->postService->findById($payload->postId);
        if ($post === null) {
            return;
        }

        $status = $payload->persisted();

        $dbUpdate = [
            'ai_job_status' => $status->status,
            'ai_job_payload' => json_encode($status->toArray(), JSON_UNESCAPED_UNICODE),
        ];

        if ($status->isCompleted()) {
            $dbUpdate['ai_script'] = $status->jsonScript;
        }

        $this->postService->updatePost($payload->postId, $dbUpdate);
    }
}
