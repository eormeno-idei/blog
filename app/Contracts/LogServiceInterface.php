<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\LogFile;
use App\DTOs\LogQuery;
use App\DTOs\LogResult;

interface LogServiceInterface
{
    /**
     * @return list<LogFile>
     */
    public function list(): array;

    public function read(LogQuery $query): LogResult;

    public function path(string $file): ?string;

    public function clear(string $file): bool;
}

