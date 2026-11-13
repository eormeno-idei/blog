<?php

namespace App\DTOs\Posts;

use App\DTOs\User\UserDTO;
use App\Enums\PostHistoryEventType;
use Carbon\CarbonImmutable;

readonly class PostHistoryEventDTO
{
    /**
     * @param array<string, mixed>|string|null $contentSnapshot
     * @param array<string, mixed>|string|null $metadata
     */
    public function __construct(
        public int $id,
        public int $postId,
        public PostHistoryEventType|string $eventType,
        public ?UserDTO $actor = null,
        public ?string $actorRole = null,
        public ?int $versionMajor = null,
        public ?int $versionMinor = null,
        public ?int $basedOnHistoryId = null,
        public ?int $relatedVersionId = null,
        public ?string $summary = null,
        public ?string $instruction = null,
        public ?string $reviewComment = null,
        public array|string|null $contentSnapshot = null,
        public array|string|null $metadata = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * Returns the version label (e.g. 1.2).
     */
    public function versionLabel(): ?string
    {
        if (
            $this->versionMajor === null ||
            $this->versionMinor === null
        ) {
            return null;
        }

        return "{$this->versionMajor}.{$this->versionMinor}";
    }
}
