<?php

namespace App\Contracts;

use App\DTOs\ReelJobStatusDTO;

interface PostReelGenerationStatusInterface
{
    public function getStatus(int $postId): ?ReelJobStatusDTO;
    public function putStatus(ReelJobStatusDTO $payload): void;
}
