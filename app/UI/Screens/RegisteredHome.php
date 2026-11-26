<?php

namespace App\UI\Screens;

use App\Models\User;
use App\UI\Screens\Auth\Profile;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\ItemCard;
use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\Enums\Style;
use Idei\Usim\Screen;
use Idei\Usim\Upload\UploadService;
use Illuminate\Support\Facades\Auth;

class RegisteredHome extends Screen
{
    protected ItemCard $icrd_welcome;

    protected function startingUI(): Container
    {
        $user = Auth::user();
        $userName = $user instanceof User && $user->getDisplayName() !== ''
            ? $user->getDisplayName()
            : 'Usuario';

        $this->hydrateWelcomeCard($user instanceof User ? $user : null, $userName);

        return $this->layoutContainer();
    }

    public function onGoToProfile(): void
    {
        $this->openModal(Profile::class);
    }

    public function onGoToHome(): void
    {
        $this->navigate(Home::class);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;

        if ($user instanceof User) {
            $name = $user->getDisplayName() !== '' ? $user->getDisplayName() : 'Usuario';
            $this->hydrateWelcomeCard($user, $name);

            return;
        }

        if (is_object($user) && isset($user->name) && is_string($user->name) && trim($user->name) !== '') {
            $this->icrd_welcome
                ->title($this->t('.greeting_title', ['name' => trim($user->name)]))
                ->avatar($this->extractInitials(trim($user->name)));
        }
    }

    private function hydrateWelcomeCard(?User $user, string $userName): void
    {
        $this->icrd_welcome
            ->title($this->t('.greeting_title', ['name' => $userName]))
            ->avatar($this->resolveAvatar($user, $userName))
            ->attributes($this->buildAccountAttributes($user))
            ->actions($this->buildWelcomeActions());

        $registeredAgo = $user?->created_at?->diffForHumans();
        if (is_string($registeredAgo) && $registeredAgo !== '') {
            $this->icrd_welcome->footerText(
                $this->t('.registered_at', ['time' => $registeredAgo])
            );
        }
    }

    /**
     * @return list<array{icon: string, label: string, value: string}>
     */
    private function buildAccountAttributes(?User $user): array
    {
        $attributes = [];

        if ($user !== null && $user->getEmail() !== '') {
            $attributes[] = [
                'icon' => 'mail',
                'label' => $this->t('.attributes.email'),
                'value' => $user->getEmail(),
            ];
        }

        if ($user !== null) {
            $isVerified = $user->hasVerifiedEmail();
            $attributes[] = [
                'icon' => $isVerified ? 'shield-check' : 'alert-circle',
                'label' => $this->t('.attributes.verification'),
                'value' => $this->t($isVerified ? '.verified' : '.unverified'),
            ];
        }

        $attributes[] = [
            'icon' => 'clock',
            'label' => $this->t('.attributes.status'),
            'value' => $this->t('.attributes.status_waiting'),
        ];

        return $attributes;
    }

    /**
     * @return list<CardActionDTO>
     */
    private function buildWelcomeActions(): array
    {
        $actions = [];

        if (Profile::canAccess()) {
            $actions[] = CardActionDTO::make(
                label: $this->t('.actions.complete_profile'),
                action: 'go_to_profile',
                icon: 'user',
                style: Style::PRIMARY,
                variant: 'solid',
            );
        }

        if (Home::canAccess()) {
            $actions[] = CardActionDTO::make(
                label: $this->t('.actions.explore_posts'),
                action: 'go_to_home',
                icon: 'home',
                style: Style::SECONDARY,
                variant: 'outline',
            );
        }

        return $actions;
    }

    private function resolveAvatar(?User $user, string $userName): string
    {
        $rawProfileImage = $user?->getAttribute('profile_image');
        if (is_string($rawProfileImage) && trim($rawProfileImage) !== '') {
            $clean = trim($rawProfileImage);
            if (str_starts_with($clean, '/') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $clean) === 1) {
                return $clean;
            }

            return UploadService::fileUrl('uploads/images/'.ltrim($clean, '/'));
        }

        return $this->extractInitials($userName);
    }

    private function extractInitials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($words) || $words === []) {
            return 'U';
        }

        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
            if (is_string($clean) && $clean !== '') {
                $initials .= mb_strtoupper(mb_substr($clean, 0, 1));
            }
        }

        return $initials !== '' ? $initials : 'U';
    }
}
