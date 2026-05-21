<?php
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// DO NOT redeclare $MODULE_ID, $MODULE_NAME, $MODULE_VERSION etc. with types —
// CModule declares them untyped; typed redeclaration = E_COMPILE_ERROR on PHP 8.1+.
class fivecorners_bpconditionalactivity extends CModule
{
    public const MODULE_ID = 'fivecorners.bpconditionalactivity';

    /** Папка активити в /local/activities/ — имя задаёт код класса CBPRequestInfoDeclineConditional. */
    private const ACTIVITY_DIR = 'requestinfodeclineconditional';

    /** Имя JS-файла, подключаемого на страницах задач БП. */
    private const JS_FILE = 'bpridc-conditional.js';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_ID           = self::MODULE_ID;
        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME         = Loc::getMessage('FCO_BPCA_MODULE_NAME');
        $this->MODULE_DESCRIPTION  = Loc::getMessage('FCO_BPCA_MODULE_DESC');
        $this->PARTNER_NAME        = '5 УГЛОВ';
        $this->PARTNER_URI         = 'https://5corners.ru';
    }

    public function DoInstall(): bool
    {
        global $APPLICATION;

        $this->InstallDB();
        $this->InstallFiles();
        $this->InstallEvents();

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('FCO_BPCA_INSTALL_TITLE'),
            __DIR__ . '/step.php'
        );

        return true;
    }

    public function DoUninstall(): bool
    {
        global $APPLICATION;

        $step = (int)($_REQUEST['step'] ?? 1);

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('FCO_BPCA_UNINSTALL_TITLE'),
                __DIR__ . '/unstep1.php'
            );
            return true;
        }

        // У модуля нет собственных опций/таблиц — save_data не на что влиять,
        // но двухшаговая форма-подтверждение оставлена по канону (Правило 3).
        try {
            $this->UnInstallEvents();
            $this->UnInstallFiles();
        } finally {
            // UnRegisterModule — всегда, даже если удаление файлов упало:
            // иначе модуль зависнет в b_module с уже удалёнными файлами,
            // и кнопка «Удалить» в админке перестанет работать (Правило 3, п.2).
            $this->UnInstallDB();
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('FCO_BPCA_UNINSTALL_TITLE'),
            __DIR__ . '/unstep.php'
        );

        return true;
    }

    public function InstallDB(): bool
    {
        RegisterModule(self::MODULE_ID);
        return true;
    }

    public function UnInstallDB(): bool
    {
        UnRegisterModule(self::MODULE_ID);
        return true;
    }

    public function InstallFiles(): bool
    {
        $docRoot = Application::getDocumentRoot();

        // (1) BP-активити: install/activities/<dir>/ → /local/activities/<dir>/
        // Канонический путь — /local/activities/<code>/, без уровня custom/.
        // Ядро (ActivitySearcher) сканирует /local/activities/ автоматически.
        $activitySrc = __DIR__ . '/activities/' . self::ACTIVITY_DIR;
        $activityDst = $docRoot . '/local/activities/' . self::ACTIVITY_DIR;
        if (is_dir($activitySrc)) {
            if (!is_dir($activityDst)) {
                @mkdir($activityDst, 0755, true);
            }
            CopyDirFiles($activitySrc, $activityDst, true, true);
        }

        // (2) Глобальный JS: install/js/<file> → /local/js/<MODULE_ID>/<file>
        // Своя подпапка по MODULE_ID — модуль владеет ею целиком, нет коллизий
        // с чужими скриптами в /local/js/. Подключается обработчиком OnEpilog.
        $jsDst = $docRoot . '/local/js/' . self::MODULE_ID;
        if (!is_dir($jsDst)) {
            @mkdir($jsDst, 0755, true);
        }
        $jsSrc = __DIR__ . '/js/' . self::JS_FILE;
        if (is_file($jsSrc)) {
            @copy($jsSrc, $jsDst . '/' . self::JS_FILE);
        }

        return true;
    }

    public function UnInstallFiles(): bool
    {
        $docRoot = Application::getDocumentRoot();

        // Симметрично InstallFiles — обе папки целиком принадлежат модулю.
        $activityDst = $docRoot . '/local/activities/' . self::ACTIVITY_DIR;
        if (is_dir($activityDst)) {
            Directory::deleteDirectory($activityDst);
        }

        $jsDst = $docRoot . '/local/js/' . self::MODULE_ID;
        if (is_dir($jsDst)) {
            Directory::deleteDirectory($jsDst);
        }

        return true;
    }

    public function InstallEvents(): bool
    {
        // Снимаем хендлер до регистрации: registerEventHandler не дедуплицирует,
        // иначе re-install (через «удалить → установить») задваивает обработчик
        // и JS подключается дважды (Правило 16).
        $this->UnInstallEvents();

        EventManager::getInstance()->registerEventHandler(
            'main',
            'OnEpilog',
            self::MODULE_ID,
            '\\FiveCorners\\BpConditionalActivity\\EventHandler',
            'onEpilog'
        );

        return true;
    }

    public function UnInstallEvents(): bool
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnEpilog',
            self::MODULE_ID,
            '\\FiveCorners\\BpConditionalActivity\\EventHandler',
            'onEpilog'
        );

        return true;
    }
}
