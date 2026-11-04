<?php

namespace App\Enums;

use App\UI\Screens\Post\Parts\DateTimeData;
use App\UI\Screens\Post\Parts\InitialData;
use App\UI\Screens\Post\Parts\ReelStudio;
use App\UI\Screens\Post\Parts\ReviewerChat;
use App\UI\Screens\Post\Parts\SumaryPublish;
use App\UI\Screens\Post\Parts\VideoStudio;
use Idei\Usim\Screen;

enum PostWorkflowStep: string
{
    case INITIAL_DATA = 'initial_data';
    case DATE_TIME = 'date_time';
    case REEL_STUDIO = 'reel_studio';
    case REVIEWER_CHAT = 'reviewer_chat';
    case VIDEO_STUDIO = 'video_studio';
    case SUMMARY_PUBLISH = 'summary_publish';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL_DATA => 'Datos Iniciales',
            self::DATE_TIME => 'Fecha y Hora',
            self::REEL_STUDIO => 'Reel Studio',
            self::REVIEWER_CHAT => 'Revisión',
            self::VIDEO_STUDIO => 'Generación de Video',
            self::SUMMARY_PUBLISH => 'Resumen y Publicación',
        };
    }

    /**
     * Mapeo estricto a la clase UI correspondiente.
     * @return class-string<Screen>
     */
    public function screenClass(): string
    {
        return match ($this) {
            self::INITIAL_DATA => InitialData::class,
            self::DATE_TIME => DateTimeData::class,
            self::REEL_STUDIO => ReelStudio::class,
            self::REVIEWER_CHAT => ReviewerChat::class,
            self::VIDEO_STUDIO => VideoStudio::class,
            self::SUMMARY_PUBLISH => SumaryPublish::class,
        };
    }

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
        return self::INITIAL_DATA;
    }

}
