# RFC: Mejoras para `CardGrid` e `ItemCard` (USIM v2.20.1)

- **Autor:** Equipo de Desarrollo de Aplicaciones USIM (Proyecto Blog)
- **Fecha:** 25 de Noviembre de 2026
- **Versión evaluada:** `idei/usim` `v2.20.1`
- **Componentes:** `Idei\Usim\Components\CardGrid`, `Idei\Usim\Components\ItemCard`, `Idei\Usim\DTOs\CardItemDTO`, `Idei\Usim\DTOs\CardActionDTO`, `UsimItemCardComponent`, `ScreenTestHarness`
- **Tipo:** Feature Request / Mejoras de DX y Consistencia
- **Estado:** Implementado en `idei/usim` `v2.21.0`

---

## 1. Resumen Ejecutivo

Al migrar una pantalla real en producción (`App\UI\Screens\Home`) desde tarjetas construidas manualmente con `UI::container()->card()` + `UI::image()` + `UI::newLabel()` + `UI::button()` hacia `UI::cardGrid()` + `ItemCard` (`v2.20.1`) y layouts declarativos `.layout.yaml`, la adopción fue limpia y redujo significativamente el código de presentación. Sin embargo, se detectaron **1 bug visual crítico** y **6 oportunidades concretas de mejora** en el framework.

---

## 2. Bug Crítico Detectado (`v2.20.1`)

### 2.0 Íconos SVG invisibles (`0px × 0px`) dentro de `CardGrid` e `ItemCard` cuando los íconos están publicados

**Síntoma:**
Cuando el pack de íconos estándar está publicado (`public/vendor/idei/usim/icons/`), todos los íconos nombrados de `CardGrid` (`search`, `filter`, `grid`, `list` en la barra de búsqueda y toggle de layout) y de `ItemCard` (`edit`, `trash`, `eye`, íconos de `attributes` y `actions`) se renderizan completamente invisibles (`0px × 0px`), mostrando botones vacíos en la UI.

**Causa raíz:**
1. `UsimCardGridComponent::_createIconNode()` (`js/components/card-grid/index.js`) y `UsimItemCardComponent::_createIconNode()` (`js/components/item-card/index.js`) invocan:
   ```js
   helpers.createIconElement(iconValue, { size, baseClass: 'usim-cg-icon' }); // o 'usim-ic-icon'
   ```
2. En `js/components/shared/content-render.js` (líneas 204–217), `createIconElement()` **ignora `options.size`** y genera `<span class="usim-cg-icon ui-icon-svg">` (sin la clase `.ui-icon`).
3. En `css/ui-components.css` (líneas 330–338), `.ui-icon-svg` solo define `background-color: currentColor` y `mask-*`, pero **no** define `display`, `width` ni `height` (esas dimensiones viven solo en `.ui-icon`).
4. Ni `css/components/card-grid/index.css` ni `css/components/item-card/index.css` definen reglas para `.usim-cg-icon` ni `.usim-ic-icon`.

**Solución propuesta:**
- Agregar en `css/ui-components.css` (o en `card-grid/index.css` e `item-card/index.css`) dimensiones base (`display: inline-block; width: 1.1em; height: 1.1em; flex-shrink: 0;`) para `.ui-icon-svg`, `.usim-cg-icon` y `.usim-ic-icon`, **y/o** respetar `options.size` en `content-render.js` (`iconElement.style.width = `${size}px``).

---

## 3. Propuestas de Mejora

### 2.1 Paridad de manejo de imágenes entre `UI::image()` y `ItemCard` / `CardItemDTO`

**Situación actual:**
- `UI::image()` resuelve automáticamente rutas relativas de almacenamiento vía `->storage('uploads/images')` (`UploadService::fileUrl()`) y genera SVG/Picsum placeholders vía `->placeholderColors()`, `->placeholderAspect()` y `->placeholderId()` (`ImagePlaceholderGeneratorInterface`).
- `ItemCard` y `CardItemDTO` solo aceptan una URL o `data:image/...` ya construida en `image`, y `imageFit` restringe los valores a `'cover' | 'contain'` (sin `'scale-down'`).

**Impacto en aplicaciones consumidoras:**
Cada servicio `CardListable` que trabaja con imágenes subidas por usuarios debe reimplementar manualmente:
1. Detección de URL absoluta vs ruta relativa + llamada a `UploadService::fileUrl()`.
2. Instanciación de `ImagePlaceholder` y llamada a `ImagePlaceholderGeneratorInterface::generate()` cuando el registro no tiene imagen.

**Propuesta:**
- Agregar soporte opcional en `CardItemDTO` / `ItemCard` (o un método fluido `imageStorage(string $dir)` / `imagePlaceholder(...)` en `ItemCard` y `CardGrid`) para delegar la resolución de rutas relativas y la generación de placeholders al mismo pipeline que utiliza `UI::image()`.
- Evaluar soportar `'scale-down'` en `imageFit` además de `'cover'` y `'contain'`.

---

### 2.2 Sincronización de `static_items` en `CardGrid::prependCard()` y `CardGrid::removeCard()`

**Situación actual:**
En `Idei\Usim\Components\CardGrid`:
- `removeCard($id)` elimina el hijo de `$this->cards` y actualiza `pagination.total`, pero **no** elimina el elemento de `$this->config['static_items']`.
- `prependCard($card)` agrega el hijo en `$this->cards`, pero **no** lo inserta en `$this->config['static_items']`.

