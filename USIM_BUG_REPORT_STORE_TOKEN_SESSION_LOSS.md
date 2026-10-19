# Bug Report: Pérdida Inesperada de Sesión y Deslogueo Silencioso por Sobreescritura de `store_token` en `sessionStorage` (Per-Tab Isolation)

**Para:** Equipo de Arquitectura y Desarrollo de USIM Framework  
**Fecha:** 19 de octubre de 2026  
**Severidad:** Crítica / Alta (Afecta directamente la persistencia de sesión de usuarios autenticados en cualquier flujo de pantallas reactivas)  
**Módulos afectados:**
- `Idei\Usim\Http\Middleware\PrepareUIContext`
- `Idei\Usim\Concerns\HandlesScreenProperties` (`getStorageVariables`, `injectStorageValues`)
- `Idei\Usim\UIChangesCollector` (`all`, `setStorage`)
- `Idei\Usim\Support\ScreenLifecycleOrchestrator`
- `resources/assets/js/ui-renderer.js` (`handleStorageUpdate`)
- Stubs oficiales: `stubs/screens/MenuStub.php.stub`, `stubs/screens/Device/KioskScreen.php.stub`

---

## 1. Resumen Ejecutivo

Al interactuar con aplicaciones desarrolladas con USIM que manejan flujos continuos de navegación y acciones entre pantallas (por ejemplo, publicar o editar varios registros consecutivos), los usuarios experimentan **pérdidas abruptas y silenciosas de su sesión autenticada**.

El síntoma visible para el usuario es desconcertante:
1. La interfaz gráfica (DOM) **continúa mostrando al usuario como logueado** (avatar, nombre, opciones del menú de usuario visibles).
2. Sin embargo, al disparar la siguiente acción en el backend (por ejemplo, guardar un post o invocar un método protegido con `Auth::user()`), el servidor rechaza la petición arrojando un error de autenticación (`Auth::user()` resulta `null`).
3. Al inspeccionar el almacenamiento del navegador en DevTools (`Application > Session Storage`), se observa que la clave de almacenamiento del framework (ej. `usim-posts-store-key`) ha sobreescrito el token legítimo por un valor vacío:
   ```json
   {"store_theme":"dark","store_token":""}
   ```

---

## 2. Comportamiento Esperado vs. Comportamiento Actual

### Comportamiento Esperado
- El token de autenticación del cliente (`store_token`) debe permanecer intacto en `sessionStorage` a lo largo de toda la sesión activa de la pestaña, garantizando la continuidad de autenticación en todas las pantallas.
- Las mutaciones de estado o propiedades locales de pantallas (`store_*`) no deben vaciar ni clobberizar propiedades de autenticación globales de la pestaña.

### Comportamiento Actual
- Si una pantalla o evento auxiliar se inicializa o serializa sin haber recibido o preservado activamente el token (por ejemplo, `MenuStub` con su valor por defecto `protected string $store_token = '';`), emite un delta de almacenamiento con `store_token: ""`.
- El cliente JavaScript (`ui-renderer.js`) sobreescribe ciegamente todo el contenido de `sessionStorage[storage_key]` sin realizar una fusión (`merge`) defensiva.
- En la siguiente solicitud HTTP, el cliente envía la cabecera `X-UI-Client-Id` junto con el `store_token` vacío.
- El middleware `PrepareUIContext` interpreta la falta de token como un intento de fuga entre pestañas bajo su política de *Per-Tab Isolation* y **destruye forzosamente el usuario de la sesión de Laravel** (`$this->clearWebGuardUser()`), deslogueando al usuario sin aviso.

---

## 3. Análisis de Causa Raíz (Paso a Paso en el Código de USIM)

### Paso 1: El mecanismo de Aislamiento por Pestaña en `PrepareUIContext`
En `vendor/idei/usim/src/Http/Middleware/PrepareUIContext.php` (Líneas 95–127):

```php
$hasTabClientId = $request->hasHeader(UIStateManager::CLIENT_ID_HEADER);

if (! empty($storeToken)) {
    // Autentica el token mediante PersonalAccessToken o DeviceSecurityGuard...
    $tokenModel = PersonalAccessToken::findToken($storeToken);
    if ($tokenModel && $tokenModel->tokenable instanceof Authenticatable) {
        $this->setWebGuardUser($tokenModel->tokenable);
        $request->setUserResolver(fn () => $tokenModel->tokenable);
    } else {
        $this->clearWebGuardUser();
        $request->setUserResolver(fn () => null);
    }
} elseif ($hasTabClientId) {
    // Per-tab isolation: when request explicitly sends tab client ID,
    // but no store_token is present for this tab, treat this tab as GUEST
    // and prevent leaking user session from the shared browser cookie!
    $this->clearWebGuardUser();
    $request->setUserResolver(fn () => null);
}
```

