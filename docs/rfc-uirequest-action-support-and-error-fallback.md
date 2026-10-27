# RFC: Soporte para Action Requests y Fallback de Errores Huérfanos en `UIRequest`

- **Autor:** Equipo de Desarrollo de Aplicaciones USIM
- **Fecha:** 27 de Octubre de 2026
- **Componente:** `Idei\Usim\Http\Requests\UIRequest`, `Idei\Usim\Support\ScreenLifecycleOrchestrator`
- **Tipo:** Feature Request / Mejora de Arquitectura
- **Estado:** Propuesta (Draft)

---

## 1. Resumen Ejecutivo (TL;DR)

Actualmente, `UIRequest` está fuertemente acoplado al supuesto de que los campos validados corresponden a componentes de entrada visuales (inputs de formularios) en el `Screen`. Cuando se utiliza `UIRequest` para validar parámetros de **acciones directas o comandos** (por ejemplo, el clic de un botón de eliminación en un listado: `->action('delete_post', ['id' => $post->id])`), los errores de validación no encuentran ningún componente al cual vincularse en la pantalla (`$this->findComponent(...) === null`). 

Como resultado:
1. Los mensajes específicos del validador (`messages()`) se descartan silenciosamente.
2. La interfaz solo proyecta el toast genérico por defecto (`'validation.check_form_errors'`), el cual suele mostrarse como una clave técnica cruda sin traducir.

**Se propone:** Extender `UIRequest` para soportar formalmente **Action Requests** (solicitudes sin componentes mapeados), implementando un mecanismo de *fallback* que proyecte el primer error no vinculado (*unbound/orphan error*) directamente en el Toast de la pantalla.

---

## 2. Contexto y Motivación

En las aplicaciones construidas con USIM, existen dos naturalezas fundamentales de interacción:

1. **Form Requests (Estado de Componentes):** Pantallas con campos de entrada (`TextInput`, `SelectBox`, etc.) donde el usuario ingresa datos. Aquí, el enlace visual de errores (`bindErrorsToScreen()`) es el comportamiento ideal.
2. **Action / Command Requests (Cargas útiles de Eventos):** Botones en tarjetas, tablas, filas o menús contextuales que disparan acciones con parámetros puntuales (IDs, estados, flags de confirmación), por ejemplo:
   ```php
   UI::button("btn_delete_post_{$post->id}")
       ->label('🗑️')
       ->action('delete_post', ['id' => (int) $post->id])
       ->style('danger');
   ```

Para manejar estas acciones en el Screen, la arquitectura actual de USIM ya permite tipar el método con una subclase de `UIRequest`:

```php
public function onDeletePost(PostIdRequest $request): void
{
    $postId = $request->getPostId();
    $this->postService->deletePost($postId);
}
```

Esto representa una práctica excelente: promueve tipado estricto, encapsulación, reglas de validación declarativas y evita que el desarrollador deba escribir código imperativo y repetitivo para inspeccionar arrays crudos (`isset($params['id']) && is_numeric(...)`).

---

## 3. Problema Técnico Actual

Al ejecutar la validación en `ScreenLifecycleOrchestrator::invoke`:

```php
try {
    $uiRequest->validate();
    $reflectionMethod->invoke($screen, $uiRequest);
} catch (UIRequestValidationException $validationException) {
    if ($uiRequest->shouldAutoBindErrors()) {
        $uiRequest->bindErrorsToScreen($screen, $validationException->validator->errors());

        $toastMsg = $uiRequest->errorToastMessage();
        if ($toastMsg !== null) {
            $screen->toast($toastMsg, 'error');
        }

        $uiRequest->failedValidation($validationException->validator);
    }
}
```

Ocurren dos fallas de experiencia de usuario y desarrollo:

### A. Errores Huérfanos (*Unbound Errors*)
En `UIRequest::bindErrorsToScreen()`:
```php
foreach ($errors->getMessages() as $field => $messages) {
    $componentName = $this->map()[$field] ?? $field;
    $component = $this->findComponent($componentName);
    // ...
    if ($component !== null) {
        $component->error($firstMessage);
    }
}
```
Si el campo validado (ej. `id`) no es un componente de entrada en el `Screen`, `$component` es `null`. El mensaje definido en `messages()` (por ejemplo: *"El ID del post es obligatorio."*) se descarta y nunca llega a la vista.

### B. Toast Crudo y Estático
La propiedad base en `UIRequest` es:
```php
protected ?string $errorToast = 'validation.check_form_errors';
```
Y su resolución:
```php
public function errorToastMessage(): ?string
{
    if (function_exists('t')) {
        return t($this->errorToast);
    }
    return $this->errorToast;
}
```
Si la aplicación no define una traducción explícita para la clave `validation.check_form_errors`, el usuario ve en pantalla una notificación roja con el texto literal `"validation.check_form_errors"`, lo cual confunde al usuario final y dificulta la depuración.

