<?php
/**
 * Страница после успешной установки модуля (DoInstall → step.php).
 *
 * У модуля нет admin-страницы — это набор BP-активити. Поэтому вместо ссылки
 * «Перейти в настройки» (Правило 2) даём пояснение, где искать активити.
 *
 * Канон кнопок инсталлятора: навигация-возврат — голый <input type="submit">
 * в <form> (admin-CSS Bitrix стилизует сам).
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
?>
<form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
    <table class="adm-detail-content-table edit-table" width="100%">
        <tr>
            <td align="center" width="100%">
                <div class="adm-info-message-wrap adm-info-message-green">
                    <div class="adm-info-message">
                        <div class="adm-info-message-title">
                            <?= Loc::getMessage('FCO_BPCA_INSTALL_SUCCESS_TITLE') ?>
                        </div>
                        <div class="adm-info-message-body">
                            <?= Loc::getMessage('FCO_BPCA_INSTALL_SUCCESS_TEXT') ?>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td align="center">
                <input type="hidden" name="lang"    value="<?= LANGUAGE_ID ?>">
                <input type="hidden" name="id"      value="fivecorners.bpconditionalactivity">
                <input type="hidden" name="install" value="Y">
                <input type="hidden" name="step"    value="2">
                <input type="submit" name="inst"    value="<?= Loc::getMessage('FCO_BPCA_INSTALL_BACK') ?>">
            </td>
        </tr>
    </table>
</form>
