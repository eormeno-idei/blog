# Instrucciones para Copilot en USIM Framework

## Contexto general del repositorio

- Este repositorio es un monorepo Laravel 11 + PHP 8.3+ que cumple dos funciones al mismo tiempo:
  1. la app consumidora o proyecto base (`/workspaces/usim-framework`), y
  2. el paquete reusable `idei/usim` desarrollado localmente en `packages/idei/usim/`.
- USIM (UI Services Implementation Model) es un framework backend-driven/server-driven UI.
- La UI se define en PHP con builders y pantallas; el frontend JavaScript es un renderizador generico que consume JSON inicial y diffs incrementales.
- La logica, validacion, autorizacion y estado deben vivir prioritariamente en backend.
- No asumas arquitectura React/Vue. El source of truth esta en el estado del servicio PHP y en los contratos JSON que USIM envia al cliente.
- Este repo usa `composer.json` con repositorio local tipo `path` apuntando a `packages/idei/usim`, asi que la app prueba el framework directamente desde el monorepo.

## Modelo mental obligatorio

- Piensa este workspace como dos capas:
  1. `packages/idei/usim/`: framework reusable, generico, publicable a Packagist.
  2. `app/`, `routes/`, `resources/`, `tests/`: app consumidora y banco de pruebas real del framework.
- Si el cambio es reusable, generico o afecta contratos/base classes/builders/renderizador/instalacion/stubs, revisa primero `packages/idei/usim/`.
- Si el cambio es especifico del producto actual, de una screen concreta o de servicios de dominio propios, revisa primero `app/` y `app/UI/`.
- Si el cambio toca ambos lados, explica el tradeoff y manten la separacion entre framework reusable y codigo especifico de la app.

## Estructura completa del proyecto base

Usa este mapa como contexto por defecto para cualquier chat:

```text
/workspaces/usim-framework
|- artisan
|- start.sh                         # flujo principal con RoadRunner
|- composer.json
|- package.json
|- README.md                        # vision general app + framework USIM
|- docs/
|  |- README.md                     # indice de documentacion del proyecto
|  |- framework/                    # docs tecnicas del framework en la app
|  |- api/                          # endpoints y contratos REST
|  |- deployment/                   # despliegue y produccion
|  |- tooling/                      # logs, colores de tests, utilidades
|  |- prompt-siguiente-sesion.md    # prompt de continuidad para refactor del framework
|  |- usim-laravel-package-refactoring.md
|- app/
|  |- Console/Commands/
|  |- Http/Controllers/
|  |- Http/Middleware/
|  |- Models/
|  |- Providers/
|  |- Services/
|  |  |- Auth/                      # AuthSessionService, LoginService, PasswordService, RegisterService
|  |  \- User/                      # UserService
|  \- UI/
|     |- Components/
|     |  |- DataTable/              # modelos/adaptadores de tablas de la app
|     |  \- Modals/                 # dialogs concretos como EditUserDialog
|     \- Screens/
|        |- Home.php
|        |- Menu.php
|        |- Admin/                  # Dashboard.php
|        |- Auth/                   # Login, Profile, ForgotPassword, ResetPassword, EmailVerified
|        \- Demo/                   # demos reales de componentes del framework
|- bootstrap/
|- config/
|  |- ui-home.php
|  \- ui-services.php               # configuracion/registro de pantallas USIM en la app
|- database/
|- public/
|- resources/
|  |- css/
|  |- js/
|  |- legal/
|  \- views/
|- routes/
|  |- web.php
|  |- api.php
|  \- console.php
|- scripts/
|  |- rebuild_dev.sh
|  |- release_usim_package          # flujo de release del paquete
|  \- utilidades de deploy/git/ssh
|- storage/
|- tests/
|  |- Feature/
|  |- Unit/
|  |- Support/
|  |- Traits/
|  |- SCREEN_TESTING_GUIDE.md
|  \- prompt.md                    # prompt base para generar tests de screens
\- packages/
	\- idei/
		\- usim/                     # paquete framework desarrollado en este monorepo
```

