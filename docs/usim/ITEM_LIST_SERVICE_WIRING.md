# Cableado de Servicios en `ItemList` (*Capability-Based Service Wiring*)

`ItemList` puede conectarse directamente a una clase de servicio PHP mediante `->service(...)` (o la clave `service:` en `.layout.yaml`), infiriendo de forma automática qué capacidades de lectura y CRUD habilita según los contratos que implemente dicho servicio.

De esta forma, la `Screen` deja de actuar como pasamanos repetitivo de `getItems()`, `onCreate()`, `onUpdate()`, `onDelete()` y `onBulkDelete()`, quedando libre para enfocarse únicamente en la coordinación de alto nivel de la vista (selección, modales de confirmación opcionales o resúmenes).

---

## 1. Contratos de Capacidades (`Idei\Usim\Contracts\ItemList`)

Los contratos siguen el Principio de Segregación de Interfaces (ISP): un servicio implementa **únicamente** las capacidades que soporta (al menos uno de los cinco contratos).

| Contrato | Método requerido | Capacidad habilitada en `ItemList` |
|---|---|---|
| `Listable` | `public function list(string $search = '', array $context = []): Collection` | Carga inicial de ítems (`Collection<int, ListItemDTO>`) + búsqueda remota vía `GET /api/ui-data` |
| `Creatable` | `public function create(string $label, array $payload = []): ListItemDTO` | Alta inline (`creatable = true`) y ruta automática `on_create` |
| `Updatable` | `public function update(int\|string $id, string $label, array $payload = []): ?ListItemDTO` | Edición inline (`editable = true`) y ruta automática `on_update` |
| `Deletable` | `public function delete(int\|string $id): bool` | Eliminación por fila (`deletable = true`), `on_delete` y fallback de `bulk_delete` |
| `BulkDeletable` | `public function deleteMany(array $ids): int` | Eliminación masiva en lote (`bulk_deletable = true`) y `on_bulk_delete` |

> **Regla de fallback para eliminación masiva:** si el servicio implementa `Deletable` pero no `BulkDeletable`, `ItemList` igualmente habilita `bulk_deletable` e itera `delete($id)` salvo que se deshabilite explícitamente con `allowBulkDelete(false)` / `allow_bulk_delete: false`.

### Contrato de datos devueltos (`Idei\Usim\DTOs\ListItemDTO`)

- `ListItemDTO` modela la representación visual e interacción de cada fila sin duplicar estado de selección (`selected` permanece en `ItemList::select()` / `selected_items`):
  - `id: int|string`, `label: string`, `subtitle: ?string = null`, `icon: ?string = null`
  - `badge: ?string = null`, `badgeStyle: Style = Style::DEFAULT`
  - `style: Style = Style::DEFAULT` (`default`, `primary`, `secondary`, `success`, `danger`, `warning`, `info`, `muted`)
  - `disabled: bool = false`, `editable: bool = true`, `deletable: bool = true`, `meta: array = []`
- `Listable::list()` devuelve `Illuminate\Support\Collection<int, ListItemDTO>`.
- `Creatable::create()` devuelve el `ListItemDTO` creado.
- `Updatable::update()` devuelve el `ListItemDTO` actualizado o `null` si el ID no existe.
- `Deletable::delete()` devuelve `bool` (`true` si eliminó el registro).
- `BulkDeletable::deleteMany()` devuelve la cantidad entera (`int`) de elementos eliminados.

---

## 2. Declaración en PHP y en `.layout.yaml`

### En PHP (API fluida)

```php
use App\Services\Movie\SessionMovieCatalogService;
use Idei\Usim\UI;

$moviesList = UI::itemList('movies_list')
    ->service(SessionMovieCatalogService::class) // o instancia resuelta
    ->searchable(true)
    ->multipleSelect()
    ->onServiceMutated('catalog_mutated'); // hook opcional tras mutaciones CRUD
```

### En `.layout.yaml`

```yaml
- movies_list:
    service: App\Services\Movie\SessionMovieCatalogService
    keep_selected_visible: true
    searchable: true
    debounce: 250
    max_height: 420
    scrollbar: always
    variant: plain
    selection_mode: multiple
    on_select: movie_selected
    on_service_mutated: catalog_mutated
```

> **Precedencia garantizada en `ContainerLayoutLoader`:** `service` se aplica **antes** que los flags compuestos (`creatable`, `editable`, `deletable`) y los modificadores individuales (`create_inline`, `edit_inline`, `allow_bulk_delete`, `on_delete`, etc.). Gracias a `isConfigDefault()`, las capacidades detectadas nunca pisan configuraciones explícitas que definas en PHP o en el mismo YAML.

---

## 3. Inferencia automática y despacho sin boilerplate en `Screen`

Al llamar a `->service($classOrInstance)`:

1. `ItemList` resuelve la instancia (vía `app($serviceClass)` cuando se pasa un class-string) y valida que implemente al menos uno de los contratos de `Idei\Usim\Contracts\ItemList`.
2. Para cada contrato implementado cuya configuración aún esté en valor default (`isConfigDefault()`):
   - **`Listable`**: configura `action` a `__usim_item_list_service__:<target>:list` y precarga `items` iniciales llamando a `list($search, $context)`.
   - **`Creatable`**: activa `creatable(true)` y configura `on_create` hacia `:create`.
   - **`Updatable`**: activa `editable(true)` y configura `on_update` hacia `:update`.
   - **`Deletable`**: activa `deletable(true)` y configura `on_delete` hacia `:delete`.
   - **`BulkDeletable` (o `Deletable`)**: activa `bulk_deletable` y configura `on_bulk_delete` hacia `:bulk_delete`.

### Despacho en el ciclo de vida HTTP y `Screen::callAction()`

Las acciones generadas por el servicio viajan por los mismos canales estándar de USIM (`GET /api/ui-data` para `list` y `POST /api/ui-event` para `create`/`update`/`delete`/`bulk_delete`):

1. `UIDataController::resolveQueryHandler()` y `UIEventController::resolveActionHandler()` buscan primero métodos concretos en la `Screen`.
2. Si no existe un método en la `Screen`, consultan `Screen::getLifecycleOrchestrator()->supportsComponentHandler($screen, $componentId, $action, queryOnly: ...)`, que verifica si el componente destino en el árbol de la pantalla (o su caller modal) posee un servicio que soporte esa operación.
3. Si es válido, ejecutan `$screen->handleQuery()` o `$screen->handleAction()` de forma uniforme (incluyendo protección `UsimReadOnlyLifecycleException` en queries GET, hidratación de contexto, storage, diffing y `Screen::callAction()` en tests/PHP).

---

## 4. Hooks, feedback e intercepción parcial (ej. `ConfirmDialog`)

El wiring por defecto permite intervenir exactamente en los puntos donde la UX de la `Screen` lo necesite:

- **`->serviceFeedback(bool $enabled = true)`**: controla si `ItemList` emite automáticamente toasts de confirmación/validación (`"\"...\" agregado"`, `"Actualizado a \"...\""`, `"Elemento eliminado"`, etc.) al completar mutaciones del servicio (activo por defecto).
- **`->onServiceMutated(string|array $action, array $parameters = [])`**: registra un handler en la `Screen` (ej. `catalog_mutated` -> `onCatalogMutated()`) que recibe `['operation' => ..., 'selected' => ..., ...payload]` inmediatamente después de cada mutación exitosa del servicio para sincronizar otros componentes (por ejemplo, contadores o labels resumen).
- **Override selectivo + delegación (`performServiceDelete` / `performServiceBulkDelete`)**: puedes sobrescribir un evento concreto (como `onDelete` / `onBulkDelete`) para abrir un `ConfirmDialog`, y una vez confirmado delegar al servicio:

```php
// Activar intercepción con diálogo de confirmación:
$this->movies_list
    ->onDelete('movie_deleted')
    ->onBulkDelete('movies_bulk_deleted');

// Al confirmar en el diálogo:
$this->movies_list->performServiceDelete($id, $this);
$this->movies_list->performServiceBulkDelete($ids, $this);

// Restaurar handlers automáticos del servicio si vuelve a modo directo:
$this->movies_list->useServiceDeleteHandlers();
```

---

## 5. Métodos clave en `ItemList`

- `service(object|string $service): static`
- `getService(): ?object`
- `serviceFeedback(bool $enabled = true): static`
- `onServiceMutated(string|array $action, array $parameters = []): static`
- `useServiceDeleteHandlers(): static`
- `serviceActionFor(string $operation): string`
- `supportsServiceOperation(string $operation): bool`
- `queryServiceItems(string $search = '', array $parameters = []): array`
- `refreshFromService(array $context = []): static`
- `handleServiceAction(string $operation, array $parameters = [], ?Screen $screen = null): void`
- `performServiceDelete(int|string $id, ?Screen $screen = null): bool`
- `performServiceBulkDelete(array $ids, ?Screen $screen = null): int`

---

## 6. Ejemplo completo de referencia (`ItemListDemo` + `SessionMovieCatalogService`)

- Servicio mock con persistencia aislada en sesión: [app/Services/Movie/SessionMovieCatalogService.php](../../../../app/Services/Movie/SessionMovieCatalogService.php)
- Layout declarativo: [app/UI/Screens/Demo/item-list-demo.layout.yaml](../../../../app/UI/Screens/Demo/item-list-demo.layout.yaml)
- Pantalla demo: [app/UI/Screens/Demo/ItemListDemo.php](../../../../app/UI/Screens/Demo/ItemListDemo.php)
- Tests de unidad e integración: [tests/Unit/ItemListComponentTest.php](../../../../tests/Unit/ItemListComponentTest.php) y [tests/Feature/ItemListServiceWiringTest.php](../../../../tests/Feature/ItemListServiceWiringTest.php)
