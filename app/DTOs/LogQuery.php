<?php

declare(strict_types=1);

namespace App\DTOs;

final class LogQuery
{
    public string $file;

    public int $lines;

    public string $term;

    public bool $back;

    public static function make(string $file = 'laravel.log', int $lines = 1000, string $term = '', bool $back = false): self
    {
        $dto = new self;
        $clean = basename($file === '' ? 'laravel.log' : $file);
        $dto->file = $clean === '' ? 'laravel.log' : $clean;
        $dto->lines = $lines > 0 ? $lines : 1000;
        $dto->term = $term;
        $dto->back = $back;

        return $dto;
    }
}