## Estructura completa del paquete `packages/idei/usim`

Cuando la tarea diga "framework", "componente reusable", "contrato JSON", "renderer", "installer" o "stubs", esta carpeta es el punto de partida:

```text
packages/idei/usim/
|- README.md
|- CHANGELOG.md
|- composer.json
|- LICENSE.md
|- .github/
|- config/
|  |- usim.php
|  \- users.php
|- docs/
|  |- SCREEN_TESTING_GUIDE.md
|  |- component_prompt.md
|  |- tests_prompt.md
|  |- usim_context_prompt.md
|  |- package-update-and-consumer-upgrade-guide.md
|  |- public-release-checklist.md
|  |- usim_json_contract.md
|  |- usim_json_contract_quickstart.md
|  |- usim_contract_v1_proposal.md
|  \- client_templates/
|- resources/
|  |- assets/
|  |  |- js/
|  |  |  |- ui-renderer.js
|  |  |  |- uploader-component.js
|  |  |  |- image-crop-editor.js
|  |  |  |- calendar-component.js
|  |  |  \- carousel-component.js
|  |  |- css/
|  |  |  |- ui-components.css
|  |  |  |- ui-theme-tokens.css
|  |  |  |- uploader-component.css
|  |  |  |- image-crop-editor.css
|  |  |  \- carousel-component.css
|  |  \- images/
|  \- views/
|     \- app.blade.php             # shell que carga assets/renderizador del paquete
|- routes/
|  \- api.php
|- src/
|  |- UsimServiceProvider.php
|  |- Console/
|  |- Events/
|  |- Http/
|  |- Jobs/
|  |- Listeners/
|  |- Notifications/
|  |- Services/
|  |  |- Screen.php
|  |  |- UI.php
|  |  |- UIChangesCollector.php
|  |  |- Contracts/
|  |  |- DataTable/
|  |  |- Enums/
|  |  |- Modals/
|  |  |- Support/
|  |  |- Upload/
|  |  \- Components/
|  |     |- BaseUIBuilder.php
|  |     |- UIComponent.php
|  |     |- Container.php
|  |     |- Label.php
|  |     |- Button.php
|  |     |- Input.php
|  |     |- Select.php
|  |     |- Checkbox.php
|  |     |- Form.php
|  |     |- Card.php
|  |     |- Table.php y builders relacionados
|  |     |- Uploader.php
|  |     |- Calendar.php
|  |     |- Carousel.php
|  |     \- MenuDropdown.php
|  |- Support/
|  \- Traits/
|- stubs/
|  |- config/
|  |- controllers/
|  |- migrations/
|  |- models/
|  |- providers/
|  |- routes/
|  |- seeders/
|  |- services/
|  |  |- Auth/
|  |  \- User/
|  |- screens/
|  |  |- Home.php.stub
|  |  |- Menu.php.stub
|  |  |- Admin/Dashboard.php.stub
|  |  \- Auth/                     # Login, Profile, ForgotPassword, ResetPassword, EmailVerified
|  |- tests/
|  |  |- Feature/
|  |  |- Support/
|  |  |- Traits/
|  |  |- Pest.php.stub
|  |  \- TestCase.php.stub
|  \- views/
|- tests/
|  \- test-install-command.sh
\- routes/
```

## Fuentes de verdad que debes revisar segun la tarea

Antes de proponer arquitectura nueva o editar archivos, usa estas fuentes como contexto funcional:

- `README.md` del repo raiz: presenta la vision general del proyecto/app y resume beneficios, componentes disponibles y stack.
- `docs/README.md`: indice de la documentacion vigente de framework, API, deployment y tooling.
- `packages/idei/usim/README.md`: fuente principal del paquete reusable. Describe conceptos core, componentes, lifecycle, autorizacion, menu, helpers, modals, tablas, uploads, auth, tests, comandos, rutas y directorios.
- `packages/idei/usim/CHANGELOG.md`: fuente de cambios recientes del framework; si cambias API, comportamiento, stubs, renderer o contratos, normalmente debes actualizarlo.
- `docs/usim-laravel-package-refactoring.md`: documento de contexto historico/arquitectonico para la extraccion y productizacion del framework.

