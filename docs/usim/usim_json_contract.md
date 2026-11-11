# USIM JSON Contract

Este documento define el contrato JSON observado en los archivos de soporte y testing de USIM:

- `tests/Support/UiScenario.php`
- `tests/Support/UiScreenTestHelpers.php`
- `tests/Support/UiPayloadHelpers.php`
- `tests/Pest.php`
- `tests/TestCase.php`
- `tests/SCREEN_TESTING_GUIDE.md`

Objetivo: permitir construir clientes frontend alternativos (mobile, iOS, SmartTV, desktop o web) que consuman USIM sin depender del renderer web actual.

## 1. Alcance y supuestos

- USIM es server-driven UI: el backend define estructura, estado y cambios de UI.
- El cliente renderiza componentes desde JSON y envia eventos al backend.
- El backend puede responder con snapshot completo (pantalla inicial) o delta parcial (cambios).
- Ademas de componentes, la respuesta puede incluir contratos meta (`toast`, `redirect`, `abort`, `modal`, etc.).
- Este documento describe el contrato inferido desde los helpers/tests listados arriba.

## 2. Endpoints y flujo general

## 2.1 Carga de pantalla

Request:

```http
GET /api/ui{screen_route_path}?{query_params}
```

Notas:

- `screen_route_path` proviene de `ScreenClass::getRoutePath()` (backend).
- `query_params` puede incluir `reset=true` para reiniciar estado inicial de pantalla.

Response esperada:

- HTTP `200 OK` con JSON.
- El JSON puede contener componentes y meta-keys.

## 2.2 Envio de eventos UI

Request:

```http
POST /api/ui-event?query_param=val
Content-Type: application/json
```

> **Propagación de Query String:** Las peticiones a `POST /api/ui-event` adjuntan automáticamente todos los parámetros de la URL activa del navegador (`window.location.search`), haciéndolos accesibles en la Screen mediante `$this->query('param')` o en `UIRequest` vía `$request->query('param')`.

Body base:

```json
{
  "component_id": 123,
  "event": "click",
  "action": "submit_login",
  "parameters": {
    "field_a": "value"
  },
  "query_params": {
    "optional_headless_query": "value"
  }
}
```

Campos:

- `component_id` (int, requerido): JSON key numérica del componente origen del evento.
- `event` (string, requerido): tipo de evento. Valores observados:
  - `click`
  - `input`
  - `change`
  - `action`
  - `timeout`
- `action` (string, requerido): accion backend a ejecutar. Corresponde al handler `onPascalCase` en la Screen.
- `parameters` (object, requerido): datos enviados por el componente o formulario.
- `query_params` (object, opcional): parámetros de query provistos en el body para clientes headless o pruebas API.

Response esperada:

- Normalmente HTTP `200 OK` con JSON (deltas de componentes y/o meta-keys).
- Si falta un parámetro obligatorio o hay incompatibilidad de tipo: HTTP `422 Unprocessable Content` con objeto `{ "message": "...", "errors": { ... } }`.

## 2.3 Consulta y obtención de datos UI (Data Fetching / Query)

Request:

```http
GET /api/ui-data?component_id={id}&action={action}&parameters={parameters}&page_filter=value
```

Notas:

- Diseñado para componentes que consumen datos asíncronos o medios binarios (ej. `image`, listas dinámicas).
- `component_id` (int, requerido): ID del componente que solicita los datos.
- `action` (string, requerido): Acción en `snake_case` asociada a un método `get*()` en la `Screen`.
- `parameters` (array|json_string, opcional): Argumentos pasados al método `get*()`.
- **Query String:** Anexa automáticamente la query string de la página del navegador, aislando las claves reservadas del framework (`component_id`, `action`, `parameters`, `storage`).
- **Hidratación de parámetros:** Los métodos de consulta (`get*()`) admiten argumentos tipados (`int $id`, `string $size`, `BackedEnum $type`), resolviéndose automáticamente.
- Ciclo de vida de solo lectura: El método en la Screen no puede mutar componentes (lanza HTTP `422 Unprocessable Content`).
- Retorna directamente el contenido según su MIME: JSON, texto plano, SVG, imagen binaria (`image/png`, `image/jpeg`), audio o video.

