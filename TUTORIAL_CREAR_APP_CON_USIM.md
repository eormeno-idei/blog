# 🚀 Tutorial: Crear un Gestor de Posts con Laravel y USIM Framework

En este tutorial aprenderás a construir desde cero una aplicación **Gestor de Posts** 100% **SPA (Single Page Application)** utilizando **Laravel** y **USIM Framework** (Server-Driven UI en PHP).

---

## 🎯 ¿Qué vamos a construir?

Construiremos una aplicación didáctica pensada para enseñar los conceptos esenciales de USIM a un desarrollador Laravel:

1. **Una única Unidad Organizacional (`lobby`)**: Simplificamos la estructura multi-unidad dejando únicamente `lobby` en `config/usim.php`.
2. **Cuatro estados/roles de usuario con comportamientos bien diferenciados**:
   - **`guest` (Invitado / No autenticado)**: Ve la pantalla `Home` con los últimos posts publicados y botones para iniciar sesión o registrarse mediante modales SPA.
   - **`registered` (Recién registrado)**: Ve **únicamente** la pantalla `Registered` con el mensaje *"Se ha registrado en el sistema, en breve se le asignará un rol"*, desde donde puede editar su perfil y cambiar su **avatar** (usando `UI::uploader()` y `UI::image()`).
   - **`publisher` (Publicador)**: Ve los últimos posts en `Home` y puede acceder a la pantalla `PublishPost` para publicar nuevos posts con imagen de portada (`UI::uploader()`).
   - **`admin` (Administrador)**: Además de publicar, puede **eliminar posts** directamente desde `Home` y acceder al **Gestor de Usuarios (`UsersManager`)** para aprobar a los usuarios `registered` asignándoles el rol `publisher` o `admin`.
3. **Componentes Multimedia**: Uso práctico de `UI::uploader()` para subir imágenes temporales y confirmarlas, y `UI::image()` para renderizar portadas de posts y avatares.
4. **Navegación 100% SPA**: Toda transición entre pantallas y apertura de formularios ocurre mediante `$this->showInto(...)`, `$this->openModal(...)` y `MenuBuilder::screenShow(...)`, **sin utilizar `$this->redirect()` ni recargas de página**.
5. **Idioma único (Español)**: Configurado directamente en español (`es`) sin complejidad multi-idioma.

---

## 🛠️ Paso 1: Creación del Proyecto e Instalación de USIM

### 1.1 Crear el proyecto Laravel e instalar el paquete

```bash
composer create-project laravel/laravel gestor-posts
cd gestor-posts

# Instalar el paquete USIM
composer require idei/usim

# Ejecutar el instalador interactivo/automático de USIM
php artisan usim:install --force --no-interaction
```

> [!TIP]
> **Desarrolladores en el monorepo de USIM Framework**:
> Si te encuentras trabajando dentro del propio repositorio de desarrollo de `usim-framework`, no necesitas ejecutar `composer create-project` ni requerir el paquete manualmente. Puedes ejecutar directamente el script:
> ```bash
> ./scripts/rebuild_dev.sh
> ```
> Este script crea automáticamente el entorno `dev/`, inicializa Laravel Octane con Roadrunner y enlaza el paquete local `@dev` de USIM.

> [!NOTE]
> Durante la ejecución de `php artisan usim:install`, USIM publica automáticamente varias migraciones necesarias para el sistema, incluyendo `add_profile_image_to_users_table`, que añade el campo `profile_image` a la tabla `users` para soportar la carga de avatares.

### 1.2 Configurar el idioma Español en `.env`

Edita tu archivo `.env` para fijar el idioma de la aplicación en español (`es`):

```env
APP_NAME="USIM Gestor de Posts"
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
```

### 1.3 Habilitar `profile_image` en el modelo `User.php`

Para que el modelo `User` pueda asignar y persistir de forma segura la imagen de perfil subida por el usuario en la pantalla `Profile`, asegúrate de incluir `'profile_image'` en los atributos asignables de `app/Models/User.php`:

```php
#[Fillable(['name', 'email', 'password', 'profile_image'])]
class User extends Authenticatable implements ...
```
*(O mediante la propiedad protegida `$fillable = ['name', 'email', 'password', 'profile_image'];` según tu versión de Laravel).*

---

## ⚙️ Paso 2: Configurar `config/usim.php` (Unidad `lobby` y Roles)

Reemplaza el contenido de `config/usim.php` para definir:
- **Una sola unidad organizacional**: `lobby` (tipo `system`).
- **Roles del sistema**: `root`, `admin`, `publisher` y `registered` (el rol por defecto al registrarse).
- **Pantallas de inicio (`home_screen`) por rol**:
  - `registered` $\rightarrow$ `\App\UI\Screens\Registered::class`
  - `publisher`, `admin`, `root` $\rightarrow$ `\App\UI\Screens\Home::class`
- **Usuarios de semilla**: asignados a la unidad `lobby` con sus respectivos roles (`root`, `admin`, `publisher`, `registered`).
- **Idioma español (`es`)** como idioma activo.

Edita `config/usim.php`:

```php
<?php

use App\UI\Screens\Home;
use App\UI\Screens\Registered;

return [

    /*
    |--------------------------------------------------------------------------
    | Front Store Key
    |--------------------------------------------------------------------------
    */
    'front_store_key' => env('FRONT_STORE_KEY', 'usim-posts-store-key'),

    /*
    |--------------------------------------------------------------------------
    | UI Screens Namespace & Path
    |--------------------------------------------------------------------------
    */
    'screens_namespace' => 'App\\UI\\Screens',
    'screens_path' => app_path('UI/Screens'),

    /*
    |--------------------------------------------------------------------------
    | Default Layout & Menu Screen
    |--------------------------------------------------------------------------
    */
    'default_layout' => 'App\\UI\\Layouts\\MainLayout',
    'default_menu_screen' => 'App\\UI\\Screens\\Menu',

    /*
    |--------------------------------------------------------------------------
    | Application Name & API Base URL
    |--------------------------------------------------------------------------
    */
    'app_name' => env('APP_NAME', 'Gestor de Posts USIM'),
    'api_url' => env('API_BASE_URL', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Upload Disk
    |--------------------------------------------------------------------------
    */
    'upload_disk' => env('UPLOAD_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Models Configuration
    |--------------------------------------------------------------------------
    */
    'models' => [
        'user' => \App\Models\User::class,
        'device' => \App\Models\Device::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Unidades Organizacionales (Sólo "lobby")
    |--------------------------------------------------------------------------
    */
    'units' => [
        'structure' => [
            'lobby' => [
                'parent' => null,
                'type' => 'system',
                'default_translations' => [
                    'es' => [
                        'display_name' => 'Lobby',
                        'description' => 'Unidad única del sistema.',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usuarios Iniciales (Seed Users)
    |--------------------------------------------------------------------------
    */
    'users' => [
        'root' => [
            'first_name' => env('ROOT_FIRST_NAME', 'Root'),
            'last_name' => env('ROOT_LAST_NAME', 'User'),
            'email' => env('ROOT_EMAIL', 'root@example.com'),
            'password' => env('ROOT_PASSWORD', '11111111'),
            'unit_roles' => [
                'lobby' => ['root'],
            ],
        ],

        'admin' => [
            'first_name' => 'Admin',
            'last_name' => 'Posts',
            'email' => 'admin@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'lobby' => ['admin'],
            ],
        ],

        'publisher' => [
            'first_name' => 'Autor',
            'last_name' => 'Publicador',
            'email' => 'publisher@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'lobby' => ['publisher'],
            ],
        ],

        'registered' => [
            'first_name' => 'Usuario',
            'last_name' => 'Registrado',
            'email' => 'registered@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'lobby' => ['registered'],
            ],
        ],
    ],

    'devices' => [],

    /*
    |--------------------------------------------------------------------------
    | Roles del Sistema
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'root' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Raíz',
                    'description' => 'Acceso total al sistema.',
                ],
            ],
            'priority' => 1,
            'home_screen' => Home::class,
            'permissions' => [
                'home.access',
                'publish_post.access',
                'posts.delete',
                'admin.users_manager.access',
                'manage.roles',
                'create.users',
                'remove.users',
            ],
        ],

        'admin' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Administrador',
                    'description' => 'Puede eliminar posts y asignar el rol de publisher o admin a usuarios registrados.',
                ],
            ],
            'priority' => 2,
            'home_screen' => Home::class,
            'permissions' => [
                'home.access',
                'publish_post.access',
                'posts.delete',
                'admin.users_manager.access',
                'manage.roles',
                'create.users',
                'remove.users',
            ],
        ],

        'publisher' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Publicador',
                    'description' => 'Puede crear y publicar nuevos posts con imagen.',
                ],
            ],
            'priority' => 3,
            'home_screen' => Home::class,
            'permissions' => [
                'home.access',
                'publish_post.access',
            ],
        ],

        'registered' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Usuario Registrado',
                    'description' => 'Usuario recién registrado pendiente de asignación de rol.',
                ],
            ],
            'priority' => 5,
            'home_screen' => Registered::class,
            'permissions' => [
                'registered.access',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'manage.roles' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Gestionar Roles',
                    'description' => 'Permite asignar y editar roles y permisos.',
                ],
            ],
        ],
        'create.users' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Crear Usuarios',
                    'description' => 'Permite crear nuevos usuarios en el sistema.',
                ],
            ],
        ],
        'remove.users' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Eliminar Usuarios',
                    'description' => 'Permite eliminar usuarios del sistema.',
                ],
            ],
        ],
        'publish_post.access' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Publicar Posts',
                    'description' => 'Permite acceder a la pantalla de creación de posts.',
                ],
            ],
        ],
        'posts.delete' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Eliminar Posts',
                    'description' => 'Permite eliminar cualquier post publicado.',
                ],
            ],
        ],
    ],

    'default_registering_role' => 'registered',

    /*
    |--------------------------------------------------------------------------
    | Internacionalización (Sólo Español Activo)
    |--------------------------------------------------------------------------
    */
    'i18n' => [
        'default_locale' => env('USIM_DEFAULT_LOCALE', env('APP_LOCALE', 'es')),
        'fallback_locale' => env('USIM_FALLBACK_LOCALE', 'es'),
        'auto_key_max_length' => (int) env('USIM_I18N_AUTO_KEY_MAX_LENGTH', 30),
        'log_channel' => env('USIM_I18N_LOG_CHANNEL', 'i18n'),
        'log_autokey_suggestions' => env('USIM_I18N_LOG_AUTOKEY_SUGGESTIONS', false),
        'languages' => [
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'active' => true],
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'active' => false],
        ],
        'i18n_key_prefixes' => [
            'role' => 'role.',
            'permission' => 'permission.',
            'screen' => 'screens.',
            'screens' => 'screens.',
        ],
    ],

    'headless_mode' => env('USIM_HEADLESS_MODE', false),
];
```

---

## 🧩 Paso 3: Adaptar el Contexto de Unidad Única (`lobby`)

Por defecto, los servicios generados por `usim:install` asumen que existe una unidad operacional o una segunda unidad de sistema llamada `main`. Como nuestra aplicación simplifica la arquitectura utilizando **únicamente la unidad `lobby`**, adaptaremos los servicios y pantallas administrativas correspondientes:

### 3.1 `app/Services/User/UserService.php`

En `app/Services/User/UserService.php`, actualiza los dos métodos donde se verifica si el usuario está en el lobby para usar el método nativo `$user->isRegisteredInLobby()`, y ajusta la sincronización para que, al no existir `main`, mantenga al usuario en `lobby`:

1. En el método `getUser(int $userId)`:
```php
// Cambiar:
$isInLobby = $user->usimUnits->contains('slug', 'lobby');

// Por:
$isInLobby = $user->isRegisteredInLobby();
```

2. En el método `syncUserRolesAndUnits(User $user, array $roles, array $data)`:
```php
// Cambiar:
$isInLobby = $user->usimUnits()->where('slug', 'lobby')->exists();

// Por:
$isInLobby = $user->isRegisteredInLobby();
```

Y en la asignación de `$targetUnit` dentro de ese mismo método, utiliza `lobby` como fallback:
```php
// Si no hay unidades operacionales:
$targetUnit = UsimUnit::where('slug', 'main')->first()
    ?? UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);

// Y en el fallback final:
if (! $targetUnit) {
    $targetUnit = UsimUnit::where('slug', 'main')->first()
        ?? UsimUnit::where('slug', 'lobby')->first();
}

// Al remover el rol registrado, no desasociar el lobby si targetUnit es lobby:
if (! $targetUnit || $targetUnit->id !== $lobbyUnit->id) {
    $user->usimUnits()->detach($lobbyUnit->id);
}
```

### 3.2 `app/UI/Screens/Admin/TableModels/UserTableModel.php`

En `app/UI/Screens/Admin/TableModels/UserTableModel.php`, dentro del método privado `formatRoles(User $user, bool $hasOperationalUnits): string`:

