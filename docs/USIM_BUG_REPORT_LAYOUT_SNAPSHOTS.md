# Bug Report & RFC: Inconsistencia de Estado en Layouts y Omisión del Ciclo de Vida en Recargas (F5)

**Para:** Equipo de Desarrollo de USIM Framework (`idei/usim`)  
**Fecha:** 28 de Octubre, 2026  
**Severidad:** Media-Alta (Consistencia de UI / Seguridad en Autenticación)  
**Módulos afectados:** `ScreenLifecycleOrchestrator`, `AbstractLayout`, `UIStateRepository`, `AuthSessionService`

---

## 1. Resumen del Problema

Tras completar un flujo de autenticación exitoso (login), la interfaz de usuario se actualiza dinámicamente mediante eventos SPA (`UsimEvent('logged_user')`), mostrando correctamente la barra de navegación (`Menu`) con el avatar y nombre del usuario autenticado.

Sin embargo, al recargar la página (**F5** o navegación directa a la URL):
- La pantalla de contenido (`Home`) sí se renderiza en estado autenticado.
- **La barra de navegación (`Menu`) revierte inmediatamente a estado de visitante (Guest)**, mostrando el trigger por defecto (`⚙️`) y las opciones *"Iniciar sesión"* y *"Registrarse"*, a pesar de que la sesión de Laravel (`Auth::check()`) sigue activa.

---

## 2. Comportamiento Esperado vs. Comportamiento Actual

| Escenario | Comportamiento Esperado | Comportamiento Actual |
| :--- | :--- | :--- |
| **Login exitoso (SPA)** | `Menu` muestra dropdown con imagen y nombre del usuario. | `Menu` muestra dropdown con imagen y nombre del usuario (Correcto). |
| **Recarga (F5) tras Login** | `Menu` ejecuta su ciclo de vida (`postLoadUI`), detecta `Auth::check() === true` y mantiene el avatar/nombre. | `Menu` se muestra como visitante (`⚙️`), `postLoadUI()` nunca se ejecuta, y se sirve un snapshot desactualizado. |
| **Logout + F5** | El menú vuelve a visitante tras recargar. | Si el layout se serializó con el usuario activo, muestra datos obsoletos del usuario previo. |

---

## 3. Análisis de Causa Raíz (Root Cause Analysis)

La investigación exhaustiva del código fuente del framework reveló **tres causas interconectadas** en el flujo de renderizado y persistencia de estado:

### Causa A: Snapshot anidado en el Layout y falta de invalidación en la frontera de autenticación
1. En la primera visita del usuario (como visitante), `MainLayout` se renderiza y guarda su árbol completo en caché bajo la clave:
   ```
   ui_state:MainLayout:{clientId}
   ```
2. Debido a que `Menu` se monta dentro del slot `main_menu_container` de `MainLayout`, **el árbol serializado de `Menu` (en modo visitante) queda horneado dentro del JSON del layout**.
3. Al ocurrir el login:
   - `Menu` procesa `onLoggedUser()` y actualiza su propio snapshot (`ui_state:Menu:{clientId}`).
   - **`MainLayout` no implementa `onLoggedUser` ni es invalidado**, por lo que su snapshot en caché permanece congelado con la *"foto anterior"* de visitante.
4. Como el identificador de cliente (`clientId`) se conserva entre la navegación de invitado y la autenticada, `ui_state:MainLayout:{clientId}` entrega la foto estática de visitante.

### Causa B: Asimetría en la gestión de slots en `ScreenLifecycleOrchestrator::render()`
En `ScreenLifecycleOrchestrator.php` (L113-L118), el framework reconoce que los slots de un layout reconstruido de caché deben limpiarse y embeberse dinámicamente, **pero lo implementa únicamente para el slot de contenido**:
```php
// ScreenLifecycleOrchestrator.php
$slot = $layout->getSlot('content') ?? $layout->getSlot('main');
if ($slot instanceof Container) {
    $slot->clear();
    $this->embedInto($screen::class, $slot, $buildParams);
}
```
**Todos los demás slots registrados** (como `top_menu` o `menu`) son completamente ignorados:
- No se limpian.
- No se re-embebden.
- Se envían al navegador directamente tal cual vienen serializados en el snapshot de `MainLayout`.

### Causa C: Omisión del ciclo de vida (`postLoadUI()`) de las sub-pantallas del Layout
- Como `MainLayout` existe en caché, su método `buildBaseUI()` no se ejecuta en F5.
- En consecuencia, la llamada `Screen::embedInto($menuClass, $mainMenuContainer)` nunca ocurre.
- Al no ejecutarse `embedInto()` para `Menu`, **la clase `Menu` ni siquiera se instancia durante la petición de F5**.
- `Menu::postLoadUI()` nunca llega a ejecutarse, imposibilitando que la barra de navegación evalúe `Auth::check()` o `Auth::user()` en recargas de página.

---

## 4. Test de Reproducción (Pest PHP)

El siguiente test reproduce el fallo de manera determinista:

