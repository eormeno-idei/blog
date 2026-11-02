<?php

namespace App\UI\Screens\Post\Support;

class WizzardPartsLoader
{
    public static function getWizzardParts(): array
    {
        return [
            \App\UI\Screens\Post\Parts\InitialData::class,
            \App\UI\Screens\Post\Parts\DateTimeData::class,
            \App\UI\Screens\Post\Parts\ReelStudio::class,
            \App\UI\Screens\Post\Parts\ReviewerChat::class,
            \App\UI\Screens\Post\Parts\VideoStudio::class,
            \App\UI\Screens\Post\Parts\SumaryPublish::class,
        ];
    }

    public static function getWizzardByIndex(int $index): ?string
    {
        $parts = self::getWizzardParts();
        return $parts[$index] ?? null;
    }
}
