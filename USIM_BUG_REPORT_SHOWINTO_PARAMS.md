# Reporte de Bug: Pérdida de `$params` en navegación entre Screens mediante `showInto()` y `embedInto()`

**Paquete afectado:** `idei/usim`  
**Severidad:** Alta (Afecta el paso de datos entre pantallas SPA del framework)  
**Fecha de reporte:** 2026-10-23  

---

## 1. Resumen Ejecutivo

Al invocar `$this->showInto(TargetScreen::class, params: ['key' => $val])`, los parámetros enviados **se descartan silenciosamente** en el flujo interno de USIM. En consecuencia, el método `buildBaseUI(Container $container, ...$params)` de la pantalla destino recibe siempre un array vacío (`[]`).

Aunque la documentación de PHPDoc de `Idei\Usim\Concerns\HandlesNavigation` documenta explícitamente:
```php
@param array<int|string, mixed> $params Parameters passed to target's buildBaseUI
```
la implementación de `Idei\Usim\Layout\AbstractLayout::showInto` no propaga `$params` hacia `Idei\Usim\Screen::embedInto`, y a su vez `embedInto()` no acepta ni transfiere `$buildParams` hacia `initializeEventContext()`.

---

## 2. Flujo de Reproducción

1. Desde una pantalla origen (ej. `Home.php`), se dispara una acción que navega a otra pantalla con parámetros:
   ```php
   $this->showInto(EditPost::class, updateBrowserUrl: true, force: true, params: ['post_id' => $postId]);
   ```
2. La pantalla destino implementa `buildBaseUI`:
   ```php
   protected function buildBaseUI(Container $container, ...$params): void
   {
       Log::info('Params:', $params); // Resultado: []
       $postId = $params['post_id'] ?? 0;
       if ($postId <= 0) {
           return; // Falla silenciosa: la pantalla se renderiza en blanco
       }
       ...
   }
   ```
3. Resultado: `...$params` es `[]`. `$postId` evalúa a `0` y la UI nunca se construye.

---

## 3. Análisis de Causa Raíz (Traza de Código)

```text
Home::showInto(..., params: [...])
  └── HandlesNavigation::showInto()
        └── AbstractLayout::showInto($screenClass, $slot, $params)
              └── [OMITE $params] ──> Screen::embedInto($targetClass, $targetContainer)
                                        └── ScreenLifecycleOrchestrator::embedInto()
                                              └── initializeEventContext(..., buildParams: [])
                                                    └── reconstructScreenTree($screen, ...[])
                                                          └── TargetScreen::buildBaseUI($container, ...[]) => []
```

### Detalle de los componentes involucrados:

### 1. `AbstractLayout::showInto()` descarta `$params`
En `vendor/idei/usim/src/Layout/AbstractLayout.php`:
```php
public function showInto(string $screenClass, ?string $slot = null, array $params = [], bool $updateBrowserUrl = true, bool $force = false): bool
{
    ...
    // Líneas 298-301:
    // Clear existing content in the slot container
    $targetContainer->clear();
    Screen::embedInto($targetClass, $targetContainer); // ❌ $params no se pasa aquí
    $this->setActiveScreen($effectiveSlot, $targetClass);
    ...
}
```
`$params` se recibe en la firma pero nunca se envía a `Screen::embedInto()`.

### 2. Rama Fallback en `HandlesNavigation::showInto()` también descarta `$params`
En `vendor/idei/usim/src/Concerns/HandlesNavigation.php`:
```php
if ($targetContainer instanceof Container) {
    $targetContainer->clear();
    Screen::embedInto($targetClass, $targetContainer); // ❌ $params no se pasa aquí
}
```

### 3. `Screen::embedInto()` carece del argumento `$params`
En `vendor/idei/usim/src/Screen.php` y `vendor/idei/usim/src/Contracts/ScreenLifecycleOrchestratorInterface.php`:
```php
public static function embedInto(string $class, Container $parent): void
{
    static::getLifecycleOrchestrator()->embedInto($class, $parent);
}
```

### 4. `ScreenLifecycleOrchestrator::embedInto()` no pasa `buildParams`
En `vendor/idei/usim/src/Support/ScreenLifecycleOrchestrator.php`:
```php
public function embedInto(string $screenClass, Container $parent): void
{
    ...
    $this->initializeEventContext(
        screen: $instance,
        incomingStorage: Screen::getCurrentIncomingStorage(),
        queryParams: Screen::getCurrentQueryParams(),
        parent: $parentId
        // ❌ Omitido: buildParams: $buildParams
    );
```
Al omitir `buildParams`, el método `initializeEventContext` usa su valor predeterminado `array $buildParams = []`, lo que provoca que `reconstructScreenTree` y consecuentemente `callBuildBaseUI()` reciban una lista vacía de parámetros.

---

## 4. ¿Por qué sí funciona en Modales y Tests?

