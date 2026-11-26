# Guía de `CardGrid` e `ItemCard` (`UI::cardGrid()` y `UI::itemCard()`)

Introducidos en **USIM v2.20.0**, **`ItemCard`** (`type: 'item_card'`) y **`CardGrid`** (`type: 'card_grid'`) conforman el sistema moderno de tarjetas de USIM, 100% alineado con la filosofía **server-driven + `ComponentDiffer` nativo**:

- **`UI::itemCard()`** es un componente de tarjeta estandarizado de primera clase (reemplazo moderno de `UI::card()`, marcado `@deprecated` en v2.20.0). Puede usarse **suelto** en cualquier `Screen`, `Container`, `Modal` o `.layout.yaml`, o **administrado** dentro de un `CardGrid`.
- **`UI::cardGrid()`** es una grilla 2D responsive que administra hijos reales `ItemCard`, conectada a servicios por contrato (`CardListable` o `Listable`) con toolbar integrada (búsqueda, ordenamiento y filtros) y paginado (`pages` y `load_more`) vía `POST /api/ui-event`.

---

## 1. Cuándo usar `CardGrid` vs `Table` vs `ItemList`

| Componente | Ideal para | Capacidades clave |
|---|---|---|
| `UI::table()` | Datos tabulares densos de backoffice | Columnas fijas, filas/celdas `TableRow`/`TableCell`, paginación y orden |
| `UI::itemList()` | Listas 1D, selectores en formularios y CRUD inline | Selección simple/múltiple persistente, `Listable`/`Creatable`/`Updatable`/`Deletable` |
| **`UI::cardGrid()` + `UI::itemCard()`** | **Catálogos visuales, fichas ricas y tarjetas individuales** | **Grilla CSS responsive, hijos reales `ItemCard` con diffing nativo de `ComponentDiffer`, búsqueda + filtros + ordenamiento integrados y paginado (`pages` / `load_more`)** |

---

## 2. Uso de `ItemCard` de forma independiente (Standalone)

`ItemCard` funciona de forma completamente autónoma en cualquier contenedor o pantalla (por ejemplo, para reemplazar el viejo `UI::card()`):

```php
use Idei\Usim\Enums\Style;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

$card = UI::itemCard('welcome_card')
    ->eyebrow('Cuenta')
    ->title('Registro completado')
    ->subtitle('Esperando asignación de rol')
    ->description('Tu cuenta fue registrada correctamente y pronto será asignada a una unidad.')
    ->icon('sparkles')
    ->badge('Pendiente', Style::INFO)
    ->style(Style::INFO)
    ->maxWidth(Size::px(650))
    ->marginTop(Spacing::px(24))
    ->addAction('Ir al perfil', 'go_to_profile', icon: 'user', style: Style::PRIMARY, variant: 'solid')
    ->addAction('Ver ayuda', 'open_help', style: Style::DEFAULT, variant: 'outline');
```

### Anatomía estandarizada (6 zonas tipadas, sin HTML crudo)
1. **Media / Visual**: `image()` (con `loading="lazy"`, `imageFit('cover'|'contain'|'scale-down')` y fallback automático), `aspectRatio()`, `imageStorage('uploads/images')` (resolución automática vía `UploadService::fileUrl()`), `imagePlaceholder()` / `imagePlaceholderAspect()` / `imagePlaceholderColors()` / `imagePlaceholderId()` (mismo pipeline que `UI::image()`), o `icon()` / `avatar()` cuando no hay imagen.
2. **Header**: `eyebrow()`, `title()`, `subtitle()` y `badge($text, $style)`.
3. **Highlight**: `metric($value, $label, $style)` (ej. precio, rating, porcentaje).
4. **Body**: `description()` (line-clamp automático), `attributes([...])` (pares `{ icon?, label, value }`) y `tags([...])` (capado a `max_visible_tags` + `+N`).
5. **Footer y Acciones**: siempre alineado al borde inferior (`margin-top: auto`). Muestra `footerText()` y hasta `max_visible_actions` botones (default `2`); cualquier acción adicional (o marcada `overflow: true`) colapsa automáticamente en un menú **`⋮`**. Las acciones *icon-only* (`label: ''` + `tooltip`) asignan `aria-label` automáticamente y muestran el `tooltip` como texto legible si caen en el menú `⋮`.
6. **Estilo y Estado**: `style(Style::...)` (borde superior `3px` en vertical / izquierdo `4px` en horizontal con soporte Light/Dark), `featured()`, `disabled()`, `accentColor()`, `variant('elevated'|'outlined'|'flat')`, `density('compact'|'comfortable'|'spacious')`, `vertical()` / `horizontal()`.

