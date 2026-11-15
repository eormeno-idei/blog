<?php

namespace App\DTOs\Posts;

use App\DTOs\User\UserDTO;
use App\Enums\PostHistoryEventType;
use Carbon\CarbonImmutable;

readonly class PostHistoryEventDTO
{
    /**
     * @param array<string, mixed>|string|null $reel_script
     */
    public function __construct(
        public int $id,
        public int $post_id,
        public PostHistoryEventType $event_type,
        public ?UserDTO $actor = null,
        public ?int $version = null,
        public ?int $based_on_history_id = null,
        public ?int $related_version_id = null,
        public ?string $summary = null,
        public ?string $extra_instructions = null,
        public ?string $review_comment = null,
        public array|null $reel_script = null,
        public ?CarbonImmutable $created_at = null,
        public ?CarbonImmutable $updated_at = null,
    ) {}

}