---

## 4. Especificación de la Solución Propuesta

### 4.1. Detección de Errores Huérfanos y Fallback al Toast

Se propone modificar `bindErrorsToScreen()` para que retorne o registre aquellos errores que no pudieron vincularse a ningún componente interactivo de la pantalla. Si existen errores huérfanos, el Toast debe utilizarlos como mensaje prioritario.

#### Modificación en `UIRequest.php`:

```php
/**
 * Almacena los errores de validación que no se pudieron vincular a componentes visuales.
 * 
 * @var array<string, list<string>>
 */
protected array $unboundErrors = [];

/**
 * Automatically bind validation error messages to screen components.
 */
public function bindErrorsToScreen(Screen $screen, MessageBag $errors): void
{
    $this->unboundErrors = [];
    $inverseMap = array_flip($this->map());

    foreach ($errors->getMessages() as $field => $messages) {
        $componentName = $this->map()[$field] ?? $field;
        $component = $this->findComponent($componentName);

        if ($component === null && isset($inverseMap[$field])) {
            $component = $this->findComponent($inverseMap[$field]);
        }

        if ($component !== null) {
            $firstMessage = is_array($messages) ? ($messages[0] ?? '') : (string) $messages;

            if (method_exists($component, 'error')) {
                $component->error($firstMessage);
            } elseif (method_exists($component, 'errorMessage')) {
                $component->errorMessage($firstMessage);
            }
        } else {
            // Error huérfano: no existe componente en la pantalla para este campo
            $this->unboundErrors[$field] = (array) $messages;
        }
    }
}

/**
 * Retorna true si hubo errores de validación que no encontraron componente en pantalla.
 */
public function hasUnboundErrors(): bool
{
    return ! empty($this->unboundErrors);
}

/**
 * Obtiene el primer error no vinculado a componentes visuales.
 */
public function firstUnboundErrorMessage(): ?string
{
    foreach ($this->unboundErrors as $messages) {
        return $messages[0] ?? null;
    }

    return null;
}
```

### 4.2. Toast Dinámico e Inteligente en `errorToastMessage()`

Permitir que el Toast muestre el mensaje real del error si existen errores huérfanos, o configurable mediante una directiva:

```php
/**
 * Si es true, el toast mostrará el primer mensaje de validación real cuando
 * no haya componentes visuales asociados en la pantalla.
 */
protected bool $fallbackToFirstErrorOnToast = true;

/**
 * Get the translated error toast message if configured.
 */
public function errorToastMessage(): ?string
{
    // Si hay errores que no pudieron mostrarse en ningún input, usamos el primero como Toast
    if ($this->fallbackToFirstErrorOnToast && $this->hasUnboundErrors()) {
        $firstError = $this->firstUnboundErrorMessage();
        if ($firstError !== null) {
            return $firstError;
        }
    }

    if ($this->errorToast === null || $this->errorToast === '') {
        return null;
    }

    if (function_exists('t')) {
        $translated = t($this->errorToast);
        // Evita mostrar la clave técnica si no hay traducción definida
        return ($translated !== $this->errorToast) ? $translated : 'Por favor verifique los datos ingresados.';
    }

    return $this->errorToast;
}
```

---

## 5. Beneficios

| Aspecto | Antes | Con la Propuesta |
| :--- | :--- | :--- |
| **Acciones de botones con parámetros** | Obliga a usar `array $params` sin tipar o manual. | Soporte formal de `UIRequest` para cualquier acción/evento. |
| **Feedback de validación** | Texto técnico crudo `"validation.check_form_errors"`. | Mensaje real configurado en la regla (ej. *"El ID del post es obligatorio"*). |
| **Retrocompatibilidad** | N/A | **100% compatible**. Pantallas con formularios e inputs conservan exactamente el mismo comportamiento actual. |
| **Ergonomía de desarrollo** | Fricción alta al validar acciones sin campos visuales. | Misma experiencia consistente de Laravel `FormRequest` en todo USIM. |

---

## 6. Alternativa Considerada: Clase `ActionRequest` especializada

Se evaluó la posibilidad de crear una clase derivada `ActionRequest extends UIRequest` con `$autoBindComponentErrors = false`. Sin embargo, unificar la lógica de *fallback* directamente en `UIRequest` ofrece una experiencia mucho más cohesiva: una misma pantalla puede tener formularios donde algunos campos son inputs y otros son parámetros de contexto (como un `post_id` en una acción de guardado), beneficiándose ambos del mismo comportamiento predecible.
