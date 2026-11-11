# 📐 Layouts Declarativos (`.layout.yaml`) e Internacionalización (i18n) en USIM

> **Referencia técnica completa** de la sintaxis YAML de contenedores, ciclo de vida en `Screen`, sistema de slots/grids y arquitectura de internacionalización en dos niveles.

---

## 📑 Índice

1. [Visión General](#1-visión-general)
2. [Resolución y Ubicación de Archivos `.layout.yaml`](#2-resolución-y-ubicación-de-archivos-layoutyaml)
3. [Integración con `Screen` y Ciclo de Vida](#3-integración-con-screen-y-ciclo-de-vida)
4. [Referencia Completa de Sintaxis YAML](#4-referencia-completa-de-sintaxis-yaml)
   - [4.1 Propiedades de Contenedor / Caja Raíz](#41-propiedades-de-contenedor--caja-raíz)
   - [4.2 Propiedades del Grid (`grid`)](#42-propiedades-del-grid-grid)
   - [4.3 Escala Semántica de Espaciado (`Spacing`)](#43-escala-semántica-de-espaciado-spacing)
   - [4.4 Tamaños y Tracks (`Size` / Grid Tracks)](#44-tamaños-y-tracks-size--grid-tracks)
   - [4.5 Definición de Hijos (`children` e `items`)](#45-definición-de-hijos-children-e-items)
   - [4.6 Reglas de Posicionamiento y Spanning](#46-reglas-de-posicionamiento-y-spanning)
   - [4.7 Propiedades Personalizadas y Delegación Fluida](#47-propiedades-personalizadas-y-delegación-fluida)
5. [Sistema de Slots y Composición de Sub-Screens](#5-sistema-de-slots-y-composición-de-sub-screens)
6. [Sistema de Internacionalización (i18n)](#6-sistema-de-internacionalización-i18n)
   - [6.1 Convención de Claves por Namespace](#61-convención-de-claves-por-namespace)
   - [6.2 Prioridad y Motores de Traducción (`UsimTranslator`)](#62-prioridad-y-motores-de-traducción-usimtranslator)
   - [6.3 Claves Relativas en PHP (`t('.clave')` vs `$this->t('.clave')`)](#63-claves-relativas-en-php-tclave-vs-this-tclave)
   - [6.4 Nivel 1: Claves Explícitas en `.layout.yaml` (`.clave` y `t:`)](#64-nivel-1-claves-explícitas-en-layoutyaml-clave-y-t)
   - [6.5 Nivel 2: Auto-hidratación por Convención (`i18n: auto`)](#65-nivel-2-auto-hidratación-por-convención-i18n-auto)
   - [6.6 Tabla de Schemas y Prefijos por Componente](#66-tabla-de-schemas-y-prefijos-por-componente)
   - [6.7 Reglas de Precedencia y Seguridad (`isConfigDefault`)](#67-reglas-de-precedencia-y-seguridad-isconfigdefault)
7. [Ejemplos Completos de Producción](#7-ejemplos-completos-de-producción)
   - [7.1 Pantalla Declarativa Pura (`FormDemo`, sin `startingUI`)](#71-pantalla-declarativa-pura-formdemo-sin-startingui)
   - [7.2 Pantalla Declarativa + Datos Dinámicos (`ItemListDemo` con `layoutContainer()`)](#72-pantalla-declarativa--datos-dinámicos-itemlistdemo-con-layoutcontainer)
   - [7.3 Dashboard Multi-Panel con `embedToSlot()`](#73-dashboard-multi-panel-con-embedtoslot)

---

## 1. Visión General

El mecanismo de layouts declarativos de USIM separa limpiamente **estructura visual, CSS Grid y traducción** (en archivos `.layout.yaml` + `lang/`) de la **lógica de dominio, datos dinámicos y manejadores de eventos** (en PHP).

**Importante sobre arquitectura Server-Driven UI:**
El archivo `.layout.yaml` **se procesa íntegramente en el backend PHP** (`Idei\Usim\Support\ContainerLayoutLoader`), se cachea en memoria por `realPath:mtime` y genera el árbol estándar de componentes USIM (`Container` + `UIComponent`) que luego viaja como JSON al renderer frontend. El cliente navegador **no interpreta archivos YAML**.

---

## 2. Resolución y Ubicación de Archivos `.layout.yaml`

`ContainerLayoutLoader::resolvePath(?string $path = null, ?string $callingContext = null)` resuelve las rutas en este orden:

1. **Resolución automática por convención (`$path === null` o vacío):**
   Busca en el **mismo directorio** que la clase `Screen` llamante un archivo llamado:
   ```text
   <nombre-de-clase-en-kebab-case>.layout.yaml
   ```
   - `App\UI\Screens\Demo\FormDemo` → `app/UI/Screens/Demo/form-demo.layout.yaml`
   - `App\UI\Screens\Demo\ItemListDemo` → `app/UI/Screens/Demo/item-list-demo.layout.yaml`
   - `App\UI\Screens\Admin\UsersManager` → `app/UI/Screens/Admin/users-manager.layout.yaml`

2. **Ruta o alias explícito (`$path !== null`):**
   Dado un string como `'custom-form'`, genera candidatos en orden:
   - `'custom-form'`
   - `'custom-form.layout.yaml'`
   - `'custom-form.yaml'`

   Y los busca sucesivamente en:
   1. Directorio de la `Screen` llamante (`<ScreenDir>/<candidato>`).
   2. Ruta directa en el sistema de archivos.
   3. Relativo a `base_path(<candidato>)` (útil para rutas desde la raíz del proyecto como `'app/UI/Screens/Demo/form-demo.layout.yaml'`).

---

## 3. Integración con `Screen` y Ciclo de Vida

Las subclases de `Idei\Usim\Screen` cuentan con tres modalidades de inicialización compatibles entre sí:

### Modalidad A: Cero código de montaje (sin `startingUI()` ni `buildBaseUI()`)
Si la `Screen` omite `startingUI()` y `buildBaseUI()`, `Screen::callBuildBaseUI()` verifica automáticamente si existe `static::$layoutFile !== null` o un archivo hermano `<kebab-name>.layout.yaml` (`ContainerLayoutLoader::hasDefaultLayout()`). Si existe, construye automáticamente el contenedor raíz llamando a `$this->layoutContainer(static::$layoutFile)`.

```php
class FormDemo extends Screen
{
    protected NewLabel $lbl_instruction;
    protected Input $input_name;
    protected Button $btn_submit;

    // Sin startingUI(): USIM carga automáticamente form-demo.layout.yaml
}
```

### Modalidad B: Inicialización de datos + `$this->layoutContainer()`
Cuando la pantalla necesita cargar datos iniciales, opciones dinámicas o selección inicial antes de devolver el contenedor, implementa `startingUI()` y finaliza devolviendo `$this->layoutContainer()`:

```php
protected function layoutContainer(?string $path = null, ?string $name = null): Container
```
- `$path`: opcional; si se omite usa `static::$layoutFile` o infiere `<screen-kebab>.layout.yaml`.
- `$name`: opcional; si se omite usa la clave raíz `name:` del YAML, o en su defecto `<screen_snake>_wrapper` (ej. `item_list_demo_wrapper`).

```php
protected function startingUI(): Container
{
    $this->ensureCatalogLoaded();
    $this->movies_list->items($this->store_movies);

    return $this->layoutContainer();
}
```

### Modalidad C: Propiedades de clase opcionales en `Screen`
```php
// Ruta/alias de layout declarativo por defecto cuando no se define startingUI()
protected static ?string $layoutFile = null;

// Activa la auto-hidratación por convención i18n aunque el YAML no declare `i18n: auto`
protected static bool $autoTranslate = false;
```
> **Nota:** No confundir `protected static ?string $layoutFile` (archivo `.layout.yaml` del contenido de la Screen) con `public static ?string $layout = 'default'` (clase `AbstractLayout` externa que envuelve la página con menú/frame).

### Orden de ejecución durante el render inicial
1. `Screen::autoWireComponentProperties()` instancia todas las propiedades tipadas como `UIElement` que no estén inicializadas.
2. Ejecuta `buildBaseUI()`, `startingUI()`, o fallback `$this->layoutContainer()`.
3. `HandlesScreenProperties::reconcileComponentProperties()`:
   - Auto-asigna `name` e ID determinístico a elementos creados sin nombre.
   - En `reconcileLayoutContainers()`: auto-monta en el contenedor cualquier propiedad declarada en `children` del YAML que aún no tenga padre (`getParent() === null`), reordena hijos según el YAML, aplica propiedades de caja, placement CSS Grid, propiedades personalizadas (con resolución i18n **Nivel 1**) y finalmente **Nivel 2 (`i18n: auto`)**.
4. Si existe `updateUI()` / `postLoadUI()`, se ejecuta después del montaje base para hidratar o resetear estado dinámico.

---

## 4. Referencia Completa de Sintaxis YAML

Un archivo `.layout.yaml` acepta propiedades de caja/i18n en la raíz del documento y la sección `grid:` (o las propiedades de `grid` directamente).

```yaml
# Metadatos y caja raíz (opcionales)
name: form_wrapper
appearance: card        # o `card: true`
max_width: 500
center_horizontal: true
i18n: auto

grid:
  cols: [1fr, 1fr]
  rows: [auto, 1fr, auto]
  gap: m
  row_gap: m
  column_gap: s
  padding: l
  default_span: 2
  align: center
  justify: stretch

  children:
    - ...
```

### 4.1 Propiedades de Contenedor / Caja Raíz

Aplicadas por `ContainerLayoutLoader::applyBoxProperties()` tanto en la raíz del YAML como dentro de `grid:` (si se colocan ahí):

| Clave YAML | Tipo | Método PHP equivalente | Notas |
|---|---|---|---|
| `name` | `string` | `$wrapperName` en `Screen::layoutContainer()` | Nombre del contenedor raíz (`$ui->component('...')`). Si se omite: `<screen_snake>_wrapper`. |
| `card` | `bool` | `$container->card()` | Si es `true`, asigna `appearance: 'card'`. |
| `appearance` | `string` | `$container->appearance($val)` | Ej. `'card'`, `'flat'`, `'outlined'`, etc. |
| `center_horizontal` | `bool` | `$container->centerHorizontal()` | Si es `true`, centra horizontalmente (`margin_left: auto`, `margin_right: auto`). |
| `center` | `bool` | `$container->centerHorizontal()` | Alias de `center_horizontal` a nivel contenedor. |
| `width` | `int\|string` | `$container->width(Size::from(...))` | Acepta `500`, `'500px'`, `'100%'`, `'50rem'`. |
| `max_width` | `int\|string` | `$container->maxWidth(Size::from(...))` | Ancho máximo del contenedor. |
| `height` | `int\|string` | `$container->height(Size::from(...))` | Alto del contenedor. |
| `padding` | `int\|string` | `$container->padding(Spacing::from(...))` | Escala semántica (`l`), entero (`24` → `24px`), o CSS (`'1rem 2rem'`). |
| `gap` | `int\|string` | `$container->gap(Spacing::from(...))` | Espaciado entre filas y columnas del grid. |
| `row_gap` | `int\|string` | `$container->rowGap(Spacing::from(...))` | Espaciado vertical entre filas. |
| `column_gap` | `int\|string` | `$container->columnGap(Spacing::from(...))` | Espaciado horizontal entre columnas. |
| `i18n` | `string\|bool` | Auto-hidratación i18n | Valores `'auto'` o `true` activan auto-traducción (Nivel 2). |

### 4.2 Propiedades del Grid (`grid`)

| Clave YAML | Alias | Descripción | Ejemplos |
|---|---|---|---|
| `cols` | `columns` | Define `grid_template_columns` y calcula `$columnCount`. | `12` (`repeat(12, 1fr)`), `[1fr, 1fr]` (`1fr 1fr`), `[260, 1fr]` (`260px 1fr`), `"repeat(3, minmax(0, 1fr))"` |
| `rows` | — | Define explícitamente `grid_template_rows`. Si se omite, puede calcularse automáticamente según `row` / `row_height` en `children`. | `[auto, 1fr, auto]`, `"auto 1fr"` |
| `default_span` | — | Columnas que ocupa cada hijo cuando no especifica `span`. Por defecto `1`. | `2`, `12`, `full` |
| `align` | — | `align_self` por defecto para todos los hijos del grid. | `start`, `end`, `center`, `stretch`, `baseline` |
| `justify` | — | `justify_self` por defecto para todos los hijos del grid. | `start`, `end`, `center`, `stretch` |
| `children` | — | Lista ordenada o diccionario asociativo con slots, reglas y propiedades de componentes hijos. | Ver sección 4.5 |
| `items` | — | Mapa alternativo por nombre de slot o posición numérica `1-based` (útil solo para aplicar reglas sobre hijos ya añadidos sin reordenar). | `items: { 1: { span: 6 }, btn_ok: { justify: end } }` |

### 4.3 Escala Semántica de Espaciado (`Spacing`)

Las propiedades `padding`, `gap`, `row_gap` y `column_gap` usan `Idei\Usim\ValueObjects\Spacing::from()`, aceptando:

| Token YAML | Valor CSS resultante |
|---|---|
| `none` / `0` | `0` |
| `xs` | `4px` |
| `s` / `sm` | `8px` |
| `m` / `md` | `16px` |
| `l` / `lg` | `24px` |
| `xl` | `32px` |
| `2xl` / `xxl` | `48px` |
| `3xl` / `xxxl` | `64px` |
| Entero (ej. `14`) | `14px` |
| String CSS (ej. `'1.5rem'`, `'12px 24px'`) | Se conserva literal |

### 4.4 Tamaños y Tracks (`Size` / Grid Tracks)

- **En propiedades `width` / `height` de hijos (`UIComponent` o `Container`):**
  - `'full'` → `Size::full()` (`100%`)
  - Entero (ej. `320`) → `Size::px(320)` (`320px`)
  - String CSS (`'50%'`, `'20rem'`, `'fit-content'`, `'auto'`) → literal
- **En tracks (`cols`, `rows`, `row` / `row_height`):**
  - Entero / numérico (`260`, `'260'`) → `'260px'`
  - Cadenas (`'1fr'`, `'auto'`, `'minmax(200px, 1fr)'`) → literales

### 4.5 Definición de Hijos (`children` e `items`)

`ContainerLayoutLoader::normalizeChildEntry()` admite **4 sintaxis en `children:`** (pueden combinarse en el mismo archivo):

1. **Cadena simple (solo slot / propiedad PHP):**
   ```yaml
   children:
     - input_name
     - input_email
   ```
2. **Par clave-valor (la forma más limpia y recomendada):**
   ```yaml
   children:
     - lbl_instruction: { justify: center, style: info, center: true }
     - btn_submit:      { span: 1, style: primary, action: submit_form }
     - movies_list:
         action: movies
         searchable: true
         max_height: 320
   ```
3. **Diccionario asociativo completo en `children`:**
   ```yaml
   children:
     header: { justify: center }
     body: null
   ```
4. **Objeto legacy con `slot:` explícito:**
   ```yaml
   children:
     - { slot: btn_submit, span: 1, style: primary }
     - { slot: 1, span: 2 }   # Por posición 1-based si los hijos no tienen nombre
   ```

> **Ordenamiento y Auto-montaje:**
> Si una propiedad protegida en la `Screen` (ej. `protected Input $input_name;`) coincide con el nombre declarado en `children:` y aún no fue agregada manualmente al contenedor, `HandlesScreenProperties` la monta y reordena los hijos del `Container` exactamente en el orden del YAML.

### 4.6 Reglas de Posicionamiento y Spanning

| Clave en regla hija | Valores soportados | Efecto CSS Grid generado |
|---|---|---|
| `span` | `int`, `'full'`, `[rowSpan, colSpan]`, `[rowSpan, 'full']` | `grid_column: "span X"` (y si `rowSpan > 1`, `grid_row: "span Y"`). `'full'` equivale a todas las columnas del grid. |
| `at` | `[row, col]` (1-based) | Posicionamiento 2D explícito (`grid_row` y `grid_column`, combinable con `span`). Ej. `at: [2, 1]` con `span: 2` → `grid_row: "2"`, `grid_column: "1 / span 2"`. |
| `row` / `row_height` | `int\|string` (ej. `1fr`, `auto`, `200`) | Si no definiste `rows:` arriba en el grid, USIM calcula automáticamente `grid_template_rows` por cada fila lógica según el flujo de `span` y `cols`. |
| `align` | `start`, `end`, `center`, `stretch`, `baseline` | Llama `$element->alignSelf(...)` (sobrescribe `grid.align`). |
| `justify` | `start`, `end`, `center`, `stretch` | Llama `$element->justifySelf(...)` (sobrescribe `grid.justify`). |

### 4.7 Propiedades Personalizadas y Delegación Fluida

Cualquier otra clave en la regla de un hijo (que no sea `slot`, `span`, `at`, `row`, `row_height`, `align`, `justify` ni `null`) se delega al componente así:

1. **Atajos especiales:**
   - `width: full` → `$element->width(Size::full())`; numérico/string → `Size::from(...)`.
   - `height: full` → `$element->height(Size::full())`; numérico/string → `Size::from(...)`.
   - `center: true` → `$element->center()` (si el componente tiene `center()`).
2. **Traducción Nivel 1:** si el valor es `string` y la clave pertenece a `TRANSLATABLE_TEXT_PROPERTIES`, resuelve `.clave` o `t:clave` (ver sección 6.4).
3. **Delegación automática `snake_case` → `camelCase`:**
   Convierte la clave con `Str::camel($key)` e invoca `$element->{$methodName}($value)` si el método existe en el componente:
   - `style: primary` → `->style('primary')`
   - `action: submit_form` → `->action('submit_form')`
   - `required: true` → `->required(true)`
   - `type: email` → `->type('email')`
   - `on_change: selection_mode_changed` → `->onChange('selection_mode_changed')`
   - `max_height: 320` → `->maxHeight(320)`
   - `scrollbar: always` → `->scrollbar('always')`
   - `create_inline: false` → `->createInline(false)`

> **Orden determinista de flags CRUD en `ItemList`:**
> `ContainerLayoutLoader::orderCustomRuleProperties()` garantiza que flags compuestos (`creatable`, `editable`, `deletable`) se apliquen siempre **antes** que modificadores individuales (`create_inline`, `edit_inline`, `allow_bulk_delete`, `create_placeholder`, etc.), haciendo que el orden de las claves en YAML nunca sobrescriba tus ajustes.

---

## 5. Sistema de Slots y Composición de Sub-Screens

Un contenedor gobernado por `.layout.yaml` puede recibir componentes o sub-Screens completas asignadas a slots concretos por **nombre** o por **posición numérica 1-based**:

1. **Con componentes:**
   ```php
   $dashboard->add(UI::newLabel('lbl')->text('Header'), slot: 'header_slot');
   // o mediante método fluido:
   $panel = UI::container('panel')->slot('left_panel');
   $dashboard->add($panel);
   // o por posición 1-based:
   $dashboard->add(UI::newLabel()->text('Segundo bloque'), slot: 2);
   ```
2. **Embebiendo otras `Screen` completas en slots:**
   ```php
   $dashboard->embedToSlot('left_panel', LeftNavScreen::class, ['section' => 'movies']);
   $dashboard->embed(DetailScreen::class, ['id' => 5], slot: 'right_panel');

   // También soporta posición numérica (en cualquier orden de argumentos):
   $dashboard->embedToSlot(1, LeftNavScreen::class);
   ```
3. **Limpiar/Reemplazar un slot sin borrar el resto:**
   - Al llamar nuevamente `$dashboard->embed(OtherScreen::class, slot: 'left_panel')`, USIM reemplaza únicamente ese slot y emite el delta de eliminación (`parent => null`) para la sub-screen anterior.
   - `clearSlot(int|string $slot)` permite vaciar un slot específico programáticamente.

---

## 6. Sistema de Internacionalización (i18n)

USIM unifica traducciones en archivos PHP estándar de Laravel (`lang/{locale}/...`) con respaldo opcional en base de datos (`TranslationService` / `UsimTextKey` / `UsimTextValue`).

### 6.1 Convención de Claves por Namespace

`TranslationResolver::resolveClassPrefix(string $class)` deriva automáticamente el prefijo base desde el namespace de la clase:

| Tipo de clase | Ejemplo de clase PHP | Prefijo generado | Archivo `lang/` correspondiente |
|---|---|---|---|
| **Screen** (bajo `config('usim.screens_namespace', 'App\\UI\\Screens')`) | `App\UI\Screens\Demo\FormDemo` | `screens.demo.form_demo` | `lang/{locale}/screens/demo/form_demo.php` |
| **Screen** anidada | `App\UI\Screens\Demo\Events\Main` | `screens.demo.events.main` | `lang/{locale}/screens/demo/events/main.php` |
| **UIRequest** (bajo `config('usim.requests_namespace', 'App\\UI\\Requests')`) | `App\UI\Requests\Demo\FormDemoRequest` | `requests.demo.form_demo_request` | `lang/{locale}/requests/demo/form_demo.php` *(y variante con `_request`)* |

> `TranslationResolver::buildCandidates()` soporta tanto puntos como rutas anidadas con `/` (ej. `screens.demo.form_demo.name.label` busca en `screens/demo/form_demo` clave `name.label`, en `screens/demo` clave `form_demo.name.label`, y en `screens` clave `demo.form_demo.name.label`).

### 6.2 Prioridad y Motores de Traducción (`UsimTranslator`)

Al traducir una clave (vía `t()`, `$this->t()`, Nivel 1 YAML o Nivel 2 `i18n: auto`), la precedencia estricta es:

1. **Archivos PHP de Laravel (`lang/{locale}/...`)** — máxima prioridad.
2. **Base de datos (`UsimTextValue` / `TranslationService`)** — en el idioma activo, o en `config('usim.i18n.fallback_locale', 'en')` si falta en el activo.
3. **La clave original sin cambios** como último recurso.

> **Importante:**
> - `UsimTranslator::get()` registra claves faltantes en BD (`registerMissingKey()`).
> - En cambio, la **auto-hidratación Nivel 2 (`i18n: auto`)** carga el diccionario completo de la Screen mediante `TranslationResolver::resolveScreenTranslationTree()` en una sola pasada y **NO registra *missing keys* falsas** ni realiza consultas SQL por cada campo faltante.

### 6.3 Claves Relativas en PHP (`t('.clave')` vs `$this->t('.clave')`)

En clases `Screen` y `UIRequest`, toda clave que empiece por `.` se expande con el prefijo de la clase:

- `$this->t('.result.success', ['name' => $name])` → usa `static::class` directamente (recomendado dentro de `Screen` porque no depende de `debug_backtrace`).
- `t('.instruction')` → usa `TranslationResolver::detectCallerClass()` para detectar la clase llamante automáticamente.

### 6.4 Nivel 1: Claves Explícitas en `.layout.yaml` (`.clave` y `t:`)

`ContainerLayoutLoader` soporta traducciones explícitas directamente en las propiedades textuales de cada hijo definido en `.layout.yaml`.

**Atributos textuales soportados (`TRANSLATABLE_TEXT_PROPERTIES`):**
`text`, `label`, `placeholder`, `help_text`, `title`, `description`, `empty_message`, `no_results_message`, `min_chars_message`, `search_placeholder`, `create_placeholder`, `create_button_label`, `bulk_delete_label`, `save_button_label`, `cancel_button_label`, `tooltip`.

**Sintaxis disponibles:**
1. **Clave relativa a la Screen (`.clave`):**
   Debe cumplir `/^\.[a-zA-Z_][a-zA-Z0-9_.-]*$/`.
   ```yaml
   - input_name: { help_text: .validation.name_required }
   - lbl_result: { text: .result.initial }
   ```
   > **Protección de literales:** Cadenas como `"..."`, `".5"`, `".png"` o textos comunes no coinciden con la expresión regular y permanecen **literales sin traducir**.
2. **Prefijo explícito `t:` (global o relativo):**
   ```yaml
   - btn_cancel: { label: "t:common.actions.cancel" }
   - lbl_custom: { text: "t:.sub.custom_key" }
   ```

> **Nota sobre caché multi-idioma:** `ContainerLayoutLoader` cachea únicamente el AST YAML crudo de disco (sin traducir). La traducción se evalúa dinámicamente al aplicar el layout en el locale activo (`app()->setLocale(...)`).

### 6.5 Nivel 2: Auto-hidratación por Convención (`i18n: auto`)

Permite omitir prácticamente todas las claves textuales tanto en PHP como en YAML.

**Activación (opt-in):**
- En `.layout.yaml`: `i18n: auto` (o `i18n: true`) en la raíz o dentro de `grid:`.
- O en la clase `Screen`: `protected static bool $autoTranslate = true;`.

**Orden de búsqueda por cada atributo traducible de un componente:**
Dado un slot/nombre (ej. `$input_name` o `$btn_submit`), USIM genera `$nameCandidates`:
1. Nombre exacto (`'input_name'`, `'btn_submit'`).
2. Nombre sin prefijo estándar si aplica (`'name'`, `'submit'`).

Luego busca el primer `string` no vacío en el diccionario de la Screen en este orden estricto:
1. `<nombre>.<atributo>` → ej. `input_name.label`, luego `name.label`.
2. `<grupo>.<nombre>.<atributo>` → ej. `inputs.name.label`, `actions.submit.label`.
3. **Solo si `<atributo>` es el atributo primario del componente** (ver tabla abajo):
   - `<nombre>` (escalar string directo) → ej. `instruction`, `item_list_demo_title`.
   - `<grupo>.<nombre>` (escalar string directo) → ej. `actions.submit`, `buttons.submit`, `labels.intro`.

> Si una clave apunta a un `array` en lugar de un `string` (por ejemplo `'result' => ['initial' => '...']`), nunca se usa como texto escalar accidentalmente en el paso 3. En esos casos usa Nivel 1 (`text: .result.initial`).

### 6.6 Tabla de Schemas y Prefijos por Componente

**Prefijos reconocidos y eliminados automáticamente (`COMPONENT_PREFIXES`):**
`input_`, `btn_`, `lbl_`, `sel_`, `chk_`, `txt_`, `tbl_`, `lst_`, `img_`, `cal_`, `car_`, `upl_`, `crd_`, `cnt_`.

**Schema de atributos auto-hidratables por clase:**

| Componente | Atributo primario | Grupos buscados | Todos los atributos hidratables automáticamente |
|---|---|---|---|
| `NewLabel`, `Label` | `text` | `['labels']` | `text`, `tooltip` |
| `Button` | `label` | `['actions', 'buttons']` | `label`, `tooltip`, `title` |
| `Input`, `Textarea` | `label` | `['inputs', 'fields']` | `label`, `placeholder`, `help_text`, `tooltip` |
| `Select` | `label` | `['selects', 'inputs', 'fields']` | `label`, `placeholder`, `help_text`, `empty_message`, `tooltip` |
| `Checkbox` | `label` | `['checkboxes', 'inputs', 'fields']` | `label`, `description`, `help_text`, `tooltip` |
| `ItemList` | `title` | `['lists']` | `title`, `label`, `help_text`, `empty_message`, `no_results_message`, `search_placeholder`, `create_placeholder`, `create_button_label`, `bulk_delete_label` |
| `Table` | `title` | `['tables']` | `title`, `empty_message` |
| `Card` | `title` | `['cards']` | `title`, `description` |
| Otros `UIComponent` | `label` | `[]` | `label`, `title`, `text`, `placeholder`, `help_text` |

### 6.7 Reglas de Precedencia y Seguridad (`isConfigDefault`)

La prioridad final ante cualquier conflicto es siempre:

```text
PHP runtime (startingUI / updateUI)  >  YAML explícito (Nivel 1 / literal)  >  Auto-i18n (Nivel 2)  >  Default del componente
```

- Esto se implementa mediante `UIComponent::isConfigDefault(string $key)`:
  - Si un atributo fue tocado en PHP (`->label(...)`) o en YAML (`label: ...`), su clave queda en `$dirtyKeys` (o difiere de `getDefaultConfig()`), por lo que Nivel 2 **nunca lo sobrescribe**.
  - Si un componente tiene un **default no vacío** en su fábrica (como `Select::$placeholder = 'Select an option'` o `ItemList::$search_placeholder = 'Buscar...'`), mientras no haya sido tocado explícitamente, `isConfigDefault()` devuelve `true` y Nivel 2 **sí lo traduce automáticamente** si existe la clave en el diccionario.

---

## 7. Ejemplos Completos de Producción

### 7.1 Pantalla Declarativa Pura (`FormDemo`, sin `startingUI`)

**`app/UI/Screens/Demo/form-demo.layout.yaml`:**
```yaml
name: form_wrapper
appearance: card
max_width: 500
center_horizontal: true
i18n: auto

grid:
  cols: [1fr, 1fr]
  gap: 14
  padding: l
  default_span: 2

  children:
    - lbl_instruction: { justify: center, style: info, center: true }
    - input_name:      { type: text,  required: true, width: full, help_text: .validation.name_required }
    - input_email:     { type: email, required: true, width: full, help_text: .validation.email_required }
    - input_message:   { row: 1fr,    required: true, width: full, help_text: .validation.message_required }
    - btn_submit:      { span: 1, justify: start, style: primary,   action: submit_form, width: full }
    - btn_reset:       { span: 1, justify: end,   style: secondary, action: reset_form,  width: full }
    - lbl_result:      { text: .result.initial, justify: center, style: secondary, width: full }
```

**`app/UI/Screens/Demo/FormDemo.php`:**
```php
class FormDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected NewLabel $lbl_instruction;
    protected Input $input_name;
    protected Input $input_email;
    protected Textarea $input_message;
    protected Button $btn_submit;
    protected Button $btn_reset;
    protected NewLabel $lbl_result;

    protected function updateUI(): void
    {
        $this->input_name->value('')->error(null);
        $this->input_email->value('')->error(null);
        $this->input_message->value('')->error(null);
        $this->lbl_result
            ->text($this->t('.result.initial'))
            ->style(Style::SECONDARY);
    }

    public function onSubmitForm(FormDemoRequest $request): void { ... }
    public function onResetForm(): void { $this->updateUI(); }
    public function onValidationFailed(UIRequest $request, Validator $validator): void { ... }
}
```

### 7.2 Pantalla Declarativa + Datos Dinámicos (`ItemListDemo` con `layoutContainer()`)

**`app/UI/Screens/Demo/item-list-demo.layout.yaml`:**
```yaml
name: item_list_demo_wrapper
appearance: card
max_width: 900
center_horizontal: true
i18n: auto

grid:
  cols: 12
  gap: m
  padding: l
  default_span: 12

  children:
    - item_list_demo_title:   { style: default }
    - item_list_demo_intro:   { style: info }
    - sel_selection_mode:     { span: 5, on_change: selection_mode_changed }
    - chk_confirm_delete:     { span: 4, align: center, on_change: toggle_confirm_delete }
    - btn_reset_catalog:      { span: 3, justify: end, align: center, style: secondary, icon: refresh, action: reset_catalog }
    - movies_list:
        action: movies
        auto_fetch: true
        keep_selected_visible: true
        searchable: true
        debounce: 250
        max_height: 320
        scrollbar: always
        variant: card
        creatable: true
        create_inline: true
        editable: true
        edit_inline: true
        deletable: true
        allow_bulk_delete: true
        on_select: movie_selected
        on_create: movie_created
        on_update: movie_updated
        on_delete: movie_deleted
        on_bulk_delete: movies_bulk_deleted
    - lbl_selection_summary:  { span: 9, align: center, style: default }
    - btn_confirm_marathon:   { span: 3, justify: end, align: center, style: primary, action: confirm_marathon }
```

**`app/UI/Screens/Demo/ItemListDemo.php` (extracto `startingUI`):**
```php
    protected function startingUI(): Container
    {
        $this->ensureCatalogLoaded();

        $this->sel_selection_mode
            ->options([
                ['value' => SelectionMode::MULTIPLE->value, 'label' => $this->t('.selection_mode.options.multiple')],
                ['value' => SelectionMode::SINGLE->value,   'label' => $this->t('.selection_mode.options.single')],
                ['value' => SelectionMode::NONE->value,     'label' => $this->t('.selection_mode.options.none')],
            ])
            ->value($this->store_selection_mode);

        $this->chk_confirm_delete->checked($this->store_confirm_delete);

        $initialSelected = $this->store_movies !== [] ? [$this->store_movies[0]['id']] : [];

        $this->movies_list
            ->items($this->store_movies)
            ->selectionMode(SelectionMode::from($this->store_selection_mode))
            ->select($initialSelected);

        $this->lbl_selection_summary->text($this->formatSelectionSummary($initialSelected));

        return $this->layoutContainer();
    }
```

### 7.3 Dashboard Multi-Panel con `embedToSlot()`

**`dashboard.layout.yaml`:**
```yaml
name: dashboard_root
i18n: auto

grid:
  cols: [260, 1fr]
  gap: m
  padding: l
  rows: [auto, 1fr]

  children:
    - header_slot: { span: full, justify: center }
    - left_panel:  { span: 1, align: start }
    - right_panel: { span: 1, align: stretch }
```

**`Dashboard.php`:**
```php
protected function startingUI(): Container
{
    $root = $this->layoutContainer();
    $root->add(UI::newLabel('header_slot'), slot: 'header_slot');
    $root->embedToSlot('left_panel', SidebarMenuScreen::class);
    $root->embedToSlot('right_panel', MainAnalyticsScreen::class);

    return $root;
}
```
