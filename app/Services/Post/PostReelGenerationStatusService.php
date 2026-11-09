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
        $ai_script = $post->aiScript;
        $ai_reel_video = $post->reelVideo;

        $decoded = is_string($ai_job_payload ?? null) && $ai_job_payload !== ''
            ? json_decode($ai_job_payload, true)
            : [];

        return ReelStructureGenerationStatus::fromArray(
            is_array($decoded) ? $decoded : [],
            is_string($ai_job_status ?? null) ? $ai_job_status : null
        );

    }

    public function putStatus(int $postId, ReelStructureGenerationStatus $payload): void
    {
        $post = $this->postService->findById($postId);
        if ($post === null) {
            return;
        }

        $currentStatus = $this->getStatus($postId);
        $versions = $currentStatus !== null ? $currentStatus->versions : [];

        if ($payload->isCompleted()) {
            $versions[] = $payload->toVersionEntry(count($versions) + 1);
        }

        $status = $payload->persisted($versions);

        $dbUpdate = [
            'ai_job_status' => $status->status,
            'ai_job_payload' => json_encode($status->toArray(), JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        if ($status->isCompleted()) {
            $dbUpdate['ai_script'] = $status->jsonScript;
        }

        $this->postService->updatePost($postId, $dbUpdate);
    }
}