## 3. Sesion de estado UI (storage + client id)

USIM depende de estado entre requests. Un cliente debe mantener y reenviar dos cosas:

## 3.1 Client ID (Cookie y Header X-UI-Client-Id)

- El backend usa el header `X-UI-Client-Id` o la cookie de client id (`UIStateManager::CLIENT_ID_COOKIE`).
- **Aislamiento por pestaña (Web):** En clientes web, cada pestaña genera un UUID único almacenado en `sessionStorage` y lo envía en el header `X-UI-Client-Id`. El backend prioriza este header sobre la cookie para aislar el árbol de componentes y la sesión por pestaña.
- Debe persistirse y enviarse en todas las requests subsecuentes de la misma sesión UI.
- Si no se conserva, el backend puede perder continuidad de estado/diffs.

## 3.2 Storage serializado (`usim`)

En respuestas, puede venir:

```json
{
  "storage": {
    "usim": "{\"store_theme\":\"dark\",\"store_token_crypt\":\"<encrypted-value>\"}"
  }
}
```

Reglas:

- `storage.usim` es un `JSON string` serializado por backend.
- En navegadores web se almacena en `sessionStorage` para mantener aislamiento estricto por pestaña.
- El cliente puede parsearlo para leer estado no sensible (`store_*` sin sufijo `_crypt`).
- Los keys con sufijo `_crypt` contienen datos protegidos por backend y deben tratarse como valores opacos (el cliente no debe intentar desencriptarlos).
- Reenviar siempre el string actualizado en eventos siguientes.
- Preservar keys desconocidas para compatibilidad forward.
- Soportes observados:
  - Header `X-USIM-Storage` (contrato esperado en backend).
  - Fallback en body (observado en tests):
    - `usim` para eventos tipo header-like (`click`, `action`, `change`).
    - `storage` para `input`.

Recomendacion para clientes nuevos:

- Implementar primero `X-USIM-Storage` y enviar `X-UI-Client-Id`.
- Mantener fallback por body si el backend/proxy lo requiere.

## 4. Formato de respuesta JSON

La respuesta es un objeto JSON con mezcla de:

- componentes UI (keys dinamicas, frecuentemente numericas serializadas como string)
- meta-keys reservadas

Ejemplo simplificado:

```json
{
  "10": {
    "type": "container",
    "parent": "root",
    "name": "main_container"
  },
  "11": {
    "type": "input",
    "parent": 10,
    "name": "login_email",
    "action": "submit_login"
  },
  "storage": {
    "usim": "{\"store_theme\":\"dark\",\"store_page\":2}"
  },
  "toast": {
    "type": "success",
    "message": "Login correcto"
  },
  "redirect": null,
  "abort": null
}
```

## 4.1 Meta-keys reservadas

Keys reservadas observadas:

- `storage`
- `action`
- `redirect`
- `toast`
- `abort`
- `change_theme`
- `change_language`
- `modal`
- `update_modal`
- `clear_uploaders`
- `set_uploader_existing_file`
- `agent_context` (optional, headless mode)

Importante:

- Estas keys no son componentes.
- Deben procesarse con semantica propia.

## 4.2 Contratos meta (interpretacion cliente)

`storage`

- Contenedor de estado serializado en `storage.usim` (o bajo la clave configurada en `usim.front_store_key`).
- `storage.usim` representa un objeto JSON de variables persistidas `store_*`.
- `_crypt` indica valor protegido y encriptado simétricamente por el backend (vía `encrypt()` de Laravel).

`toast`

- Mensaje transitorio para UX.
- Parámetros soportados: `message`, `type` (`info|success|warning|error`), `duration`, `position`, `open_effect`, `show_effect`, `close_effect`.
- En tests se valida comúnmente `toast.type` (por ejemplo `success`) y `toast.message`.

`redirect`

- Instruccion de navegacion.
- Si viene no-nulo (URL string), el cliente debe navegar segun el payload.

