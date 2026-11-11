# USIM Contract v1 Proposal

Este documento propone una evolucion del contrato JSON actual de USIM para hacerlo mas tipado, versionable y facil de implementar en clientes multiplataforma (mobile, iOS, SmartTV, desktop, web).

Objetivo principal:

- mantener compatibilidad con el contrato actual,
- reducir ambiguedades,
- facilitar validacion automatica,
- habilitar certificacion de clientes.

## 1. Principios de diseno

- Backward compatible por defecto.
- Envelope explicito para evitar inferencias.
- Tipado de meta-contratos.
- Evolucion controlada por version semantica.
- Cliente tolerante a campos desconocidos.

## 2. Envelope propuesto (obligatorio)

Todas las respuestas de `GET /api/ui...` y `POST /api/ui-event` deben incluir:

```json
{
  "contract_version": "1.0",
  "payload_mode": "initial",
  "request_id": "f64a3c2f-5c58-47f2-9ced-1d8b2f2d53d4",
  "timestamp": "2026-03-07T13:15:22Z",
  "components": {
    "10": {
      "type": "container",
      "parent": "root",
      "name": "main_container"
    }
  },
  "meta": {
    "storage": {
      "usim": "{\"store_theme\":\"light\",\"store_page\":1}"
    },
    "toast": null,
    "redirect": null,
    "abort": null,
    "change_theme": null,
    "change_language": null,
    "modal": null,
    "update_modal": null,
    "clear_uploaders": null,
    "set_uploader_existing_file": null
  },
  "errors": []
}
```

Campos:

- `contract_version` (string): version mayor/menor del contrato (`1.0`, `1.1`, etc.).
- `payload_mode` (string enum): `initial` o `delta`.
- `request_id` (string): correlacion para logs cliente/servidor.
- `timestamp` (ISO-8601 string): fecha de emision del payload.
- `components` (object): nodos UI por key.
- `meta` (object): contratos auxiliares tipados.
- `errors` (array): errores no fatales de contrato/parsing (opcional, recomendado).

## 3. Reglas de compatibilidad

- Si `contract_version` mayor es desconocida, cliente entra en modo compatible y aplica solo campos conocidos.
- Si `payload_mode` falta (legacy), cliente debe inferir como hoy:
  - primera carga => `initial`
  - respuestas de evento => `delta` (salvo evidencia de snapshot completo).
- Campos adicionales en `components` o `meta` no deben romper el cliente.

## 4. Modelo de componentes v1

Base minima por componente:

```json
{
  "type": "input",
  "parent": 10,
  "name": "login_email"
}
```

Campos recomendados:

- `type` (string, requerido)
- `parent` (string|int, requerido)
- `name` (string, recomendado)
- `action` (string, opcional)
- `props` (object, opcional): propiedades visuales/funcionales tipadas por tipo.
- `style` (string|object, opcional)

Recomendacion v1.1:

- mover propiedades de tipo especifico a `props` para minimizar colisiones.

## 5. Meta-contratos tipados

## 5.1 storage

```json
{
  "storage": {
    "usim": "{\"store_theme\":\"light\",\"store_token_crypt\":\"<encrypted-value>\"}"
  }
}
```

- `usim` transporta un JSON serializado con variables `store_*`.
- Variables `store_*` sin sufijo `_crypt` son legibles por cliente.
- Variables con sufijo `_crypt` deben tratarse como valores opacos en cliente.
- Transporte oficial en request: header `X-USIM-Storage`.

## 5.2 toast

```json
{
  "toast": {
    "type": "success",
    "message": "Operacion completada",
    "duration_ms": 2500
  }
}
```

- `type` enum recomendado: `success|info|warning|error`.

## 5.3 redirect

```json
{
  "redirect": {
    "target": "/dashboard",
    "replace": true
  }
}
```

## 5.4 abort

```json
{
  "abort": {
    "code": "VALIDATION_ERROR",
    "message": "Datos invalidos",
    "retryable": false
  }
}
```

Catalogo inicial de `code` recomendado:

- `VALIDATION_ERROR`
- `UNAUTHORIZED`
- `FORBIDDEN`
- `NOT_FOUND`
- `CONFLICT`
- `RATE_LIMITED`
- `INTERNAL_ERROR`

## 5.5 modal / update_modal

```json
{
  "modal": {
    "id": "session-timeout",
    "components": {
      "201": {
        "type": "label",
        "parent": "modal",
        "name": "timeout_text",
        "text": "Tu sesion expirara pronto"
      }
    }
  }
}
```