## Prompts y documentos operativos que debes usar como contexto

Estos archivos no son decorativos; describen flujos de trabajo esperados y deben influir en las respuestas:

- `tests/prompt.md`: prompt base para generar tests de screens de la app con Pest + `uiScenario(...)`.
- `docs/prompt-siguiente-sesion.md`: prompt de continuidad para sesiones enfocadas en refactor/seguridad del framework; remite a `docs/usim-laravel-package-refactoring.md`.
- `packages/idei/usim/docs/usim_context_prompt.md`: resumen reusable del modelo mental de USIM; util para arrancar cualquier chat tecnico sobre el framework.
- `packages/idei/usim/docs/component_prompt.md`: checklist end-to-end para agregar un componente nuevo al framework y probarlo en app + paquete.
- `packages/idei/usim/docs/tests_prompt.md`: prompt base para generar tests de screens USIM en el estilo esperado.
- `packages/idei/usim/docs/package-update-and-consumer-upgrade-guide.md`: flujo recomendado para cambiar, validar, publicar y actualizar el paquete en la app consumidora y en apps externas.

## Estado reciente del framework que debes tener presente

Segun `packages/idei/usim/CHANGELOG.md` y `packages/idei/usim/README.md`, el contexto reciente del paquete incluye:

- Version actual documentada: `0.7.0`.
- `Container` tiene API de apariencia con `appearance()`, `card()` y `plain()`.
- Calendar y Carousel usan CSS theme tokens para consistencia light/dark.
- `Screen` persiste el estado final luego de `postLoadUI()`, incluyendo recargas con `?reset=true`.
- Los checkboxes sincronizan correctamente su estado incremental desde el backend.
- El contrato de storage cambio: `store_*` ahora se serializa plain por defecto y solo valores sensibles deben usar sufijo `_crypt`.
- El framework soporta cambio de tema y usa `ui-theme-tokens.css` para light/dark mode.
- Soporte de **Headless Mode** con `USIM_HEADLESS_MODE=true`: el catch-all web devuelve `406` y los clientes deben usar `GET /api/ui/{screen}` + `POST /api/ui-event`.
- Soporte de **Agent Context** por Screen mediante `getAgentContext(): array`: si no esta vacio, se serializa en `agent_context` dentro del payload JSON para clientes IA/headless.

## Reglas para decidir donde editar

- Si agregas o cambias una pantalla de negocio, menu de la app, modal de la app o un servicio de dominio, edita primero `app/`.
- Si agregas o cambias un componente reusable de UI, builders, renderer JS, contratos JSON, comandos artisan, provider, middleware, rutas del framework, instalador o stubs, edita primero `packages/idei/usim/`.
- Si una feature del framework debe verse en la app actual, ademas del paquete considera demo screen, entrada de menu, assets publicados y tests de regresion en la app.
- No dupliques validaciones en frontend y backend salvo pedido explicito.
- Preserva backward compatibility en payloads y en meta keys reservadas: `storage`, `action`, `redirect`, `toast`, `abort`, `modal`, `update_modal`, `clear_uploaders`, `set_uploader_existing_file`.
- Si cambias docs de onboarding o guia visual de la app, considera actualizar tambien `resources/views/welcome-usim.blade.php` y sus claves i18n `welcome.*` en `database/translations/{en,es}.json`.

## Convenciones tecnicas USIM

- Usa `Screen` para screens y `UI::*` para crear componentes.
- Los handlers siguen la convencion `on<ActionName>` en PascalCase a partir del action snake_case.
- Piensa siempre en IDs deterministas, diffs incrementales y reconstruccion correcta del estado.
- Las propiedades `store_*` son persistidas entre requests; usa `_crypt` solo para valores sensibles.
- Prioriza implementaciones en PHP alineadas con Laravel y con la arquitectura server-driven del framework.
- Evita logica frontend ad hoc si el backend puede resolverlo de forma clara.
- En modo headless, asume integracion por contrato API: carga inicial con `GET /api/ui/{screen}`, eventos con `POST /api/ui-event`, y continuidad de estado reenviando `X-USIM-Storage`.

