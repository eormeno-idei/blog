<?php

// @usim: feature="auth", type="test"

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\User\UsimUserService;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\LegalTerms;
use App\UI\Screens\Auth\Register;
use App\UI\Screens\Home;
use Idei\Usim\DTOs\RegistrationResult;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
});

it('renders guest register screen with terms components and without role selector', function () {
    $harness = ScreenTestHarness::for(Register::class)
        ->render(['fakeData' => false, 'askForRole' => false], shouldReset: true);

    $harness
        ->assertHasComponent('name')
        ->assertHasComponent('email')
        ->assertHasComponent('password')
        ->assertHasComponent('password_confirmation')
        ->assertHasComponent('accept_terms')
        ->assertHasComponent('btn_terms')
        ->assertHasComponent('btn_submit_register')
        ->assertHasComponent('btn_cancel_register')
        ->assertHasComponent('btn_close_modal');

    expect($harness->findComponent('roles'))->toBeNull()
        ->and($harness->findComponent('send_verification_email'))->toBeNull();
});

it('renders admin modal register screen with role selector and verification email checkbox', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $harness = ScreenTestHarness::for(Register::class)
        ->render(['fakeData' => false, 'askForRole' => true], shouldReset: true);

    $harness
        ->assertHasComponent('name')
        ->assertHasComponent('email')
        ->assertHasComponent('password')
        ->assertHasComponent('password_confirmation')
        ->assertHasComponent('roles')
        ->assertHasComponent('send_verification_email')
        ->assertHasComponent('btn_submit_register');

    expect($harness->findComponent('accept_terms'))->toBeNull()
        ->and($harness->findComponent('btn_terms'))->toBeNull();
});

it('allows both guests and admins with users_manager access to pass Register::checkAccess()', function () {
    // 1. Guest access
    Auth::logout();
    expect(Register::checkAccess()['allowed'])->toBeTrue();

    // 2. Authenticated regular user without admin permission is denied
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);
    expect(Register::checkAccess()['allowed'])->toBeFalse();

    // 3. Authenticated admin with admin.users_manager.access is allowed
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);
    expect(Register::checkAccess()['allowed'])->toBeTrue();
});

it('opens Register as modal from UsersManager and closes cleanly when admin clicks close or cancel', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    // 1. Open Register modal from UsersManager via ScreenTestHarness
    $usersManagerHarness = ScreenTestHarness::for(UsersManager::class)
        ->render(shouldReset: true)
        ->click('add_user_btn');

    $usersManagerHarness
        ->assertModal(Register::class)
        ->assertNoRedirect();

    // 2. Render Register as modal and click close button ('✕')
    $registerModal = Screen::openAsModal(
        params: ['fakeData' => false, 'askForRole' => true],
        caller: $usersManagerHarness->getScreen(),
        screenClass: Register::class
    );

    $registerHarness = ScreenTestHarness::for($registerModal);

    $registerHarness
        ->click('btn_close_modal')
        ->assertModalClosed()
        ->assertNoRedirect();

    // 3. Also verify cancel button closes modal cleanly
    $registerHarness
        ->click('btn_cancel_register')
        ->assertModalClosed()
        ->assertNoRedirect();
});

it('opens LegalTerms modal when clicking read terms button in guest mode', function () {
    $harness = ScreenTestHarness::for(Register::class)
        ->render(['fakeData' => false, 'askForRole' => false], shouldReset: true)
        ->click('btn_terms');

    $harness->assertModal(LegalTerms::class);
});

it('shows error toast and does not redirect when guest does not accept terms', function () {
    $harness = ScreenTestHarness::for(Register::class)
        ->render(['fakeData' => false, 'askForRole' => false], shouldReset: true)
        ->click('btn_submit_register', [
            'name' => 'Guest Without Terms',
            'email' => 'noterms@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => false,
        ]);

    $harness
        ->assertToast(t('screens.auth.register.terms_required'), 'error')
        ->assertComponentText('lbl_register_result', '')
        ->assertNoRedirect();

    expect(User::where('email', 'noterms@example.com')->exists())->toBeFalse()
        ->and(Auth::check())->toBeFalse();
});