`abort`

- Instruccion de detener flujo por error o estado invalido / acceso no autorizado.
- Emitido por `Screen::abort($statusCode, $message)` con la forma: `{"abort": {"status_code": 403, "message": "..."}}`.
- Emitido por respuestas de acceso denegado (`checkAccess`) con la forma: `{"abort": {"code": 403, "message": "..."}}`.
- El cliente debe soportar tanto `status_code` como `code` y tratarlo como evento de error de negocio/UI.

`change_theme`

- Instrucción emitida por `Screen::changeTheme(string $theme)`.
- Payload: string con el nombre del tema (ej. `"dark"`, `"light"`).
- El cliente debe cambiar reactivamente el tema visual de la aplicación.

`change_language`

- Instrucción emitida por `Screen::changeLanguage(string $language)`.
- Payload: string con el código de idioma (ej. `"es"`, `"en"`).
- El cliente debe cambiar reactivamente el idioma/locale en el frontend.

`action`

- Meta accion de servidor; usar para instrumentacion si aplica (ej. `{"action": "close_modal"}`).

`modal`

- Contrato de modal (contenido/estado modal).

`update_modal`

- Actualizacion de modal existente emitida por `Screen::updateModal(array $content)`.

`clear_uploaders`

- Lista/instruccion para limpiar uploaders en cliente.

`set_uploader_existing_file`

- Instruccion para setear archivo existente en uploader.

Nota: la forma exacta interna de `modal`, `update_modal`, `clear_uploaders` y `set_uploader_existing_file` depende del backend concreto, pero su presencia es parte del contrato.

`agent_context` (Optional, Headless Mode)

- Metadata describing what the screen does, what inputs it expects, and what outputs it may produce.
- Only present if `Screen::getAgentContext()` returns non-empty array.
- Intended for AI agents, headless clients, and multi-client architectures.
- Example:
  ```json
  "agent_context": {
    "purpose": "User authentication with email/password",
    "inputs": ["email", "password"],
    "outputs": ["redirect", "toast", "abort"],
    "constraints": "Email must be valid format. Password 8+ chars."
  }
  ```
- Web renderer ignores this key automatically.

## 5. Contrato de componentes

## 5.1 Identificacion minima

Un nodo se considera componente UI cuando cumple alguno de estos indicios:

- Tiene `type`, o
- Tiene `parent`

El helper de tests usa como forma fuerte para detectar componente inicial:

- `type` + `parent`

## 5.2 Campos comunes observados

- `type` (string): tipo de componente (`container`, `button`, `input`, etc.).
- `parent` (string|int): jerarquia.
- `name` (string): identificador funcional para lookup en cliente/tests.
- `action` (string): accion asociada (frecuente en botones/inputs).
- `style` (string): estilo visual (opcional).
- `_timeout` (mixed): usado en componentes modales con timeout.
- `actions` (array): listado de acciones en ciertos componentes (por ejemplo cards).

Regla recomendada:

- El cliente debe ser tolerante a campos nuevos/desconocidos.

## 5.3 Componente `image`

Representa un elemento multimedia visual. Puede renderizar una URL estática directa o consultar datos dinámicos/binarios mediante el canal `GET /api/ui-data`.

Campos soportados:

- `type` (string): `"image"`.
- `parent` (string|int): ID del contenedor padre.
- `name` (string): Identificador funcional o id del componente.
- `src` (string, opcional): URL o URI base de la imagen.
- `action` (string, opcional): Nombre de la acción de consulta (invoca `Screen::get<Action>()` vía `GET /api/ui-data`).
- `parameters` (object, opcional): Parámetros enviados en la consulta HTTP GET.
- `alt` (string, opcional): Texto alternativo para accesibilidad e indexación.
- `caption` (string, opcional): Leyenda o pie de imagen.
- `width` (string, opcional): Ancho en formato CSS (ej. `"400px"`, `"100%"`).
- `height` (string, opcional): Alto en formato CSS (ej. `"300px"`, `"auto"`).
- `object_fit` (string, opcional): Modo de ajuste CSS (`contain`, `cover`, `fill`, `none`, `scale-down`).
- `border_radius` (string, opcional): Radio del borde (ej. `"8px"`, `"9999px"`).
- `border` (string, opcional): Estilo de borde CSS (ej. `"1px solid #e2e8f0"`).
- `shadow` (string, opcional): Sombra CSS (ej. `"0 4px 6px -1px rgba(0,0,0,0.1)"`).
- `lazy` (bool, opcional): Si activa carga diferida nativa (`loading="lazy"`).
- `auto_fetch` (bool, opcional): Si es `true` y tiene `action`, el frontend ejecuta automáticamente `fetchData()` al montarse.
- `fallback_src` (string, opcional): Imagen o SVG de respaldo en caso de error de red o timeout.

