# USIM Client Template - Android (Kotlin)

Template de referencia para implementar un cliente USIM en Android nativo.

## 1. Modulos sugeridos

- `network`: HTTP client, cookies, headers, retries.
- `contract`: parser de payload USIM (componentes + meta).
- `state`: snapshot local y merge de deltas.
- `renderer`: mapeo `type` -> vista nativa.
- `events`: builders para `click`, `input`, `change`, `action`, `timeout`.

## 2. Modelos base

```kotlin
data class UiEventRequest(
    val component_id: Int,
    val event: String,
    val action: String,
    val parameters: Map<String, Any?>,
    val usim: String? = null,
    val storage: String? = null,
)

data class UiMeta(
    val storage: Map<String, Any?>? = null,
    val toast: Map<String, Any?>? = null,
    val redirect: Any? = null,
    val abort: Any? = null,
    val change_theme: String? = null,
    val change_language: String? = null,
    val action: Any? = null,
    val modal: Any? = null,
    val update_modal: Any? = null,
    val clear_uploaders: Any? = null,
    val set_uploader_existing_file: Any? = null,
    val agent_context: Map<String, Any?>? = null,
)
```

## 3. Red y sesion

- Usar `OkHttp` con `CookieJar` persistente.
- Incluir `X-USIM-Storage` cuando exista.
- Mantener fallback body (`usim` o `storage`) para compatibilidad.

## 4. Motor de estado

Estructuras:

- `componentsByKey: MutableMap<String, JsonObject>`
- `rawUsim: String?`

Reglas:

- `initial`: reset y carga completa.
- `delta`: merge superficial por key.
- key numérica: resolver directamente por `jsonKey`.

## 5. Render

- Resolver por `type` con factory.
- Usar `name` como identificador estable para testing.
- Ignorar campos desconocidos sin romper UI.

## 6. Eventos

- `click`: tomar `action` del componente.
- `input/change`: enviar valor actual de campo en `parameters`.
- `timeout`: enviar evento cuando expira modal con `_timeout`.

## 7. Tests minimos

- bootstrap screen
- click success
- preservacion `storage.usim`
- merge delta parcial
- respuesta solo meta
- resync por inconsistencia