```php
// Cambiar:
if (! $hasOperationalUnits) {
    $isInLobby = in_array('registered', $roleNames, true)
        || ($user->relationLoaded('usimUnits') && $user->usimUnits->contains('slug', 'lobby'));

    if ($isInLobby) {
        return t('role.registered.waiting');
    }
}

// Por:
if (! $hasOperationalUnits) {
    if ($user->isRegisteredInLobby()) {
        return t('role.registered.waiting');
    }
}
```

### 3.3 `app/UI/Screens/Admin/EditUser.php`

En `app/UI/Screens/Admin/EditUser.php`, cambia el rol sugerido por defecto para que sea `publisher` al aprobar usuarios:

```php
private const DEFAULT_FALLBACK_ROLE = 'publisher';
```

### 3.4 Fallbacks de Unidad en Servicios de Autenticación y Contexto

Para garantizar que el sistema opere fluidamente sin la unidad `main`:

- En `app/Services/Units/UnitContextResolver.php` (en el método para root):
```php
if ($isRoot) {
    return UsimUnit::where('slug', 'main')->first()
        ?? UsimUnit::where('slug', 'lobby')->first()
        ?? UsimUnit::orderBy('id')->first();
}
```

- En `app/Services/Auth/AuthSessionService.php` (en `establishSession`):
```php
$defaultUnit = UsimUnit::where('slug', 'main')->first()
    ?? UsimUnit::where('slug', 'lobby')->first()
    ?? UsimUnit::orderBy('id')->first();
```
Y en la emisión del evento `logged_user`:
```php
'unit' => $activeUnitSlug ?? 'lobby',
```

- En `app/Services/Auth/RegisterService.php`:
```php
$defaultSystemUnit = UsimUnit::where('slug', 'main')->exists() ? 'main' : 'lobby';
$unitSlug = $unit ?? ($isPendingRegistration ? 'lobby' : $defaultSystemUnit);
```

- En `app/UI/Screens/Admin/Concerns/ResolvesActiveUnitContext.php`:
```php
return UnitContextResolver::resolve($user, null)
    ?? UsimUnit::where('slug', 'main')->first()
    ?? UsimUnit::where('slug', 'lobby')->first();
```

---

## 🗄️ Paso 4: Migración y Capa de Servicios para Posts (SOLID)

### 4.1 Crear la migración de la tabla `posts`

