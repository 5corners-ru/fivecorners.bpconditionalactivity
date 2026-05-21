<?php
/**
 * Шаг 1 удаления — форма подтверждения (DoUninstall, step 1).
 *
 * Rule 3 + Rule 24: method="post" + bitrix_sessid_post() (sessid не должен течь
 * в access-логи через GET, prefetch-риск).
 * Чекбокс save_data оставлен по канону, хотя у модуля нет собственных данных —
 * активити и JS удаляются всегда (это файлы модуля, не пользовательские данные).
 */

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__DIR__ . '/index.php');
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="id"        value="fivecorners.bpconditionalactivity">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step"      value="2">

    <p><?= Loc::getMessage('FCO_BPCA_UNINSTALL_CONFIRM') ?></p>
    <p><?= Loc::getMessage('FCO_BPCA_UNINSTALL_NOTE') ?></p>

    <p>
        <input type="submit" class="adm-btn-save" value="<?= htmlspecialcharsbx(Loc::getMessage('FCO_BPCA_UNINSTALL_DO')) ?>">
    </p>
</form>
