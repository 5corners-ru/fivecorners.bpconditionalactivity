<?php

namespace FiveCorners\BpConditionalActivity;

/**
 * Обработчики событий ядра для модуля fivecorners.bpconditionalactivity.
 *
 * Регистрируются в install/index.php::InstallEvents() (Правило 11 —
 * НЕ в include.php, иначе хендлер регистрируется на каждом хите).
 */
class EventHandler
{
    /** Путь к JS, куда InstallFiles() кладёт скрипт ({MODULE_ID} = имя подпапки). */
    private const JS_URL = '/local/js/fivecorners.bpconditionalactivity/bpridc-conditional.js';

    /**
     * main::OnEpilog — подключает глобальный JS условных полей формы задачи БП.
     *
     * Скрипт нужен на страницах задач БП (popup из CRM-сделки и вкладка
     * «Бизнес-процессы»). Грузится глобально: страницу задачи невозможно
     * заранее отфильтровать по URL без перебора всех сценариев рендеринга.
     * Точечная загрузка — TD в BACKLOG.md.
     */
    public static function onEpilog(): void
    {
        global $APPLICATION;

        if ($APPLICATION instanceof \CMain) {
            $APPLICATION->AddHeadScript(self::JS_URL);
        }
    }
}
