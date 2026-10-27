<?php

declare(strict_types=1);

namespace App\DTOs;

final class LogFile
{
    public string $name;

    public string $size;

    public string $date;

    public static function make(string $name, string $size, string $date): self
    {
        $dto = new self;
        $dto->name = $name;
        $dto->size = $size;
        $dto->date = $date;

        return $dto;
    }

    /**
     * @return array{name: string, size: string, modified: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'size' => $this->size,
            'modified' => $this->date,
        ];
    }
}

