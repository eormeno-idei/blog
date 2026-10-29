<?php

namespace App\Enums;

enum PostStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case CHANGES_REQUESTED = 'changes_requested';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

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
