# Propuesta Técnica de Mejoras para el Paquete `idei/usim`

**Destinatario:** Equipo / Agente de Desarrollo del Framework `idei/usim`  
**Áreas afectadas:**
- `Idei\Usim\Components\Carousel` (PHP, JS y CSS)
- `Idei\Usim\Components\Label` (CSS)
- `Idei\Usim\Layout\AbstractLayout` y `Idei\Usim\Concerns\HandlesNavigation` (Navegación SPA con parámetros en URL)
- `Idei\Usim\Http\Controllers\UIController` y `resources/assets/js/ui-renderer.js` (Deep-linking y preservación de Query Params en recargas `F5`)
- `Idei\Usim\Support\ScreenLifecycleOrchestrator` y `Idei\Usim\Concerns\HandlesScreenProperties` (Gestión de estado en servidor y eliminación de `store_*`)

---

## 1. Soporte Nativo de Relación de Aspecto (`aspect`) y Ajuste (`objectFit`) en `UI::carousel()`

### Contexto y Justificación
En escenarios como previsualización de Reels, Shorts, TikToks o cartelería digital vertical (`9:16`), se requiere que `UI::carousel()` renderice videos e imágenes en formato vertical (`315x560` px).

Actualmente, la hoja de estilos del componente `Carousel` (`resources/assets/css/components/carousel/index.css`) fuerza en `.usim-carousel-frame` una relación de aspecto horizontal fija (`aspect-ratio: 16 / 9`) y en `.usim-carousel-media.video` un recorte fijo (`object-fit: cover`):

```css
/* Estado actual en resources/assets/css/components/carousel/index.css */
.usim-carousel-frame {
    position: relative;
    width: 100%;
    min-height: 280px;
    aspect-ratio: 16 / 9;
    ...
}

.usim-carousel-media.image,
.usim-carousel-media.video {
    object-fit: cover;
}
```

Cuando desde PHP se configura un reproductor vertical mediante `->width(Size::px(315))->height(Size::px(560))`, las dimensiones se aplican al contenedor externo `.usim-carousel`, pero el marco interno `.usim-carousel-frame` conserva `aspect-ratio: 16 / 9` y no hereda `height: 100%`, provocando que el video vertical se vea apaisado o recortado.

### Cambios Requeridos en `idei/usim`

#### 1.1. En PHP (`src/Components/Carousel.php`)
Agregar métodos fluidos para configurar la relación de aspecto y el modo de ajuste del medio, reutilizando el enum `Idei\Usim\Enums\AspectRatio`:

```php
use Idei\Usim\Enums\AspectRatio;

public function aspect(AspectRatio $aspect): self
{
    return $this->setConfig('aspect_ratio', $aspect->value);
}

public function objectFit(string $fit = 'cover'): self
{
    return $this->setConfig('object_fit', $fit); // 'cover' | 'contain' | 'scale-down'
}
```

#### 1.2. En JavaScript (`resources/assets/js/components/carousel/index.js`)
Aplicar como variables CSS o estilos en línea la relación de aspecto y el `object_fit` configurados desde PHP:

```js
if (this.config.aspect_ratio) {
    frame.style.aspectRatio = String(this.config.aspect_ratio).replace(':', ' / ');
}
if (this.config.object_fit) {
    wrapper.style.setProperty('--usim-carousel-object-fit', this.config.object_fit);
}
```

#### 1.3. En CSS (`resources/assets/css/components/carousel/index.css`)
Permitir que `.usim-carousel-frame` respete la altura explícita del contenedor y la variable `--usim-carousel-object-fit`:

```css
.usim-carousel-media.image,
.usim-carousel-media.video {
    object-fit: var(--usim-carousel-object-fit, cover);
}

.usim-carousel[style*="height"] .usim-carousel-frame {
    height: 100%;
    aspect-ratio: 9 / 16;
}

.usim-carousel[style*="height"] .usim-carousel-media.video {
    object-fit: var(--usim-carousel-object-fit, contain);
}
```

---

## 2. Estilos Visuales para Variantes `secondary` y `muted` en `UI::label()`

### Contexto y Justificación
En `resources/assets/css/ui-components.css`, el componente `.ui-label` cuenta con estilos visuales de pastilla/badge (fondo traslúcido, color de texto semántico y borde) únicamente para `.success`, `.warning`, `.error` e `.info`. Sin embargo, `Idei\Usim\Components\Label` expone los métodos `->secondary()` y `->muted()` (`->style('secondary')`, `->style('muted')`), los cuales carecen de regla CSS equivalente en `ui-components.css` y se renderizan sin fondo ni borde cuando se combinan con `->variant('badge')`.

### Cambios Requeridos en `idei/usim`
En `resources/assets/css/ui-components.css`, agregar las reglas para `.ui-label.secondary` y `.ui-label.muted`:

```css
.ui-label.secondary,
.ui-label.muted {
    background: var(--usim-label-secondary-bg, rgba(148, 163, 184, 0.18));
    color: var(--usim-label-secondary-text, var(--ui-text-muted));
    border: 1px solid var(--usim-label-secondary-border, rgba(148, 163, 184, 0.38));
}
```