Crea el archivo `database/migrations/2026_10_16_030000_create_posts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

### 4.2 Crear el Contrato `app/Contracts/PostServiceInterface.php`

Siguiendo el principio de Inversión de Dependencias (SOLID), nuestras Screens dependerán de una interfaz y no de consultas SQL directas:

```php
<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface PostServiceInterface
{
    /**
     * Obtiene los últimos posts ordenados por fecha descendente.
     */
    public function getLatestPosts(): Collection;

    /**
     * Busca un post por su ID.
     */
    public function findById(int $id): ?object;

    /**
     * Crea un nuevo post y devuelve su ID.
     *
     * @param array{user_id: int, title: string, content: string, image: ?string} $data
     */
    public function createPost(array $data): int;

    /**
     * Elimina un post por su ID.
     */
    public function deletePost(int $id): bool;
}
```

### 4.3 Crear la Implementación `app/Services/Post/DatabasePostService.php`

```php
<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabasePostService implements PostServiceInterface
{
    public function getLatestPosts(): Collection
    {
        return DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select('posts.*', 'users.name as author_name')
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->get();
    }

    public function findById(int $id): ?object
    {
        return DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select('posts.*', 'users.name as author_name')
            ->where('posts.id', $id)
            ->first();
    }

    public function createPost(array $data): int
    {
        return DB::table('posts')->insertGetId([
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'content' => $data['content'],
            'image' => $data['image'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function deletePost(int $id): bool
    {
        return DB::table('posts')->where('id', $id)->delete() > 0;
    }
}
```

### 4.4 Registrar el Servicio en `app/Providers/AppServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Contracts\PostServiceInterface;
use App\Services\Post\DatabasePostService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PostServiceInterface::class, DatabasePostService::class);
    }

    public function boot(): void
    {
        //
    }
}
```

---

## 🖥️ Paso 5: Crear y Modificar las Pantallas (Screens) en USIM

### 5.1 Pantalla de Espera para Usuarios Registrados (`app/UI/Screens/Registered.php`)

Cuando un usuario se registra en el sistema, obtiene el rol `registered`. Esta pantalla le informa que en breve un administrador le asignará un rol (`publisher` o `admin`), muestra su avatar actual con `UI::image()` y le permite abrir el modal `Profile` para cambiar su nombre o subir su avatar con `UI::uploader()`.

Edita `app/UI/Screens/Registered.php`:

```php
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
```

---

### 5.2 Pantalla para Publicar Posts (`app/UI/Screens/PublishPost.php`)

Esta pantalla utiliza `UI::uploader()` para subir la imagen de portada del post y `self::requireRole(['publisher', 'admin'])` para autorizar únicamente a publicadores y administradores. Al guardar el post, navega en modo SPA a `Home::class` usando `$this->showInto(Home::class, force: true, updateBrowserUrl: true)`.

Crea el archivo `app/UI/Screens/PublishPost.php`:

```php
<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class PublishPost extends Screen
{
    protected Input $post_title;
    protected Input $post_content;
    protected Uploader $post_image;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
    }

    public static function authorize(): bool
    {
        return self::requireRole(['publisher', 'admin']);
    }

    public static function getMenuLabel(): string
    {
        return 'Publicar Post';
    }

    public static function getMenuIcon(): ?string
    {
        return '✍️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->width(Size::full())
            ->maxWidth(Size::px(720))
            ->centerHorizontal()
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $header = UI::container('publish_header')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(16));

        $header->add(
            UI::label('lbl_publish_title')
                ->text('✍️ Publicar Nuevo Post')
                ->style('h2')
                ->color('primary')
        );

        $header->add(
            UI::button('btn_back_home')
                ->label('← Volver al Inicio')
                ->action('cancel_post')
                ->style('secondary')
        );

        $container->add($header);

        $formCard = UI::container('publish_form_card')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $this->post_title = UI::input('post_title')
            ->label('Título del Post')
            ->placeholder('Escribe un título atractivo...')
            ->required(true)
            ->width(Size::full());

        $this->post_content = UI::input('post_content')
            ->label('Contenido')
            ->placeholder('Desarrolla el contenido de tu publicación...')
            ->required(true)
            ->width(Size::full());

        // Componente Uploader para subir la imagen de portada
        $this->post_image = UI::uploader('post_image')
            ->images()
            ->maxFiles(1)
            ->maxSize(5)
            ->aspect('16:9')
            ->size(2)
            ->label('Imagen de Portada (opcional)');

        $actionsRow = UI::container('publish_actions_row')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(12))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $actionsRow->add(
            UI::button('btn_cancel_post')
                ->label('Cancelar')
                ->action('cancel_post')
                ->style('secondary')
        );

        $actionsRow->add(
            UI::button('btn_save_post')
                ->label('Publicar Post')
                ->action('save_post')
                ->style('primary')
        );

        $formCard->add($this->post_title);
        $formCard->add($this->post_content);
        $formCard->add($this->post_image);
        $formCard->add($actionsRow);

        $container->add($formCard);
    }

    public function onSavePost(array $params): void
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            $this->toast('Debes iniciar sesión para publicar.', 'error');
            return;
        }

        $rawTitle = $params['post_title'] ?? '';
        $rawContent = $params['post_content'] ?? '';

        $title = is_string($rawTitle) ? trim($rawTitle) : '';
        $content = is_string($rawContent) ? trim($rawContent) : '';

        $this->post_title->error(null);
        $this->post_content->error(null);

        $hasErrors = false;
        if ($title === '') {
            $this->post_title->error('El título es obligatorio.');
            $hasErrors = true;
        }

        if ($content === '') {
            $this->post_content->error('El contenido es obligatorio.');
            $hasErrors = true;
        }

        if ($hasErrors) {
            $this->toast('Por favor completa los campos obligatorios.', 'warning');
            return;
        }

        // Confirmar y mover la imagen temporal a storage/app/public/uploads/images/
        $confirmedImage = $this->post_image->confirm($params, 'images');
        $imageFilename = null;
        if (is_string($confirmedImage) && $confirmedImage !== '') {
            $imageFilename = $confirmedImage;
        } elseif (is_array($confirmedImage) && isset($confirmedImage[0]) && is_string($confirmedImage[0])) {
            $imageFilename = $confirmedImage[0];
        }

        $this->postService->createPost([
            'user_id' => (int) $user->id,
            'title' => $title,
            'content' => $content,
            'image' => $imageFilename,
        ]);

        $this->toast('¡Post publicado correctamente!', 'success');

        // Limpiar snapshot en caché de Home y navegar vía SPA forzando recarga de los últimos posts
        Screen::make(Home::class)->clearCachedScreenSnapshot();
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    public function onCancelPost(array $params): void
    {
        Screen::make(Home::class)->clearCachedScreenSnapshot();
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }
}
```

---

### 5.3 Pantalla Principal de Últimos Posts (`app/UI/Screens/Home.php`)

La pantalla `Home` es pública (`Visibility::PUBLIC`) y adapta su interfaz según quién la visite:
- Si el usuario autenticado sólo tiene el rol `registered`, embebe únicamente `Registered::class`.
- Si es `guest`, muestra los últimos posts y botones para abrir `Login` o `Register` en modal.
- Si es `publisher` o `admin`, muestra el botón **Publicar Post** (`showInto(PublishPost::class)`).
- Si es `admin`, muestra además el botón **Gestionar Usuarios** (`showInto(UsersManager::class)`) y el botón **🗑️ Eliminar** con diálogo modal de confirmación (`ConfirmDialog::open(...)`) en cada post.
- Cada post con imagen utiliza `UI::image()` para renderizar su portada.
- Implementa `postLoadUI()` para refrescar siempre el contenedor y volver a consultar los últimos posts desde la base de datos aunque el snapshot de `Home` se recupere de la caché de USIM.

Edita `app/UI/Screens/Home.php`:

```php
<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Register;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class Home extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected ?Container $posts_list_container = null;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
    }

    public static function getMenuLabel(): string
    {
        return 'Inicio';
    }

    public static function getMenuIcon(): ?string
    {
        return '🏠';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->renderHomeContent($container);
    }

    protected function postLoadUI(): void
    {
        $this->container->clear();
        $this->renderHomeContent($this->container);
    }

    private function renderHomeContent(Container $container): void
    {
        $user = Auth::user();

        // Si el usuario tiene únicamente el rol "registered", sólo debe ver la pantalla Registered
        if ($user instanceof User && $user->isRegisteredInLobby()) {
            $container
                ->plain()
                ->padding(Spacing::zero());
            Screen::make(Registered::class)->clearCachedScreenSnapshot();
            Screen::embedInto(Registered::class, $container);
            return;
        }

        $container
            ->maxWidth(Size::px(900))
            ->centerHorizontal()
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(20))
            ->plain();

        $header = UI::container('home_header')
            ->card()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(20))
            ->gap(Spacing::px(16));

        $titleBox = UI::container('home_title_box')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(4))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $titleBox->add(
            UI::label('lbl_home_title')
                ->text('📰 Últimos Posts')
                ->style('h2')
                ->color('primary')
        );

        $subtitleText = $this->resolveRoleSubtitle($user instanceof User ? $user : null);
        $titleBox->add(
            UI::label('lbl_home_subtitle')
                ->text($subtitleText)
                ->style('p')
                ->color('#7a8499')
        );

        $actionsBox = UI::container('home_actions_box')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(10))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        if (!$user instanceof User) {
            $actionsBox->add(
                UI::button('btn_home_login')
                    ->label('🔑 Iniciar Sesión')
                    ->action('open_login')
                    ->style('primary')
            );
            $actionsBox->add(
                UI::button('btn_home_register')
                    ->label('📝 Registrarse')
                    ->action('open_register')
                    ->style('secondary')
            );
        } else {
            if ($this->currentUserHasRole(['publisher', 'admin'])) {
                $actionsBox->add(
                    UI::button('btn_go_publish')
                        ->label('✍️ Publicar Post')
                        ->action('go_to_publish')
                        ->style('primary')
                );
            }

            if ($this->currentUserHasRole('admin')) {
                $actionsBox->add(
                    UI::button('btn_go_users')
                        ->label('👥 Gestionar Usuarios')
                        ->action('go_to_users')
                        ->style('secondary')
                );
            }
        }

        $header->add($titleBox);
        $header->add($actionsBox);
        $container->add($header);

        $this->posts_list_container = UI::container('posts_list_container')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(20))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $this->renderPostsList();

        $container->add($this->posts_list_container);
    }

    /**
     * Comprueba si el usuario autenticado posee alguno de los roles indicados.
     *
     * @param string|list<string> $roles
     */
    private function currentUserHasRole(string|array $roles): bool
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->isRoot()) {
            return true;
        }

        $roleList = is_array($roles) ? $roles : [$roles];

        return $user->globalRoles()->whereIn('name', $roleList)->exists();
    }

    private function resolveRoleSubtitle(?User $user): string
    {
        if ($user === null) {
            return 'Modo invitado (guest): visualiza las publicaciones recientes o inicia sesión para participar.';
        }

        if ($this->currentUserHasRole('admin')) {
            return "Conectado como {$user->name} (Administrador): puedes publicar posts, eliminarlos y asignar roles a usuarios registrados.";
        }

        if ($this->currentUserHasRole('publisher')) {
            return "Conectado como {$user->name} (Publicador): puedes compartir nuevos posts con la comunidad.";
        }

        return "Conectado como {$user->name}.";
    }

    private function renderPostsList(): void
    {
        $this->posts_list_container->clear();

        $posts = $this->postService->getLatestPosts();

        if ($posts->isEmpty()) {
            $emptyCard = UI::container('posts_empty_card')
                ->card()
                ->width(Size::full())
                ->padding(Spacing::px(32))
                ->alignItems(AlignItems::CENTER);

            $emptyCard->add(
                UI::label('lbl_no_posts')
                    ->text('Aún no hay posts publicados.')
                    ->style('h3')
                    ->center()
            );

            $this->posts_list_container->add($emptyCard);
            return;
        }

        $isAdmin = $this->currentUserHasRole('admin');

        foreach ($posts as $post) {
            $postCard = UI::container("post_card_{$post->id}")
                ->card()
                ->layout(LayoutType::VERTICAL)
                ->minWidth(Size::px(350))
                ->padding(Spacing::px(20))
                ->gap(Spacing::px(12));

            if (!empty($post->image) && is_string($post->image)) {
                $imageUrl = $this->resolvePostImageUrl($post->image);

                // Componente UI::image() para mostrar la portada del post
                $postCard->add(
                    UI::image("post_img_{$post->id}")
                        ->src($imageUrl)
                        ->alt((string) $post->title)
                        ->width(Size::full())
                        ->height(Size::px(220))
                        ->objectFit('cover')
                        ->rounded(8)
                );
            }

            $postHeader = UI::container("post_header_{$post->id}")
                ->layout(LayoutType::HORIZONTAL)
                ->justifyContent(JustifyContent::SPACE_BETWEEN)
                ->alignItems(AlignItems::CENTER)
                ->plain()
                ->shadow(false)
                ->padding(Spacing::zero());

            $postHeader->add(
                UI::label("post_title_{$post->id}")
                    ->text((string) $post->title)
                    ->style('h3')
            );

            if ($isAdmin) {
                $postHeader->add(
                    UI::button("btn_delete_post_{$post->id}")
                        ->label('🗑️ Eliminar')
                        ->action('confirm_delete_post', ['post_id' => (int) $post->id])
                        ->style('danger')
                );
            }

            $postCard->add($postHeader);

            $authorName = (string) ($post->author_name ?? 'Autor');
            $createdAt = !empty($post->created_at) ? (string) $post->created_at : '';

            $postCard->add(
                UI::label("post_meta_{$post->id}")
                    ->text("Publicado por {$authorName}" . ($createdAt !== '' ? " • {$createdAt}" : ''))
                    ->style('caption')
                    ->color('#7a8499')
            );

            $postCard->add(
                UI::label("post_content_{$post->id}")
                    ->text((string) $post->content)
                    ->style('p')
            );

            $this->posts_list_container->add($postCard);
        }
    }

    private function resolvePostImageUrl(string $image): string
    {
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return $image;
        }

        return UploadService::fileUrl("uploads/images/{$image}");
    }

    public function onOpenLogin(array $params): void
    {
        $this->openModal(Login::class);
    }

    public function onOpenRegister(array $params): void
    {
        $this->openModal(Register::class);
    }

    public function onGoToPublish(array $params): void
    {
        $this->showInto(PublishPost::class, updateBrowserUrl: true);
    }

    public function onGoToUsers(array $params): void
    {
        $this->showInto(UsersManager::class, updateBrowserUrl: true);
    }

    /**
     * Muestra un diálogo modal de confirmación antes de borrar el post.
     */
    public function onConfirmDeletePost(array $params): void
    {
        if (!$this->currentUserHasRole('admin')) {
            $this->toast('No tienes permisos para eliminar posts.', 'error');
            return;
        }

        $postId = isset($params['post_id']) && is_numeric($params['post_id'])
            ? (int) $params['post_id']
            : 0;

        if ($postId <= 0) {
            return;
        }

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::CONFIRM,
            title: '¿Eliminar publicación?',
            message: '¿Estás seguro de que deseas eliminar este post? Esta acción no se puede deshacer.',
            confirmAction: 'delete_post',
            confirmParams: ['post_id' => $postId],
            cancelAction: 'cancel_delete_post',
            confirmLabel: 'Sí, eliminar',
            cancelLabel: 'Cancelar',
        );
    }

    public function onCancelDeletePost(array $params): void
    {
        $this->closeModal();
    }

    public function onDeletePost(array $params): void
    {
        $this->closeModal();

        if (!$this->currentUserHasRole('admin')) {
            $this->toast('No tienes permisos para eliminar posts.', 'error');
            return;
        }

        $postId = isset($params['post_id']) && is_numeric($params['post_id'])
            ? (int) $params['post_id']
            : 0;

        if ($postId <= 0) {
            return;
        }

        $deleted = $this->postService->deletePost($postId);

        if ($deleted) {
            $this->toast('Post eliminado correctamente.', 'success');
            $this->renderPostsList();
        } else {
            $this->toast('No se encontró el post a eliminar.', 'warning');
        }
    }

    /**
     * Maneja el evento reactivo de dominio cuando un usuario inicia sesión.
     * Actualiza el contenido de la pantalla Home con los permisos y roles del usuario autenticado.
     *
     * @param array<string, mixed> $params
     */
    public function onLoggedUser(array $params): void
    {
        $this->container->clear();
        $this->renderHomeContent($this->container);
    }

    /**
     * Maneja el evento reactivo de dominio cuando un usuario cierra sesión.
     * Restablece el contenido de la pantalla Home al modo invitado (guest).
     *
     * @param array<string, mixed> $params
     */
    public function onUserLoggedOut(array $params): void
    {
        $this->container->clear();
        $this->renderHomeContent($this->container);
    }
}
```

> [!TIP]
> **Actualización Reactiva mediante `UsimEvent`:**
> En USIM, cuando un usuario inicia sesión (`logged_user`) o cierra sesión (`user_logged_out`), el despachador de eventos `UsimEventDispatcher` transmite el evento en **broadcast** a todas las pantallas abiertas concurrentemente del cliente (como `Menu` y `Home`).
> Al implementar `onLoggedUser()` y `onUserLoggedOut()` en `Home`, la pantalla limpia su contenedor y re-renderiza su contenido con los roles vigentes del usuario en la misma respuesta HTTP, sin necesidad de recargar la página en el navegador ni forzar navegaciones externas.


---

### 5.4 Menú Superior 100% SPA (`app/UI/Screens/Menu.php`)

Para garantizar que **ninguna acción del menú provoque una recarga del navegador**, utilizamos `MenuBuilder::screenShow()` en lugar de `MenuBuilder::link()`, y eliminamos los selectores multi-unidad y de idioma (la aplicación opera íntegramente en español dentro de la unidad `lobby`).

Reemplaza el archivo `app/UI/Screens/Menu.php`:

```php
<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens;

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Services\Units\UsimUnitsService;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Auth\Register;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Menu Screen
 *
 * Barra de navegación superior 100% SPA para el Gestor de Posts en USIM.
 */
