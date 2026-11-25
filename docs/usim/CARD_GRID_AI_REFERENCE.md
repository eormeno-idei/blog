# USIM `CardGrid` & `ItemCard` (`UI::cardGrid()` / `UI::itemCard()`) — AI Technical Reference

> **Target audience:** AI coding assistants (LLMs) and framework maintainers.  
> **Scope:** Authoritative backend, DTO, component-tree diffing, JSON protocol, frontend runtime, `.layout.yaml`, deprecation, and testing specification for `Idei\Usim\Components\CardGrid` (`type: 'card_grid'`) and `Idei\Usim\Components\ItemCard` (`type: 'item_card'`), introduced in **v2.20.0**.

---

## 1. Component Boundaries & Non-Negotiable Architectural Rules

1. **Pure USIM Server-Driven Tree (NO custom diff mini-protocols):**
   - `CardGrid` manages **real first-class child `ItemCard` components** (`parent = $cardGrid->getId()`), each with a **deterministic per-item ID** generated via `UIIdGenerator::generateFromName()` using `<gridTarget>__card_<itemId>`.
   - **Never invent custom patch arrays** (like `card_patches`) or store duplicate `items[]` payloads on `CardGrid`. All single-card updates, removals, prepends, pagination, search, sort, filters, and `load_more` appends flow exclusively through USIM's standard **`ComponentDiffer` (`ADD` / `UPDATE` / `DELETE` with `parent: null`)**.
2. **`ItemCard` is first-class and standalone-capable (Legacy `Card` is deprecated):**
   - `Idei\Usim\Components\Card` (`UI::card()`) is **`@deprecated` since v2.20.0**.
   - Use **`UI::itemCard()`** (`Idei\Usim\Components\ItemCard`, `type: 'item_card'`) both standalone (in any Screen/Container/Modal/`.layout.yaml`) and inside `CardGrid`.
   - Do **NOT** mix raw HTML into `ItemCard`: all slots are closed, typed, and rendered via `textContent` on the client.
3. **Queries run over `POST /api/ui-event` (NOT `GET /api/ui-data`):**
   - Because search, sort, integrated filters, `pages`, and `load_more` mutate the Screen's live child `ItemCard` tree on the server, `CardGrid` dispatches queries via **`POST /api/ui-event`** to internal service action `__usim_card_grid:<target>:query` (handled automatically by `ScreenLifecycleOrchestrator::handleEvent()`, zero Screen boilerplate).
   - This guarantees a **single source of truth**: `$this->grid->card($id)` ALWAYS finds the exact mounted `ItemCard` regardless of active page, filters, or `load_more` state.
4. **Card ordering uses `card_order` (NOT `position`):**
   - `UIComponent::position(string)` is reserved for CSS `position`.
   - `ItemCard` uses `cardOrder(?int $order)` / `getCardOrder(): int` (JSON key `card_order`, DOM `data-card-order`) so `ComponentDiffer` diffs card reordering cleanly without colliding with CSS `position` or ignored `_order`.
5. **Lazy initial service hydration:**
   - `CardGrid::service()` marks `$serviceDataStale = true` and defers the initial query until `toJson()`, `getCards()`, or `card()` is called, so `.layout.yaml` keys or fluent setters chained after `service()` (`perPage`, `defaultSort`, `filters`, `activeFilters`) take effect in a single initial query.

---

## 2. Backend Contracts, Components & DTOs

