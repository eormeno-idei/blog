# Propuesta de Mejoras para el Paquete `idei/usim`

**Destinatario:** Equipo de Desarrollo del Framework USIM  
**Componentes afectados:** `Idei\Usim\Components\Carousel`, `Idei\Usim\Components\Label`  
**Motivo:** Soportar de forma nativa formatos de video verticales (`9:16` / Reels / Shorts / Cartelería vertical) en `UI::carousel()` y homogeneizar las variantes de color de `UI::label()->variant('badge')`.

---

## 1. Soporte Nativo de Relación de Aspecto (`aspectRatio`) y Ajuste (`objectFit`) en `Carousel`

### Problema Detectado
Actualmente, la hoja de estilos del componente `Carousel` (`resources/assets/css/components/carousel/index.css`) define en `.usim-carousel-frame` una relación de aspecto horizontal fija (`aspect-ratio: 16 / 9`) y en `.usim-carousel-media.video` un recorte fijo (`object-fit: cover`):

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

Cuando desde el backend se configura un reproductor vertical para Reels (`9:16`) mediante `->width(Size::px(315))->height(Size::px(560))`, las dimensiones se aplican al contenedor externo `.usim-carousel`, pero el marco interno `.usim-carousel-frame` conserva `aspect-ratio: 16 / 9` y no hereda `height: 100%`, provocando que el video se visualice apaisado o recortado.

### Solución Propuesta para el Paquete

#### A. En PHP (`Idei\Usim\Components\Carousel`)
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

#### B. En JavaScript (`resources/assets/js/components/carousel/index.js`)
Aplicar como variables CSS o atributos de datos la relación de aspecto y el `object_fit` configurados desde PHP:

```js
if (this.config.aspect_ratio) {
    frame.style.aspectRatio = String(this.config.aspect_ratio).replace(':', ' / ');
}
if (this.config.object_fit) {
    wrapper.style.setProperty('--usim-carousel-object-fit', this.config.object_fit);
}
```

#### C. En CSS (`resources/assets/css/components/carousel/index.css`)
Permitir que `.usim-carousel-frame` respete la altura explícita del contenedor o la variable CSS `--usim-carousel-object-fit`:

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

## 2. Estilos Visuales para `secondary` y `muted` en `UI::label()`

### Problema Detectado
En `resources/assets/css/ui-components.css`, el componente `.ui-label` cuenta con estilos de pastilla/etiqueta (fondo traslúcido, color de texto semántico y borde) únicamente para `.success`, `.warning`, `.error` e `.info`. Sin embargo, `Idei\Usim\Components\Label` expone también los métodos `->secondary()` y `->muted()`, los cuales carecen de regla CSS equivalente en `ui-components.css` y se renderizan sin fondo ni borde cuando se usan como badges de estado.

### Solución Propuesta para el Paquete
Incorporar en `resources/assets/css/ui-components.css` las reglas para `.ui-label.secondary` y `.ui-label.muted`:

```css
.ui-label.secondary,
.ui-label.muted {
    background: var(--usim-label-secondary-bg, rgba(148, 163, 184, 0.18));
    color: var(--usim-label-secondary-text, var(--ui-text-muted));
    border: 1px solid var(--usim-label-secondary-border, rgba(148, 163, 184, 0.38));
}
```

---

## 3. Persistencia de `buildParams` (`$params`) en `ScreenLifecycleOrchestrator` ante Regeneración de Caché o Recarga (`F5`)

### Problema Detectado
Cuando una pantalla navega a otra pasando parámetros a `showInto()` —por ejemplo, `$this->showInto(PublishPost::class, params: ['post_id' => 5], updateBrowserUrl: true, force: true)`—, `ScreenLifecycleOrchestrator::embedInto()` invoca `buildBaseUI($container, ...$buildParams)` únicamente durante ese montaje inicial.

Sin embargo:
1. Si la caché del servidor se limpia (`php artisan cache:clear` / `config:clear` al recargar `.env`) mientras el usuario tiene la pantalla abierta y luego dispara un evento (`handleAction`), `getCachedScreenSnapshot()` regenera el árbol llamando a `$screen->callBuildBaseUI($container)` **sin `$buildParams`** y con las propiedades `$state_*` reiniciadas a sus valores por defecto.
2. Si el usuario presiona `F5` en el navegador sobre la ruta SPA (`/publish-post`), el frontend envía `?reset=true`, lo cual ejecuta `$screen->onResetScreen()` (`clearCachedScreenSnapshot()`) y vuelve a invocar `buildBaseUI()` sin los `$buildParams` originales con los que se abrió la pantalla.

### Solución Propuesta para el Paquete
Persistir automáticamente los últimos `$buildParams` asociados a la pantalla en `UIStateRepository` (o sincronizarlos como query params en `navigate.url` cuando `updateBrowserUrl: true`) para que, si `getCachedScreenSnapshot()` debe reconstruir la UI por expiración de caché, pueda reinyectar los `$buildParams` con los que fue montada la pantalla.


