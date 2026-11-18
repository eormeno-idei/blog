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

---

## 1. Layouts de Contenedor vs. Layouts de Página (`AbstractLayout`)

En USIM existen dos niveles complementarios de layout:

- **Layout de Página (`AbstractLayout`):** Define el marco global de la aplicación (menú lateral, barra superior, área principal de contenido). Se declara en la `Screen` con `public static ?string $layout = 'default';` (o `null` para pantallas tipo *kiosk*).
- **Layout de Contenedor (`.layout.yaml`):** Define la estructura interna de una `Screen` o `Container` (CSS Grid, cajas, espaciados, alineación y propiedades de componentes). Se procesa en PHP mediante `Idei\Usim\Support\ContainerLayoutLoader`.

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
| Panel complejo con estado propio, eventos y servicio independiente | **`embedToSlot()` (Sub-Screen)** | Aislamiento completo de ciclo de vida, estado (`store_*`/`state_*`) y traducciones. |
| Posicionar una `Screen` dentro del marco general de la app | **`AbstractLayout`** (`$layout`) | Separa el shell global (menú/navbar) del contenido de cada pantalla. |

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
| `tbl_` | `Table` | `table` |
| `lst_` | `ItemList` | `item_list` / `list` |
| `img_` | `Image` | `image` |
| `cal_` | `Calendar` | `calendar` |
| `car_` | `Carousel` | `carousel` |
| `upl_` | `Uploader` | `uploader` |
| `crd_` | `Card` | `card` |
| `cnt_` | `Container` | `container` / `split` |
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