---

## 3. Soporte Nativo de Parámetros en la URL (Deep-Linking, `showInto` con `$params` y Recarga `F5`)

### Contexto y Justificación Arquitectónica
Para mantener una arquitectura limpia sin depender de variables `store_*` en `localStorage` ni de variables sueltas en la sesión de Laravel (`session()`), las pantallas que operan sobre una entidad (como editar o revisar un post: `/publish-post?post_id=5` o `/post/review-post?post_id=5`) deben poder recibir su identificador por **parámetro**, tanto al navegar internamente con `$this->showInto(PublishPost::class, params: ['post_id' => 5], updateBrowserUrl: true)` como al ingresar o recargar directamente desde la barra de direcciones del navegador (`F5` o enlace compartido).

Al auditar el ciclo de vida actual de `idei/usim`, se detectaron **tres puntos exactos** donde los parámetros de navegación (`$params`) y de URL (`?post_id=5`) se pierden o desconectan:

---

### 3.1. `showInto()` no refleja `$params` como Query String en `navigate.url`

#### Problema Actual
En `src/Layout/AbstractLayout.php` (líneas 304–316) y en `src/Concerns/HandlesNavigation.php` (líneas 99–109), cuando se invoca:
```php
$this->showInto(PublishPost::class, params: ['post_id' => 5], updateBrowserUrl: true, force: true);
```
el método construye el payload `navigate` usando únicamente `$targetClass::getRoutePath()` (`/publish-post`), **ignorando por completo el array `$params`**:

```php
// Estado actual en src/Layout/AbstractLayout.php (líneas 304-316)
if ($updateBrowserUrl && in_array($effectiveSlot, ['main', 'content', 'center'], true)) {
    $routePath = $targetClass::getRoutePath();
    UIStateManager::setClientCurrentScreen($routePath, $targetClass);
    app(UIChangesCollector::class)->add([
        'navigate' => [
            'url' => $routePath, // <-- Devuelve '/publish-post' sin '?post_id=5'
            'route' => $routeSlug,
            'title' => $targetClass::getMenuLabel(),
            'slot' => $targetContainer->getName() ?? $effectiveSlot,
            'slot_container_id' => $targetContainer->getId(),
        ],
        'clear_container' => $targetContainer->getId(),
    ]);
}
```
Como consecuencia, la URL del navegador queda en `/publish-post` (sin `?post_id=5`). Si el usuario presiona `F5` o copia la URL para compartirla, el parámetro `post_id` ya no existe en la URL.

#### Solución Requerida en `AbstractLayout::showInto()` y `HandlesNavigation::showInto()`
Filtrar los valores escalares de `$params` y anexarlos como query string a `$navigateUrl` cuando `$updateBrowserUrl === true`:

```php
if ($updateBrowserUrl && in_array($effectiveSlot, ['main', 'content', 'center'], true)) {
    $routePath = $targetClass::getRoutePath();
    $queryParams = array_filter(
        $params,
        static fn ($value, $key) => is_string($key) && (is_scalar($value) || $value instanceof \Stringable),
        ARRAY_FILTER_USE_BOTH
    );
    $navigateUrl = ! empty($queryParams)
        ? $routePath . '?' . http_build_query($queryParams)
        : $routePath;

    UIStateManager::setClientCurrentScreen($routePath, $targetClass);
    app(UIChangesCollector::class)->add([
        'navigate' => [
            'url' => $navigateUrl,
            'route' => $routeSlug,
            'title' => $targetClass::getMenuLabel(),
            'slot' => $targetContainer->getName() ?? $effectiveSlot,
            'slot_container_id' => $targetContainer->getId(),
        ],
        'clear_container' => $targetContainer->getId(),
    ]);
}
```

Además, en `resources/assets/js/ui-renderer.js` (líneas 1963–1975), al ejecutar `window.history.pushState(...)` dentro de `if (uiUpdate.navigate)`, sincronizar `window.QUERY_PARAMS` con los query params de `navUrl`:

```js
if (navUrl && window.history && typeof window.history.pushState === 'function') {
    window.history.pushState({ path: navUrl, route: navRoute, slot: slotTarget }, navTitle, navUrl);
    window.QUERY_PARAMS = new URLSearchParams(window.location.search);
    if (navTitle) {
        document.title = navTitle;
    }
}
```

---

### 3.2. `ui-renderer.js` borra todos los Query Params de la barra de direcciones al limpiar `reset=true`

#### Problema Actual
En `resources/assets/js/ui-renderer.js` (líneas 3691–3695), al finalizar la carga inicial de la pantalla (`DOMContentLoaded`), el script limpia el flag `reset` ejecutando:

