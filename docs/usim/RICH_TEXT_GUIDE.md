# Guía del Componente `RichText` (`UI::richText()`)

`UI::richText()` es el componente editor **WYSIWYG HTML** oficial de USIM, impulsado por **SunEditor v3** (licencia MIT, sin dependencias externas). Está diseñado para editar contenido enriquecido con formato visual inmediato, tablas, enlaces e **imágenes editables** (redimensionar, rotar, espejar, alinear y agregar pie de foto).

> **¿Cuándo usar `UI::richText()` vs `UI::textarea()`?**
> - Usa **`UI::textarea()`** para texto plano multilínea (notas, comentarios, observaciones) con validación estricta de longitud (`minLength` / `maxLength`).
> - Usa **`UI::richText()`** cuando el usuario necesite formato visual enriquecido (títulos, negritas, colores, listas, tablas, enlaces o imágenes) almacenado como HTML.

---

## 1. Publicación de Assets

SunEditor v3 viene empaquetado localmente dentro de `idei/usim` y se carga de forma **lazy** (bajo demanda) únicamente en las pantallas que renderizan al menos un componente `rich_text`. Para publicarlo en tu app Laravel ejecutá:

```bash
php artisan vendor:publish --tag=usim-assets --force
```

Esto publica el componente y los assets de SunEditor en:

```text
public/vendor/idei/usim/js/components/rich-text/index.js
public/vendor/idei/usim/css/components/rich-text/index.css
public/vendor/idei/usim/lib/suneditor/
```

---

## 2. Uso Básico en PHP

```php
use Idei\Usim\Components\Container;
use Idei\Usim\Components\RichText;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;

class EditArticleScreen extends Screen
{
    protected RichText $article_body;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->article_body = UI::richText('article_body')
            ->label('Contenido del artículo')
            ->placeholder('Escribe o pega contenido enriquecido aquí…')
            ->standardToolbar()
            ->allowImages(true)
            ->width(Size::full())
            ->height(Size::px(320))
            ->charCounter(true)
            ->wordCounter(true)
            ->helpText('Puedes pegar o subir imágenes y ajustar su tamaño, rotación y alineación.')
            ->onChange('on_article_saved');

        $container->add($this->article_body);
    }

    public function onArticleSaved(string $value): void
    {
        // $value contiene el HTML generado por el editor
        $plainLength = mb_strlen(trim(strip_tags($value)));
        $this->toast("Guardado ({$plainLength} caracteres visibles)");
    }
}
```

### Lectura dentro de un formulario (`Button`)

`RichText` sincroniza su HTML en un `<textarea>` interno con el atributo `name` del componente, por lo que cualquier `UI::button()->action('save_form')` dentro del mismo contenedor recolecta automáticamente el campo bajo su `name`:

```php
public function onSaveForm(string $article_body): void
{
    // También disponible mediante $this->article_body->getValue()
}
```

---

## 3. Presets de Barra de Herramientas (`toolbar`)

El editor incluye tres presets preconfigurados y soporte para listas personalizadas de SunEditor v3:

| Método / Valor | Herramientas incluidas | Caso de uso recomendado |
|---|---|---|
| `->basicToolbar()` / `'basic'` | `undo`, `redo`, `bold`, `italic`, `underline`, `strike`, listas, `link`, `removeFormat` | Resúmenes cortos, descripciones breves |
| `->standardToolbar()` / `'standard'` *(por defecto)* | Todo lo de `basic` + `blockStyle`, `fontSize`, `fontColor`, `backgroundColor`, `align`, sangría, `blockquote`, `hr`, `table`, `image`, `fullScreen`, `codeView` | Artículos, noticias, documentación, emails |
| `->fullToolbar()` / `'full'` | Todo lo de `standard` + `font`, `subscript`, `superscript`, `lineHeight`, `codeBlock`, `video`, `showBlocks`, `preview`, `print` | CMS avanzado o edición documental completa |
| `->toolbar([...])` | Array `buttonList` nativo de SunEditor v3 | Control exacto de botones y separadores (`'|'`) |

Ejemplo con toolbar personalizada:

```php
UI::richText('custom_editor')
    ->toolbar([
        ['undo', 'redo'],
        '|',
        ['bold', 'italic', 'underline'],
        '|',
        ['list_bulleted', 'list_numbered', 'link', 'image'],
    ]);
```

---

## 4. Imágenes Editables (Base64 vs Upload a Servidor)

- **Por defecto (`allowImages(true)` sin `imageUploadUrl`):**
  - El usuario puede **pegar desde el portapapeles**, **arrastrar** o **seleccionar archivos** de imagen.
  - SunEditor convierte automáticamente las imágenes locales a **data URIs Base64** (`data:image/...;base64,...`) embebidas en el HTML.
  - Al hacer clic sobre cualquier imagen insertada aparecen controles interactivos para **redimensionar** (handles y presets `100% / 75% / 50% / auto`), **rotar** izquierda/derecha, **espejar** horizontal/vertical, **alinear**, agregar **pie de foto (caption)**, editar enlace/alt o eliminar.
- **Deshabilitar imágenes:**
  - `->allowImages(false)` quita automáticamente el botón `image` de cualquier preset y excluye el plugin `image` de SunEditor.
