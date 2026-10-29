<?php

namespace App\Enums;

enum PostTemplate: string
{
    case DEFAULT = 'default';
    case COURSE = 'course';
    case EVENT = 'event';
    case THESIS = 'thesis';
    case EPHEMERID = 'ephemerid';
    case QUOTE = 'quote';
    case BIRTHDAY = 'birthday';
    case ANNIVERSARY = 'anniversary';
    case HOLIDAY = 'holiday';
    case PROMOTION = 'promotion';
    case ALERT = 'alert';

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
        return self::DEFAULT;
    }
}