### Encabezado contextual en `CardGrid`
- `titleStyle(Style|string $style)` aplica un color semántico al título de la grilla (ej. `Style::WARNING`).
- `headerActions(array $actions)` / `addHeaderAction(...)` permiten mostrar múltiples botones CTA contextuales en el encabezado (`CardActionDTO[]` o arrays compatibles), manteniendo `headerAction()` como azúcar sintáctico de retrocompatibilidad para 1 acción.
- `imageStorage('uploads/images')` e `imagePlaceholder(...)` en `CardGrid` propagan automáticamente la resolución de rutas relativas y placeholders a todos los `ItemCard` hijos (con override opcional por ítem en `CardItemDTO`).

---

## 3. `CardGrid` conectado a un servicio `CardListable`

El servicio de dominio sigue siendo 100% puro y desacoplado de la UI: implementa `Idei\Usim\Contracts\CardGrid\CardListable` y devuelve `CardPageResult` de `CardItemDTO`:

```php
namespace App\Services\Catalog;

use Idei\Usim\Contracts\CardGrid\CardListable;
use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;
use Idei\Usim\Enums\Style;

class ProductCardService implements CardListable
{
    public function query(CardQuery $query): CardPageResult
    {
        $builder = Product::query()
            ->when($query->search !== '', fn ($q) => $q->where('name', 'like', "%{$query->search}%"))
            ->when($query->filter('category'), fn ($q, $cat) => $q->where('category', $cat))
            ->when($query->filter('in_stock'), fn ($q) => $q->where('stock', '>', 0))
            ->orderBy($query->sortBy ?? 'name', $query->sortDirection);

        $total = (clone $builder)->count();
        $rows = $builder->offset($query->offset())->limit($query->perPage)->get();

        $cards = $rows->map(fn (Product $p) => CardItemDTO::make(
            id: $p->id,
            title: $p->name,
            subtitle: $p->brand,
            eyebrow: $p->sku,
            description: $p->short_description,
            image: $p->cover_url,
            badge: $p->stock > 0 ? 'En stock' : 'Agotado',
            badgeStyle: $p->stock > 0 ? Style::SUCCESS : Style::DANGER,
            metric: '$' . number_format($p->price, 0, ',', '.'),
            metricLabel: 'ARS',
            metricStyle: Style::PRIMARY,
            tags: $p->tags ?? [],
            actions: [
                CardActionDTO::make(label: 'Ver', action: 'view_product', parameters: ['product_id' => $p->id], icon: 'eye', style: Style::PRIMARY, variant: 'solid'),
                CardActionDTO::make(label: 'Favorito', action: 'toggle_favorite', parameters: ['product_id' => $p->id], icon: 'heart'),
                CardActionDTO::make(label: 'Archivar', action: 'archive_product', parameters: ['product_id' => $p->id], icon: 'trash', style: Style::DANGER),
            ],
            clickAction: 'view_product',
            clickParameters: ['product_id' => $p->id],
            style: $p->featured ? Style::PRIMARY : Style::DEFAULT,
            featured: (bool) $p->featured,
        ));

        return CardPageResult::make(items: $cards, total: $total, page: $query->page, perPage: $query->perPage);
    }
}
```

### Compatibilidad con servicios `Listable` y colecciones estáticas
- Si ya tenés un servicio `Idei\Usim\Contracts\ItemList\Listable`, pasalo directo a `->service(...)`: `CardGrid` convierte automáticamente `ListItemDTO` → `CardItemDTO` y pagina con `CardPageResult::fromCollection()`.
- Si no necesitás servicio, podés pasar `->items([...])`: búsqueda, filtros, orden y paginación siguen ejecutándose del lado del servidor sobre `POST /api/ui-event`.

---

## 4. Cómo funciona el Diffing Nativo (`ComponentDiffer`)

`CardGrid` crea y mantiene un componente hijo **`ItemCard`** por cada ítem visible en pantalla, con **ID determinístico por `item_id`** (`<grid>__card_<itemId>`).

