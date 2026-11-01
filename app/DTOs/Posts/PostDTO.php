<?php

namespace App\DTOs\Posts;

use App\DTOs\User\UserDTO;
use App\Enums\PostStatus;
use App\Enums\PostTemplate;
use Carbon\CarbonImmutable;
use Idei\Usim\Enums\AspectRatio;

readonly class PostDTO
{
    /**
     * @param array<string, mixed>|string|null $aiJobPayload
     */
    public function __construct(
        public int $id,
        public ?UserDTO $author = null,
        public string $title = '',
        public string $content = '',
        public PostTemplate $template = PostTemplate::DEFAULT,
        public AspectRatio $aspectRatio = AspectRatio::_16_9,
        public PostStatus|string $status = PostStatus::DRAFT,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?string $image = null,
        public ?string $aiScript = null,
        public ?string $reelVideo = null,
        public string $aiJobStatus = 'idle',
        public array|string|null $aiJobPayload = null,
        public ?string $reviewerComment = null,
        public ?UserDTO $reviewedBy = null,
        public ?CarbonImmutable $reviewedAt = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    public function statusValue(): string
    {
        return is_string($this->status) ? $this->status : $this->status->value;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'status_value' => $this->statusValue(),
            'user_id', 'author_id' => $this->author?->id,
            'author_name' => $this->author?->name,
            'reviewed_by' => $this->reviewedBy?->id,
            'reviewer_name' => $this->reviewedBy?->name,
            'reviewer_comment' => $this->reviewerComment,
            'ai_job_status' => $this->aiJobStatus,
            'ai_job_payload' => $this->aiJobPayload,
            'ai_script' => $this->aiScript,
            'reel_video' => $this->reelVideo,
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, [
            'status_value',
            'user_id',
            'author_id',
            'author_name',
            'reviewed_by',
            'reviewer_name',
            'reviewer_comment',
            'ai_job_status',
            'ai_job_payload',
            'ai_script',
            'reel_video',
        ], true);
    }
}
