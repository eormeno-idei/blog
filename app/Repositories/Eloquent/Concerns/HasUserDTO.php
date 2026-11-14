<?php

namespace App\Repositories\Eloquent\Concerns;

use App\DTOs\User\UserDTO;
use App\Models\User;
use Carbon\CarbonImmutable;

trait HasUserDTO
{
    private function toUserDTO(?User $user): ?UserDTO
    {
        if (!$user) {
            return null;
        }

        return new UserDTO(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            emailVerifiedAt: $user->email_verified_at ? CarbonImmutable::parse($user->email_verified_at) : null,
            termsAcceptedAt: $user->terms_accepted_at ? CarbonImmutable::parse($user->terms_accepted_at) : null,
            isRoot: $user->isRoot(),
            isRegisteredInLobby: $user->isRegisteredInLobby(),
            displayName: $user->getDisplayName(),
            createdAt: $user->created_at ? CarbonImmutable::parse($user->created_at) : null,
            updatedAt: $user->updated_at ? CarbonImmutable::parse($user->updated_at) : null,
        );
    }
}
