# CBPRequestInfoDeclineConditional

Кастомное активити **«5 УГЛОВ Запрос с доп. информацией»** для дизайнера бизнес-процессов Битрикс24 (on-premise).

Расширяет стандартный `CBPRequestInformationOptionalActivity`. Добавляет поддержку **зависимых полей**: когда пользователь выбирает определённое значение в одном поле — другое поле автоматически появляется и становится обязательным. Скрытие/показ работает на клиенте (JS), обязательность проверяется и на сервере (PHP).

---

## Требования

- Битрикс24 on-premise (коробочная версия)
- PHP 8.0+

---

## Структура файлов

```
local/
  activities/custom/requestinfodeclineconditional/
    requestinfodeclineconditional.php   ← главный класс активити
    .description.php                    ← регистрация активити в дизайнере БП
    properties_dialog.php               ← форма настройки в дизайнере БП
    lang/ru/
      requestinfodeclineconditional.php ← языковые строки
      properties_dialog.php
      .description.php
  js/
    bpridc-conditional.js               ← глобальный JS (show/hide логика)
  php_interface/
    init.php                            ← подключение JS через OnEpilog
```

---

## Установка

1. Скопируйте папку `local/activities/custom/requestinfodeclineconditional/` в `<bitrix_root>/local/activities/custom/`.
2. Скопируйте `local/js/bpridc-conditional.js` в `<bitrix_root>/local/js/`.
3. В файле `<bitrix_root>/local/php_interface/init.php` добавьте регистрацию обработчика из `local/php_interface/init.php` этого репозитория.
4. Сбросьте OPcache на сервере:
   ```php
   opcache_reset();
   ```
5. Перейдите в дизайнер БП — активити появится в категории **Прочее** под названием **«5 УГЛОВ Запрос с доп. информацией»**.

---

## Как работают зависимые поля

### Настройка в дизайнере БП

В форме настройки активити каждое поле имеет три режима обязательности:

| Режим | Поведение |
|---|---|
| Не обязательно | Всегда видно, не обязательно |
| Всегда обязательно | Всегда видно, всегда обязательно |
| Обязательно при условии | Скрыто; появляется и становится обязательным при выборе нужного значения в триггер-поле |

Для режима **«Обязательно при условии»** указывается:
- **Поле (триггер)** — другое поле этого же активити
- **Значение** — при каком значении триггера показать поле

Для типа `UF:iblock_element` значение вводится текстом (например, `Низкая ЗП`) — PHP автоматически резолвит его в числовой ID элемента инфоблока.

### Два пути рендеринга (оба поддерживаются)

#### Путь 1: Попап из CRM-сделки

- `ShowTaskForm()` инжектирует `<div id="bpridc-task-rules" data-rules="[...]">`
- `bpridc-conditional.js` (IIFE 2) полит за этим div, применяет show/hide
- Имена инпутов: `bpriact_FIELDNAME` (CONTROLS_PREFIX без `o`)

#### Путь 2: Вкладка Автоматизация → Бизнес-процессы (`/company/personal/bizproc/NNN/`)

- `getTaskControls()` добавляет `DependOnField`/`DependOnValue` в FIELDS
- Данные попадают в JS через `BX.Bizproc.Component.WorkflowInfo.Instance.taskFields`
- `bpridc-conditional.js` (IIFE 3) полит за Instance, применяет show/hide
- Имена инпутов: `bprioact_FIELDNAME` (из `field.FieldId`)

---

## Совместимость

| Сценарий | Поддерживается |
|---|---|
| Несколько зависимых полей | ✓ |
| Несколько зависимых полей на одном триггере | ✓ |
| PostgreSQL | ✓ (только ORM, нет сырого SQL) |

---

## Ключевые технические решения

### Почему `<div data-rules>` вместо `<script>`

Битрикс рендерит попап задачи через `innerHTML`. Браузеры не выполняют `<script>` теги, инжектированные таким способом. Решение: правила передаются через `data`-атрибут, глобальный JS читает их при загрузке страницы.

### Резолвинг текста в ID для `UF:iblock_element`

В дизайнере БП значение триггера хранится как текст (`"Низкая ЗП"`), а hidden input в форме задачи содержит числовой ID (`78`). Метод `resolveIblockElementId()` вызывается в трёх местах:
- `extractDepRules()` — для Пути 1
- `getTaskControls()` — для Пути 2
- `validateTaskEventParameters()` — серверная валидация

### Почему нельзя использовать MutationObserver на `document.body`

`observer.observe(document.body, { subtree: true })` глобально ломает список сделок CRM в Битрикс24. Вместо этого используется:
- MutationObserver только на форме задачи (в пределах `<form>`)
- Polling каждые 200 мс для отслеживания изменений через `.value = x`

### Разные префиксы имён инпутов в двух путях

- Путь 1: `bpriact_` (из PHP CONTROLS_PREFIX без `o`)
- Путь 2: `bprioact_` (из `field.FieldId` в `getTaskControls()`)

В JS Пути 2 используется `triggerField.FieldId` напрямую, а не вычисленный префикс.

---

## Лицензия

MIT
