<?php

namespace App\Contracts;

use App\DTOs\ReelStructureGenerationStatus;

interface PostReelGenerationStatusInterface
{
    public function getStatus(int $postId): ?ReelStructureGenerationStatus;
    public function putStatus(ReelStructureGenerationStatus $payload): void;
}
