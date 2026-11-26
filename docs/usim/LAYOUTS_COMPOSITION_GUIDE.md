# 🧩 Guía Completa de Posibilidades de Layouts en USIM

> Catálogo práctico de patrones de diseño y composición en `.layout.yaml` y `Screen`: desde grids planos y posicionamiento 2D hasta contenedores anidados *inline*, inclusión modular de archivos y composición de sub-screens.

---

## 📑 Índice

1. [Layouts de Contenedor vs. Layouts de Página (`AbstractLayout`)](#1-layouts-de-contenedor-vs-layouts-de-página-abstractlayout)
2. [Grid Declarativo: Columnas, Filas y Tracks](#2-grid-declarativo-columnas-filas-y-tracks)
3. [Posicionamiento 2D (`at:`) y Spanning (`span:`)](#3-posicionamiento-2d-at-y-spanning-span)
4. [Contenedores Anidados *Inline* (`grid:` dentro de `children:`)](#4-contenedores-anidados-inline-grid-dentro-de-children)
5. [Contenedores Estructurales sin Propiedad PHP en `Screen`](#5-contenedores-estructurales-sin-propiedad-php-en-screen)
6. [Referencias a Otros Layouts (`include`, `layout_file`, `file`, `layout`)](#6-referencias-a-otros-layouts-include-layout_file-file-layout)
7. [Sobrescritura de Propiedades de Caja (`overrides`) y Detección de Ciclos](#7-sobrescritura-de-propiedades-de-caja-overrides-y-detección-de-ciclos)
8. [Layouts Flex / Horizontal / Vertical](#8-layouts-flex--horizontal--vertical)
9. [Slots y Composición de Sub-Screens (`embedToSlot`)](#9-slots-y-composición-de-sub-screens-embedtoslot)
10. [Buenas Prácticas: ¿Cuándo usar *Inline*, `include:` o `embedToSlot`?](#10-buenas-prácticas-cuándo-usar-inline-include-o-embedtoslot)
11. [Auto-instanciación por Prefijo, `component:` y Timers No Visuales (`timers:`)](#11-auto-instanciación-por-prefijo-component-y-timers-no-visuales-timers)
12. [Proyección Declarativa de Slots hacia el `AbstractLayout` (`slots:`)](#12-proyección-declarativa-de-slots-hacia-el-abstractlayout-slots)

---

## 1. Layouts de Contenedor vs. Layouts de Página (`AbstractLayout`)

En USIM ambos niveles comparten **el mismo motor declarativo (`.layout.yaml` + `ContainerLayoutLoader`)**:

- **Layout de Página (`AbstractLayout`):** Define el marco global de la aplicación. USIM incluye dos layouts listos para usar directamente en el paquete:
  - **`Idei\Usim\Layout\WorkbenchLayout`** *(alias `'workbench'`)*: Activity Bar fija de `52px` en `[1, 1]`, panel izquierdo `left_panel` en `Split` colapsable a `0px`, cabecera sticky `top_bar` oculta por defecto sobre `main`, panel inferior `bottom_panel` en `Split` vertical colapsable a `0px`, `right_panel` en `0px` y `status_bar` de ancho completo.
  - **`Idei\Usim\Layout\MainLayout`** *(alias `'main'`)*: barra superior clásica (`top_menu` / `menu`) + contenido principal (`main`).
  - Se declara en la `Screen` con `public static ?string $layout = 'default';` (resuelve `guest_layout` vs `default_layout`), un alias corto (`'workbench'`, `'main'`, `'none'`), una clase explícita o `null` para pantallas standalone/kiosk. Si tu app publica un override en `App\UI\Layouts\WorkbenchLayout` (lo hace automáticamente `php artisan usim:install`), los aliases priorizan tu clase de app y heredan el `.layout.yaml` del paquete salvo que definas un `.layout.yaml` hermano propio.
- **Layout de Contenedor (`.layout.yaml`):** Define la estructura interna de una `Screen`, un `Container` o un `AbstractLayout` (CSS Grid, `component: split` con panes `first:` / `second:`, `layout_slot`, cajas, espaciados, alineación y propiedades de componentes). Se procesa en PHP mediante `Idei\Usim\Support\ContainerLayoutLoader`.

> **Scaffolding rápido de Screens declarativas:** generá una Screen ya configurada para `WorkbenchLayout` con su `.layout.yaml` hermano, slots, `i18n: auto` y test de Pest en un solo comando:
> ```bash
> php artisan usim:screen Orders/OrderExplorer --layout=workbench --slots=top_bar,left_panel,right_panel,status_bar --visibility=authenticated --i18n --test --discover
> ```
> También podés correr `php artisan usim:screen` sin argumentos para usar el asistente interactivo, o usar presets como `--type=workspace`, `--type=modal` o `--type=kiosk`.
>
> Para la referencia técnica completa de `WorkbenchLayout`, `Split` declarativo, `NavTree`, `right_panel`, `StatusBar` y transiciones de layout, consultá [WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md](./WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md).

---

## 2. Grid Declarativo: Columnas, Filas y Tracks

### 2.1 Grid de 12 columnas (ideal para formularios y dashboards responsivos)

```yaml
name: item_list_demo_wrapper
appearance: card
max_width: 900
center_horizontal: true

grid:
  cols: 12
  gap: s
  padding: l
  default_span: 12

  children:
    - title:         { span: 12, size: xl, font_weight: bold }
    - sel_filter:    { span: 5 }
    - chk_active:    { span: 4, align: center }
    - btn_reset:     { span: 3, justify: end, style: secondary }
```

### 2.2 Tracks explícitos con fracciones (`fr`), píxeles y `auto`

```yaml
grid:
  cols: [260, 1fr]           # 260px sidebar + resto fluido
  rows: [auto, 1fr, auto]    # Header + cuerpo expansivo + footer
  gap: m
```

También admite strings CSS literales en `cols` o `rows`:

```yaml
grid:
  cols: "repeat(auto-fit, minmax(280px, 1fr))"
  gap: m
```

### 2.3 Cálculo automático de filas (`row` / `row_height`)

Si omites `rows:` arriba, puedes indicar la altura de fila directamente en un hijo:

```yaml
grid:
  cols: [1fr, 1fr]
  default_span: 2
  children:
    - header_lbl
    - body_textarea: { row: 1fr }   # Convierte la fila de body_textarea en 1fr
    - footer_btn:    { row_height: auto }
```

---

## 3. Posicionamiento 2D (`at:`) y Spanning (`span:`)

Cuando necesitas ubicar elementos en coordenadas exactas `[fila, columna]` (1-based), usa `at:` combinado opcionalmente con `span:`:

```yaml
grid:
  cols: [1fr, 1fr]
  rows: [auto, 1fr, auto]
  gap: m

  children:
    - hero_banner:  { at: [1, 1], span: full }     # Fila 1, columnas 1..2
    - left_panel:   { at: [2, 1], align: stretch } # Fila 2, columna 1
    - right_panel:  { at: [2, 2], align: stretch } # Fila 2, columna 2
    - status_bar:   { at: [3, 1], span: [1, 2] }   # Fila 3, 1 fila x 2 columnas
```

---

## 4. Contenedores Anidados *Inline* (`grid:` dentro de `children:`)

Puedes definir sub-grids directamente dentro de un contenedor hijo sin crear archivos `.layout.yaml` adicionales. Esto es ideal para micro-estructuras locales (como una barra de prompt `[1fr, auto]` o una fila de acciones).

```yaml
name: reel_studio_panel
appearance: plain
width: 100%
height: 100%
i18n: auto

grid:
  cols: [1fr]
  rows: [auto, auto, 1fr, auto]
  padding: none
  gap: xs

  children:
    - lbl_post_title:
        at: [1, 1]
        width: full
        size: xl
        font_weight: bold
        text: .post_title

    - cnt_status_row:
        at: [2, 1]
        width: full
        grid:
          cols: [auto, 1fr]
          gap: s
          align: center
          children:
            - lbl_status_caption: { text: "ESTADO:" }
            - badge_status:       { justify: start }

    - lst_history:
        at: [3, 1]
        width: full
        service: App\Services\Post\PostHistoryService
        selection_mode: single

    - cnt_prompt_input_row:
        at: [4, 1]
        width: full
        appearance: elevated
        padding: xs
        grid:
          cols: [1fr, auto]
          gap: s
          align: center
          children:
            - input_prompt: { width: full, placeholder: .prompt_placeholder }
            - btn_send:     { style: primary, icon: send, action: send_prompt }
```

En este ejemplo:
- `cnt_prompt_input_row` se coloca en la fila 4 del panel padre (`at: [4, 1]`).
- Internamente es un grid de 2 columnas `[1fr, auto]`: el input reclama todo el ancho disponible (`1fr`) y el botón queda anclado a la derecha con su ancho natural (`auto`).

---

## 5. Contenedores Estructurales sin Propiedad PHP en `Screen`

Si un contenedor anidado (`cnt_prompt_input_row`, `cnt_status_row`, etc.) solo cumple un rol visual/estructural y no necesitas manipularlo desde PHP, **no estás obligado a declararlo como propiedad** en la clase `Screen`.

```php
class ReelStudioScreen extends Screen
{
    protected NewLabel $lbl_post_title;
    protected NewLabel $lbl_status_caption;
    protected NewLabel $badge_status;
    protected ItemList $lst_history;
    protected Input $input_prompt;
    protected Button $btn_send;

    // No hace falta declarar `protected Container $cnt_prompt_input_row;`
    // USIM lo crea automáticamente al detectar su `grid:` o `include:` en el YAML
    // y monta `input_prompt` y `btn_send` dentro de él.
}
```

Si en algún momento necesitas ocultarlo o cambiarlo dinámicamente en PHP (por ejemplo `$this->cnt_prompt_input_row->visible(false)`), basta con declarar `protected Container $cnt_prompt_input_row;` en la `Screen` y USIM reutilizará esa instancia.

---

## 6. Referencias a Otros Layouts (`include`, `layout_file`, `file`, `layout`)

Cuando un bloque de UI se repite en múltiples pantallas o crece demasiado para mantenerse *inline*, puedes extraerlo a su propio `.layout.yaml` y referenciarlo desde el layout padre.

### 6.1 Claves soportadas para referenciar archivos

| Clave YAML | Comportamiento |
|---|---|
| `include: ruta/archivo` | Carga `ruta/archivo` (o `.layout.yaml` / `.yaml`) como definición del sub-contenedor. |
| `layout_file: ruta/archivo` | Alias explícito equivalente a `Container::layoutFile()`. |
| `file: ruta/archivo` | Alias corto equivalente a `include:`. |
| `layout: ruta/archivo` | Si el valor **no** es `vertical`, `horizontal`, `grid` o `flex`, se resuelve como archivo `.layout.yaml`. |

### 6.2 Ejemplo modular

**Archivo compartido:** `partials/prompt-row.layout.yaml`

```yaml
appearance: subtle
padding: s

grid:
  cols: [1fr, auto]
  gap: s
  align: center
  children:
    - input_prompt: { width: full, placeholder: .prompt_placeholder }
    - btn_send:     { style: primary, icon: send, action: send_prompt }
```

**Archivo principal:** `reel-studio.layout.yaml`

```yaml
name: reel_studio_main
grid:
  cols: [1fr]
  rows: [auto, 1fr, auto]
  gap: m
  children:
    - lbl_title: { at: [1, 1], size: xl }
    - lst_history: { at: [2, 1], width: full }
    - cnt_prompt_input_row:
        at: [3, 1]
        width: full
        include: partials/prompt-row
```

### 6.3 Orden de resolución de rutas

Cuando un `.layout.yaml` referencia otro archivo mediante `include:` / `layout_file:` / `file:` / `layout:`:

1. Relativo al directorio del `.layout.yaml` padre que lo incluye.
2. Relativo al directorio de la clase `Screen` llamante.
3. Ruta directa en el sistema de archivos.
4. Relativo a `base_path(...)` (útil para rutas desde la raíz del proyecto, ej. `app/UI/Shared/prompt-row.layout.yaml`).

Si la ruta no termina en `.yaml` ni `.yml`, USIM prueba automáticamente `<ruta>.layout.yaml` y `<ruta>.yaml`.

---

## 7. Sobrescritura de Propiedades de Caja (`overrides`) y Detección de Ciclos

### 7.1 Overrides desde el padre

Cualquier propiedad de caja declarada junto al `include:` en el padre (`appearance`, `card`, `center_horizontal`, `center`, `padding`, `gap`, `row_gap`, `column_gap`, `width`, `max_width`, `height`, `i18n`) **sobrescribe** la definición del archivo incluido:

```yaml
    - cnt_prompt_input_row:
        at: [3, 1]
        width: full
        include: partials/prompt-row
        appearance: elevated   # Sobrescribe `appearance: subtle` de prompt-row.layout.yaml
        padding: xs            # Sobrescribe `padding: s`
```

### 7.2 Detección de ciclos

Si `a.layout.yaml` incluye `b.layout.yaml` y `b.layout.yaml` vuelve a incluir `a.layout.yaml` (directa o indirectamente), `ContainerLayoutLoader` detecta el ciclo y lanza `InvalidArgumentException` indicando la ruta conflictiva.

---

## 8. Layouts Flex / Horizontal / Vertical

Para contenedores simples donde no necesitas columnas `grid`, puedes seguir usando `layout: horizontal`, `layout: vertical` o `layout: flex` junto con propiedades de alineación y espaciado:

```yaml
    - cnt_actions:
        at: [4, 1]
        layout: horizontal
        gap: s
        align_items: center
        justify_content: flex-end
```

> **Tip UX:** Si necesitas que un elemento crezca para llenar el espacio restante y otro quede fijo a la derecha (como input + botón), prefiere un sub-grid `cols: [1fr, auto]` en lugar de `layout: horizontal` con `justify_content: space-between`.

---

## 9. Slots y Composición de Sub-Screens (`embedToSlot`)

Cuando cada columna o panel tiene su propia lógica de negocio, estado y ciclo de vida (`Screen` independiente), combina un `.layout.yaml` maestro con `embedToSlot()`:

```yaml
# reel-studio.layout.yaml
name: reel_studio_main_row
appearance: card
width: 100%
max_width: 1200
center_horizontal: true

grid:
  cols: [1fr, 1fr]
  padding: s
  gap: m
  children:
    - edit_panel:             { at: [1, 1], min_width: 400, align: stretch }
    - reel_scene_viewer_slot: { at: [1, 2], min_width: 400, align: stretch, justify: stretch, width: full }
```

```php
protected function startingUI(): Container
{
    $root = $this->layoutContainer();
    $root->embedToSlot('edit_panel', ReelEditScreen::class, ['post_id' => $this->postId]);
    $root->embedToSlot('reel_scene_viewer_slot', SceneViewerScreen::class, ['post_id' => $this->postId]);

    return $root;
}
```

---

## 10. Buenas Prácticas: ¿Cuándo usar *Inline*, `include:` o `embedToSlot`?

| Escenario | Herramienta recomendada | Por qué |
|---|---|---|
| Agrupar 2-4 elementos locales (input + botón, label + badge) | **`grid:` inline** | Evita proliferación de micro-archivos y mantiene la estructura visible en un solo lugar. |
| Barra, cabecera o bloque reutilizable en varias pantallas | **`include:` / `layout_file:`** | Un único archivo fuente reutilizable con overrides por instancia y sin lógica PHP duplicada. |
| Filtros, inspector o ítems de `status_bar` acoplados al estado de la misma `Screen` | **`slots:` en `.layout.yaml`** | Proyecta subárboles hacia `left_panel`, `right_panel` o `status_bar*` compartiendo propiedades `protected`, `$state_*`, `i18n: auto` y handlers `on*()` sin `UsimEvent`. |
| Panel complejo reutilizable con estado propio, eventos y servicio independiente | **`embedToSlot()` / `slots.<slot>.screen:` / `showInto()`** | Aislamiento completo de ciclo de vida, estado (`store_*`/`state_*`) y traducciones. |
| Posicionar una `Screen` dentro del marco general de la app | **`AbstractLayout`** (`$layout`) | Separa el shell global (menú/navbar/workbench) del contenido de cada pantalla. |

---

## 11. Auto-instanciación por Prefijo, `component:` y Timers No Visuales (`timers:`)

### 11.1 Componentes sin propiedad `protected` en `Screen`

Si un componente declarado en `.layout.yaml` no necesita ser manipulado desde PHP, **no necesitas declararlo como propiedad `protected`** en la `Screen`. USIM lo instancia automáticamente usando su prefijo convencional o la clave explícita `component:`:

| Prefijo | Clase instanciada | Alias `component:` |
|---|---|---|
| `input_` | `Input` | `input` |
| `btn_` | `Button` | `button` |
| `lbl_` | `NewLabel` | `new_label` / `label` |
| `sel_` | `Select` | `select` |
| `chk_` | `Checkbox` | `checkbox` |
| `txt_` | `Textarea` | `textarea` |
| `rte_` | `RichText` | `rich_text` / `richtext` |
| `tbl_` | `Table` | `table` |
| `lst_` | `ItemList` | `item_list` / `list` |
| `grd_` | `CardGrid` | `card_grid` / `cardgrid` |
| `icrd_` | `ItemCard` | `item_card` / `itemcard` |
| `img_` | `Image` | `image` |
| `cal_` | `Calendar` | `calendar` |
| `car_` | `Carousel` | `carousel` |
| `upl_` | `Uploader` | `uploader` |
| `crd_` | `Card` | `card` |
| `cnt_` | `Container` | `container` / `split` |
| `nav_` | `NavTree` | `nav_tree` / `navtree` |
| `tmr_` | `Timer` | `timer` |

```yaml
grid:
  cols: [1fr, auto]
  children:
    - lbl_title: { text: "Título", size: xl }                 # Instancia NewLabel
    - submit_action: { component: button, label: "Enviar" }   # Instancia Button sin prefijo
```

Si eventualmente necesitas acceder en PHP a un componente auto-instanciado, puedes usar `$this->ui('btn_send')` en la `Screen`.

### 11.2 Sección raíz `timers:` y control desde `Screen`

Para componentes autónomos no visuales (`Timer`), usa la sección raíz `timers:` fuera de `grid:` para no ocupar celdas ni alterar el flujo de filas:

```yaml
grid:
  cols: [1fr]
  children:
    - lbl_status

timers:
  - tmr_poll_status:
      action: check_generation_status
      every: 3000
      enabled: false
```

Desde tu `Screen` puedes controlarlos con los helpers tipados:

```php
$this->startTimer('tmr_poll_status');   // o $this->startTimer() si hay un único Timer
$this->stopTimer('tmr_poll_status');    // o $this->stopTimer()
$timer = $this->timer('tmr_poll_status'); // ?Timer
```

---

## 12. Proyección Declarativa de Slots hacia el `AbstractLayout` (`slots:`)

Además de definir su propio contenido principal en `main` (`grid:` raíz o `slots.main` / `slots.center`), una `Screen` puede usar la sección raíz **`slots:`** en su `.layout.yaml` para proyectar contenedores, componentes o Sub-Screens directamente hacia los paneles y zonas del `AbstractLayout` activo (por ejemplo `top_bar`, `left_panel`, `right_panel`, `bottom_panel`, `status_bar`, `status_bar_left`, `status_bar_right` en `WorkbenchLayout`).

Todos los componentes proyectados de forma *inline* o vía `include:` **permanecen dentro de la misma `Screen` PHP** (comparten propiedades `protected`, `$state_*`, `$store_*`, `i18n: auto` y handlers `on*()`), sin necesidad de crear pantallas satélites ni coordinar con `UsimEvent`.

### 12.1 Ejemplo completo

```yaml
name: catalog_workspace
i18n: auto

grid:
  cols: [1fr]
  gap: m
  children:
    - lbl_title:    { size: xl, font_weight: bold }
    - tbl_products: { width: full }

slots:
  top_bar:                # Cabecera sticky sobre main (aliases: header, toolbar); oculta (0px) por defecto si no se proyecta
    open: true
    fallback: ignore
    appearance: plain
    padding: s
    grid:
      cols: [1fr, auto]
      align: center
      children:
        - lbl_breadcrumb: { text: "Catálogo / Productos", font_weight: bold }
        - btn_console:    { label: ">_ Consola", size: xs, style: secondary, action: toggle_console }

  left_panel:
    open: true            # Expande el Split al entrar (default: true)
    size: 280px           # Ajusta el ancho del panel temporalmente; se restaura al salir
    fallback: inline_top  # Si el layout activo no tiene left_panel, se renderiza arriba de main
    appearance: subtle
    padding: m
    grid:
      cols: [1fr]
      gap: s
      children:
        - lbl_filters:  { font_weight: bold }
        - input_search: { width: full }
        - btn_filter:   { style: primary, action: apply_filter, width: full }

  right_panel:
    open: false           # Arranca colapsado; se expande con $this->openSlot('right_panel')
    size: 360px           # Soporta px o % incluso en el segundo panel del Split
    fallback: ignore      # Default: si no existe en el layout, no se monta en DOM sin romper PHP
    grid:
      cols: [1fr]
      gap: s
      children:
        - lbl_detail_title
        - btn_close_detail: { label: "✕", size: xs, action: close_detail }

  # O montar declarativamente una Sub-Screen autónoma en cualquier slot:
  # right_panel:
  #   open: true
  #   size: 380px
  #   screen: App\UI\Screens\Demo\RightPanelInspector
  #   params: { title: "Inspector" }

  bottom_panel:           # Panel inferior colapsable en Split vertical (aliases: bottom, panel, console, terminal)
    open: false           # Arranca en 0px; se abre con $this->openSlot('bottom_panel') o toggleSlot('bottom_panel')
    size: 220px
    fallback: ignore
    appearance: subtle
    padding: m
    grid:
      cols: [1fr]
      rows: [1fr, auto]
      gap: s
      children:
        - lbl_console_output: { at: [1, 1], width: full }
        - cnt_prompt_row:
            at: [2, 1]
            grid:
              cols: [1fr, auto]
              gap: s
              children:
                - input_command:   { width: full, placeholder: "Comando o prompt..." }
                - btn_run_command: { label: "Ejecutar", style: primary, size: small, action: run_command }

  status_bar:             # Se proyecta en status_bar_center y oculta lbl_status_message mientras está activo
    children:
      - lbl_items_count: { text: "0 items" }

  status_bar_right:       # Se inserta antes de btn_reset, preservando los controles globales del shell
    children:
      - btn_sync: { size: xs, style: info, action: sync_now }
```

### 12.2 Reglas clave

- **`grid:` raíz vs. `slots.main` / `slots.center`:** Puedes usar `grid:` en la raíz **o** declarar el contenido principal en `slots.main` / `slots.center` (declarar ambos simultáneamente lanza `InvalidArgumentException`).
- **Sub-slots contextuales en `StatusBar`:** `status_bar` (alias de `status_bar_center`), `status_bar_left` y `status_bar_right` preservan siempre `unit_menu` y `btn_reset`. Cuando una `Screen` proyecta en `status_bar` / `status_bar_center`, `lbl_status_message` se oculta automáticamente y vuelve a mostrarse al limpiar o navegar fuera de la pantalla.
- **Limpieza automática al navegar:** Al navegar `main` hacia otra `Screen`, los portales con `persist_on_navigate: false` (default) se limpian automáticamente, sus `Split` se colapsan y se restauran los tamaños por defecto del `AbstractLayout`.
- **Modos de `fallback`:** `ignore` (default, seguro para `MainLayout` o `$layout = null`), `inline` / `inline_bottom` e `inline_top`.

### 12.3 Control desde PHP (`Screen`)

```php
$this->openSlot('right_panel');          // Expande el Split (y re-monta el portal si otro ocupó el slot)
$this->openSlot('right_panel', '420px'); // Expande aplicando un tamaño dinámico
$this->closeSlot('right_panel');         // En portales propios: colapsa preservando el estado de sus componentes
$this->closeSlot('right_panel', clear: true); // Fuerza limpieza del DOM además de colapsar
$this->toggleSlot('left_panel');         // Alterna abierto/cerrado
$this->isSlotOpen('right_panel');        // bool
$this->slotContainer('left_panel');      // ?Container (acceso directo al contenedor portal)
```