class Menu extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    public static ?string $layout = null;

    public function __construct(
        protected AuthSessionService $authSessionService,
        protected UsimUnitsService $usimUnitsService
    ) {
    }

    protected MenuDropdown $main_menu;
    protected MenuDropdown $user_menu;
    protected Button $theme_toggle;
    protected string $store_theme = 'dark';
    protected string $store_token = '';

    protected function buildBaseUI(Container $container, ...$params): void
    {
        if ($this->parent === null || $this->parent === 'main') {
            $container->parent('menu');
        }

        $container
            ->card()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(5))
            ->marginBottom(Spacing::px(0));

        $this->initActiveUnit();

        $this->main_menu = $this->buildLeftMenu();
        $this->user_menu = $this->buildUserMenu();

        $this->theme_toggle = UI::button('theme_toggle')
            ->action('toggleTheme')
            ->plain();

        $rightSection = UI::container('menu_right_section')
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(12))
            ->marginLeft(Spacing::auto());

        $rightSection->add($this->theme_toggle);
        $rightSection->add($this->user_menu);

        $container->add($this->main_menu);
        $container->add($rightSection);
        $this->updateThemeButton();
    }

    private function updateThemeButton(): void
    {
        $icon = $this->store_theme === 'light' ? 'theme-icon-light' : 'theme-icon-dark';
        $this->theme_toggle->icon("/vendor/idei/usim/images/$icon.svg");
        $this->theme_toggle->iconColor('var(--usim-menu-trigger-text)');
        $this->theme_toggle->iconSize(24);
        $this->theme_toggle->tooltip(t('screens.menu.theme_switch_to', ['theme' => $this->store_theme === 'light' ? 'dark' : 'light']));
    }

    public function onToggleTheme(array $params): void
    {
        $this->store_theme = $this->store_theme === 'light' ? 'dark' : 'light';
        $this->updateThemeButton();
        event(new UsimEvent('theme_changed', ['theme' => $this->store_theme]));
        $this->changeTheme($this->store_theme);
    }

    private function initActiveUnit(): void
    {
        /** @var Collection<int, UsimUnit> $units */
        $units = $this->usimUnitsService->getAvailableUnits();
        if ($units->isEmpty()) {
            UIStateManager::setActiveUnit('lobby');
            return;
        }

        $cachedUnit = UIStateManager::getActiveUnit();
        if ($cachedUnit !== null && $units->contains('slug', $cachedUnit)) {
            $unit = $units->firstWhere('slug', $cachedUnit);
            if ($unit instanceof UsimUnit) {
                setPermissionsTeamId($unit->id);
            }
            return;
        }

        $first = $units->first();
        if ($first instanceof UsimUnit) {
            UIStateManager::setActiveUnit($first->slug);
            setPermissionsTeamId($first->id);
        }
    }

    protected function postLoadUI(): void
    {
        $this->updateThemeButton();

        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof User) {
                $this->updateUserMenuTrigger($user);
                $this->initActiveUnit();
            } else {
                $this->user_menu->trigger("⚙️");
            }
            $this->populateMainMenu($this->main_menu);
            $this->populateUserMenu($this->user_menu);
        } else {
            $this->user_menu->trigger("⚙️");
            $this->populateMainMenu($this->main_menu);
            $this->populateUserMenu($this->user_menu);
        }
    }

    private function updateUserMenuTrigger(User $user): void
    {
        if ($user->profile_image) {
            $imageUrl = UploadService::fileUrl("uploads/images/$user->profile_image");
            $this->user_menu->triggerImage(
                imageUrl: $imageUrl,
                alt: $user->name,
                label: $user->name
            );
        } else {
            $this->user_menu->trigger("👤 $user->name");
        }
    }

    private function getMainMenuBuilder(): MenuBuilder
    {
        $builder = MenuBuilder::make('main_menu')
            ->trigger()
            ->position('bottom-left')
            ->width(Size::px(220));

        $user = Auth::user();
        $isRegisteredOnly = $user instanceof User && $user->isRegisteredInLobby();

        // Navegación 100% SPA mediante screenShow() (sin recarga de página)
        $builder->screenShow(
            Registered::class,
            when: $isRegisteredOnly
        );

        $builder->screenShow(
            Home::class,
            when: !$isRegisteredOnly
        );

        $builder->screenShow(
            PublishPost::class,
            when: PublishPost::checkAccess()['allowed'] == true
        );

        $builder->screenShow(
            UsersManager::class,
            when: UsersManager::checkAccess()['allowed'] == true
        );

        $builder->separator();
        $builder->action(t('screens.menu.items.about'), 'show_about_info', [], 'ℹ️');

        return $builder;
    }

    private function buildLeftMenu(): MenuDropdown
    {
        return $this->getMainMenuBuilder()->render('main_menu');
    }

    private function populateMainMenu(MenuDropdown $menu): void
    {
        $this->getMainMenuBuilder()->populate($menu);
    }

    private function buildUserMenu(): MenuDropdown
    {
        $builder = $this->getUserMenuBuilder();
        $menu = $builder->render('user_menu');
        $menu->trigger("⚙️");
        return $menu;
    }

    private function populateUserMenu(MenuDropdown $menu): void
    {
        $builder = $this->getUserMenuBuilder();
        $builder->populate($menu);
    }

    private function getUserMenuBuilder(): MenuBuilder
    {
        $builder = MenuBuilder::make('user_menu')
            ->position('bottom-right')
            ->width(Size::px(180));

        $builder->screenShow(Login::class, modal: true, when: !Auth::check());
        $builder->screenShow(Register::class, modal: true, when: !Auth::check());
        $builder->screenShow(Profile::class, modal: true, when: Auth::check());

        $builder->action(
            t('screens.menu.items.logout'),
            'confirm_logout',
            [],
            '🚪',
            when: Auth::check()
        );

        return $builder;
    }

    public function onShowScreen(array $params): void
    {
        /** @var class-string<Screen>|string|null $screen */
        $screen = $params['screen'] ?? null;
        $modal = $params['modal'] ?? false;

        if ($screen !== null && is_subclass_of($screen, Screen::class)) {
            /** @var string|null $slot */
            $slot = isset($params['slot']) && \is_string($params['slot']) ? $params['slot'] : null;

            if ($modal) {
                $this->openModal($screen);
                return;
            }

            Screen::make($screen)->clearCachedScreenSnapshot();
            $this->showInto($screen, slot: $slot, updateBrowserUrl: true, force: true);
        }
    }

    public function onLoggedUser(array $params): void
    {
        /** @var User|null $user */
        $user = $params['user'] ?? null;
        $unitValue = $params['unit'] ?? '';
        $token = $params['token'] ?? null;

        if (\is_string($token) && $token !== '') {
            $this->store_token = $token;
        }

        if (\is_string($unitValue) && $unitValue !== '') {
            UIStateManager::setActiveUnit($unitValue);
        }

        if ($user instanceof User) {
            $this->updateUserMenuTrigger($user);
        }

        $this->populateUserMenu($this->user_menu);
        $this->populateMainMenu($this->main_menu);
    }

    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;
        if ($user instanceof User) {
            $this->updateUserMenuTrigger($user);
        }
    }

    public function onConfirmLogout(array $params): void
    {
        if (UIStateManager::getClientActiveModal() !== null) {
            $this->closeModal();
        }

        $homeScreen = $this->authSessionService->logout();

        $this->toast(t('screens.menu.logout_success'), 'success');
        $this->resetToGuestState($homeScreen);
    }

    public function onUserLoggedOut(array $params): void
    {
        if ($this->store_token === '' && !Auth::check()) {
            return;
        }

        /** @var class-string<Screen>|string|null $homeScreen */
        $homeScreen = $params['home_screen'] ?? Home::class;
        $targetClass = \is_string($homeScreen) && class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)
            ? $homeScreen
            : Home::class;

        $this->resetToGuestState($targetClass);
    }

    private function resetToGuestState(string $homeScreen): void
    {
        $this->store_token = '';

        $this->user_menu->trigger("⚙️");
        $this->populateUserMenu($this->user_menu);
        $this->populateMainMenu($this->main_menu);

        Screen::make(Home::class)->clearCachedScreenSnapshot();
        Screen::make(Registered::class)->clearCachedScreenSnapshot();
        if (class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)) {
            Screen::make($homeScreen)->clearCachedScreenSnapshot();
        }

        $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
    }

    public function onShowAboutInfo(array $params): void
    {
        $version = "0.7.0";
        $aboutMessage = t('screens.menu.about.message', [
            'version' => $version,
        ]);

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::INFO,
            title: t('screens.menu.about.title'),
            message: $aboutMessage,
        );
    }

    public function onCloseProfileDialog(array $params): void
    {
        $this->closeModal();
    }

    public function onLogoutUser(array $params): void
    {
        ConfirmDialog::open(
            caller: $this,
            type: DialogType::CONFIRM,
            title: t('screens.menu.logout_confirm.title'),
            message: t('screens.menu.logout_confirm.message'),
            confirmAction: 'confirm_logout',
            cancelAction: 'cancel_logout',
        );
    }

    public function onCancelLogout(array $params): void
    {
        $this->closeModal();
    }
}
```

---

### 5.5 Adaptaciones en las Pantallas de Autenticación (`Login.php`, `Register.php`, `Profile.php`)

Para garantizar que el registro y el inicio de sesión se integren con la navegación SPA sin recargas:

1. **`app/UI/Screens/Auth/Login.php`**:
   - Agrega las etiquetas de menú en español:
   ```php
   public static function getMenuLabel(): string
   {
       return 'Iniciar Sesión';
   }

   public static function getMenuIcon(): ?string
   {
       return '🔑';
   }
   ```
   - En `onLoggedUser()`, si la pantalla se abrió como modal, ciérralo y retorna; el despachador de eventos enviará el `UsimEvent('logged_user')` a todas las pantallas abiertas (`Menu`, `Home`), actualizándolas de forma reactiva sin navegación forzada:
   ```php
   if ($this->isOpenedAsModal()) {
       $this->closeModal();
       return;
   }

   $homeScreen = $params['home_screen'] ?? null;
   if (\is_string($homeScreen) && class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)) {
       $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
   }
   ```
   - En `onCloseLoginDialog()` y `onNavigateForgotPassword()`, asegura que se mantenga el flujo SPA (sin `$this->redirect()`).

2. **`app/UI/Screens/Auth/Register.php`**:
   - Agrega las etiquetas de menú en español:
   ```php
   public static function getMenuLabel(): string
   {
       return 'Registrarse';
   }

   public static function getMenuIcon(): ?string
   {
       return '📝';
   }
   ```
   - En `onSubmitRegister()`, tras registrar la cuenta y cerrar el modal, resuelve y navega inmediatamente a la pantalla de inicio del usuario recién registrado:
   ```php
   if ($this->isOpenedAsModal()) {
       $this->closeModal();
   }

   $this->authSessionService->establishSession($user, null, $result->token);

   $homeScreen = $this->authSessionService->resolveHomeScreen($user);
   if (class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)) {
       Screen::make($homeScreen)->clearCachedScreenSnapshot();
       $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
   }
   ```

3. **`app/UI/Screens/Auth/Profile.php`**:
   - Configura `getMenuLabel(): string` para devolver `'Mi Perfil'`.

---

## 🔄 Paso 6: Ejecutar Migraciones, Sincronizar USIM y Sembrar Datos

### 6.1 Seeder Opcional con Posts de Bienvenida (`database/seeders/DatabaseSeeder.php`)

Si deseas contar con posts y portadas de ejemplo pre-cargados al iniciar la app, edita `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('posts')->count() > 0) {
            return;
        }

        $publisher = User::where('email', 'publisher@example.com')->first()
            ?? User::where('email', 'admin@example.com')->first()
            ?? User::first();

        $admin = User::where('email', 'admin@example.com')->first()
            ?? $publisher;

        if (!$publisher instanceof User || !$admin instanceof User) {
            return;
        }

        Storage::disk('public')->makeDirectory('uploads/images');

        $svg1 = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
  <defs>
    <linearGradient id="bg1" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0f172a"/>
      <stop offset="100%" stop-color="#00d4aa"/>
    </linearGradient>
  </defs>
  <rect width="800" height="450" fill="url(#bg1)"/>
  <text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="36" font-weight="bold">Bienvenido a USIM Post Manager</text>
  <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" fill="#e2e8f0" font-family="sans-serif" font-size="20">Arquitectura Server-Driven UI 100% SPA en Laravel</text>