## Definicion operacional de "Screen" (obligatoria para el chat)

Cuando en este repo se hable de "Screen", debes interpretarlo siempre con este significado:

- Una `Screen` es una clase PHP que representa una pagina completa (no un componente aislado ni una vista pasiva).
- Su contrato base es extender `Screen` e implementar `buildBaseUI(Container $container, ...$params): void`.
- La `Screen` concentra interfaz + estado + reglas de interaccion en backend; el frontend solo renderiza el contrato JSON.
- El estado vive del lado servidor y se restaura entre requests; las propiedades `store_*` persisten entre eventos.
- El flujo reactivo esperado es: restaurar estado -> ejecutar handler -> calcular diff -> enviar solo delta al cliente.
- Los handlers se resuelven por convencion: `action` en snake_case -> metodo `onPascalCase(array $params)`.
- La autorizacion y acceso pertenecen a la `Screen` (`authorize`, `checkAccess`) y no al frontend.
- La ruta de una `Screen` se deriva por convencion del namespace/clase (`getRoutePath`), salvo personalizaciones explicitas.
- La metadata de navegacion tambien pertenece a la `Screen`: `getMenuLabel()`, `getMenuIcon()` y `getRoutePath()`.
- USIM no registra una ruta Laravel individual por cada `Screen`; usa una ruta catch-all y un loader API que traducen `URL <-> clase PHP` por convencion.

Implicancias para tus respuestas y cambios:

- No propongas mover logica de negocio de una `Screen` al cliente salvo pedido explicito.
- No trates una `Screen` como "template HTML" tradicional: es un servicio UI stateful.
- Si el usuario pide "agregar una screen", piensa en ciclo de vida, estado `store_*`, handlers, autorizacion y testing, no solo en markup.
- Si describes arquitectura, deja explicito que la fuente de verdad es backend + contrato JSON incremental.

## Cuando agregues un componente nuevo al framework

Sigue el flujo definido por `packages/idei/usim/docs/component_prompt.md`:

1. Backend del paquete:
	- crear builder en `packages/idei/usim/src/Services/Components/`
	- registrar factory method en `packages/idei/usim/src/Services/UI.php`
	- registrar mapping de tipo en `packages/idei/usim/src/Services/Screen.php`
2. Frontend del paquete:
	- crear JS en `packages/idei/usim/resources/assets/js/`
	- crear CSS si aplica en `packages/idei/usim/resources/assets/css/`
	- registrar el componente en `packages/idei/usim/resources/assets/js/ui-renderer.js`
	- cargar assets en `packages/idei/usim/resources/views/app.blade.php`
3. Integracion en la app:
	- crear demo screen en `app/UI/Screens/Demo/`
	- agregar entrada en `app/UI/Screens/Menu.php` si aplica
4. Tests:
	- crear o actualizar tests Pest con `uiScenario(...)`
	- cubrir contrato inicial, eventos, deltas y edge cases
5. Validacion/ejecucion:
	- `composer dump-autoload`
	- `php artisan usim:discover`
	- `php artisan test ...`
	- `php artisan vendor:publish --tag=usim-assets --force` si cambian assets del paquete

## Cuando actualices el framework o prepares release

Sigue `packages/idei/usim/docs/package-update-and-consumer-upgrade-guide.md`:

- Valida el paquete con `composer validate --strict --no-check-publish`.
- Valida PHP del paquete (`src`, `config`, `routes`) con `php -l`.
- Si cambias API, instalacion, stubs o comportamiento visible, actualiza `packages/idei/usim/README.md` y `packages/idei/usim/CHANGELOG.md`.
- Para refrescar la app consumidora local, usa `composer update idei/usim`, `php artisan optimize:clear` y `php artisan package:discover --ansi`.
- Si cambias assets del paquete, recuerda publicarlos en la app.
- No asumas que publicar el paquete basta; verifica tambien el impacto en esta app monorepo.

