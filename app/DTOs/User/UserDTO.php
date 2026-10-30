<?php

namespace App\DTOs\User;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use App\DTOs\Role\UsimRoleDTO;
use App\DTOs\Unit\UsimUnitDTO;

readonly class UserDTO
{
    /**
     * @param Collection<int, UsimRoleDTO>|null $globalRoles
     * @param Collection<int, UsimUnitDTO>|null $usimUnits
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?CarbonImmutable $emailVerifiedAt,
        public ?CarbonImmutable $termsAcceptedAt,

        public bool $isRoot,
        public bool $isRegisteredInLobby,
        public string $displayName,

        public ?Collection $globalRoles = null,
        public ?Collection $usimUnits = null,

        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}
}
