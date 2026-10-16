<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens;

use App\Models\User;
use App\UI\Screens\Auth\Profile;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Image;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class Registered extends Screen
{
    protected Image $registered_avatar;
    protected Label $registered_greeting;

    public static function authorize(): bool
    {
        return self::requireAuth();
    }

    public static function getMenuLabel(): string
    {
        return 'Inicio';
    }

    public static function getMenuIcon(): ?string
    {
        return '⏳';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $user = Auth::user();
        $userName = $user instanceof User ? $user->name : '';

        $message = 'Se ha registrado en el sistema, en breve se le asignará un rol. Mientras tanto, puede actualizar su perfil y su foto de avatar.';

        $container
            ->layout(LayoutType::VERTICAL)
            ->plain()
            ->justifyContent('start')
            ->alignItems('center')
            ->padding(Spacing::px(30))
            ->minHeight(Size::vh(80));

        $container->add(
            UI::label('registered_icon')
                ->text('⏳')
                ->style('h1')
                ->center()
                ->fontSize('64px')
        );

        // Vista previa del avatar usando el componente UI::image()
        $this->registered_avatar = UI::image('registered_avatar')
            ->alt($userName)
            ->width(Size::px(96))
            ->height(Size::px(96))
            ->circular()
            ->objectFit('cover')
            ->shadow(true)
            ->visible(false);

        $container->add($this->registered_avatar);

        $container->add(
            UI::label('registered_title')
                ->text('Cuenta Pendiente de Asignación de Rol')
                ->style('h2')
                ->center()
                ->fontSize('26px')
                ->fontWeight('bold')
                ->color('#00d4aa')
                ->marginTop(Spacing::px(12))
        );

        $this->registered_greeting = UI::label('registered_greeting')
            ->text("¡Hola, {$userName}!")
            ->style('h3')
            ->center()
            ->fontSize('18px')
            ->color('#7a8499')
            ->marginTop(Spacing::px(6));

        $container->add($this->registered_greeting);

        $container->add(
            UI::card('registered_card')
                ->title('Estado de su cuenta')
                ->description($message)
                ->theme('info')
                ->maxWidth(Size::px(650))
                ->marginTop(Spacing::px(24))
                ->addAction('👤 Editar Mi Perfil / Avatar', 'go_to_profile', [], 'primary')
        );

        $container->add(
            UI::label('registered_message')
                ->text('Un administrador revisará su solicitud y le asignará el rol de Publicador o Administrador.')
                ->style('p')
                ->center()
                ->maxWidth(Size::px(650))
                ->fontSize('15px')
                ->marginTop(Spacing::px(16))
                ->color('#7a8499')
        );
    }

    protected function postLoadUI(): void
    {
        $user = Auth::user();
        if ($user instanceof User) {
            $this->refreshUserView($user);
        }
    }

    /**
     * Abre el modal de Perfil en modo SPA.
     */
    public function onGoToProfile(array $params): void
    {
        $this->openModal(Profile::class);
    }

    /**
     * Escucha el evento 'updated_profile' emitido por la pantalla Profile
     * para actualizar reactivamente el nombre y el avatar sin recargar la página.
     */
    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? Auth::user();
        if ($user instanceof User) {
            $this->refreshUserView($user);
        }
    }

    private function refreshUserView(User $user): void
    {
        $this->registered_greeting->text("¡Hola, {$user->name}!");

        if (\is_string($user->profile_image) && $user->profile_image !== '') {
            $imageUrl = UploadService::fileUrl("uploads/images/{$user->profile_image}") . '?t=' . time();
            $this->registered_avatar
                ->src($imageUrl)
                ->alt($user->name)
                ->visible(true);
        } else {
            $this->registered_avatar->visible(false);
        }
    }
}
