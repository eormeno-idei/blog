# USIM Client Template - iOS (Swift)

Template de referencia para implementar un cliente USIM en iOS nativo.

## 1. Capas sugeridas

- `Networking`: `URLSession`, cookies, headers.
- `Contract`: parseo payload USIM.
- `StateStore`: snapshot + merge de deltas.
- `UIRenderer`: mapping de `type` a componentes UIKit/SwiftUI.
- `EventDispatcher`: envio de eventos.

## 2. Modelos base

```swift
struct UiEventRequest: Codable {
    let component_id: Int
    let event: String
    let action: String
    let parameters: [String: CodableValue]
    let usim: String?
    let storage: String?
}

struct UiStateStore {
    var componentsByKey: [String: [String: CodableValue]]
    var keyByInternalId: [Int: String]
    var rawUsim: String?
}
```

Nota: `CodableValue` representa un wrapper para tipos JSON dinamicos.

## 3. Red y sesion

- Persistir cookies con `HTTPCookieStorage`.
- Enviar `X-USIM-Storage` cuando exista.
- Soportar fallback body (`usim`/`storage`) si backend lo requiere.

## 4. Reglas de merge

- snapshot inicial: reset completo.
- delta: merge superficial por key.
- key numérica en response: resolver directamente por `jsonKey`.

## 5. Render

- Construir arbol por `parent`.
- Usar `name` como id de automatizacion.
- Mantener tolerancia a keys desconocidas.

## 6. Manejo meta

- `toast`: banner/snackbar nativo.
- `redirect`: navegacion.
- `abort`: estado de error controlado (`status_code` / `code`).
- `change_theme`: ajuste reactivo de Appearance (light/dark mode).
- `change_language`: ajuste reactivo de locale/idioma.
- `modal` / `update_modal`: presentacion/actualizacion modal.
- `agent_context`: metadata para clientes headless o agentes.

## 7. Conformance tests

- mismos 7 casos definidos en `usim_json_contract.md`.