| Class | Role |
|---|---|
| `Idei\Usim\Contracts\CardGrid\CardListable` | Domain service contract: `query(CardQuery $query): CardPageResult`. |
| `Idei\Usim\DTOs\CardQuery` | Immutable query VO (`search`, `page`, `perPage`, `sortBy`, `sortDirection`, `filters`, `context`; helpers `fromArray()`, `filter()`, `hasFilter()`, `offset()`). |
| `Idei\Usim\DTOs\CardPageResult` | Immutable page VO (`items: list<CardItemDTO>`, `total: ?int`, `page`, `perPage`, `hasMore`; helpers `make()`, `fromCollection()`, `totalPages()`). |
| `Idei\Usim\DTOs\CardItemDTO` | Immutable presentation DTO (`make()`, `fromArray()`, `fromListItemDTO()`). |
| `Idei\Usim\DTOs\CardActionDTO` | Immutable per-card action DTO (`label`, `action`, `parameters`, `url`, `target`, `icon`, `style`, `variant` `'solid'\|'outline'\|'ghost'`, `disabled`, `overflow`, `tooltip`). |
| `Idei\Usim\Components\ItemCard` | First-class card component (`UI::itemCard()`, `type: 'item_card'`). Methods: `title()`, `subtitle()`, `eyebrow()`, `description()`, `image()`, `icon()`, `avatar()`, `badge()`, `badgeStyle()`, `metric()`, `attributes()`, `tags()`, `footerText()`, `actions()`, `addAction()`, `clickAction()`/`onClick()`, `url()`, `style()`, `featured()`, `disabled()`, `accentColor()`, `layout()`/`vertical()`/`horizontal()`, `variant()`, `density()`, `cardOrder()`, `applyFromDTO()`, `patch()`. |
| `Idei\Usim\Components\CardGrid` | Parent grid component (`UI::cardGrid()`, `type: 'card_grid'`). Accepts `CardListable` or `Listable` in `service()`, or static arrays in `items()`. Child tree API: `card($id)`, `getCards()`, `patchCard($id, ...)`, `removeCard($id)`, `prependCard(...)`, `reload()`. |

---

## 3. JSON Contract

### 3.1 `card_grid` Node (Parent)
```json
{
  "742103412": {
    "type": "card_grid",
    "parent": 742100001,
    "name": "movies_grid",
    "title": "Cartelera Destacada",
    "subtitle": "Subtítulo opcional",
    "show_count": true,
    "header_action": null,
    "visible_count": 6,
    "pagination": {
      "mode": "pages",
      "page": 1,
      "per_page": 6,
      "per_page_options": [6, 12, 18],
      "show_per_page": true,
      "total": 18,
      "total_pages": 3,
      "has_more": true,
      "can_prev": false,
      "can_next": true
    },
    "searchable": true,
    "search_placeholder": "Buscar...",
    "search_term": null,
    "min_search_chars": 0,
    "debounce": 250,
    "sort_options": [{ "field": "rating", "label": "Puntaje IMDb" }],
    "sort_by": "rating",
    "sort_direction": "desc",
    "filters": [
      { "name": "genre", "label": "Género", "type": "select", "placeholder": "Género: Todos", "options": [{ "value": "sci-fi", "label": "Ciencia Ficción" }] },
      { "name": "tags", "label": "Etiquetas", "type": "multi_select", "options": [{ "value": "4K", "label": "4K" }] },
      { "name": "featured", "label": "Solo destacadas", "type": "toggle" },
      { "name": "decade", "label": "Década", "type": "chips", "options": [{ "value": "2010", "label": "2010s" }] }
    ],
    "active_filters": {},
    "query_action": "__usim_card_grid:movies_grid:query",
    "min_card_width": "270px",
    "max_columns": 3,
    "gap": "16px",
    "card_layout": "vertical",
    "variant": "elevated",
    "density": "comfortable",
    "show_layout_toggle": true
  }
}
```

### 3.2 `item_card` Node (Standalone or Child of `card_grid`)
```json
{
  "742106541": {
    "type": "item_card",
    "parent": 742103412,
    "name": "movies_grid__card_2",
    "item_id": 2,
    "card_order": 2,
    "title": "Inception",
    "subtitle": "Dir. Christopher Nolan",
    "eyebrow": "Ciencia Ficción • 2010",
    "description": "...",
    "image": "data:image/svg+xml;...",
    "image_alt": "Poster de Inception",
    "image_fit": "cover",
    "aspect_ratio": "16/9",
    "icon": "🌀",
    "badge": "Destacada",
    "badge_style": "primary",
    "metric": "★ 8.8",
    "metric_label": "/ 10 IMDb",
    "metric_style": "success",
    "attributes": [{ "icon": "calendar", "label": "Año", "value": "2010" }],
    "tags": ["Thriller", "Premiada", "4K"],
    "footer_text": "148 min",
    "actions": [
      { "label": "Ver detalle", "action": "view_movie", "parameters": { "movie_id": 2 }, "icon": "eye", "style": "primary", "variant": "solid", "disabled": false, "overflow": false }
    ],
    "click_action": "view_movie",
    "click_parameters": { "movie_id": 2 },
    "style": "info",
    "featured": true,
    "disabled": false,
    "layout": "vertical",
    "variant": "elevated",
    "density": "comfortable",
    "max_visible_actions": 2,
    "max_visible_tags": 3,
    "_order": 3
  }
}
```