```js
// Estado actual en resources/assets/js/ui-renderer.js (líneas 3691-3695)
// Clear reset flag after both screen and menu consumed it.
if (initialResetState) {
    window.history.replaceState({}, document.title, window.location.pathname);
    window.RESET_STATE = false;
}
```
Debido a que pasa `window.location.pathname` (sin `search`) a `replaceState`, cuando un usuario ingresa directamente a `/publish-post?post_id=5` (o presiona `F5` sobre `/publish-post?post_id=5`), el navegador **borra inmediatamente `?post_id=5` de la barra de direcciones**, dejando la URL en `/publish-post`. Si el usuario vuelve a presionar `F5`, el parámetro se habrá perdido.

#### Solución Requerida en `resources/assets/js/ui-renderer.js`
Eliminar únicamente el parámetro `reset` de la URL y conservar intactos todos los demás query parameters del usuario (`?post_id=5`, etc.):

```js
// Clear reset flag after both screen and menu consumed it, preserving other query params.
if (initialResetState) {
    const cleanParams = new URLSearchParams(window.location.search);
    cleanParams.delete('reset');
    const cleanQuery = cleanParams.toString();
    const cleanUrl = cleanQuery
        ? `${window.location.pathname}?${cleanQuery}`
        : window.location.pathname;
    window.history.replaceState({}, document.title, cleanUrl);
    window.QUERY_PARAMS = cleanParams;
    window.RESET_STATE = false;
}
```

---

### 3.3. Pasar los Query Params de la URL a `buildBaseUI(Container $container, ...$params)` en `UIController::show()`

#### Problema Actual
En `src/Http/Controllers/UIController.php` (líneas 48–53), cuando se carga una pantalla por URL (`GET /api/ui/publish-post?post_id=5`), se invoca `$screen->render()` pasando `queryParams`, pero sin `buildParams`:

```php
// Estado actual en src/Http/Controllers/UIController.php (líneas 48-53)
$screen->render(
    incomingStorage: $requestData['storage'],
    queryParams: $requestData['queryParams'],
    parent: $parent,
    shouldReset: $requestData['shouldReset']
);
```
Esto hace que cuando una pantalla se abre mediante `$this->showInto(PublishPost::class, params: ['post_id' => 5])`, el valor llegue en `$params['post_id']` (argumento de `buildBaseUI(Container $container, ...$params)`), pero cuando se abre o recarga por URL (`/publish-post?post_id=5`), `$params` llegue vacío (`[]`) y el desarrollador deba buscarlo por separado en `$this->getQueryParams()['post_id']`.

#### Solución Requerida en `src/Http/Controllers/UIController.php`
Inyectar como `buildParams` los parámetros de consulta de la URL (excluyendo los parámetros internos del framework como `reset` y `parent`), de modo que `buildBaseUI(Container $container, ...$params)` reciba siempre `$params['post_id']` de manera 100% uniforme sin importar si la navegación fue por `showInto()` o por acceso directo / `F5` en la URL:

```php
$buildParams = array_diff_key($requestData['queryParams'], array_flip(['reset', 'parent']));

$screen->render(
    incomingStorage: $requestData['storage'],
    queryParams: $requestData['queryParams'],
    parent: $parent,
    shouldReset: $requestData['shouldReset'],
    buildParams: $buildParams
);
```

---

## 4. Eliminación del Mecanismo `store_*` (`X-USIM-Storage`) y Saneamiento del Estado

### Justificación Arquitectónica
Actualmente `HandlesScreenProperties` soporta dos mecanismos paralelos de estado en las pantallas:
1. `state_*`: Estado persistido en el servidor mediante `UIStateRepositoryInterface`.
2. `store_*`: Estado enviado al navegador, almacenado en `localStorage` y reenviado en cada request HTTP mediante la cabecera `X-USIM-Storage` (con reflexión y cifrado opcional `_crypt`).

Mantener `store_*` rompe el principio de **Única Fuente de Verdad (SSOT)** propio de una arquitectura Backend-Driven UI:
- Introduce desincronización (*split-brain*) entre lo que guarda el `localStorage` del navegador y el estado del servidor o entre múltiples pestañas abiertas.
- Genera sobrecarga en cada request HTTP y complejidad innecesaria en `HandlesScreenProperties` (`injectStorageValues`, `getStorageVariables`) y `UIChangesCollector` (`setStorage`, `storage_changes`).
- Con el soporte completo de **parámetros en la URL** (Punto 3) más las propiedades **`state_*` en el servidor**, las variables `store_*` resultan completamente innecesarias.

### Recomendación para la Próxima Versión de `idei/usim`
1. **Deprecar y eliminar el prefijo `store_*`** en `Idei\Usim\Concerns\HandlesScreenProperties`, `Idei\Usim\UIChangesCollector` y el envío de `X-USIM-Storage` en `ui-renderer.js`.
2. **Conservar únicamente:**
   - **Parámetros (`$params` / URL query params)** para identificar recursos y permitir *deep-linking* y recargas idempotentes.
   - **Propiedades `state_*` en el servidor** (`UIStateRepository`) para el estado interno del ciclo de vida de la pantalla entre eventos de la interfaz.
