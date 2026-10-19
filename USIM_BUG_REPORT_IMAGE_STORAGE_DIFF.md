# Bug Report: Inconsistencia en resolución de URLs de imágenes relativas con `storage()` durante eventos reactivos en USIM Framework

**Para:** Equipo de Arquitectura y Desarrollo de USIM Framework  
**Fecha:** 19 de octubre de 2026  
**Severidad:** Media / Alta (Afecta la carga de recursos estáticos en cualquier actualización reactiva que involucre imágenes)  
**Módulos afectados:** `Idei\Usim\Components\Image`, `Idei\Usim\Support\ScreenLifecycleOrchestrator`

---

## 1. Resumen Ejecutivo

Cuando un componente `Idei\Usim\Components\Image` se configura con una ruta de almacenamiento relativo mediante `->storage('path')`, el renderizado inicial (`GET /api/ui/...`) resuelve adecuadamente el nombre del archivo a su URL canónica y accesible (vía `Idei\Usim\Upload\UploadService::fileUrl(...)`).

Sin embargo, cuando una pantalla procesa una acción reactiva (`POST /api/ui-event`), el cálculo diferencial de cambios en `ScreenLifecycleOrchestrator::buildDiffResponse()` extrae los valores crudos directamente desde las propiedades marcadas como *sucias* (`$dirtyKeys`) del componente. Esto provoca que la URL absoluta resuelta por `Image::toJson()` sea **sobreescrita en el payload JSON de diff** por el nombre de archivo relativo crudo (ej. `"116ad8cd-...png"` en vez de `"https://dominio/files/uploads/images/116ad8cd-...png"`).

Como consecuencia, el frontend asigna esta ruta relativa al atributo `src` de la etiqueta `<img>`, provocando una solicitud HTTP errónea contra la raíz del dominio (`404 Not Found`) y rompiendo visualmente la imagen.

---

## 2. Comportamiento Esperado vs. Comportamiento Actual

### Comportamiento Esperado
Cualquier componente `Image` que utilice `->storage('uploads/images')` debe emitir siempre en su propiedad `src` (tanto en la carga inicial como en los diffs emitidos por eventos) la URL procesada y accesible:
```json
{
  "24815051": {
    "type": "image",
    "src": "https://dominio.test/files/uploads/images/116ad8cd-6001-4b3c-8323-cf5f76a99341.png"
  }
}
```

### Comportamiento Actual
Durante el cálculo de deltas tras un evento (`onDeletePost`, cambios dinámicos de imagen, etc.), el payload del diff sobrescribe `src` con el valor crudo no resuelto:
```json
{
  "24815051": {
    "type": "image",
    "src": "116ad8cd-6001-4b3c-8323-cf5f76a99341.png"
  }
}
```

El navegador intenta resolver la imagen como `https://dominio.test/116ad8cd-6001-4b3c-8323-cf5f76a99341.png`, fallando con `404 Not Found`.

---

## 3. Análisis de Causa Raíz (Root Cause Analysis)

### A. Transformación en `Image::toJson()`
En `vendor/idei/usim/src/Components/Image.php` (Líneas 190–222):
```php
public function toJson(?int $order = null): array
{
    $json = parent::toJson($order);
    $component = reset($json);

    if (! is_array($component)) {
        return $json;
    }

    $src = $this->config['src'] ?? null;
    if (is_string($src) && trim($src) !== '') {
        $component['src'] = $this->resolveStoredSource($src); // <--- Transforma a URL absoluta
    } elseif ($this->shouldGeneratePlaceholder()) {
        $component['src'] = $this->generatePlaceholderSource();
    }

    ...
    $json[$this->id] = $component;

    return $json;
}
```
Aquí la resolución funciona correctamente: `$newUI` contiene el array con el `src` procesado hacia `/files/uploads/...`.

### B. Sobreescritura en `ScreenLifecycleOrchestrator::buildDiffResponse()`
En `vendor/idei/usim/src/Support/ScreenLifecycleOrchestrator.php` (Líneas 458–470):
```php
$container = $screen->getContainer();
if (! $reload && $container !== null) {
    foreach ($this->collectDirtyComponentChanges($container) as $componentId => $dirtyProps) {
        if (! isset($newUI[$componentId])) {
            continue;
        }
        if (($newUI[$componentId]['parent'] ?? null) === null) {
            continue;
        }
        foreach ($dirtyProps as $propKey => $propValue) {
            $diff[$componentId][$propKey] = $propValue; // <--- ERROR
        }
    }
}
```

#### El conflicto:
1. Al instanciar o modificar una imagen con `->src('archivo.png')`, `UIComponent::setConfig()` registra internamente:
   ```php
   $this->dirtyKeys['src'] = 'archivo.png';
   ```
2. `collectDirtyComponentChanges()` devuelve el valor original crudo de `$dirtyKeys['src']` (`'archivo.png'`), el cual **nunca pasó por `resolveStoredSource()`**.
3. En la línea `$diff[$componentId][$propKey] = $propValue;`, el orquestador pisa el valor ya transformado que existía en `$newUI[$componentId]['src']` con el valor crudo de `$dirtyProps`.

---

## 4. Pasos para Reproducir (MRE)

1. Crear un Screen con una imagen almacenada en storage:
   ```php
   $container->add(
       UI::image('user_avatar')
           ->src('avatar_123.png')
           ->storage('uploads/images')
   );
   ```
2. Realizar un renderizado inicial (`GET /api/ui/...`): la imagen carga adecuadamente con `src = "http://.../files/uploads/images/avatar_123.png"`.
3. Disparar cualquier acción POST (`POST /api/ui-event`) que cause que el componente o su contenedor sea re-evaluado o mutado (por ejemplo, `$this->user_avatar->src('avatar_456.png');` o una reconstrucción de colección).
4. **Verificar el JSON de respuesta:** el diff retornado contendrá `"src": "avatar_456.png"` en vez de la URL completa.

---

## 5. Solución Sugerida

Existen dos enfoques complementarios para corregir esta anomalía en el framework:

### Opción 1: Extraer el valor del componente ya transformado en `$newUI` (Recomendada)
En `ScreenLifecycleOrchestrator::buildDiffResponse()`, al transferir los cambios de componentes sucios al `$diff`, se debe priorizar el valor ya transformado y serializado por `toJson()` presente en `$newUI`, en lugar de inyectar a ciegas el valor crudo de `$propValue`:

```php
// vendor/idei/usim/src/Support/ScreenLifecycleOrchestrator.php
foreach ($dirtyProps as $propKey => $propValue) {
    // Si newUI ya transformó este atributo (como hace Image con src), usar el valor transformado
    if (array_key_exists($propKey, $newUI[$componentId])) {
        $diff[$componentId][$propKey] = $newUI[$componentId][$propKey];
    } else {
        $diff[$componentId][$propKey] = $propValue;
    }
}
```

### Opción 2: Resolver el valor en el hook de mutación de `Image`
Sobrescribir `setConfig` en `Image` o implementar un resolvedor de propiedades sucias para que `$this->dirtyKeys['src']` almacene el valor procesado cuando `$this->storagePath` esté configurado.

---

## 6. Conclusión

Este issue genera fallos silenciosos y roturas de UI en cualquier pantalla con componentes de imagen dinámicos que dependan del método `->storage()`. Aplicar la corrección en `ScreenLifecycleOrchestrator` garantizará que las transformaciones implementadas en `toJson()` por componentes especializados se respeten estrictamente en el ciclo de vida reactivo.