## Publicar el paquete ("publica el paquete")

Cuando el usuario diga "publica el paquete" o una frase equivalente, sigue estos pasos **en orden** sin pedir confirmacion intermedia:

1. **Leer el CHANGELOG** (`packages/idei/usim/CHANGELOG.md`) para identificar:
   - La ultima version publicada (ultimo encabezado `## [X.Y.Z]`).
   - El contenido de la seccion `## [Unreleased]` para clasificar los cambios.

2. **Calcular la nueva version** con Semantic Versioning (pre-1.0: `0.x`):
   - **Patch** (`0.Y.Z+1`): solo fixes, sin nuevas features ni breaking changes.
   - **Minor** (`0.Y+1.0`): nuevas features, breaking changes o eliminacion de API (en `0.x` el minor absorbe breaking changes).
   - Regla practica: si `[Unreleased]` incluye `### Added` o `### Changed` con eliminacion/renombrado de clases/metodos, sube minor. Si solo tiene `### Fixed`, sube patch.

3. **Confirmar la version calculada** brevemente al usuario antes de ejecutar el script (una sola linea: "Version calculada: vX.Y.Z — ejecutando release...").

4. **Ejecutar el script de release**:
   ```bash
   bash scripts/release_usim_package -v vX.Y.Z -f
   ```
   - Usa `-f` siempre para forzar limpieza del split branch previo.
   - No agregar `-p` salvo que el usuario lo pida explicitamente.

5. **Reportar el resultado**: indicar si el push y el tag tuvieron exito, y recordar al usuario que puede triggerear Packagist manualmente o con `-p` si tiene las variables `PACKAGIST_USERNAME` / `PACKAGIST_TOKEN` exportadas.

6. **Post-release obligatorio**: actualizar la seccion `## [Unreleased]` del CHANGELOG para moverla a `## [vX.Y.Z] - YYYY-MM-DD` con la fecha actual, y dejar una nueva seccion `## [Unreleased]` vacia encima. Hacer commit con mensaje `chore: mark vX.Y.Z as released in CHANGELOG`.

## Testing y validacion

- Para tests de UI/Screen en la app, sigue `tests/SCREEN_TESTING_GUIDE.md` y `tests/prompt.md`.
- Para tareas del paquete, usa tambien `packages/idei/usim/docs/SCREEN_TESTING_GUIDE.md` y `packages/idei/usim/docs/tests_prompt.md` si corresponde.
- El patron preferido es `uiScenario(...)->component(...)->expect(...)`.
- Evita parseo raw del payload salvo necesidad real.
- Si hay notificaciones, usa `Notification::fake()`.
- Si la tarea afecta auth, menus, modales, uploads o renderer, intenta validar con tests o al menos con comandos de descubrimiento/publicacion relevantes.

## Ejecucion local y entorno

- Para levantar el proyecto, ten presente que el flujo principal usa RoadRunner mediante `./start.sh`; no asumas `php artisan serve` como flujo principal.
- El frontend del framework depende de assets del paquete publicados/servidos; si cambias JS/CSS del paquete, considera el paso de publicacion de assets.
- Las pantallas de la app se descubren con el flujo USIM y conviven con las pantallas y stubs publicados por el paquete.

## Estilo de ayuda esperado

- Responde con foco practico y orientado a cambios reales en el codigo.
- Explica tradeoffs cuando una solucion pueda tocar framework y aplicacion al mismo tiempo.
- Si una solicitud contradice la arquitectura backend-driven de USIM, senalalo y propone una alternativa alineada con el proyecto.
- Cuando falte contexto, prioriza leer README, CHANGELOG, docs y prompts relevantes antes de inventar arquitectura.

## General

Al final de cada respuesta dime "Ready!" para que sepa que terminaste de responder.