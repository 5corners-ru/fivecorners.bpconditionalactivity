<?php
/**
 * Страница после удаления модуля (DoUninstall → unstep.php).
 *
 * Канон кнопок инсталлятора: навигация-возврат — <a class="adm-btn">
 * (НЕ adm-btn-save: она для save/destructive и только на <input>/<button>).
 */

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__DIR__ . '/index.php');
?>
<p><?= Loc::getMessage('FCO_BPCA_UNINSTALL_DONE') ?></p>
<p>
    <a href="/bitrix/admin/partner_modules.php?lang=<?= LANGUAGE_ID ?>" class="adm-btn">
        <?= Loc::getMessage('FCO_BPCA_BACK_TO_MODULES') ?>
    </a>
</p>