</svg>
SVG;

        $svg2 = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
  <defs>
    <linearGradient id="bg2" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#1e1b4b"/>
      <stop offset="100%" stop-color="#3b82f6"/>
    </linearGradient>
  </defs>
  <rect width="800" height="450" fill="url(#bg2)"/>
  <text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="34" font-weight="bold">Componentes UI::uploader() y UI::image()</text>
  <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" fill="#cbd5e1" font-family="sans-serif" font-size="20">Gestión de archivos temporales y confirmación reactiva</text>
</svg>
SVG;

        Storage::disk('public')->put('uploads/images/sample-welcome.svg', $svg1);
        Storage::disk('public')->put('uploads/images/sample-components.svg', $svg2);

        /** @var PostServiceInterface $postService */
        $postService = app(PostServiceInterface::class);

        $postService->createPost([
            'user_id' => (int) $publisher->id,
            'title' => 'Cómo publicar contenido con UI::uploader y UI::image',
            'content' => 'En USIM, el componente UI::uploader() sube los archivos de forma asíncrona a una carpeta temporal con vista previa inmediata, y al enviar el formulario se confirma con $this->post_image->confirm($params, \'images\'). Luego, UI::image() los renderiza de forma responsiva.',
            'image' => 'sample-components.svg',
        ]);

        $postService->createPost([
            'user_id' => (int) $admin->id,
            'title' => 'Bienvenido al Gestor de Posts Didáctico con USIM',
            'content' => 'Esta aplicación demuestra el flujo de roles en una única unidad organizacional (lobby): los invitados (guest) leen los últimos posts, los nuevos usuarios (registered) ven su pantalla de espera y editan su avatar, los publicadores (publisher) crean posts y los administradores (admin) moderan posts y asignan roles.',
            'image' => 'sample-welcome.svg',
        ]);
    }
}
```

### 6.2 Comandos de Inicialización

Ejecuta los siguientes comandos para crear la tabla `posts`, sincronizar las unidades, roles, permisos y usuarios de `config/usim.php`, sembrar los datos y enlazar el almacenamiento público:

```bash
php artisan migrate
php artisan usim:sync
php artisan db:seed
php artisan storage:link
```

> [!NOTE]
> **Descubrimiento de Pantallas (`usim:discover`)**:
> Siempre que crees pantallas nuevas o modifiques metadatos (como títulos, visibilidad o accesos), puedes regenerar el manifiesto con `php artisan usim:discover`. Nota que el comando `php artisan usim:sync` ya ejecuta automáticamente este paso.

---

## 🧭 Paso 7: Recorrido por los Roles de la Aplicación

### 7.1 Iniciar el Servidor de Desarrollo

Puedes iniciar el servidor tradicional con Artisan:

```bash
php artisan serve
```

O bien, si estás utilizando **Laravel Octane con Roadrunner** (la arquitectura de alto rendimiento recomendada para USIM):

```bash
# Iniciar Octane con Roadrunner:
./start.sh

