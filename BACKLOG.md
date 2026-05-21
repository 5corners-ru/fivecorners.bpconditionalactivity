# Backlog

Техдолг и планы модуля. TD-N — сквозная нумерация.

## Запланировано

### TD-1. Точечная загрузка JS вместо глобального OnEpilog
`EventHandler::onEpilog()` подключает `bpridc-conditional.js` на **каждой**
странице портала. Скрипт нужен только на страницах задач БП (popup из CRM-сделки
и вкладка «Бизнес-процессы»). Сейчас он лёгкий и при отсутствии целевого DOM
просто ничего не делает, но грузить его глобально — лишний HTTP-запрос на всех
страницах. Нужно сузить загрузку до релевантных страниц, не сломав оба сценария
рендеринга (см. `docs/ARCHITECTURE.md`).
Приоритет: низкий.

### TD-2. Английская локализация активити
`install/activities/requestinfodeclineconditional/lang/` содержит только `ru`.
Для публикации в Marketplace нужен `en`-перевод `.description.php`,
`properties_dialog.php`, `requestinfodeclineconditional.php`.
Приоритет: средний (блокирует Marketplace).

### TD-4. Мёртвый метод `buildConditionalScript()` в активити
`requestinfodeclineconditional.php:357-541` — приватный метод `buildConditionalScript()`
(~185 строк) нигде не вызывается. Активити перешло с inline-`<script>` на
доставку правил через `data-rules`-атрибут + глобальный JS, а старый метод
остался. Дублирует логику `install/js/bpridc-conditional.js`. Удалить при
ближайшей правке активити (это бизнес-логика — не трогалось при переоформлении).
Приоритет: низкий.

### TD-3. Индивидуальная иконка активити
`install/activities/requestinfodeclineconditional/icon.png` — брендовый
placeholder из `fivecorners.admintemplate`. Заменить на индивидуальную иконку
активити, когда дизайнер её нарисует (PNG, RGBA).
Приоритет: низкий.

## В работе

—

## Сделано

- Переоформление проекта в устанавливаемый модуль по канону (v1.0.0).