## 5.4 Componente `item_list`

Representa una lista vertical interactiva de elementos con soporte para selección (`none`, `single`, `multiple`), búsqueda local o remota (`GET /api/ui-data` con indicador de carga) y operaciones CRUD (inline o delegadas a modales).

Campos soportados:

- `type` (string): `"item_list"` (alias soportado: `"itemlist"`).
- `parent` (string|int): ID del contenedor padre.
- `name` (string): Identificador funcional y nombre del campo para recolección automática en formularios (`name` en selección simple, `name[]` en selección múltiple).
- `label` (string, opcional): Título del componente.
- `items` (array, opcional): Lista de objetos de ítem `{ id, label, subtitle?, badge?, badge_style?, icon?, avatar?, disabled?, actions?, meta? }`.
- `selection_mode` (string, opcional): `"none"` | `"single"` | `"multiple"` (default: `"none"`).
- `selected_items` (array, opcional): Lista de IDs seleccionados (`list<int|string>`).
- `keep_selected_visible` (bool, opcional): Si es `true`, mantiene visibles y fijados los ítems seleccionados aunque una búsqueda posterior en `/api/ui-data` no los devuelva.
- `show_select_all` (bool, opcional): Muestra el control de seleccionar/deseleccionar todos en modo `"multiple"`.
- `searchable` (bool, opcional): Habilita la barra de búsqueda integrada.
- `search_placeholder` (string, opcional): Placeholder del campo de búsqueda.
- `search_term` (string, opcional): Término de búsqueda inicial o actual.
- `min_search_chars` (int, opcional): Mínimo de caracteres antes de disparar consulta remota (default: `0`).
- `debounce` (int, opcional): Tiempo de espera en ms al escribir en el buscador (default: `250`).
- `action` (string, opcional): Nombre de la acción de consulta (invoca `Screen::get<Action>()` vía `GET /api/ui-data` pasando `{ search, ...parameters }`).
- `parameters` (object, opcional): Parámetros adicionales enviados en la consulta HTTP GET.
- `auto_fetch` (bool, opcional): Si es `true` y tiene `action`, consulta `GET /api/ui-data` al montarse si `items` está vacío.
- `reload` (bool, opcional): Si se envía `true` en un delta, fuerza al frontend a re-ejecutar `fetchData()` contra `GET /api/ui-data`.
- `creatable`, `editable`, `deletable` (bool, opcional): Habilitan las operaciones CRUD en la lista.
- `inline_create`, `inline_edit` (bool, opcional): Controlan si el alta y la edición se realizan mediante inputs inline dentro del componente (`true`) o disparan directamente el evento al backend para abrir un modal (`false`).
- `on_select`, `on_create`, `on_update`, `on_edit`, `on_delete`, `on_bulk_delete`, `on_search` (string, opcional): Nombres de las acciones (`snake_case`) invocadas vía `POST /api/ui-event`.

## 6. Snapshot inicial vs delta

USIM puede responder de dos formas:

- `initial`: snapshot completo de pantalla.
- `delta`: solo cambios respecto al estado previo.

## 6.1 Reglas de merge de delta

Regla observada en `UiMemoryRenderer::mergeComponent`:

- Merge superficial por campo (replace por key).
- Si llega `{ "11": { "label": "Nuevo" } }`, solo se actualiza `label` en componente `11`.
- **Garantía del framework (`Screen::buildDiffResponse`):** Todo delta generado por el backend siempre incluye el atributo `'type'` correspondiente al componente modificado (ej. `{ "11": { "type": "label", "text": "Nuevo" } }`). Esto permite al cliente conocer con precisión la fábrica y semántica del componente al recibir una actualización incremental.

## 6.2 Resolucion de key en deltas

Un delta viene siempre con la misma key JSON numérica del componente.

Cliente robusto:

1. Mantener `jsonKey -> component`.
2. Al recibir key numérica en delta, resolver directamente por `jsonKey`.

## 6.3 Delta de componente desconocido

Si llega delta para componente no conocido:

- Si payload "parece componente" (`type` o `parent`), agregarlo.
- Si no parece componente, registrar issue de contrato y considerar resync.

## 6.4 Respuesta solo meta (sin componentes)

Hay flujos donde `POST /api/ui-event` devuelve solo meta-keys.

Comportamiento recomendado:

- Aplicar meta-keys.
- Mantener snapshot actual.
- Si el cliente necesita consistencia fuerte, hacer `GET /api/ui{route}` para resincronizar.

## 7. Eventos soportados (semantica)

`click`

- Se dispara desde componente con `action` configurada.
- Requiere `component_id` valido.

`input`

- Evento de tipeo/cambio incremental.
- En tests se envia `storage.usim` serializado en body key `storage`.

`change`

- Cambio confirmado de valor/seleccion.

`action`

- Disparo explicito de accion, aun sin click.

`timeout`

- Evento asociado a modales/dialogos con timeout.
- `component_id` corresponde al service/caller id del modal.

## 8. Helpers de contrato utiles (para validar cliente)

Funciones de `UiPayloadHelpers.php` representan asunciones de contrato:

- `uiPayloadContainsAction`: buscar `action` en payload anidado.
- `uiPayloadContainsText`: buscar texto en payload.
- `uiPayloadContainsStyle`: buscar estilos.
- `hasModalComponents`: detectar componentes con `parent == "modal"`.
- `firstTimeoutModalComponent`: detectar primer componente modal con `_timeout`.
- `modalPayloadHasNamedComponent`: buscar nombre dentro de modal.
- `cardHasAction`: buscar accion en `actions[]` de card.
- `menuItemsContainLabel`: buscar label en items de menu.

Un cliente alternativo puede usar la misma logica para tests de compatibilidad.

## 9. Ejemplo completo de ciclo (multiplataforma)

## 9.1 Cargar pantalla

```http
GET /api/ui/auth/login?reset=true
Cookie: <client-id-cookie>
```

Guardar:

- cookie de client id
- `storage.usim` si existe
- snapshot de componentes

## 9.2 Renderizar

- Buscar componentes por `type` y `name`.
- Construir arbol usando `parent`.

## 9.3 Usuario toca boton login

Enviar:

```json
{
  "component_id": 101,
  "event": "click",
  "action": "submit_login",
  "parameters": {
    "login_email": "admin@example.com",
    "login_password": "password"
  },
  "usim": "<serialized-storage-json-string>"
}
```

Procesar respuesta:

1. actualizar `storage.usim` si viene nuevo
2. aplicar `toast`/`redirect`/`abort`/`modal`
3. aplicar deltas de componentes si existen
4. rerender incremental

## 10. Recomendaciones de implementacion para clientes multiplataforma

## 10.1 Capa de red

- Usar un cliente HTTP con manejo persistente de cookies/sesion.
- Guardar y reenviar `X-USIM-Storage` y/o fallback body.
- Manejar retries de red sin perder estado local.

## 10.2 Motor de estado UI

Mantener estructuras:

- `componentsByKey: MutableMap<String, JsonObject>`
- `metaState` para `toast`, `redirect`, `modal`, etc.
- `rawUsim: String?`
- `parsedStorage: MutableMap<String, Any?>` (opcional, derivado de `rawUsim`)

Algoritmo:

1. `initial`: reset maps + cargar todos los componentes.
2. `delta`: merge por key/resolved id.
3. aplicar meta-keys en cada respuesta.
4. exponer stream de cambios para render.

## 10.3 Render

- Resolver factory por `type`.
- Si `name` existe, usarlo como id estable de automatizacion/testing.
- Soportar campos opcionales y desconocidos sin crashear.

## 10.4 Acciones

- Botones: usar `action` del componente.
- Inputs: enviar `input` o `change` segun UX.
- Modales con `_timeout`: programar timer y enviar `timeout`.

## 10.5 Compatibilidad futura

- No hardcodear conjunto cerrado de keys de componente.
- Ignorar meta-keys desconocidas con logging.
- Versionar internamente parser/renderer del cliente.

## 11. Errores y resiliencia

- Si `POST /api/ui-event` responde no-200: mostrar error y conservar ultimo snapshot valido.
- Si llega payload invalido: registrar diagnostico y forzar reload de pantalla (`GET /api/ui...`).
- Si no se encuentra `component_id` al disparar evento: recargar snapshot antes de reintentar.

## 12. Checklist de compatibilidad para un cliente USIM

- Puede cargar una screen con `GET /api/ui{route}`.
- Persiste cookie de client id entre requests.
- Persiste y reenvia `storage.usim` serializado.
- Puede parsear `storage.usim` para leer `store_*` no sensibles.
- Trata keys `*_crypt` como valores opacos.
- Envia eventos con `component_id`, `event`, `action`, `parameters`.
- Distingue componentes vs meta-keys reservadas.
- Aplica merge incremental de deltas.
- Interpreta `toast`, `redirect`, `abort`, `change_theme`, `change_language`, `modal`, `update_modal`.
- Tolera campos y keys nuevos sin fallar.
- Tiene estrategia de resync por snapshot completo.

## 12.1 Suite minima de conformance tests

Estos casos deben automatizarse en cualquier cliente para validar compatibilidad real con USIM.

Caso 1: bootstrap de pantalla

- Ejecutar `GET /api/ui{route}`.
- Verificar `200` y al menos un componente con `type`.
- Guardar cookie de client id y `storage.usim` (si existe).

Caso 2: evento click exitoso

- Elegir componente clickable con `action`.
- Enviar `POST /api/ui-event` con `component_id`, `event=click`, `action`, `parameters`.
- Verificar `200`.
- Verificar que el cliente procesa meta-keys si aparecen.

Caso 3: preservacion de estado serializado

- Disparar dos eventos consecutivos.
- Validar que en el segundo se reenvia el ultimo `storage.usim` conocido.
- Verificar que no se pierde continuidad de estado.

Caso 3.1: parse de storage serializado

- Si `storage.usim` contiene JSON valido, parsearlo y exponer valores `store_*` no sensibles.
- Verificar que valores con sufijo `_crypt` se preservan sin transformacion.

Caso 4: merge de delta parcial

- Inyectar o capturar respuesta con delta parcial de componente existente.
- Verificar merge superficial por campo sin borrar keys no presentes en delta.

Caso 5: respuesta solo meta

- Ejecutar evento que devuelva solo meta-keys.
- Verificar que no se rompe el snapshot local.
- Verificar aplicacion de `toast`/`redirect`/`abort`/`change_theme`/`change_language` si vienen.

Caso 6: resync por inconsistencia

- Simular `component_id` inexistente o payload invalido.
- Verificar fallback de resincronizacion via `GET /api/ui{route}`.

Caso 7: tolerancia a evolucion de contrato

- En respuesta con keys desconocidas (componente o meta), verificar:
  - no crash
  - logging diagnostico
  - continuidad de operacion

## 13. Resumen ejecutivo

Si implementas los puntos de este documento, cualquier cliente (mobile, iOS, SmartTV, desktop o web) deberia poder:

- renderizar pantallas USIM a partir de JSON,
- enviar eventos de interaccion al backend,
- mantener estado de sesion UI entre requests,
- aplicar diffs y contratos meta de forma compatible con el flujo actual de USIM.