### 3.3 Native `ComponentDiffer` Deltas Over `POST /api/ui-event`
- **Single-card mutation** (e.g. toggling favorite on `id: 2` via `$grid->card(2)->patch(...)`):
  ```json
  {
    "742106541": {
      "badge": "★ Favorita",
      "badge_style": "warning",
      "footer_text": "En tu lista",
      "actions": [...]
    }
  }
  ```
  Zero diff on `CardGrid`, zero diff on sibling `ItemCard`s!
- **Card removal** (`$grid->removeCard(5)` or page change detaching cards):
  `{ "742104088": { "parent": null } }`
- **`load_more` (`append: true`)**:
  Existing page-1 `ItemCard`s produce zero diff; response contains ONLY the new page-2 `ItemCard` nodes (`ADD`) + updated `CardGrid.pagination` / `visible_count`.

---

## 4. Framework Integration Points

| File | Responsibility |
|---|---|
| `packages/idei/usim/src/UI.php` | `UI::itemCard()`, `UI::cardGrid()`, and `@deprecated` on `UI::card()`. |
| `packages/idei/usim/src/Components/Card.php` | Marked `@deprecated` since v2.20.0 (backward compatible). |
| `packages/idei/usim/src/Support/ScreenLifecycleOrchestrator.php` | Maps `'item_card'/'itemcard' => ItemCard::class` and `'card_grid'/'cardgrid' => CardGrid::class`; routes `__usim_card_grid:<target>:query` in `handleEvent()` (`queryOnly: false`); traverses `CardGrid::getCards()` in dirty-state clearing/collection. |
| `packages/idei/usim/src/Support/ContainerLayoutLoader.php` | Prefixes `'grd_' => CardGrid::class` and `'icrd_' => ItemCard::class`; aliases `'card_grid'`/`'cardgrid'` and `'item_card'`/`'itemcard'`; `i18n: auto` hydration for both. |
| `packages/idei/usim/resources/views/app.blade.php` | Loads `item-card/index.css`, `card-grid/index.css`, `item-card/index.js`, and `card-grid/index.js`. |
| `packages/idei/usim/resources/assets/js/components/item-card/index.js` | `UsimItemCardComponent` (`'item_card'`). Safe DOM rendering, `⋮` overflow menu, in-place `update()` reusing unchanged `<img>` DOM nodes, and notifying parent `CardGrid` on `card_order` change. |
| `packages/idei/usim/resources/assets/js/components/card-grid/index.js` | `UsimCardGridComponent` (`'card_grid'`). Implements `getChildMountTarget()` returning `.usim-cg-grid`, `MutationObserver` + `scheduleOrderSync()` keeping children sorted by `data-card-order`, integrated toolbar & pagination dispatching `POST /api/ui-event`. |

---

## 5. Testing Patterns

- **Unit tests (`tests/Unit/CardGridComponentTest.php`):** standalone `ItemCard`, `CardGrid` child tree serialization (`parent = $grid->getId()`), DTO normalization, `.layout.yaml` `grd_`/`icrd_`/`card_grid`/`item_card` auto-instantiation, and direct tree mutations (`card()`, `patchCard()`, `removeCard()`, `prependCard()`).
- **Feature tests (`tests/Feature/CardGridServiceWiringTest.php`):** lazy `CardListable` binding, `Listable` adapter, `POST /api/ui-event` single-card diff assertions (only mutated keys on the target `ItemCard`, zero diff on `CardGrid` or siblings), search reuse + `parent: null` detach, multi-filter queries, and `load_more` appending ONLY new `ItemCard`s.
