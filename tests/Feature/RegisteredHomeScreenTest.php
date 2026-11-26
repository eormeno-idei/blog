<?php

declare(strict_types=1);

use App\Services\User\UsimUserService;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Home;
use App\UI\Screens\RegisteredHome;
use Idei\Usim\Components\ItemCard;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('renders RegisteredHome with declarative centered layout and hero ItemCard', function () {
    $defaultRegisteringRole = (string) config('usim.default_registering_role', 'registered');
    $registeredUser = app(UsimUserService::class)->provisionFromConfig($defaultRegisteringRole);
    $this->actingAs($registeredUser);

    $harness = ScreenTestHarness::for(RegisteredHome::class)
        ->render(shouldReset: true);

    $harness
        ->assertHasComponent('registered_home_wrapper')
        ->assertHasComponent('icrd_welcome');

    /** @var ItemCard $welcomeCard */
    $welcomeCard = $harness->getComponent('icrd_welcome');
    /** @var list<array<string, mixed>> $actions */
    $actions = is_array($welcomeCard->get('actions')) ? $welcomeCard->get('actions') : [];
    $actionNames = collect($actions)->pluck('action')->all();

    expect($welcomeCard)->toBeInstanceOf(ItemCard::class)
        ->and($welcomeCard->get('eyebrow'))->toBe('Sala de espera')
        ->and($welcomeCard->get('title'))->toBe("¡Hola, {$registeredUser->name}!")
        ->and($welcomeCard->get('badge'))->toBe('Pendiente de aprobación')
        ->and($welcomeCard->get('badge_style'))->toBe('warning')
        ->and($welcomeCard->get('style'))->toBe('info')
        ->and($actionNames)->toContain('go_to_profile', 'go_to_home');
});

it('updates welcome ItemCard greeting and avatar when profile is updated', function () {
    $defaultRegisteringRole = (string) config('usim.default_registering_role', 'registered');
    $registeredUser = app(UsimUserService::class)->provisionFromConfig($defaultRegisteringRole);
    $this->actingAs($registeredUser);

    $harness = ScreenTestHarness::for(RegisteredHome::class)
        ->render(shouldReset: true);

    $registeredUser->name = 'Pepe Honguito';
    $harness->call('updated_profile', ['user' => $registeredUser]);

    /** @var ItemCard $welcomeCard */
    $welcomeCard = $harness->getComponent('icrd_welcome');

    expect($welcomeCard->get('title'))->toBe('¡Hola, Pepe Honguito!')
        ->and($welcomeCard->get('avatar'))->toBe('PH');

    $registeredUser->profile_image = 'pepe-avatar.jpg';
    $harness->call('updated_profile', ['user' => $registeredUser]);

    /** @var ItemCard $updatedWelcomeCard */
    $updatedWelcomeCard = $harness->getComponent('icrd_welcome');

    expect($updatedWelcomeCard->get('avatar'))->toContain('/files/uploads/images/pepe-avatar.jpg');
});

it('opens Profile as a modal and navigates to Home from RegisteredHome actions', function () {
    $defaultRegisteringRole = (string) config('usim.default_registering_role', 'registered');
    $registeredUser = app(UsimUserService::class)->provisionFromConfig($defaultRegisteringRole);
    $this->actingAs($registeredUser);

    $profileHarness = ScreenTestHarness::for(RegisteredHome::class)
        ->render(shouldReset: true)
        ->call('go_to_profile');

    $profileHarness->assertModal(Profile::class);

    $homeHarness = ScreenTestHarness::for(RegisteredHome::class)
        ->render(shouldReset: true)
        ->call('go_to_home');

    $homeHarness->assertRedirect(Home::getRoutePath());
});
