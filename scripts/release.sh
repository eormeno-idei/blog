#!/usr/bin/env bash

set -e

VERSION=$1

if [ -z "$VERSION" ]; then
  echo "Uso: ./release.sh v1.0.0"
  exit 1
fi

# Validar formato simple semver
if ! [[ $VERSION =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Formato inválido. Use: vX.Y.Z (ej: v1.0.0)"
  exit 1
fi

echo "---------------------------------------"
echo "Publicando versión $VERSION"
echo "---------------------------------------"

# Verificar que estamos en un repo git
if [ ! -d ".git" ]; then
  echo "No es un repositorio git."
  exit 1
fi

# Verificar branch actual
BRANCH=$(git rev-parse --abbrev-ref HEAD)
echo "Branch actual: $BRANCH"

# Verificar cambios pendientes
if [[ -n $(git status --porcelain) ]]; then
  echo "Hay cambios sin commit. Se agregarán automáticamente."

  git add .
  git commit -m "Release $VERSION"
else
  echo "No hay cambios para commitear."
fi

# Verificar si el tag ya existe
if git rev-parse "$VERSION" >/dev/null 2>&1; then
  echo "El tag $VERSION ya existe."
  exit 1
fi

# Crear tag
git tag -a "$VERSION" -m "Release $VERSION"

# Push código
echo "Subiendo código..."
git push origin "$BRANCH"

# Push tag
echo "Subiendo tag..."
git push origin "$VERSION"

echo "---------------------------------------"
echo "Release $VERSION publicado correctamente"
echo "---------------------------------------"