> **El problema:** `PrepareUIContext` asume dogmáticamente que si la cabecera `X-UI-Client-Id` está presente, `store_token` **debe** existir en `X-USIM-Storage`. Si por cualquier error de sincronización de UI el token llega vacío o ausente, el middleware purga activamente la sesión `web` de Laravel (`clearWebGuardUser()`).

---

### Paso 2: Sobreescritura Destructiva en el Cliente JavaScript (`ui-renderer.js`)
En `vendor/idei/usim/resources/assets/js/ui-renderer.js` (Líneas 1535–1558):

```javascript
handleStorageUpdate(storageData) {
    const storageKeys = Object.keys(storageData);
    ...
    storageKeys.forEach(key => {
        const value = storageData[key];
        try {
            if (typeof value === 'object' && value !== null) {
                sessionStorage.setItem(key, JSON.stringify(value));
            } else {
                sessionStorage.setItem(key, String(value));
            }
        } catch (error) {
            // Ignore storage errors
        }
    });
}
```

> **El problema:** `handleStorageUpdate` ejecuta un `sessionStorage.setItem(key, ...)` **totalmente destructivo**. No realiza un `merge` (`Object.assign`) con las claves preexistentes en `sessionStorage[key]`. Si el backend responde con un objeto que contiene `{ "store_theme": "dark", "store_token": "" }`, la clave en `sessionStorage` es pisada de inmediato, destruyendo el token que estaba almacenado previamente.

---

### Paso 3: Ámbito por Pantalla (`Screen`) vs. Almacenamiento Global de Pestaña
En `vendor/idei/usim/src/Concerns/HandlesScreenProperties.php` (Líneas 96–123):

```php
public function getStorageVariables(): array
{
    $storage = [];
    $reflection = new ReflectionClass($this);
    $properties = $reflection->getProperties(ReflectionProperty::IS_PROTECTED);

    foreach ($properties as $property) {
        $propertyName = $property->getName();
        if (str_starts_with($propertyName, 'store_')) {
            ...
            $storage[$propertyName] = $value;
        }
    }

    return $storage;
}
```

Y en `vendor/idei/usim/src/Support/ScreenLifecycleOrchestrator.php` (Línea 348–350):

```php
$storageVariables = $screen->getStorageVariables();
$this->changesCollector->add($diff);
$this->changesCollector->setStorage($storageVariables);
```

> **El problema:** USIM modela las variables de almacenamiento como propiedades protegidas de la clase `Screen` activa. Pero `sessionStorage` es un recurso compartido a nivel de toda la pestaña del navegador.
> 
> Si una pantalla (como `Home` o `PublishPost`) no declara explícitamente `protected string $store_token = '';`, su `$screen->getStorageVariables()` no lo incluye. Peor aún: en stubs como `MenuStub.php.stub`, la propiedad viene declarada con valor inicial vacío:
> ```php
> protected string $store_token = '';
> ```
> Si por cualquier orden de ejecución o evento del menú el token no es inyectado desde `$incomingStorage` antes de que se recolecten las variables de storage, la pantalla emite `['store_token' => '']`.

---

### Paso 4: Precedente Oficial en el Propio Framework
Este comportamiento anómalo ya fue detectado por los desarrolladores de USIM en pantallas de kiosco y registrado en `vendor/idei/usim/CHANGELOG.md` (Líneas 20–22):

> *- "Per-tab authentication isolation in `PrepareUIContext`: when requests provide `X-UI-Client-Id` without a valid `store_token`, the web guard user is cleared (`clearWebGuardUser()`) and resolved as guest, preventing cross-tab session leakage from shared browser session cookies (`laravel_session`)."*  
> *- "Declared `protected string $store_token = '';` on `KioskScreen` and stub to preserve `sessionStorage` device authentication token across screen reloads and polling ticks."*

**Conclusión:** Declarar `protected string $store_token = '';` pantalla por pantalla fue un parche localizado para `KioskScreen`, pero la falla arquitectónica subsiste: **el token de autenticación de la pestaña no puede depender de las declaraciones de propiedades de cada clase de pantalla individual ni ser susceptible de borrado accidental por serializaciones parciales de storage.**

---

## 4. Escenario de Reproducción (MRE)

