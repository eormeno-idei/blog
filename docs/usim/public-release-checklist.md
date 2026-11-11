# Public Release Checklist

This checklist is for publishing `idei/usim` from the monorepo to a dedicated public repository and Packagist.

## 1. Pre-release validation

- Ensure package files are up to date:
  - `README.md`
  - `CHANGELOG.md`
  - `LICENSE.md`
  - `composer.json`
- Run quality checks from package root:

```bash
composer validate --strict --no-check-publish
find src config routes -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

## 2. Split package history into release branch

From monorepo root:

```bash
git subtree split --prefix=packages/idei/usim -b usim-public-release
```

## 3. Push to public repository

```bash
git remote add usim-public git@github.com:idei/usim.git
# or HTTPS:
# git remote add usim-public https://github.com/idei/usim.git

git push usim-public usim-public-release:main
```

## 4. Tag a version

Use semantic versioning, for example:

```bash
git checkout usim-public-release
git tag v0.1.0
git push usim-public v0.1.0
```

## 5. Register in Packagist

- Go to Packagist and submit `https://github.com/idei/usim`.
- Verify webhook is enabled for auto-updates on new tags.

## 6. Post-release checks

- Validate install from clean Laravel app:

```bash
composer require idei/usim
php artisan usim:install --force
```

- Verify generated files compile and routes work.
- Create release notes from `CHANGELOG.md`.
