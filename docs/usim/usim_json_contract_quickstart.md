# USIM JSON Contract Quick Start

Guia corta para implementar un cliente USIM en cualquier plataforma: Android, iOS, SmartTV, desktop o web.

Documento largo de referencia: `packages/idei/usim/docs/usim_json_contract.md`

Propuesta de evolucion de contrato: `packages/idei/usim/docs/usim_contract_v1_proposal.md`

Plantillas de implementacion por plataforma:

- `packages/idei/usim/docs/client_templates/android_kotlin_template.md`
- `packages/idei/usim/docs/client_templates/ios_swift_template.md`
- `packages/idei/usim/docs/client_templates/flutter_template.md`

## 1. Flujo minimo viable

1. Cargar screen con `GET /api/ui{screen_route_path}`.
2. Guardar cookie de client id y `storage.usim` si viene.
3. Renderizar componentes por `type`/`parent`/`name`.
4. Enviar eventos a `POST /api/ui-event` con `component_id`, `event`, `action`, `parameters`.
5. Reenviar `storage.usim` (JSON serializado) en cada evento.
6. Aplicar meta-keys y deltas de componentes.
7. Re-renderizar incrementalmente.

## 2. Request/Response base

Carga inicial:

```http
GET /api/ui/auth/login?reset=true
Cookie: <client-id-cookie>
X-UI-Client-Id: <tab-client-id>
```

Evento:

```http
POST /api/ui-event
Content-Type: application/json
Cookie: <client-id-cookie>
X-UI-Client-Id: <tab-client-id>
X-USIM-Storage: <serialized-storage-json-string>
```

```json
{
  "component_id": 101,
  "event": "click",
  "action": "submit_login",
  "parameters": {
    "login_email": "admin@example.com",
    "login_password": "password"
  }
}
```

Consulta de datos (Data Fetching):

```http
GET /api/ui-data?component_id=101&action=avatar&parameters[size]=thumb
Cookie: <client-id-cookie>
X-UI-Client-Id: <tab-client-id>
```

## 3. Keys reservadas (no son componentes)

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
- `agent_context`

Interpretacion minima:

- `storage.usim`: JSON serializado con variables `store_*` (persistir/reenviar).
- `*_crypt`: valores protegidos por backend; tratarlos como opacos en cliente.
- `toast`: mensaje UX.
- `redirect`: navegacion.
- `abort`: error/flujo abortado o acceso denegado (`status_code` o `code`).
- `change_theme`: cambio de tema visual (`dark`, `light`).
- `change_language`: cambio de idioma activo (`es`, `en`).
- `modal` y `update_modal`: estado y actualización de modales.
- `agent_context`: metadata semántica del Screen para clientes IA y headless.

## 4. Reglas de estado local

- Identificar componentes por su key JSON numérica.
- Delta = merge superficial por campo.
- Si respuesta no trae componentes (solo meta), conservar snapshot previo.
- Si hay inconsistencia, resincronizar con `GET /api/ui{route}`.

## 5. Checklist rapido de compatibilidad

- Persiste cookie de client id.
- Persiste y reenvia `storage.usim` serializado.
- Puede parsear `storage.usim` para leer `store_*` no sensibles cuando aplique.
- No intenta desencriptar valores `*_crypt` en cliente.
- Distingue componentes de meta-keys.
- Soporta `click`, `input`, `change`, `action`, `timeout`.
- Aplica merge incremental de deltas.
- Interpreta meta-keys: `toast`, `redirect`, `abort`, `change_theme`, `change_language`, `modal`, `update_modal`.
- Tolera campos y keys nuevas sin romperse.
- Implementa resync por snapshot completo.

## 6. Recomendaciones de arquitectura

- Separar red, parser de contrato y renderer.
- Mantener parser tolerante a campos desconocidos.
- Loggear payloads invalidos para debugging.
- No acoplarse a un conjunto cerrado de tipos de componente.
