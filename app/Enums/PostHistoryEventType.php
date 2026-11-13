<?php

namespace App\Enums;

enum PostHistoryEventType: string
{
    case VERSION_GENERATED = 'version_generated';
    case REVIEW_REQUESTED = 'review_requested';
    case REVIEW_CHANGES_REQUESTED = 'review_changes_requested';
    case REVIEW_APPROVED = 'review_approved';
    case TEMPLATE_APPLIED = 'template_applied';
    case PUBLICATION_SCHEDULED = 'publication_scheduled';
    case PUBLISHED = 'published';
    case PUBLICATION_FAILED = 'publication_failed';
    case ARCHIVED = 'archived';
    case GENERATION_FAILED = 'generation_failed';

    /**
     * Get all event types as an array of strings.
     *
     * @return array<string> An array of event type strings.
     */
    public static function all(): array
    {
        $all = self::cases();
        return array_map(fn($case) => $case->value, $all);
    }

    /**
     * Get the default event type.
     *
     * @return string The default event type string.
     */
    public static function default(): string
    {
        return self::VERSION_GENERATED->value;
    }
}