1. **Inicio de Sesión:** El usuario inicia sesión a través del modal de login. Se emite el evento `logged_user` y el token Sanctum se guarda en `sessionStorage` bajo `usim-posts-store-key`.
2. **Navegación / Flujo continuo:** El usuario ingresa a `PublishPost`, completa un formulario con título, texto e imagen, y pulsa "Publicar Post".
3. **Transición:** `PublishPost::onSavePost` guarda el registro y ejecuta `$this->showInto(Home::class, force: true, updateBrowserUrl: true)`.
4. **Repetición rápida:** El usuario repite la operación para publicar un segundo, tercer y cuarto post consecutivo.
5. **Vaciado de Token:** Durante uno de los ciclos de navegación entre `Home` y `PublishPost` (o al interactuar con componentes auxiliares como el menú de usuario o uploader), una respuesta incluye `storage: { "usim-posts-store-key": "{\"store_theme\":\"dark\",\"store_token\":\"\"}" }`.
6. **Inconsistencia de Sesión:**
   - En el frontend, el DOM sigue mostrando el estado logueado.
   - En `sessionStorage`, `store_token` es `""`.
7. **Fallo en Backend:** Al intentar publicar el siguiente post, la petición lleva `X-UI-Client-Id` y `store_token: ""`. `PrepareUIContext` invoca `$this->clearWebGuardUser()`. El método `onSavePost` verifica `Auth::user()`, encuentra `null`, y rechaza la publicación solicitando iniciar sesión nuevamente.

---

## 5. Soluciones Propuestas para el Framework

### Solución 1: Fusión No Destructiva en el Cliente JavaScript (`ui-renderer.js`) (Recomendada)
Modificar `handleStorageUpdate` en `ui-renderer.js` para que **fusiona** incrementalmente los datos recibidos con los ya existentes en `sessionStorage`, impidiendo además que un `store_token` existente sea vaciado a menos que exista una orden explícita de logout:

```javascript
// vendor/idei/usim/resources/assets/js/ui-renderer.js
handleStorageUpdate(storageData) {
    const storageKeys = Object.keys(storageData);
    if (storageKeys.length > 0) {
        const currentKey = getActiveUsimStorageKey();
        const preferredKey = storageKeys.includes(currentKey) ? currentKey : storageKeys[0];
        setActiveUsimStorageKey(preferredKey);
    }

    storageKeys.forEach(key => {
        const rawValue = storageData[key];
        try {
            const incomingObj = typeof rawValue === 'object' && rawValue !== null
                ? rawValue
                : safeParseJsonObject(rawValue);

            if (incomingObj && typeof incomingObj === 'object') {
                const existingRaw = sessionStorage.getItem(key);
                const existingObj = safeParseJsonObject(existingRaw) || {};

                // PROTECCIÓN: Si ya existe un store_token válido y la nueva actualización
                // trae un store_token vacío/falsy sin ser una acción de logout explícita,
                // preservar el token previo.
                if (existingObj.store_token && (!incomingObj.store_token || incomingObj.store_token === '')) {
                    if (incomingObj.action !== 'logout' && incomingObj.store_token !== null) {
                        incomingObj.store_token = existingObj.store_token;
                    }
                }

                const merged = Object.assign({}, existingObj, incomingObj);
                sessionStorage.setItem(key, JSON.stringify(merged));
            } else {
                sessionStorage.setItem(key, String(rawValue));
            }
        } catch (error) {
            // Ignore storage errors
        }
    });

    applyPersistedThemeFromStorage(true, 'framework-storage-update');
}
```

---

### Solución 2: Desacoplar `store_token` del ciclo de vida de Screens individuales
- El token de autenticación de sesión no debe tratarse como una simple variable de pantalla `store_*`.
- En `HandlesScreenProperties::getStorageVariables()`, no se deben emitir claves de autenticación sensibles (`store_token`, `store_token_crypt`) con valores vacíos `""` si el usuario se encuentra actualmente autenticado en la petición.

---

### Solución 3: Salvaguarda en `PrepareUIContext`
En `PrepareUIContext.php`, antes de ejecutar `$this->clearWebGuardUser()`, verificar si la petición cuenta con una sesión web activa y válida de Laravel (`Auth::guard('web')->check()`). Si la cookie de sesión es válida y no ha expirado, no forzar la destrucción del usuario a menos que se trate inequívocamente de una petición originada desde otra pestaña con un token diferente y disjunto.

---

## 6. Conclusión

El mecanismo de *Per-Tab Isolation* implementado en USIM resuelve la fuga de sesiones entre pestañas, pero su implementación actual es excesivamente punitiva ante desincronizaciones de `sessionStorage`. Al combinarse con una sobreescritura destructiva en el frontend y con la inicialización en string vacío en los stubs de pantalla, produce desconexiones involuntarias en aplicaciones en producción.

Implementar una fusión defensiva en `ui-renderer.js` y proteger el token frente a sobreescrituras accidentales en el ciclo de vida de los screens restablecerá la robustez y confiabilidad de la autenticación en el framework.
