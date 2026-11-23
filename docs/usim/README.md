# 📚 USIM Package Documentation

Esta carpeta se distribuye junto con `idei/usim` (`vendor/idei/usim/docs/`) y también puedes copiarla directamente a tu proyecto Laravel ejecutando:

```bash
php artisan vendor:publish --tag=usim-docs --force
```

Eso publicará todas estas guías en `docs/usim/` para consulta local del equipo y de asistentes de IA en tu IDE.

---

## Guías principales del Framework

| Documento | Descripción |
|---|---|
| **[DECLARATIVE_LAYOUTS_AND_I18N.md](DECLARATIVE_LAYOUTS_AND_I18N.md)** | Referencia completa de `.layout.yaml`, CSS Grid, slots, proyección declarativa `slots:`, escala `Spacing`, `Size`, `Screen::layoutContainer()`, `startingUI()` opcional e i18n en dos niveles (`t:` / `.clave` + `i18n: auto`) |
| **[LAYOUTS_COMPOSITION_GUIDE.md](LAYOUTS_COMPOSITION_GUIDE.md)** | Catálogo práctico con ejemplos de todas las posibilidades de layouts: grids 12-col y `[1fr, auto]`, `at:`/`span:`, sub-grids *inline*, `include:`/`layout_file:`, proyección de `slots:` hacia paneles/StatusBar, `embedToSlot()` y `AbstractLayout` |
| **[WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md](WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md)** | Referencia técnica y contrato completo para IA/desarrolladores: motor unificado de layouts (`AbstractLayout` + `.layout.yaml`), `WorkbenchLayout`, `Split` declarativo (`split_size_target`), `NavTree`, Activity Bar (`52px`), proyección de `slots:`, `StatusBar` contextual, `guest_layout` y transiciones SPA |
| **[ITEM_LIST_SERVICE_WIRING.md](ITEM_LIST_SERVICE_WIRING.md)** | Cableado de `ItemList` mediante contratos de capacidades (`Listable`, `Creatable`, `Updatable`, `Deletable`, `BulkDeletable`), soporte YAML `service:` y despacho en ciclo de vida |
| **[RICH_TEXT_GUIDE.md](RICH_TEXT_GUIDE.md)** | Guía de usuario del editor WYSIWYG HTML `UI::richText()` (SunEditor v3 vendoreado con carga lazy, presets de toolbar, imágenes editables en Base64/upload, `.layout.yaml` y tema claro/oscuro) |
| **[ICONS_GUIDE.md](ICONS_GUIDE.md)** | Sistema agnóstico de íconos, biblioteca estándar de 80 SVGs (`--tag=usim-icons`), íconos propios (`public/images/icons/`), `UsimIconRegistry` y adaptación automática a temas y `ContainerAppearance` |
| **[USIM_EVENTS_SYSTEM.md](USIM_EVENTS_SYSTEM.md)** | Arquitectura completa de eventos UI y de dominio, lifecycle handlers `on*()`, diffing e integración |
| **[SCREEN_TESTING_GUIDE.md](SCREEN_TESTING_GUIDE.md)** | Guía práctica para testear Screens con `ScreenTestHarness`, `uiScenario()` y Pest |
| **[package-update-and-consumer-upgrade-guide.md](package-update-and-consumer-upgrade-guide.md)** | Cómo actualizar `idei/usim` en aplicaciones consumidoras de forma segura |

## Guidelines / Casos de Uso Prácticos

| Documento | Descripción |
|---|---|
| **[guidelines/ITEM_LIST_GUIDELINES.md](guidelines/ITEM_LIST_GUIDELINES.md)** | Casos de uso prácticos para `ItemList`: solo lectura con `Listable`, filtrado por contexto y claves foráneas (`parameters`), refresco dinámico y sincronización reactiva (`onServiceMutated`) |

## Especificación del Protocolo y Clientes Multiplataforma

| Documento | Descripción |
|---|---|
| **[usim_json_contract.md](usim_json_contract.md)** | Contrato JSON completo entre backend USIM y renderers cliente |
| **[usim_json_contract_quickstart.md](usim_json_contract_quickstart.md)** | Guía rápida para implementar renderers cliente |
| **[client_templates/](client_templates/)** | Plantillas base para clientes Android (Kotlin), iOS (Swift) y Flutter |
| **[usim_contract_v1_proposal.md](usim_contract_v1_proposal.md)** | Propuesta evolutiva del contrato tipado v1 |

## Contexto y Prompts para Asistentes IA

| Documento | Descripción |
|---|---|
| **[usim_context_prompt.md](usim_context_prompt.md)** | Prompt base para dar contexto de USIM a un chat/agente IA |
| **[WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md](WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md)** | Contrato técnico detallado para IA sobre Layouts Unificados, `WorkbenchLayout`, `NavTree`, `Split` y `StatusBar` |
| **[RICH_TEXT_AI_REFERENCE.md](RICH_TEXT_AI_REFERENCE.md)** | Contrato técnico y guardrails para IA sobre `UI::richText()` (`type: 'rich_text'`), SunEditor v3 lazy-load, sanitización HTML y separación estricta con `UI::textarea()` |
| **[component_prompt.md](component_prompt.md)** | Plantilla para crear nuevos componentes USIM end-to-end |
| **[tests_prompt.md](tests_prompt.md)** | Plantilla para generar tests de Screens |
