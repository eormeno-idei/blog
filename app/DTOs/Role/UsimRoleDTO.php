<?php

namespace App\DTOs\Role;

readonly class UsimRoleDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $guardName,
        public string $homeScreen,
        public int $priority,
    ) {}
}
