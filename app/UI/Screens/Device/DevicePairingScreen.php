<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Device;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Timer;
use Idei\Usim\Contracts\DeviceSecurityGuardInterface;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\Support\DevicePairingManager;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class DevicePairingScreen extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    /**
     * La pantalla de emparejamiento no debe mostrar layout ni menú de navegación.
     */
    public static ?string $layout = null;

    protected string $store_session_token = '';

    protected string $state_pin = '';

    protected string $store_token = '';

    protected Label $lbl_pin_display;

    protected Label $lbl_status;

    protected ?Timer $tmr_pairing_poll = null;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->updatePIN();

        $container
            ->maxWidth(Size::px(800))
            ->centerHorizontal()
            ->padding(Spacing::px(50));

        $container->add(
            UI::label('lbl_title')
                ->text('Vincular este Dispositivo')
                ->style('h1')
        );

        $container->add(
            UI::label('lbl_instructions')
                ->text('Ingresa al panel de administración de USIM en tu computadora e introduce el siguiente PIN para autorizar este equipo.')
                ->center()
                ->animation('fadeIn')
                ->style('h3')
        );

        $container->add(
            UI::label('lbl_pin_display')
                ->text($this->state_pin)
                ->style('h1')
        );

        $container->add(
            UI::label('lbl_status')
                ->text('Esperando autorización...')
                ->style('warning')
        );

        $container->add(
            UI::timer('tmr_pairing_poll')
                ->action('check_status')
                ->every(4000)
        );
    }

    protected function postLoadUI(): void
    {
        $this->updatePIN();
        $this->lbl_pin_display->text($this->state_pin);
    }

    protected function updatePIN(): void
    {
        /** @var DevicePairingManager $manager */
        $manager = app(DevicePairingManager::class);

        $hasActiveSession = false;
        if (! empty($this->store_session_token)) {
            $hasActiveSession = $manager->pollStatus($this->store_session_token) !== null;
        }

        if (! $hasActiveSession || empty($this->state_pin)) {
            $pairingData = $manager->initiate();
            $this->store_session_token = $pairingData['session_token'];
            $this->state_pin = $pairingData['pin'];
        }
    }

    /**
     * Handle the polling action from the Smart TV
     *
     * @param  array<string, mixed>  $params
     */
    public function onCheckStatus(array $params): void
    {
        /** @var DevicePairingManager $manager */
        $manager = app(DevicePairingManager::class);
        $status = $manager->pollStatus($this->store_session_token);

        if ($status === null) {
            // Expiró
            $this->store_session_token = '';
            $this->state_pin = '';
            $this->tmr_pairing_poll?->stop();
            $this->toast('El PIN expiró. Generando uno nuevo...', 'warning');
            $this->showInto(self::class, force: true, updateBrowserUrl: true);

            return;
        }

        if ($status !== 'pending') {
            // ¡Aprobado! $status contiene el token de Sanctum
            $this->store_session_token = '';
            $this->state_pin = '';
            $this->tmr_pairing_poll?->stop();

            // Persistimos el token en el storage del dispositivo
            $this->store_token = $status;

            // Iniciar sesión en el guard 'device' mediante el contrato DeviceSecurityGuardInterface
            $deviceSecurity = app(DeviceSecurityGuardInterface::class);
            $device = $deviceSecurity->resolveDeviceByToken($status);
            if ($device !== null) {
                $deviceSecurity->authenticateDevice($device);
            }

            $this->toast('¡Dispositivo vinculado exitosamente!', 'success');
            $this->showInto(KioskScreen::class, force: true, updateBrowserUrl: true);

            return;
        }

        // Si sigue pendiente, damos feedback visual
        $this->lbl_status
            ->text('Aún esperando autorización... (Última revisión: '.now()->format('H:i:s').')')
            ->style('info');
    }
}
