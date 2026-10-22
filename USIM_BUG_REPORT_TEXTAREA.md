# Bug Report: Componente `Textarea` — `TypeError: this.triggerAction is not a function` en `onChange` / `onInput`

**Para:** Equipo de Desarrollo de USIM Framework
**Fecha:** 22 de octubre de 2026
**Paquete:** `idei/usim` (instalado en `vendor/idei/usim`, versión: `v1.3.4`)
**Componente:** `Idei\Usim\Components\Textarea` (`UI::textarea()`) y su renderer JS
**Pantalla de reproducción:** [app/UI/Screens/PublishPost.php](./app/UI/Screens/PublishPost.php) (URL `/publish-post`)

**Estado general:**
- 🔴 **Bug 1 (PENDIENTE):** `Uncaught TypeError: this.triggerAction is not a function` al cambiar el foco (`change`) o teclear (`input`).
- 🟢 **Bug 2 (CORREGIDO en v1.3.4):** Posición incorrecta en el montaje del DOM.
- 🟢 **Bug 3 (CORREGIDO en v1.3.4):** Tipo de retorno `UI::textarea(): Textarea` en `src/UI.php`.

Archivos del framework involucrados (Bug 1):
- `resources/assets/js/components/textarea/index.js`
- `resources/assets/js/components/input/index.js` (referencia de implementación correcta)
- `resources/assets/js/components/shared/ui-event.js` (`sendUiEvent`)

---

## Bug 1 — `Uncaught TypeError: this.triggerAction is not a function` al cambiar el foco (`change`) o teclear (`input`)

### Síntoma y Evidencia
En cualquier pantalla con un componente Textarea configurado con `->onChange(...)` o `->onInput(...)` (por ejemplo en `PublishPost.php:103`), al escribir texto y perder el foco (desencadenando el evento DOM `change`) o al escribir con debounce, la consola del navegador arroja:

```text
Uncaught TypeError: this.triggerAction is not a function
    at UsimTextareaComponent._triggerOnChange (index.js?v=...:540:14)
    at HTMLTextAreaElement.<anonymous> (index.js?v=...:330:18)
```

Como consecuencia inmediata:
- El evento nunca es transmitido al backend vía HTTP (`POST /api/ui-event`).
- El método manejador en PHP (ej. `onPlainSaved(array $params)`) nunca se ejecuta.
- La ejecución del hilo JavaScript queda interrumpida con una excepción no capturada en la consola.

### Flujo de Ejecución y Causa Raíz
1. En `resources/assets/js/components/textarea/index.js` (línea 329):
   ```javascript
   ta.addEventListener('change', () => {
       this._triggerOnChange();
   });
   ```
2. Al perder el foco tras editar, el navegador dispara `change`, llamando a `_triggerOnChange()` (línea 538):
   ```javascript
   _triggerOnChange() {
       if (!this.config.on_change) return;
       this.triggerAction(
           this.config.on_change.action,
           { ...this.config.on_change.parameters, value: this._textarea?.value ?? '' }
       );
   }
   ```
3. `UsimTextareaComponent` hereda de la clase abstracta `UIComponent` (`ui-renderer.js:394`).
4. **`triggerAction` no existe en la clase base `UIComponent` ni está implementado en `UsimTextareaComponent`.**
5. En otros componentes del framework (como `Input` en `resources/assets/js/components/input/index.js:121`), `triggerAction` fue implementado localmente utilizando `window.USIM_COMPONENT_HELPERS.sendUiEvent`. En `Textarea` se escribieron las llamadas a `this.triggerAction(...)` en `_triggerOnChange` (línea 540) y `_triggerOnInput` (línea 531), pero **se omitió por completo la definición del método**.
6. Adicionalmente, `Textarea` omite el helper `buildActionParameters`, por lo que si el componente tiene un `name` definido en su configuración, no se inyecta la clave con el nombre del campo en los parámetros del evento.

### Solución Propuesta para el Paquete USIM

En `resources/assets/js/components/textarea/index.js`, agregar los métodos `buildActionParameters` y `triggerAction` dentro de la clase `UsimTextareaComponent` (siguiendo el mismo contrato de `Input`), y actualizar `_triggerOnInput` y `_triggerOnChange`:

```javascript
    // ─── Backend events ───────────────────────────────────────────────────────

    buildActionParameters(baseParameters = {}, currentValue = '') {
        const params = {
            ...(baseParameters || {}),
            value: currentValue,
        };

        if (this.config.name) {
            params[this.config.name] = currentValue;
            params.name = this.config.name;
        }

        return params;
    }

    async triggerAction(action, parameters) {
        try {
            const componentId = this.getComponentId();
            const helpers = window.USIM_COMPONENT_HELPERS;
            if (!helpers?.sendUiEvent) {
                return;
            }

            const { ok, result } = await helpers.sendUiEvent({
                componentId,
                event: 'input',
                action,
                parameters,
            });

            if (ok) {
                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Textarea action error:', error);
        }
    }

    _triggerOnInput() {
        if (!this.config.on_input) return;
        const debounce = this.config.debounce ?? 0;
        if (this._debounceTimer) clearTimeout(this._debounceTimer);
        this._debounceTimer = setTimeout(() => {
            this.triggerAction(
                this.config.on_input.action,
                this.buildActionParameters(this.config.on_input.parameters, this._textarea?.value ?? '')
            );
        }, debounce);
    }

    _triggerOnChange() {
        if (!this.config.on_change) return;
        this.triggerAction(
            this.config.on_change.action,
            this.buildActionParameters(this.config.on_change.parameters, this._textarea?.value ?? '')
        );
    }
```

> **Recomendación arquitectónica adicional:** Dado que múltiples componentes (`Input`, `Textarea`, `Checkbox`, `Select`, `TableCell`) necesitan despachar acciones mediante `sendUiEvent` y aplicar deltas con `applyUiUpdate`, evaluar promover `triggerAction()` directamente a la clase base `UIComponent` en `ui-renderer.js` para evitar duplicación y futuras omisiones en nuevos componentes.

### Criterios de Aceptación
- Al editar un textarea con `->onChange('on_action')` y perder el foco, el cliente emite la petición `POST /api/ui-event` con los parámetros `{ value: "...", [name]: "..." }`.
- Si el backend responde con actualizaciones de UI (`applyUiUpdate`), estas se procesan reactivamente sin recargar.
- Mismo comportamiento para `->onInput(...)` con y sin `debounce`.
- Cero excepciones de tipo `TypeError: this.triggerAction is not a function` en la consola.

---

## Bug 2 — [CORREGIDO en v1.3.4] El textarea se renderiza al final del formulario en lugar de entre el input de título y el uploader

> **Estado:** Corregido en la versión `v1.3.4`. El componente ya respeta el orden de inserción declarado en el contenedor. Se conserva esta sección exclusivamente como referencia histórica.

### Síntoma
En `PublishPost` el orden declarado en PHP es:

```php
$formCard->add($this->post_title);    // Input
$formCard->add($this->post_content);  // Textarea
$formCard->add($this->post_image);    // Uploader
$formCard->add($actionsRow);          // Botones Cancelar / Publicar
```

Orden esperado en pantalla: **Título → Textarea → Uploader → Botones**.
Orden observado: **Título → Uploader → Botones → Textarea** (el textarea aparece al final de la tarjeta, debajo de los botones; ver captura de pantalla adjunta al reporte original).

### Lo que ya se verificó en el código
- `Container::add()` conserva el orden de inserción (`$this->children[$id]`) y `Container::toJson()` asigna `_order` incremental (1, 2, 3…), por lo que el JSON inicial **debería** ser correcto (`_order` del textarea = 2).
- El renderer ordena hijos por `config._order` en el montaje inicial (`ui-renderer.js` ≈ líneas 1280-1335) y en inserciones incrementales usa el atributo `data-order` (≈ líneas 2860-2880).

### Hipótesis (a confirmar por el equipo)
El textarea es el único componente de la pantalla que se monta fuera del orden esperado, lo que apunta a algo específico del tipo `textarea`:

1. **`data-order` no se asigna en el montaje inicial.** La inserción con `insertBefore` solo funciona si los hermanos ya tienen `data-order`; si el montaje inicial hace `appendChild` sin `data-order` y el textarea se monta/re-inserta después (por ejemplo vía delta o montaje tardío), siempre cae al final. Verificar que todos los componentes montados reciban `data-order`.
2. **Montaje asíncrono / diferido del textarea.** Revisar `UsimTextareaComponent.render()/mount` y el flujo de `ui-renderer.js` (≈ líneas 1353-1420): si el elemento se crea de forma diferida o se re-monta tras un `update()`, termina con `appendChild` en el contenedor padre.
3. **Delta sin `_order`.** `ComponentDiffer` (`src/Support/ComponentDiffer.php`) y `ScreenLifecycleOrchestrator` ignoran `_order` al comparar (`$ignoredKeys`). Si el textarea se envía como “componente añadido” en un diff, el `_order` puede perderse y el componente quedar añadido al final (`parentElement.appendChild(element)` cuando `_order` no es entero > 0).
4. **ID autogenerado.** `UI::textarea()` se usó sin nombre (`UI::textarea()`), a diferencia del resto de componentes (`UI::input('post_title')`, `UI::uploader('post_image')`). `UIComponent::getId()` devuelve `int`; comprobar si el ID numérico generado altera el orden (por ejemplo mapas JS/PHP con claves enteras que se reordenan) o la asignación de `parent`. **Prueba rápida:** repetir con `UI::textarea('post_content')`.