Esto significa que el diffing es **100% estándar de USIM**, sin protocolos paralelos:

```php
public function onToggleFavorite(array $params): void
{
    $movieId = (int) ($params['movie_id'] ?? $params['id'] ?? 0);
    $added = app(MovieCardGridService::class)->toggleFavorite($movieId);

    // Mutación directa sobre el ItemCard hijo montado:
    // ComponentDiffer envía ÚNICAMENTE las propiedades modificadas de ese ItemCard
    // (0 diff sobre CardGrid, 0 diff sobre las demás tarjetas, 0 recarga de imagen)
    $this->movies_grid->card($movieId)
        ?->badge($added ? '★ Favorita' : 'Destacada', $added ? Style::WARNING : Style::PRIMARY)
        ->footerText($added ? 'En tu lista' : '148 min')
        ->actions($updatedActions);
}
```

Operaciones disponibles en `CardGrid`:
- `$grid->card($itemId)`: devuelve el `ItemCard` hijo actualmente montado para mutarlo directamente con la API fluida (o `$card->patch([...])`).
- `$grid->patchCard($itemId, CardItemDTO|array $changes)`: atajo para aplicar un DTO o un array parcial sobre `$grid->card($itemId)`.
- `$grid->removeCard($itemId)`: desengancha ese `ItemCard` del árbol → `ComponentDiffer` emite `{ "<card_id>": { "parent": null } }` y actualiza `pagination.total`.
- `$grid->prependCard(CardItemDTO|array $card)`: agrega un `ItemCard` en `card_order: 0` → `ComponentDiffer` emite solo la nueva tarjeta y los `card_order` desplazados.
- `$grid->reload()`: vuelve a consultar el servicio con el estado actual (búsqueda, orden, filtros, página) y sincroniza los hijos reutilizando IDs existentes.

### Comportamiento en búsquedas, filtros y `load_more`
- Todas las consultas de la toolbar (búsqueda con debounce, filtros `select`/`multi_select`/`toggle`/`chips`, ordenamiento y paginación) se envían vía **`POST /api/ui-event`** hacia la acción interna `__usim_card_grid:<nombre>:query` (atendida automáticamente por `ScreenLifecycleOrchestrator` sin código extra en la `Screen`).
- Si un ítem que ya estaba en pantalla sigue coincidiendo tras filtrar o buscar, **conserva su ID y produce diff = 0** (sin parpadeo de imagen).
- En modo **`load_more`**, las tarjetas ya montadas **quedan intactas** (diff = 0) y `ComponentDiffer` envía únicamente las N tarjetas nuevas de la página siguiente.

---

## 5. Uso Declarativo en `.layout.yaml`

- `CardGrid`: prefijo automático **`grd_`** o alias **`component: card_grid`** (`cardgrid`).
- `ItemCard` standalone: prefijo automático **`icrd_`** o alias **`component: item_card`** (`itemcard`).

```yaml
name: card_grid_demo_wrapper
appearance: card
max_width: 1200
center_horizontal: true
i18n: auto

grid:
  cols: 12
  gap: m
  padding: l
  default_span: 12

  children:
    - movies_grid:
        component: card_grid
        service: App\Services\Movie\MovieCardGridService
        searchable: true
        debounce: 250
        per_page: 6
        per_page_options: [6, 12, 18]
        min_card_width: 270px
        max_columns: 3
        variant: elevated
        show_layout_toggle: true
        on_card_click: view_movie
```

---

## 6. Demo, Tests y Referencia IA

- **Pantalla demo**: `App\UI\Screens\Demo\CardGridDemo` (**Demos → Grilla de Tarjetas**).
- **Servicio demo**: `App\Services\Movie\MovieCardGridService`.
- **Tests**: `tests/Unit/CardGridComponentTest.php`, `tests/Feature/CardGridServiceWiringTest.php` y helpers de `ScreenTestHarness` (`findCard()`, `getCard()`, `assertHasCard()`, `assertMissingCard()`, `assertCardCount()`, `assertCardBadge()`, `assertCardHasAction()`, `assertCardMissingAction()`, `clickCardAction()`, `clickCard()`).
- **Referencia técnica para IA**: [CARD_GRID_AI_REFERENCE.md](CARD_GRID_AI_REFERENCE.md).