it('shows formatted validation error in toast and label when registration fields are invalid', function () {
    $harness = ScreenTestHarness::for(Register::class)
        ->render(['fakeData' => false, 'askForRole' => false], shouldReset: true)
        ->click('btn_submit_register', [
            'name' => 'Mismatch User',
            'email' => 'mismatch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
            'accept_terms' => true,
        ]);

    $harness
        ->assertToast(null, 'error')
        ->assertNoRedirect();

    $labelComponent = $harness->getComponent('lbl_register_result');
    $labelText = method_exists($labelComponent, 'getText') ? (string) $labelComponent->getText() : '';
    expect($labelText)->not->toBeEmpty()
        ->and(User::where('email', 'mismatch@example.com')->exists())->toBeFalse();
});

it('registers a guest user, starts session, stores token and navigates to home screen', function () {
    Auth::logout();

    $harness = ScreenTestHarness::for(Register::class)
        ->render(['fakeData' => false, 'askForRole' => false], shouldReset: true)
        ->click('btn_submit_register', [
            'name' => 'New Guest User',
            'email' => 'newguest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => true,
        ]);

    $createdUser = User::where('email', 'newguest@example.com')->first();

    expect($createdUser)->not->toBeNull()
        ->and(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($createdUser->id);

    $harness
        ->assertToast(t('service.auth.register.success'), 'success')
        ->assertStorageHas('store_token')
        ->assertRedirect(Home::getRoutePath());
});

it('creates a user from admin modal, dispatches user_created event, closes modal and preserves admin session', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    Event::fake([UsimEvent::class]);

    $callerScreen = Screen::make(UsersManager::class);
    $registerModal = Screen::openAsModal(
        params: ['fakeData' => false, 'askForRole' => true],
        caller: $callerScreen,
        screenClass: Register::class
    );

    $harness = ScreenTestHarness::for($registerModal)
        ->click('btn_submit_register', [
            'name' => 'Created By Admin',
            'email' => 'createdbyadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['publisher'],
            'send_verification_email' => false,
        ]);

    $newUser = User::where('email', 'createdbyadmin@example.com')->first();

    expect($newUser)->not->toBeNull()
        ->and(Auth::id())->toBe($admin->id);

    $harness
        ->assertToast(t('service.auth.register.success'), 'success')
        ->assertModalClosed()
        ->assertNoRedirect();

    Event::assertDispatched(UsimEvent::class, function (UsimEvent $event) use ($newUser): bool {
        return $event->eventName === 'user_created'
            && ($event->params['user_id'] ?? null) === $newUser->id;
    });
});

it('maps and normalizes RegisterRequest payload for guest and admin flows', function () {
    $guestData = RegisterRequest::toRegisterData([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'accept_terms' => '1',
    ], askForRole: false);

    expect($guestData->name)->toBe('Alice')
        ->and($guestData->email)->toBe('alice@example.com')
        ->and($guestData->acceptTerms)->toBeTrue()
        ->and($guestData->roles)->toBe([config('usim.default_registering_role', 'registered')])
        ->and($guestData->sendVerificationEmail)->toBeTrue();

    $adminData = RegisterRequest::toRegisterData([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'roles' => ['admin', 'publisher'],
        'send_verification_email' => false,
    ], askForRole: true);

    expect($adminData->acceptTerms)->toBeTrue()
        ->and($adminData->roles)->toBe(['admin', 'publisher'])
        ->and($adminData->sendVerificationEmail)->toBeFalse();

    $errorResult = RegistrationResult::failed('Error base', [
        'email' => ['El correo ya existe.'],
        'password' => ['La contraseña es muy corta.'],
    ]);

    expect(RegisterRequest::formatErrorMessage($errorResult))
        ->toBe('El correo ya existe. La contraseña es muy corta.');
});