### Cómo reproducir y aislar
1. Abrir `/publish-post` autenticado como `publisher`/`admin`.
2. En DevTools inspeccionar el JSON inicial (`GET /api/ui/publish-post` en modo headless, o la respuesta de carga) y comprobar `_order` y `parent` de `post_title`, el textarea y `post_image`.
3. Inspeccionar el DOM de `#publish_form_card`: orden de hijos y atributos `data-order`.
4. Si el JSON es correcto y el DOM no → bug de renderer (hipótesis 1/2). Si el JSON ya viene mal → bug de backend/diff (hipótesis 3/4).
5. Probar variantes: textarea con nombre explícito; textarea en otra posición (primero/último); sustituir por `UI::input()` para confirmar que solo ocurre con `textarea`.

### Criterios de aceptación
- El DOM respeta el orden declarado con `Container::add()` para el textarea en cualquier posición, tanto en la carga inicial como tras deltas/`?reset=true`.
- Test de regresión que verifique el `_order` y el orden de hijos del contenedor con un textarea entre un input y un uploader.

---

## Bug 3 — [CORREGIDO en v1.3.4] `UI::textarea()` declara retorno `UIComponent`: el IDE/análisis estático marca `label()` y demás métodos como inexistentes

> **Estado:** Corregido en la versión `v1.3.4`. La firma del método en `src/UI.php:200` ahora retorna correctamente `: Textarea`. Se conserva como referencia histórica.

### Síntoma
En `PublishPost.php` (línea 96), `UI::textarea()->label('Notas rápidas')` aparece subrayado con error de método inexistente (`Undefined method 'label'` en `Idei\Usim\Components\UIComponent`). Aunque en tiempo de ejecución PHP lo resuelve dinámicamente, rompe el autocompletado y genera falsos positivos en Intelephense y PHPStan. Además, impide asignar el resultado a propiedades tipadas como `Textarea` sin advertencias de tipos.

### Causa raíz
En `src/UI.php` (≈ línea 200):

```php
public static function textarea(?string $name = null): UIComponent
{
    $textareaClass = 'Idei\\Usim\\Components\\Textarea';
    return new $textareaClass($name);
}
```

El tipo de retorno declarado era la clase base abstracta `UIComponent`, la cual no define métodos específicos del componente como `label()`, `placeholder()`, `plainText()`, `maxLength()`, `helpText()`, `onChange()`, etc. Todos los demás métodos factoría de `UI.php` (como `UI::input()`, `UI::button()`, etc.) retornan su clase concreta correspondiente.

*(Nota adicional sobre nomenclatura: la clase concreta es `Idei\Usim\Components\Textarea` con minúscula en la 'a', importante para evitar fallos de autoloading PSR-4 en entornos Linux caso-sensitivos).*

### Solución aplicada en v1.3.4
Importar `Textarea` y reemplazar el tipo de retorno `UIComponent` por `Textarea`, instanciando directamente la clase:

```php
use Idei\Usim\Components\Textarea;

/**
 * Create a new textarea component
 *
 * @param  string|null  $name  The optional semantic name for the textarea
 */
public static function textarea(?string $name = null): Textarea
{
    return new Textarea($name);
}
```

---

## Resumen

| # | Bug | Severidad | Ubicación principal | Estado |
|---|-----|-----------|---------------------|--------|
| 1 | `this.triggerAction is not a function` (`onChange`/`onInput` rotos) | Alta (funcionalidad inutilizable) | `resources/assets/js/components/textarea/index.js` | 🔴 **PENDIENTE** (Causa confirmada, fix listo) |
| 2 | Textarea se monta al final del contenedor | Media (visual/UX) | `ui-renderer.js` / diff / `Textarea` | 🟢 **CORREGIDO** en v1.3.4 |
| 3 | `UI::textarea()` tipado como `UIComponent` (IDE marca `label()`) | Baja (DX / Análisis estático) | `src/UI.php` | 🟢 **CORREGIDO** en v1.3.4 |

Nota para quien implemente: tras modificar assets del paquete ejecutar `php artisan vendor:publish --tag=usim-assets --force` en la app consumidora y actualizar `CHANGELOG.md` (sección `[Unreleased] / Fixed`).
