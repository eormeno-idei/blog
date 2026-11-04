<?php

namespace App\DTOs\Posts;

use App\DTOs\User\UserDTO;
use App\Enums\PostStatus;
use App\Enums\PostTemplate;
use App\Enums\PostWorkflowStep;
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
        public PostWorkflowStep $workflowStep = PostWorkflowStep::INITIAL_DATA,
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

}
