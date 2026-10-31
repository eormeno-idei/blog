<?php

namespace App\Enums;

enum PostStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case CHANGES_REQUESTED = 'changes_requested';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
    case IA_GENERATED = 'ia_generated';
    case IA_GENERATION_FAILED = 'ia_generation_failed';
    case IA_GENERATION_PENDING = 'ia_generation_pending';
    case REEL_GENERATED = 'reel_generated';
    case REEL_GENERATION_FAILED = 'reel_generation_failed';
    case REEL_GENERATION_PENDING = 'reel_generation_pending';
    case READY_FOR_REVIEW = 'ready_for_review';

    /**
     * Get all statuses as an array of strings.
     *
     * @return array<string> An array of aspect ratio strings.
     */
    public static function all(): array
    {
        $all = self::cases();
        return array_map(fn($case) => $case->value, $all);
    }

    public static function default(): self
    {
        return self::DRAFT;
    }
}
