<?php

namespace App\DTOs\Unit;

use Illuminate\Support\Collection;
use App\DTOs\User\UserDTO;

readonly class UsimUnitDTO
{
    /**
     * @param Collection<int, UsimUnitDTO>|null $children
     * @param Collection<int, UsimUnitDTO>|null $ancestors
     * @param Collection<int, UserDTO>|null $users
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $type,
        public ?int $parentId,

        // Atributo computado/traducido
        public string $displayName,

        // Relaciones (Opcionales para Eager Loading)
        public ?UsimUnitDTO $parent = null,
        public ?Collection $children = null,
        public ?Collection $ancestors = null,
        public ?Collection $users = null,
    ) {}
}