- **Subida a servidor (opcional):**
  - Si preferís almacenar imágenes en disco/S3 en lugar de Base64, configurá `->imageUploadUrl('/api/tu-endpoint-upload')`. El endpoint debe responder con el formato esperado por SunEditor v3:
    ```json
    {
      "result": [
        { "url": "https://ejemplo.com/storage/img.png", "name": "img.png", "size": 12345 }
      ]
    }
    ```

> **Recomendaciones de almacenamiento y seguridad:**
> 1. **Base de datos:** si habilitás imágenes en Base64, utilizá columnas `MEDIUMTEXT` o `LONGTEXT` (una sola captura en Base64 puede superar los 64 KB de un `TEXT` estándar).
> 2. **Sanitización XSS:** aunque SunEditor filtra etiquetas peligrosas en cliente, siempre sanitizá el HTML en el backend (con una whitelist de etiquetas y atributos seguros) antes de persistirlo o renderizarlo en otros usuarios con `UI::newLabel()->html(...)`.

---

## 5. Uso Declarativo en `.layout.yaml`

`ContainerLayoutLoader` soporta `RichText` con:
- **Prefijo automático:** `rte_` (por ejemplo `rte_description` instancia `RichText` automáticamente sin requerir propiedad `protected`).
- **Alias `component:`:** `rich_text` o `richtext`.
- **Hidratación `i18n: auto`:** busca claves en `['inputs', 'fields']` para `label`, `placeholder`, `help_text` y `tooltip`.

```yaml
grid:
  cols: 12
  gap: md
  children:
    - rte_content:
        span: 12
        toolbar: standard
        allow_images: true
        height: 300px
        char_counter: true
        word_counter: true
```

---

## 6. Referencia Completa de Métodos PHP

| Método | Parámetro | Por defecto | Descripción |
|---|---|---|---|
| `label(string $label)` | `string` | `null` | Etiqueta superior del campo |
| `placeholder(string $text)` | `string` | `null` | Texto de ayuda cuando el editor está vacío |
| `value(?string $html)` | `?string` | `null` | Contenido HTML inicial o actualizado desde backend |
| `getValue()` | — | `''` | Devuelve el HTML actual sincronizado en backend |
| `toolbar(string\|array $toolbar)` | `'basic'\|'standard'\|'full'\|array` | `'standard'` | Preset o `buttonList` personalizado de SunEditor v3 |
| `basicToolbar()` / `standardToolbar()` / `fullToolbar()` | — | — | Atajos de preset de toolbar |
| `allowImages(bool $allow = true)` | `bool` | `true` | Habilita o deshabilita inserción y edición de imágenes |
| `imageUploadUrl(?string $url)` | `?string` | `null` | Endpoint opcional de upload (si es `null`, usa Base64) |
| `maxLength(?int $maxLength)` | `?int` | `null` | Límite de caracteres **visibles** (activa `charCounter`) |
| `charCounter(bool $enabled = true)` | `bool` | `false` | Muestra contador de caracteres visibles en la barra inferior |
| `wordCounter(bool $enabled = true)` | `bool` | `false` | Muestra contador de palabras en la barra inferior |
| `statusbar(bool $enabled = true)` | `bool` | `true` | Muestra u oculta la barra inferior de estado/resize |
| `lang(?string $lang)` | `'es'\|'en'\|null` | `null` (auto) | Idioma de la interfaz del editor (detecta `html[lang]`) |
| `width(Size $size)` / `height(Size $size)` | `Size` | `100%` / `280px` | Ancho del componente y alto del área editable |
| `minHeight(Size $size)` / `maxHeight(Size $size)` | `Size` | `160px` / `null` | Límites de altura del área editable |
| `required(bool $v = true)` | `bool` | `false` | Marca el campo como obligatorio |
| `disabled(bool $v = true)` | `bool` | `false` | Deshabilita la edición y la barra de herramientas |
| `readonly(bool $v = true)` | `bool` | `false` | Pone el editor en modo solo lectura |
| `error(?string $message)` | `?string` | `null` | Muestra u oculta estado de error con tooltip |
| `helpText(?string $text)` | `?string` | `null` | Muestra ícono `?` con tooltip junto al label |
| `onInput(string $action, array $params = [])` | `string`, `array` | `null` | Acción backend mientras el usuario edita (con debounce) |
| `onChange(string $action, array $params = [])` | `string`, `array` | `null` | Acción backend al perder foco si hubo cambios |
| `debounce(int $ms)` | `int` | `250` (en JS) | Retardo en milisegundos para `onInput` |
| `borderColor(?string)` / `borderWidth(?int)` / `borderRadius(?int)` | varios | `null` | Personalización visual del borde contenedor |

---

## 7. Tema Claro / Oscuro y Demo

El componente detecta el tema activo de USIM (`data-theme="dark" | "light"`) al montarse y escucha el evento global `usim:theme-changed` para alternar el tema nativo de SunEditor v3 en caliente sin recargar la pantalla.

Podés probar todas sus capacidades en vivo en la pantalla demo **`App\UI\Screens\Demo\RichTextDemo`** (menú **Demos → Editor RichText**).
