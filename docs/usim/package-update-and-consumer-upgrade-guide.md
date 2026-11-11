# Guia de modificacion y actualizacion de `idei/usim`

Esta guia explica:

1. Como subir una modificacion del paquete `idei/usim`.
2. Como actualizar la app actual (`usim-framework`).
3. Como actualizar cualquier otra app consumidora.

## 1. Flujo recomendado para modificar y publicar el paquete

### 1.1. Trabajar cambios en el monorepo

En este proyecto, el paquete vive en:

- `packages/idei/usim`

Haz tus cambios ahi y valida:

```bash
cd /workspaces/usim-framework
composer validate --strict --no-check-publish
find packages/idei/usim/src packages/idei/usim/config packages/idei/usim/routes -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Opcional pero recomendado:

- Actualiza `packages/idei/usim/CHANGELOG.md`.
- Actualiza `packages/idei/usim/README.md` si cambias API, comandos o comportamiento.

### 1.2. Commit en monorepo

```bash
cd /workspaces/usim-framework
git add packages/idei/usim
git commit -m "feat(usim): descripcion corta del cambio"
```

### 1.3. Sincronizar al repo publico `idei/usim`

Genera rama de split con solo el paquete:

```bash
cd /workspaces/usim-framework
git subtree split --prefix=packages/idei/usim -b usim-public-release
```

Push al repo publico:

```bash
git push usim-public usim-public-release:main
```

### 1.4. Versionar con tag (SemVer)

Ejemplo de release patch:

```bash
cd /workspaces/usim-framework
git checkout usim-public-release
git tag v0.1.1
git push usim-public v0.1.1
```

Notas de version:

- `v0.1.x` para fixes compatibles.
- `v0.2.0` para nuevas features compatibles.
- `v1.0.0` para version estable.

### 1.5. Packagist y webhook

Con webhook configurado, Packagist detecta push/tag automaticamente.

Si necesitas forzar update manual:

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer PACKAGIST_USERNAME:PACKAGIST_TOKEN" \
  "https://packagist.org/api/update-package" \
  -d '{"repository":"https://github.com/idei/usim.git"}'
```

## 2. Actualizar la app actual (`usim-framework`)

Esta app usa repositorio `path` en su `composer.json`:

```json
"repositories": [
  { "type": "path", "url": "packages/idei/usim" }
]
```

Eso significa que la app toma el paquete local del monorepo.

### 2.1. Refrescar dependencias localmente

Si cambiaste solo codigo del paquete y no cambiaste constraints:

```bash
cd /workspaces/usim-framework
composer update idei/usim
php artisan optimize:clear
php artisan package:discover --ansi
```

### 2.2. Si agregaste nuevas dependencias al paquete

Ejemplo: agregaste algo en `packages/idei/usim/composer.json`.

```bash
cd /workspaces/usim-framework
composer update idei/usim --with-all-dependencies
php artisan optimize:clear
php artisan package:discover --ansi
```

### 2.3. Verificar comandos registrados

```bash
php artisan list | grep "usim:"
```

Debes ver al menos:

- `usim:discover`
- `usim:install`

## 3. Actualizar cualquier otra app consumidora

## Escenario A: app externa via Packagist (recomendado)

### 3.1. Instalar por primera vez

```bash
composer require idei/usim:^0.1
```

### 3.2. Actualizar a la ultima patch/minor compatible

```bash
composer update idei/usim
```

### 3.3. Actualizar a una version especifica

```bash
composer require idei/usim:^0.2
# o
composer require idei/usim:0.1.3
```

### 3.4. Refrescar framework despues del update

```bash
php artisan optimize:clear
php artisan package:discover --ansi
```

## Escenario B: app externa apuntando directo a GitHub (sin Packagist)

Solo para pruebas o emergencia. En `composer.json` de la app:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/idei/usim.git" }
]
```

Luego:

```bash
composer require idei/usim:^0.1
```

## 4. Checklist rapido de release

1. Cambios hechos en `packages/idei/usim`.
2. Validacion local (`composer validate`, `php -l`).
3. `CHANGELOG.md` actualizado.
4. Commit en monorepo.
5. `git subtree split` + push a `idei/usim`.
6. Tag nuevo (`vX.Y.Z`) + push tag.
7. Verificar en Packagist que aparezca la nueva version.
8. Validar `composer require idei/usim:^X.Y` en proyecto limpio.

## 4.1. Script automatizado (recomendado)

Puedes automatizar el flujo con:

```bash
scripts/release_usim_package -v v0.1.1 -p -f
```

Opciones utiles:

- `-v vX.Y.Z`: crea y sube tag.
- `-p`: dispara update en Packagist (requiere variables de entorno).
- `-f`: reemplaza la rama local de split si ya existe.

Variables para `-p`:

```bash
export PACKAGIST_USERNAME="tu_usuario"
export PACKAGIST_TOKEN="tu_token"
```

## 5. Comandos de verificacion utiles

Ver metadata en Packagist:

```bash
curl -s https://packagist.org/packages/idei/usim.json | head -c 1200
```

Ver si una version/tag ya fue indexada:

```bash
curl -s https://packagist.org/packages/idei/usim.json | grep -o '"v0.1.1"'
```

Probar instalacion real en entorno limpio:

```bash
tmpdir=$(mktemp -d)
cd "$tmpdir"
composer init -n --name=temp/check --require=php:^8.2 >/dev/null
composer require idei/usim:^0.1 -n
```

## 6. Errores frecuentes y solucion

- `Package "idei/usim" not found`:
  - El paquete aun no fue indexado o no existe el tag esperado.
  - Verifica webhook y fuerza update manual en Packagist.

- `No application encryption key has been specified` en tests:
  - Ejecuta `php artisan key:generate` en la app consumidora.

- Comandos `usim:*` no aparecen:
  - Ejecuta `php artisan package:discover --ansi`.
  - Limpia caches con `php artisan optimize:clear`.

- La app sigue usando una version vieja:
  - Revisa constraint en `composer.json`.
  - Corre `composer update idei/usim --with-all-dependencies`.
