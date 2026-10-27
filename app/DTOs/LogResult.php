<?php

declare(strict_types=1);

namespace App\DTOs;

final class LogResult
{
    public string $body = '';

    public string $size = '';

    public int $total = 0;

    public ?string $warn = null;

    public ?string $fail = null;

    public static function ok(string $body, string $size, int $total, ?string $warn = null): self
    {
        $dto = new self;
        $dto->body = $body;
        $dto->size = $size;
        $dto->total = $total;
        $dto->warn = $warn;

        return $dto;
    }

    public static function fail(string $fail): self
    {
        $dto = new self;
        $dto->fail = $fail;

        return $dto;
    }

    public function code(): int
    {
        return $this->fail !== null ? 404 : 200;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->fail !== null) {
            return ['error' => $this->fail];
        }

        if ($this->warn !== null) {
            return [
                'warning' => $this->warn,
                'content' => $this->body,
                'fileSize' => $this->size,
            ];
        }

        return [
            'content' => $this->body,
            'fileSize' => $this->size,
            'totalLines' => $this->total,
        ];
    }
}

