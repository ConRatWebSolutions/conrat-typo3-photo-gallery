# Contributing

Thanks for helping to improve the ConRat Photo Gallery.

## Issues

- Bugs: describe TYPO3 and PHP version, image processor (ImageMagick/GraphicsMagick), steps to reproduce,
  expected and actual result. Browser console output helps for frontend problems.
- Feature requests are welcome; please describe the use case. New features are planned separately and may not
  be merged right away.
- Security issues: see [SECURITY.md](SECURITY.md), not the public issue tracker.

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Keep changes focused; one topic per pull request.
3. Run the checks locally (ImageMagick or GraphicsMagick required):

   ```bash
   composer install
   .Build/bin/phpstan analyse -c Build/phpstan.neon
   typo3DatabaseDriver=pdo_sqlite .Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml
   ```

4. Add or adapt functional tests in `Tests/Functional/` for changed behaviour.
5. Add a line to `CHANGELOG.md` under the upcoming version.

## Code conventions

- PHP: `declare(strict_types=1)`, PSR-12, typed properties and return types, PHPStan level 6 without errors.
- Use TYPO3 APIs (DataHandler, FAL, QueryBuilder with named parameters) – no raw SQL, no direct file access.
- Frontend: no jQuery, no build step; JavaScript as ES modules; texts via `locallang*.xlf`.
- Many identifiers in the code are German (`Galerie`, `Bilder`, `darstellung`). Keep the existing naming in the
  area you change; new public APIs may use English names.

By contributing you agree that your contribution is licensed under GPL-2.0-or-later.
