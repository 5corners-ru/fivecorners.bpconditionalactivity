# Архитектура модуля fivecorners.bpconditionalactivity

Модуль — установщик одного BP-активити. Бизнес-логика целиком в активити;
модуль отвечает за деплой файлов и регистрацию обработчика событий.

---

## Карта классов

| Класс / файл | Роль |
|---|---|
| `fivecorners_bpconditionalactivity` (`install/index.php`) | Установщик: `DoInstall`/`DoUninstall`, `Install*`/`UnInstall*`. |
| `FiveCorners\BpConditionalActivity\EventHandler` (`lib/EventHandler.php`) | Обработчик `main::OnEpilog` — подключает JS активити. |
| `CBPRequestInfoDeclineConditional` (`install/activities/.../requestinfodeclineconditional.php`) | Само активити. Наследует `CBPRequestInformationOptionalActivity`. |

---

## Что куда копируется при установке

`InstallFiles()`:

| Источник (в модуле) | Назначение (на портале) |
|---|---|
| `install/activities/requestinfodeclineconditional/` | `/local/activities/requestinfodeclineconditional/` |
| `install/js/bpridc-conditional.js` | `/local/js/fivecorners.bpconditionalactivity/bpridc-conditional.js` |

`UnInstallFiles()` удаляет обе папки целиком — модуль владеет ими полностью.

Ядро (`\Bitrix\Bizproc\Runtime\ActivitySearcher\Searcher`) сканирует
`/local/activities/` автоматически при каждом открытии дизайнера БП — явная
регистрация активити в БД не нужна.

`InstallEvents()` регистрирует `main::OnEpilog → EventHandler::onEpilog` через
`EventManager::registerEventHandler` (не в `include.php` — Правило 11). Перед
регистрацией вызывается `UnInstallEvents()` для защиты от дублей при re-install.

---

## Два пути рендеринга формы задачи

Активити поддерживает оба интерфейса задач БП. Разница — в способе доставки
правил зависимости (`depRules`) в браузер и в префиксах имён инпутов.

### Путь 1: Попап из CRM-сделки

- `ShowTaskForm()` инжектирует `<div id="bpridc-task-rules" data-rules="[...]">`.
- `bpridc-conditional.js` (IIFE 2) полит за этим `div`, применяет show/hide.
- Имена инпутов: `bpriact_FIELDNAME` (`CONTROLS_PREFIX` без `o`).

### Путь 2: Вкладка «Автоматизация → Бизнес-процессы»

- `getTaskControls()` добавляет `DependOnField`/`DependOnValue` в `FIELDS`.
- Данные попадают в JS через `BX.Bizproc.Component.WorkflowInfo.Instance.taskFields`.
- `bpridc-conditional.js` (IIFE 3) полит за `Instance`, применяет show/hide.
- Имена инпутов: `bprioact_FIELDNAME` (из `field.FieldId`).

---

## Ключевые технические решения

### Почему `<div data-rules>` вместо `<script>`

Битрикс рендерит попап задачи через `innerHTML`. Браузеры не выполняют `<script>`,
инжектированные таким способом. Решение: правила передаются через `data`-атрибут,
глобальный JS читает их при загрузке страницы.

### Резолвинг текста в ID для `UF:iblock_element`

В дизайнере БП значение триггера хранится как текст (`"Низкая ЗП"`), а hidden
input в форме задачи содержит числовой ID (`78`). Метод `resolveIblockElementId()`
вызывается в трёх местах: `extractDepRules()` (Путь 1), `getTaskControls()`
(Путь 2), `validateTaskEventParameters()` (серверная валидация).

### Почему нельзя `MutationObserver` на `document.body`

`observer.observe(document.body, { subtree: true })` глобально ломает список
сделок CRM. Вместо этого — `MutationObserver` только на форме задачи (в пределах
`<form>`) + polling каждые 200–250 мс для отслеживания `.value = x`.

### Разные префиксы имён инпутов

- Путь 1: `bpriact_` (из PHP `CONTROLS_PREFIX` без `o`).
- Путь 2: `bprioact_` (из `field.FieldId` в `getTaskControls()`).

---

## Совместимость

| Сценарий | Поддержка |
|---|---|
| Несколько зависимых полей | да |
| Несколько зависимых полей на одном триггере | да |
| PostgreSQL | да (только ORM/`CIBlockElement`, нет сырого SQL) |
