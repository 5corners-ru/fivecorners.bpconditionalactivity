# Changelog

Все значимые изменения модуля. Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/).

## [1.0.0] — 2026-05-21

### Added
- Установщик модуля (`install/index.php`): `DoInstall`/`DoUninstall` с симметричными
  `InstallDB`/`InstallFiles`/`InstallEvents` и зеркальными `UnInstall*`.
- Двухшаговое удаление с подтверждением (`unstep1.php` → `unstep.php`).
- `lib/EventHandler.php` — обработчик `main::OnEpilog`, подключающий
  `bpridc-conditional.js`. Регистрируется через `InstallEvents()` (ранее был
  анонимной функцией в `local/php_interface/init.php`).
- Иконка активити (`NODE_ICON`) для нового node-дизайнера БП.
- `docs/ARCHITECTURE.md`, `CHANGELOG.md`, `BACKLOG.md`, lang-файлы установщика (ru/en).

### Changed
- Проект «набор файлов под `/local/`» переоформлен в устанавливаемый модуль
  по канону `fivecorners.admintemplate`.
- Активити переехало из `/local/activities/custom/requestinfodeclineconditional/`
  в канонический путь `/local/activities/requestinfodeclineconditional/`.
- JS переехал из `/local/js/bpridc-conditional.js` в module-owned подпапку
  `/local/js/fivecorners.bpconditionalactivity/`.

### Removed
- Ручная установка через копирование папок и правку `php_interface/init.php`
  портала — заменена штатной установкой модуля.
