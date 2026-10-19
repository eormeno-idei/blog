<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Auth;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class Profile extends Screen
{
    protected ?Input $input_email = null;
    protected ?Input $input_name = null;
    protected ?Uploader $uploader_profile = null;

    public static function authorize(): bool
    {
        // This screen should only be accessible to authenticated users
        return self::requireAuth();
    }

    public static function getMenuLabel(): string
    {
        return 'Mi Perfil';
    }

    public static function getMenuIcon(): ?string
    {
        return '👤';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $user = Auth::user();

        if (!$user instanceof \App\Models\User) {
            $wrapper = UI::container('profile_wrapper')
                ->maxWidth(Size::px(600))
                ->centerHorizontal()
                ->plain()
                ->padding(Spacing::each(
                    Spacing::px(5),
                    Spacing::px(20),
                    Spacing::px(20),
                    Spacing::px(20)
                ));

            if ($this->isOpenedAsModal()) {
                $header = UI::container('profile_modal_header')
                    ->layout(LayoutType::HORIZONTAL)
                    ->justifyContent(JustifyContent::SPACE_BETWEEN)
                    ->alignItems(AlignItems::CENTER)
                    ->plain()
                    ->shadow(false)
                    ->padding(Spacing::zero())
                    ->margin(Spacing::px(5));

                $header->add(
                    UI::label('lbl_title')
                        ->text("👤 Configuración de Perfil")
                        ->style('h3')
                );

                $header->add(
                    UI::button('btn_close_modal')
                        ->label('✕')
                        ->action('close_modal')
                        ->style('secondary')
                        ->variant('ghost')
                        ->plain()
                );

                $wrapper->add($header);
            }

            $wrapper->add(
                UI::label('lbl_unauthenticated')
                    ->text(t('screens.auth.profile.unauthenticated'))
                    ->style('error')
            );

            $container->add($wrapper);
            return;
        }

        $wrapper = UI::container('profile_wrapper')
            ->maxWidth(Size::px(600))
            ->centerHorizontal()
            ->plain()
            ->padding(Spacing::each(
                Spacing::px(5),
                Spacing::px(20),
                Spacing::px(20),
                Spacing::px(20)
            ));

        // Encabezado
        if ($this->isOpenedAsModal()) {
            $header = UI::container('profile_modal_header')
                ->layout(LayoutType::HORIZONTAL)
                ->justifyContent(JustifyContent::SPACE_BETWEEN)
                ->alignItems(AlignItems::CENTER)
                ->plain()
                ->shadow(false)
                ->padding(Spacing::zero())
                ->margin(Spacing::px(5));

            $header->add(
                UI::label('lbl_title')
                    ->text("👤 Configuración de Perfil")
                    ->style('h3')
            );

            $header->add(
                UI::button('btn_close_modal')
                    ->label('✕')
                    ->action('close_modal')
                    ->style('secondary')
                    ->variant('ghost')
                    ->plain()
            );

            $wrapper->add($header);
        } else {
            // Título estándar
            $wrapper->add(
                UI::label('lbl_title')
                    ->text("👤 Configuración de Perfil")
                    ->style('primary')
                    ->fontSize('20px')
                    ->fontWeight('bold')
            );
        }

        // Email (readonly)
        $this->input_email = UI::input('input_email')
            ->label(t('screens.auth.profile.email.label'))
            ->type('email')
            ->value((string) $user->email)
            ->disabled(true)
            ->width(Size::full());

        $wrapper->add($this->input_email);

        // Nombre
        $this->input_name = UI::input('input_name')
            ->label(t('screens.auth.profile.name.label'))
            ->type('text')
            ->placeholder(t('screens.auth.profile.name.placeholder'))
            ->value($user->name ?? '')
            ->required(true)
            ->width(Size::full());

        $wrapper->add($this->input_name);

        // Foto de perfil
        $this->uploader_profile = UI::uploader('uploader_profile')
            ->allowedTypes(['image/*'])
            ->label(t('screens.auth.profile.photo.label'))
            ->maxFiles(1)
            ->maxSize(2)
            ->aspect(AspectRatio::_1_1)
            ->size(1);

        $wrapper->add($this->uploader_profile);

        // Botones de acción
        $wrapper->add(
            UI::button('btn_save_profile')
                ->label(t('screens.auth.profile.actions.save'))
                ->action('save_profile')
                ->style('primary')
                ->width(Size::full())
        );

        $wrapper->add(
            UI::button('btn_change_password')
                ->label(t('screens.auth.profile.actions.change_password'))
                ->action('change_password')
                ->style('secondary')
                ->width(Size::full())
        );

        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        $user = Auth::user();

        if (!$user instanceof \App\Models\User || $this->input_email === null || $this->input_name === null || $this->uploader_profile === null) {
            return;
        }

        $profileImage = $user->profile_image;

        // Actualizar inputs con datos actuales del usuario
        $this->input_email->value((string) $user->email);
        $this->input_name->value($user->name ?? '');

        if (!$user->email_verified_at) {
            $this->input_email->error(t('screens.auth.profile.email.not_verified'));
        } else {
            $this->input_email->error(null);
        }

        $imageUrl = null;

        // Actualizar uploader con imagen actual (si existe)
        if (\is_string($profileImage) && $profileImage !== '') {
            $imageUrl = UploadService::fileUrl("uploads/images/{$profileImage}") . '?t=' . time();
        }

        $this->uploader_profile->existingFile($imageUrl);
    }

    /**
     * Guardar cambios del perfil
     */
    /** @param array<string, mixed> $params */
    public function onSaveProfile(array $params): void
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();

            if (!$user instanceof \App\Models\User || $this->input_name === null || $this->uploader_profile === null) {
                $this->toast(t('screens.auth.profile.toast.unauthenticated'), 'error');
                return;
            }

            // Obtener datos del formulario
            $rawName = $params['input_name'] ?? '';
            $name = is_string($rawName) ? trim($rawName) : '';

            if (empty($name)) {
                $this->input_name->error(t('screens.auth.profile.validation.name_required'));
                return;
            }

            // Actualizar nombre
            $user->name = $name;

            // Procesar imagen de perfil si fue subida
            $confirmedFile = $this->uploader_profile->confirm($params, 'images', $user->profile_image);

            if (is_string($confirmedFile) && $confirmedFile !== '') {
                $user->profile_image = $confirmedFile;
            } elseif (is_array($confirmedFile) && isset($confirmedFile[0]) && $confirmedFile[0] !== '') {
                $user->profile_image = $confirmedFile[0];
            }

            // Guardar cambios
            $user->save();
            $this->input_name->error(null);

            event(new UsimEvent('updated_profile', [
                'user' => $user
            ]));

            $this->closeModal(); // Cerrar modal después de guardar

            // Mostrar éxito
            $this->toast(t('screens.auth.profile.toast.updated'), 'success');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error saving profile: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->toast(t('screens.auth.profile.toast.save_error', ['message' => $e->getMessage()]), 'error');
        }
    }

    /**
     * Reenviar email de verificación
     */
    /** @param array<string, mixed> $params */
    public function onResendVerification(array $params): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->email_verified_at) {
            $this->toast(t('screens.auth.profile.toast.already_verified'), 'info');
            return;
        }

        // Enviar notificación de verificación
        $user->sendEmailVerificationNotification();

        $this->toast(t('screens.auth.profile.toast.verification_sent'), 'success');
    }

    /**
     * Cambiar contraseña
     */
    /** @param array<string, mixed> $params */
    public function onChangePassword(array $params): void
    {
        $user = Auth::user();

        if (!$user instanceof \App\Models\User) {
            return;
        }

        // Enviar email de reset de contraseña
        $status = Password::sendResetLink([
            'email' => (string) $user->email
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->toast(t('screens.auth.profile.toast.password_link_sent'), 'success');
        } else {
            $this->toast(t('screens.auth.profile.toast.password_link_error'), 'error');
        }
    }
}