# O reiniciar el entorno eliminando caché/bd fresca si es necesario:
./start.sh -r
```

> [!TIP]
> **Ciclo de Vida y Persistencia en Memoria (Laravel Octane / Roadrunner)**:
> En servidores persistentes como Roadrunner, los procesos de PHP se mantienen en memoria entre peticiones sucesivas. USIM fue diseñado desde sus bases para este entorno:
> - El método `postLoadUI()` de las Screens siempre se ejecuta tras recuperar un snapshot de caché, lo que permite re-evaluar datos frescos (como la lista de posts más recientes o el avatar actualizado) sin penalizaciones.
> - Métodos como `Screen::make(Home::class)->clearCachedScreenSnapshot()` invalidan la caché previa de la vista para que las mutaciones de datos se sincronicen de inmediato con los clientes SPA.

### 7.2 Probar los Flujos y Roles de Usuario

Abre `http://localhost:8000` (o el puerto configurado) en tu navegador y prueba cada flujo sin que la página se recargue en ningún momento:

1. **Como `guest` (Invitado)**:
   - Al entrar a `/`, ves la pantalla `Home` con los últimos posts publicados y sus imágenes (`UI::image()`).
   - No ves botones para publicar ni para eliminar posts.
2. **Como `registered` (`registered@example.com` / `11111111` o registrando una cuenta nueva)**:
   - Al iniciar sesión o registrarte, USIM te lleva automáticamente en modo SPA a la pantalla `Registered`.
   - Verás el mensaje: *"Se ha registrado en el sistema, en breve se le asignará un rol."*
   - Haz clic en **👤 Editar Mi Perfil / Avatar**: se abrirá el modal `Profile` donde puedes subir una imagen con `UI::uploader()`. Al guardar, tanto el avatar circular en la pantalla `Registered` (`UI::image()`) como el icono del menú superior se actualizan reactivamente.
3. **Como `admin` (`admin@example.com` / `11111111`)**:
   - En `Home`, cada post muestra el botón **🗑️ Eliminar**. Al pulsarlo, se despliega un diálogo nativo de confirmación (`ConfirmDialog::open(...)`). Al confirmar, el post se elimina y la lista se refresca instantáneamente en pantalla.
   - Haz clic en **👥 Gestionar Usuarios** (`UsersManager`): verás a los usuarios en estado `En espera (Registrado)`. Al hacer clic en **Editar**, podrás aprobar al usuario y asignarle el rol **Publicador (`publisher`)** o **Administrador (`admin`)**.
4. **Como `publisher` (`publisher@example.com` / `11111111` o el usuario recién promovido)**:
   - En `Home` (o desde el menú principal `☰`), haz clic en **✍️ Publicar Post**.
   - Completa el título, el contenido y sube una imagen de portada con `UI::uploader()`.
   - Al pulsar **Publicar Post**, la imagen temporal se confirma (`$this->post_image->confirm($params, 'images')`) y vuelves mediante SPA a `Home`, donde el nuevo post aparece en primer lugar.