**Impacto:**
Si una pantalla utiliza `CardGrid` en modo estático (`->items([...])`) y luego invoca `removeCard()` o `prependCard()`, el diff inmediato se ve correcto, pero al ejecutar cualquier búsqueda, ordenamiento, filtro o cambio de página (`executeStaticQuery()`), los ítems eliminados reaparecen y los insertados desaparecen.

**Propuesta:**
Actualizar `$this->config['static_items']` cuando `is_array($this->config['static_items'])` en `prependCard()`, `removeCard()` y `patchCard()`, manteniendo coherencia total entre el árbol montado y la fuente estática.

---

### 2.3 Accesibilidad (`aria-label`) y fallback en menú overflow (`⋮`) para acciones *icon-only* en `ItemCard`

**Situación actual:**
En `resources/assets/js/components/item-card/index.js`:
1. En `_buildInlineActionButton(act)` (líneas 462–492), una acción con `label: ''`, `icon: 'trash'` y `tooltip: 'Eliminar post'` asigna `el.title = act.tooltip`, pero **no** establece `aria-label`.
2. En `_buildActions(actions)` (líneas 443–450), si esa misma acción sin `label` cae en el menú de desborde (`⋮`) por superar `max_visible_actions` o marcarse `overflow: true`, el ítem del menú renderiza `lbl.textContent = act.label` (texto vacío), dejando una entrada de menú solo con ícono sin texto descriptivo.

**Propuesta:**
- En `_buildInlineActionButton()`: cuando `!act.label && act.tooltip`, asignar `el.setAttribute('aria-label', act.tooltip)`.
- En `_buildActions()` (menú overflow): usar `lbl.textContent = act.label || act.tooltip || ''` para que las acciones *icon-only* muestren siempre un texto legible dentro del menú `⋮`.

---

### 2.4 Soporte para múltiples acciones en el encabezado de `CardGrid` (`headerActions()`)

**Situación actual:**
`CardGrid::headerAction()` permite configurar únicamente **un** botón CTA (`header_action`) y un título con estilo fijo.

**Impacto:**
Pantallas con encabezado contextual que muestran múltiples acciones condicionales (ej. `Iniciar sesión` + `Registrarse` para invitados, o `Publicar Post` + `Gestionar Usuarios` para administradores) y/o títulos con estado semántico (`Style::WARNING`) se ven obligadas a mantener un `UI::container()->card()` separado arriba del `CardGrid` y ocultar el header nativo de `CardGrid`.

**Propuesta:**
- Soportar `headerActions(array $actions)` (aceptando `CardActionDTO[]` o arrays compatibles) manteniendo `headerAction()` como azúcar sintáctico de retrocompatibilidad para 1 acción.
- Opcionalmente permitir `titleStyle(Style|string $style)` en `CardGrid`.

---

### 2.5 Helpers de testing para `CardGrid` e `ItemCard` en `ScreenTestHarness`

**Situación actual:**
Al migrar pantallas de contenedores manuales (`UI::button` / `UI::newLabel`) a `CardGrid` + `ItemCard`:
- Los badges y botones de acción dejan de ser componentes independientes del árbol (`findComponent("btn_review_post_{$id}")`, `findComponent("post_status_{$id}")`) y pasan a ser propiedades (`badge`, `actions`) del componente hijo `<grid>__card_{id}` dentro del `CardGrid`.
- Además, `Container::findByName()` no recorre internamente `CardGrid::getCards()` a menos que se consulte el propio `CardGrid::card($itemId)` o el JSON serializado.

**Propuesta:**
Incorporar utilidades en `ScreenTestHarness` y/o soporte en `Container::findByName()` para hijos de `CardGrid`, por ejemplo:
- `$harness->findCard('posts_grid', $itemId): ?ItemCard`
- `$harness->assertCardBadge('posts_grid', $itemId, '🟢 Publicado')`
- `$harness->assertCardHasAction('posts_grid', $itemId, 'review_post')`
- `$harness->clickCardAction('posts_grid', $itemId, 'delete_post')`

---

### 2.6 Claves traducibles Nivel 1 faltantes para `CardGrid` en `ContainerLayoutLoader`

**Situación actual:**
En `Idei\Usim\Support\ContainerLayoutLoader`, el schema de **Nivel 2 (`i18n: auto`)** para `CardGrid` (líneas 1779–1792) incluye `load_more_label` y `clear_filters_label`, pero la constante `TRANSLATABLE_TEXT_PROPERTIES` (Nivel 1, líneas 49–66) no las incluye.

**Impacto:**
Declarar explícitamente `load_more_label: .posts_grid.load_more_label` o `clear_filters_label: .posts_grid.clear_filters_label` en `.layout.yaml` no se traduce y muestra la clave literal.

**Propuesta:**
Agregar `'load_more_label'` y `'clear_filters_label'` a `ContainerLayoutLoader::TRANSLATABLE_TEXT_PROPERTIES` para paridad completa entre Nivel 1 y Nivel 2.

---

## 4. Referencia de Implementación

- Layout declarativo: `app/UI/Screens/home.layout.yaml`
- Traducciones `i18n: auto`: `lang/es/screens/home.php`
- Pantalla refactorizada: `app/UI/Screens/Home.php`
- Servicio `CardListable`: `app/Services/Post/PostCardGridService.php`
- Tests adaptados: `tests/Feature/HomeMenuScreenTest.php`, `tests/Feature/PostReviewWorkflowTest.php`