1. **Modales (`openAsModal`)**:
   En `vendor/idei/usim/src/Concerns/HandlesModals.php`, se ejecuta:
   ```php
   $instance->render(
       incomingStorage: Screen::$currentIncomingStorage,
       queryParams: $queryParams,
       parent: 'modal',
       shouldReset: false,
       buildParams: $params, // ✅ Se pasa explícitamente
   );
   ```
2. **Tests (`ScreenTestHarness::render`)**:
   En `vendor/idei/usim/src/Testing/ScreenTestHarness.php`, se invoca `$this->screen->render(..., buildParams: $buildParams)`, por lo que los tests unitarios pasan pero el código falla en tiempo de ejecución al usar `showInto()`.

---

## 5. Propuesta de Solución en el Código de USIM

A continuación se presenta el parche recomendado para el repositorio de `idei/usim`:

### 1. Actualizar `src/Contracts/ScreenLifecycleOrchestratorInterface.php`
```diff
--- a/src/Contracts/ScreenLifecycleOrchestratorInterface.php
+++ b/src/Contracts/ScreenLifecycleOrchestratorInterface.php
@@ -119,7 +119,8 @@ interface ScreenLifecycleOrchestratorInterface
     /**
      * Build and attach a nested screen inside a parent container.
      *
      * @param  class-string<Screen>  $screenClass
+     * @param  array<int|string, mixed>  $buildParams
      */
-    public function embedInto(string $screenClass, Container $parent): void;
+    public function embedInto(string $screenClass, Container $parent, array $buildParams = []): void;
```

### 2. Actualizar `src/Support/ScreenLifecycleOrchestrator.php`
```diff
--- a/src/Support/ScreenLifecycleOrchestrator.php
+++ b/src/Support/ScreenLifecycleOrchestrator.php
@@ -554,8 +554,9 @@ class ScreenLifecycleOrchestrator implements ScreenLifecycleOrchestratorInterfac
      * Build and attach a nested screen inside a parent container.
      *
      * @param  class-string<Screen>  $screenClass
+     * @param  array<int|string, mixed>  $buildParams
      */
-    public function embedInto(string $screenClass, Container $parent): void
+    public function embedInto(string $screenClass, Container $parent, array $buildParams = []): void
     {
         $parentId = $parent->getId();
@@ -576,7 +577,8 @@ class ScreenLifecycleOrchestrator implements ScreenLifecycleOrchestratorInterfac
             $this->initializeEventContext(
                 screen: $instance,
                 incomingStorage: Screen::getCurrentIncomingStorage(),
                 queryParams: Screen::getCurrentQueryParams(),
-                parent: $parentId
+                parent: $parentId,
+                buildParams: $buildParams
             );
```

### 3. Actualizar `src/Screen.php`
```diff
--- a/src/Screen.php
+++ b/src/Screen.php
@@ -805,9 +805,10 @@ abstract class Screen implements JsonSerializable
      * Build and attach a nested screen inside a parent container.
      *
      * @param  class-string<Screen>  $class
+     * @param  array<int|string, mixed>  $params
      */
-    public static function embedInto(string $class, Container $parent): void
+    public static function embedInto(string $class, Container $parent, array $params = []): void
     {
-        static::getLifecycleOrchestrator()->embedInto($class, $parent);
+        static::getLifecycleOrchestrator()->embedInto($class, $parent, $params);
     }
```

### 4. Actualizar `src/Layout/AbstractLayout.php`
```diff
--- a/src/Layout/AbstractLayout.php
+++ b/src/Layout/AbstractLayout.php
@@ -297,7 +297,7 @@ abstract class AbstractLayout extends Screen implements LayoutInterface
 
         // Clear existing content in the slot container
         $targetContainer->clear();
-        Screen::embedInto($targetClass, $targetContainer);
+        Screen::embedInto($targetClass, $targetContainer, $params);
         $this->setActiveScreen($effectiveSlot, $targetClass);
```

### 5. Actualizar `src/Concerns/HandlesNavigation.php`
```diff
--- a/src/Concerns/HandlesNavigation.php
+++ b/src/Concerns/HandlesNavigation.php
@@ -92,7 +92,7 @@ trait HandlesNavigation
         // Only clear and embed locally if target container is physically present in this screen's tree
         if ($targetContainer instanceof Container) {
             $targetContainer->clear();
-            Screen::embedInto($targetClass, $targetContainer);
+            Screen::embedInto($targetClass, $targetContainer, $params);
         }
```

---

## 6. Consideración adicional sobre URLs (`updateBrowserUrl`)

Cuando `updateBrowserUrl: true`, el payload de navegación enviado al frontend (`uiUpdate.navigate.url`) asigna la ruta base de la pantalla (ej. `/post/edit-post`). 

Se recomienda evaluar si `showInto` debería opcionalmente codificar `$params` como Query Parameters en la URL (ej. `/post/edit-post?post_id=X`) para garantizar persistencia ante recarga del navegador (F5), o permitir que la pantalla destino resuelva los valores tanto desde `$params` como desde `$this->getQueryParams()`.