```json
{
  "update_modal": {
    "id": "session-timeout",
    "components": {
      "201": {
        "text": "Tu sesion expira en 10 segundos"
      }
    }
  }
}
```

## 5.6 clear_uploaders

```json
{
  "clear_uploaders": {
    "names": ["profile_photo", "document_front"]
  }
}
```

## 5.7 set_uploader_existing_file

```json
{
  "set_uploader_existing_file": {
    "name": "profile_photo",
    "file": {
      "id": "file_123",
      "filename": "avatar.jpg",
      "size": 123456,
      "mime": "image/jpeg",
      "url": "/storage/avatar.jpg"
    }
  }
}
```

## 5.8 change_theme

```json
{
  "change_theme": "dark"
}
```

- Solicita al cliente conmutar el tema visual activo (ej. `light`, `dark`, `system`).

## 5.9 change_language

```json
{
  "change_language": "es"
}
```

- Solicita al cliente conmutar el idioma/locale activo en la interfaz (ej. `es`, `en`).

## 6. Requests de evento v1

Body recomendado:

```json
{
  "contract_version": "1.0",
  "component_id": 101,
  "event": "click",
  "action": "submit_login",
  "parameters": {
    "login_email": "admin@example.com",
    "login_password": "password"
  }
}
```

Headers recomendados:

- `X-USIM-Storage: <serialized-storage-json-string>`
- `X-Request-Id: <uuid>`

Eventos soportados:

- `click`
- `input`
- `change`
- `action`
- `timeout`

## 7. Reglas de merge (normativas)

Para `payload_mode=delta`:

- merge superficial por campo de componente.
- si componente no existe y payload parece componente (`type` o `parent`), agregarlo.
- si key numerica no existe como key, registrar warning y activar estrategia de resync.
- si no se puede resolver, registrar warning y activar estrategia de resync.

No permitido en v1:

- borrar implicitamente componentes por ausencia en delta.

Extensible en v1.1:

- operaciones explicitas: `remove_components`, `replace_components`.

## 8. Errores y observabilidad

Cada respuesta puede incluir:

```json
{
  "errors": [
    {
      "level": "warning",
      "code": "UNKNOWN_COMPONENT_KEY",
      "detail": "delta key 999 no resolvible"
    }
  ]
}
```

Buenas practicas:

- correlacionar con `request_id`.
- log cliente + servidor con mismo id.
- no abortar render por warnings no fatales.

## 9. JSON Schemas recomendados

Publicar esquemas versionados en repositorio:

- `schemas/usim/v1/envelope.schema.json`
- `schemas/usim/v1/component-base.schema.json`
- `schemas/usim/v1/meta.schema.json`
- `schemas/usim/v1/event-request.schema.json`

Pipeline recomendado:

- validar payloads de ejemplo en CI.
- validar clientes contra fixtures oficiales.

## 10. Estrategia de migracion desde contrato actual

Fase 0 (actual):

- contrato legacy sin envelope fijo.

Fase 1 (dual-mode, recomendada):

- backend responde formato actual + envelope v1 opcional.
- clientes nuevos leen v1; clientes legacy siguen funcionando.

Fase 2 (estandarizacion):

- documentar v1 como contrato oficial.
- mantener compatibilidad legacy por periodo definido.

Fase 3 (deprecacion controlada):

- retirar fallback body de storage (`usim`/`storage`) cuando todos los clientes migren a `X-USIM-Storage`.

## 11. Criterios de aceptacion para "USIM Client Certified"

Un cliente se considera compatible v1 si pasa:

1. bootstrap `initial`.
2. eventos `click/input/change/action/timeout`.
3. preservacion de `storage.usim` serializado y parse opcional de `store_*` no sensibles.
4. merge correcto de deltas.
5. manejo de `toast/redirect/abort/modal/update_modal`.
6. tolerancia a campos desconocidos.
7. resync automatico ante inconsistencia.

## 12. Decision record recomendado

Para institucionalizar el cambio, crear un ADR con:

- contexto del contrato actual,
- riesgos de no tipar/versionar,
- decision de envelope v1,
- plan de rollout y deprecacion.

---

Resumen:

La propuesta `USIM Contract v1` conserva la filosofia server-driven de USIM, pero agrega estructura formal para escalar a un ecosistema real de clientes heterogeneos con menor riesgo de divergencias.