```php
it('reproduces stale layout menu snapshot on F5 after login', function () {
    // 1. Visita inicial como invitado (carga Home + MainLayout + Menu)
    $guestResponse = $this->getJson('/api/ui/home');
    $guestUserMenu = findComponentByName($guestResponse->json(), 'user_menu');
    expect($guestUserMenu['trigger']['label'])->toBe('⚙️');

    // 2. Login de usuario vía evento UI
    $user = User::factory()->create(['name' => 'John Doe', 'password' => bcrypt('secret')]);
    $loginRoot = serviceRootComponentId(getScreenJson($this, Login::class)->json(), Login::class);

    $loginResponse = $this->postJson('/api/ui-event', [
        'component_id' => $loginRoot,
        'event' => 'submit',
        'action' => 'submit_login',
        'parameters' => ['email' => $user->email, 'password' => 'secret', 'remember' => false],
    ]);
    $loginResponse->assertOk();

    // 3. Inspección de caché antes de F5:
    // Menu tiene la foto actualizada, pero MainLayout retiene la foto de visitante
    $repo = app(\Idei\Usim\Contracts\UIStateRepositoryInterface::class);
    $menuSnap = findComponentByName($repo->get(Menu::class), 'user_menu');
    $layoutSnap = findComponentByName($repo->get(MainLayout::class), 'user_menu');

    expect($menuSnap['trigger']['label'])->toContain('John Doe'); // OK
    expect($layoutSnap['trigger']['label'])->toBe('⚙️');          // STALE SNAPSHOT!

    // 4. Usuario presiona F5 (GET /api/ui/home)
    $f5Response = $this->getJson('/api/ui/home');
    $f5UserMenu = findComponentByName($f5Response->json(), 'user_menu');

    // FALLA: Retorna '⚙️' en vez de '👤 John Doe'
    expect($f5UserMenu['trigger']['label'])->toContain('John Doe');
});
```

---

## 5. Propuesta de Solución a Nivel de Framework

Recomendamos abordar la solución en **tres niveles complementarios**:

### Nivel 1: Invalidación de snapshots en cambios de contexto de autenticación (Crítico)
En `AuthSessionService::establishSession()` y `AuthSessionService::logout()`:
Al cambiar el estado de sesión (`guest -> authenticated` o `authenticated -> guest`), se deben invalidar todos los snapshots de pantalla y layout cacheados para ese `clientId`.

```php
// En Idei\Usim\Support\UIStateRepository
public function clearClientSnapshots(?string $clientId = null): void
{
    $clientId ??= $this->getOrCreateClientId();
    $openedScreens = $this->getClientOpenedScreens($clientId);

    foreach ($openedScreens as $screenRootId) {
        $context = $this->getIdGenerator()->getContextFromId((int) $screenRootId);
        if ($context !== null) {
            $this->clear($context, $clientId);
        }
    }

    // Invalidar layouts activos
    $currentScreenClass = $this->getClientCurrentScreenClass($clientId);
    if ($currentScreenClass !== null && method_exists($currentScreenClass, 'getLayoutClass')) {
        $layoutClass = $currentScreenClass::getLayoutClass();
        if ($layoutClass) {
            $this->clear($layoutClass, $clientId);
        }
    }
}
```

Invocar `$this->stateRepository->clearClientSnapshots()` dentro de:
- `AuthSessionService::establishSession()`
- `AuthSessionService::logout()`

### Nivel 2: Re-hidratación dinámica de todos los slots registrados en `ScreenLifecycleOrchestrator`
En `ScreenLifecycleOrchestrator::render()`, generalizar la re-hidratación de slots en vez de limitar el comportamiento a `content`:

```php
// ScreenLifecycleOrchestrator::render()
if ($parent === 'main' && $screen->modalLayerIndex === 0 && ! ($screen instanceof LayoutInterface)) {
    // ... renderizado del layout ...
    
    // 1. Re-embeber slot principal (content / main)
    $mainSlot = $layout->getSlot('content') ?? $layout->getSlot('main');
    if ($mainSlot instanceof Container) {
        $mainSlot->clear();
        $this->embedInto($screen::class, $mainSlot, $buildParams);
    }

    // 2. Re-embeber slot de menú superior si existe en el layout
    $menuSlot = $layout->getSlot('top_menu') ?? $layout->getSlot('menu');
    $menuClass = $layout::getActiveScreen('top_menu') 
        ?? $screen::getMenuScreen() 
        ?? $this->usimConfig->defaultMenuScreen;

    if ($menuSlot instanceof Container && $menuClass !== null && class_exists($menuClass)) {
        $menuSlot->clear();
        $this->embedInto($menuClass, $menuSlot);
    }
}
```

### Nivel 3: Montaje desacoplado en Layouts
Evitar que el snapshot de un `AbstractLayout` incluya los componentes internos de sub-pantallas con ciclo de vida propio (`Menu`, `Sidebar`). Los slots deben actuar como puntos de anclaje (placeholders) que se ensamblan dinámicamente en cada petición de renderizado completo, garantizando que los eventos y el estado de sesión de cada pantalla hija mantengan una **única fuente de verdad**.

---

## 6. Conclusión
Resolver este comportamiento a nivel de framework garantizará que ninguna pantalla o servicio de dominio deba acoplarse conociendo la existencia de clases `Layout` para purgar manualmente su caché (`clearCachedScreenSnapshot()`), asegurando una experiencia SPA y SSR coherente, segura y predecible.

