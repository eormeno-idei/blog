# Guía del Sistema Agnóstico de Íconos (`usim-icons`)

USIM implementa un sistema de íconos desacoplado y **agnóstico del cliente**, donde el backend únicamente declara la intención semántica del ícono (`'check'`, `'settings'`, un `BackedEnum`, un emoji `'🚀'` o una ruta/URL explícita) y su posición relativa (`left`, `right`, `top`, `bottom`), sin especificar colores en píxeles, tamaños fijos ni flags redundantes.

El cliente web adapta automáticamente tamaño, color, contraste y modo *icon-only* según el **tema activo** (`light` / `dark`), el `style`/`variant` del componente (`Button`, `NewLabel`, `Label`, celdas de `Table`) y el **`ContainerAppearance`** del contenedor padre (`plain`, `subtle`, `card`, `elevated`, `highlight`, `contrast`).

---

## 1. Publicación de la Biblioteca Estándar (`--tag=usim-icons`)

El paquete `idei/usim` incluye una biblioteca estándar de **80 íconos SVG vectoriales** pensados para ser coloreables mediante CSS. Para publicarlos en tu aplicación Laravel ejecutá:

```bash
php artisan vendor:publish --tag=usim-icons --force
```

Esto copia los 80 archivos `.svg` a:

```text
public/vendor/idei/usim/icons/
```

> **Resiliencia sin publicar:** Si aún no ejecutaste `vendor:publish --tag=usim-icons` y utilizás un nombre estándar (por ejemplo `'settings'` o `'check'`), el renderer web de USIM aplica automáticamente un fallback Unicode/emoji equivalente para no romper la interfaz.

---

## 2. Uso en PHP (API Agnóstica)

Los componentes que soportan íconos comparten el trait `Idei\Usim\Concerns\HasIcon` (`Button`, `NewLabel`, `Label`) y los botones declarados en celdas de `Table`.

```php
use Idei\Usim\Enums\Align;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\UI;

// 1. Por nombre semántico estándar (adaptativo al tema, estilo y container appearance)
UI::button('btn_save')
    ->label('Guardar')
    ->icon('save', Align::LEFT)
    ->style('primary');

// 2. Icon-only automático (Button sin label -> se renderiza centrado y compacto)
UI::button('btn_settings')
    ->icon('settings')
    ->tooltip('Configuración')
    ->ariaLabel('Abrir configuración');

// 3. En NewLabel con posición derecha y variante pill/outlined
UI::newLabel('lbl_status')
    ->text('Verificado')
    ->icon('check', Align::LEFT)
    ->pill()
    ->success();

UI::newLabel('lbl_prefs')
    ->text('Preferencias')
    ->icon('settings', Align::RIGHT)
    ->outlined()
    ->info();

// 4. Con BackedEnum propio de la aplicación
enum AppIcon: string
{
    case SEARCH = 'search';
    case SHIELD = 'shield';
}

UI::button('btn_audit')
    ->label('Seguridad')
    ->icon(AppIcon::SHIELD, Align::LEFT);

// 5. Con emoji o ruta explícita (SVG, PNG, etc.)
UI::newLabel('lbl_deploy')->text('Lanzamiento')->icon('🚀');
UI::button('btn_brand')->label('Marca')->icon('/images/icons/custom-logo.png');
```

### Métodos disponibles (`HasIcon`)

| Método | Parámetros | Notas |
|---|---|---|
| `->icon($icon, $position = null)` | `string\|BackedEnum\|null $icon`, `Align\|string\|null $position` | Acepta nombre semántico (`'check'`), emoji (`'🚀'`), ruta/URL (`/images/...`, `data:image/...`) o `BackedEnum`. Si `$position` no es `null`, configura `icon_position`. |
| `->iconPosition($position)` | `Align\|string $position` | Posición relativa al texto: `'left'`, `'right'`, `'top'`, `'bottom'` en `Button`; `'left'` o `'right'` en `NewLabel` y `Label`. |

### Uso en `.layout.yaml`

Las claves `icon` e `icon_position` se delegan automáticamente desde archivos `.layout.yaml`:

```yaml
children:
  - btn_reset:
      span: 3
      style: secondary
      icon: refresh
      icon_position: left
      action: reset_catalog
```

---

## 3. Decisiones de Diseño (Sin `icon_color`, `icon_size` ni `icon_only`)

A partir de esta versión **se eliminaron** los métodos y claves de contrato `iconColor()` / `icon_color`, `iconSize()` / `icon_size` e `iconOnly()` / `icon_only`:

1. **Color automático (`currentColor`):** Todo SVG estándar o personalizado se renderiza en el cliente web usando CSS `mask-image` con `background-color: currentColor` (`.ui-icon-svg`). De este modo hereda automáticamente el color del texto del botón, etiqueta o contenedor padre, incluyendo los tokens redefinidos en `.ui-container-contrast`, `.ui-container-highlight`, estados `:disabled` y modos `light`/`dark`.
2. **Tamaño proporcional (`1.2em`):** Los íconos escalan automáticamente en proporción a la tipografía del componente padre (por ejemplo `TextSize::XS` a `TextSize::XL` en `Button` y `NewLabel`).
3. **Detección automática de *icon-only*:** En `Button`, si existe `icon` y `label` está vacío (`""` o `null`), el cliente aplica `.ui-button-icon-only` y usa `aria_label` o `tooltip` para preservar la accesibilidad sin requerir un flag booleano en el servidor.

> **Nota sobre íconos raster (PNG/WebP/JPG):** Si utilizás archivos PNG o imágenes no monocromáticas que no pueden teñirse con `currentColor`, su contraste visual en cada tema es responsabilidad del archivo suministrado (aunque puedes definir variantes `-light` / `-dark`, ver sección 5).

---

## 4. Biblioteca de Íconos Estándar (80 íconos)

Todos los archivos viven en `packages/idei/usim/resources/icons/{nombre}.svg` y se publican en `public/vendor/idei/usim/icons/{nombre}.svg`:

| Categoría | Nombres disponibles |
|---|---|
| **Inteligencia Artificial y Asistencia** | `sparkles` (alias: `ai`), `bot` |
| **Navegación, Vistas y Layout** | `home`, `menu`, `search`, `settings`, `filter`, `sliders` (alias: `sliders-horizontal`), `columns`, `grid` (alias: `layout-grid`), `list`, `grip-vertical` (alias: `drag`), `refresh`, `maximize`, `minimize`, `external-link`, `link`, `chevron-left`, `chevron-right`, `chevron-up`, `chevron-down` |
| **Acciones y Productividad** | `plus`, `minus`, `edit`, `trash`, `save`, `copy`, `download`, `upload`, `share`, `print`, `pin`, `bookmark`, `tag`, `history`, `undo`, `redo`, `send` |
| **Colaboración y Mensajería** | `message-square` (alias: `chat`), `mail` |
| **Seguridad, Identidad y Passkeys** | `shield`, `shield-check`, `user`, `users`, `lock`, `unlock`, `key`, `login`, `logout`, `eye`, `eye-off` |
| **Dispositivos, Cloud y Datos** | `monitor`, `smartphone`, `cloud`, `cloud-off`, `database`, `file`, `folder` |
| **Negocio, Feedback y Multimedia** | `credit-card`, `crown`, `mic`, `check`, `close`, `info`, `warning`, `error`, `help`, `bell`, `star`, `heart`, `bolt`, `calendar`, `clock`, `sun`, `moon` |

---

## 5. Íconos Personalizados del Usuario y Sobrescritura

El servicio `Idei\Usim\Support\UsimIconRegistry` escanea los directorios configurados en `config/usim.php` e inyecta el mapa resuelto en el shell HTML (`window.USIM_ICONS`):

```php
// config/usim.php
'icons' => [
    'paths' => [
        'images/icons',          // Prioridad 1: íconos propios de tu app
        'vendor/idei/usim/icons', // Prioridad 2: biblioteca estándar publicada
    ],
    'aliases' => [
        // Ejemplo: 'app-logo' => '/images/brand/logo.svg',
    ],
],
```

### Reglas de descubrimiento y prioridad

1. **Sobrescritura de íconos estándar:** Colocar `public/images/icons/settings.svg` (o `.png`) reemplaza automáticamente al ícono `'settings'` estándar publicado en `vendor/idei/usim/icons/`.
2. **Subdirectorios:** Un archivo en `public/images/icons/brand/logo.png` queda disponible como `'brand/logo'`.
3. **Prioridad de extensiones:** Si existen archivos con el mismo nombre base, se prefiere en orden: `svg` → `png` → `webp` → `jpg` → `jpeg` → `gif` → `ico`. También puedes solicitar la extensión explícita (ej. `'badge.png'`).
4. **Aliases explícitos (`usim.icons.aliases`):** Tienen la máxima prioridad y permiten mapear un nombre semántico hacia cualquier ruta pública arbitraria.
5. **Variantes por tema para PNG o íconos no coloreables:** Si colocás `<nombre>-light.png` y `<nombre>-dark.png` (por ejemplo `brand-light.png` y `brand-dark.png`) y referenciás `'brand'`, el cliente web selecciona la variante según `data-theme` y la actualiza en vivo cuando el usuario cambia de tema (`usim:theme-changed`).
6. **Rutas directas:** Cualquier valor que comience con `/`, `./`, `../`, `http://`, `https://` o `data:image/` se usa directamente sin consultar `USIM_ICONS`.
