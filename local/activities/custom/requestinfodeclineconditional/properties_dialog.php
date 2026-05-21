<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * @var \Bitrix\Bizproc\Activity\PropertiesDialog $dialog
 * @var array  $requestedInformation
 * @var array  $arFieldTypes
 * @var array  $arDocumentFields
 * @var string $javascriptFunctions
 * @var string $formName
 * @var object $popupWindow
 */
?>

<?= $javascriptFunctions ?>
<script>
// BPRIAParams — the standard Bitrix param storage (keyed by sequential ID)
var BPRIAParams = <?= (is_array($requestedInformation) ? CUtil::PhpToJSObject($requestedInformation) : '{}') ?>;

// ---- standard show/hide between list view and edit form ----
function BPRIAEditForm(b) {
    var f = document.getElementById('ria_pd_edit_form');
    var l = document.getElementById('ria_pd_list_form');
    <?= $popupWindow->jsPopup ?>.btnSave.btn.disabled = !!b;
    <?= $popupWindow->jsPopup ?>.btnCancel.btn.disabled = !!b;
    if (b) {
        l.style.display = 'none';
        try { f.style.display = 'table-row'; } catch(e) { f.style.display = 'inline'; }
    } else {
        f.style.display = 'none';
        try { l.style.display = 'table-row'; } catch(e) { l.style.display = 'inline'; }
    }
}

var currentType = null;
var lastEd = false;

// ---- new field ----
function BPRIANewParam() {
    lastEd = false;
    BPRIAEditForm(true);

    var i;
    for (i = 1; i < 10000; i++) { if (!BPRIAParams[i]) break; }

    document.getElementById('id_fri_title').value       = '';
    document.getElementById('id_fri_name').value        = '';
    document.getElementById('id_fri_description').value = '';
    document.getElementById('id_fri_multiple').checked  = false;
    document.getElementById('id_fri_id').value          = i;

    // Reset conditional required controls
    document.getElementById('id_fri_required_mode').value = 'no';
    BPRIDCToggleCondBlock();

    for (var t in objFields.arFieldTypes) break;
    window.currentType = { Type: t, Options: null, Required: 'N', Multiple: 'N' };
    BPRIAChangeFieldType(window.currentType);
    document.getElementById('id_fri_type').selectedIndex = 0;
    document.getElementById('id_fri_title').focus();
}

// ---- serialize one param to hidden inputs ----
function BPRIAToHiddens(ob, name) {
    if (ob === null || ob === undefined) { return ''; }
    if (typeof ob === 'object') {
        var s = '';
        for (var k in ob) { s += BPRIAToHiddens(ob[k], name + '[' + encodeURIComponent(k) + ']'); }
        return s;
    }
    return '<input type="hidden" name="' + objFields.HtmlSpecialChars(name) + '" value="' + objFields.HtmlSpecialChars(String(ob)) + '">';
}

// ---- update one row in the list table ----
function BPRIAParamFillParam(id, p) {
    var i, t = document.getElementById('ria_pd_list_table');
    for (i = 1; i < t.rows.length; i++) {
        if (t.rows[i].paramId !== id) continue;
        var r = t.rows[i].cells;

        r[0].innerHTML =
            '<a href="javascript:void(0);" onclick="BPRIAParamEditParam(this);">' + HTMLEncode(p['Name']) + '</a>' +
            BPRIAToHiddens(p, 'requested_information[' + id + ']');
        r[1].innerHTML = HTMLEncode(p['Title']);
        r[2].innerHTML = objFields.arFieldTypes[p['Type']] ? objFields.arFieldTypes[p['Type']]['Name'] : p['Type'];

        // Required column: show 'Да' / 'Нет' / condition string
        if (p['DependOnField']) {
            var condLabel = '<?= GetMessageJS("BPRIDC_PD_F_REQ_COND_SHORT") ?>: ' +
                HTMLEncode(p['DependOnField']) + ' = ' + HTMLEncode(p['DependOnValue'] || '');
            r[3].innerHTML = '<span style="color:#8a6200" title="' + condLabel + '">' + condLabel + '</span>';
        } else {
            r[3].innerHTML = (p['Required'] === 'Y' ? '<?= GetMessageJS("BPSFA_PD_YES") ?>' : '<?= GetMessageJS("BPSFA_PD_NO") ?>');
        }

        r[4].innerHTML = (p['Multiple'] === 'Y' ? '<?= GetMessageJS("BPSFA_PD_YES") ?>' : '<?= GetMessageJS("BPSFA_PD_NO") ?>');
        return true;
    }
    return false;
}

// ---- insert new row in list table ----
function BPRIAParamAddParam(id, p) {
    var t = document.getElementById('ria_pd_list_table');
    var r = t.insertRow(-1);
    r.paramId = id;
    r.insertCell(-1);
    r.insertCell(-1);
    var c = r.insertCell(-1); c.align = 'center';
    c = r.insertCell(-1); c.align = 'center';
    r.insertCell(-1);
    c = r.insertCell(-1);
    c.innerHTML =
        '<a href="javascript:void(0);" onclick="moveRowUp(this);return false;"><?= GetMessageJS("BP_WF_UP") ?></a>' +
        ' | <a href="javascript:void(0);" onclick="moveRowDown(this);return false;"><?= GetMessageJS("BP_WF_DOWN") ?></a>' +
        ' | <a href="javascript:void(0);" onclick="BPRIAParamEditParam(this);return false;"><?= GetMessageJS("BPSFA_PD_CHANGE") ?></a>' +
        ' | <a href="javascript:void(0);" onclick="BPRIADeleteRow(this);return false;"><?= GetMessageJS("BPSFA_PD_DELETE") ?></a>';
    BPRIAParamFillParam(id, p);
}

// ---- delete row ----
function BPRIADeleteRow(ob) {
    var id = ob.parentNode.parentNode.paramId;
    delete BPRIAParams[id];
    var i, t = document.getElementById('ria_pd_list_table');
    for (i = 1; i < t.rows.length; i++) {
        if (t.rows[i].paramId === id) { t.deleteRow(i); return; }
    }
}

// ---- open edit form for existing row ----
function BPRIAParamEditParam(ob) {
    BPRIAEditForm(true);
    window.lastEd = ob.parentNode.parentNode.paramId;
    var s = BPRIAParams[window.lastEd];

    window.currentType = { Type: s['Type'], Options: s['Options'], Required: s['Required'], Multiple: s['Multiple'] };

    document.getElementById('id_fri_title').value       = s['Title'];
    document.getElementById('id_fri_name').value        = s['Name'];
    document.getElementById('id_fri_description').value = s['Description'] || '';
    document.getElementById('id_fri_multiple').checked  = (s['Multiple'] === 'Y');
    document.getElementById('id_fri_id').value          = window.lastEd;
    document.getElementById('id_td_document_value').innerHTML = '';

    // Restore required mode
    var reqMode = 'no';
    if (s['DependOnField']) {
        reqMode = 'conditional';
    } else if (s['Required'] === 'Y') {
        reqMode = 'always';
    }
    document.getElementById('id_fri_required_mode').value = reqMode;
    BPRIDCToggleCondBlock();

    if (reqMode === 'conditional') {
        BPRIDCRefreshDependFieldList(s['DependOnField'], s['DependOnValue']);
    }

    BPRIAChangeFieldType(window.currentType, s['Default']);
    document.getElementById('id_fri_title').focus();
}

// ---- change field type ----
function BPRIAChangeFieldType(type, value) {
    BX.showWait();
    var f1 = document.getElementById('id_fri_type');
    if (f1) {
        for (var i = 0; i < f1.options.length; i++) {
            if (f1.options[i].value === type['Type']) { f1.selectedIndex = i; break; }
        }
    }
    if (typeof value === 'undefined') value = '';

    if (objFields.arFieldTypes[type['Type']] && objFields.arFieldTypes[type['Type']].Complex === 'Y') {
        objFields.GetFieldInputControl4Type(type, value, { Field: 'fri_default', Form: '<?= $formName ?>' },
            'BPRIASwitchSubTypeControl',
            function (v, newPromt) {
                if (v === undefined) {
                    document.getElementById('id_td_document_value').innerHTML = '';
                    document.getElementById('id_tr_pbria_options').style.display = 'none';
                } else {
                    document.getElementById('id_tr_pbria_options').style.display = '';
                    document.getElementById('id_td_fri_options').innerHTML = v;
                }
                var lbl = newPromt.length > 0 ? newPromt : '<?= GetMessageJS("BPSFA_PD_F_VLIST") ?>';
                document.getElementById('id_td_fri_options_promt').innerHTML = lbl + ':';
                objFields.GetFieldInputControl4Subtype(type, value, { Field: 'fri_default', Form: '<?= $formName ?>' },
                    function (v1) {
                        document.getElementById('id_td_document_value').innerHTML = v1 || '';
                        BX.closeWait();
                    }
                );
            }
        );
    } else {
        document.getElementById('id_td_document_value').innerHTML = '';
        document.getElementById('id_tr_pbria_options').style.display = 'none';
        objFields.GetFieldInputControl4Subtype(type, value, { Field: 'fri_default', Form: '<?= $formName ?>' },
            function (v) {
                document.getElementById('id_td_document_value').innerHTML = v || '';
                BX.closeWait();
            }
        );
    }
}

function BPRIASwitchTypeControl(newType) {
    BX.showWait();
    objFields.GetFieldInputValue(window.currentType, { Field: 'fri_default', Form: '<?= $formName ?>' },
        function (v) {
            window.currentType['Type'] = newType;
            if (typeof v === 'object') v = v[0];
            BX.closeWait();
            BPRIAChangeFieldType(window.currentType, v);
        }
    );
}

function BPRIASwitchSubTypeControl(newSubtype) {
    BX.showWait();
    document.getElementById('dpsavebuttonform').disabled = true;
    document.getElementById('dpcancelbuttonform').disabled = true;
    objFields.GetFieldInputValue(window.currentType, { Field: 'fri_default', Form: '<?= $formName ?>' },
        function (v) {
            window.currentType['Options'] = newSubtype;
            if (typeof v === 'object') v = v[0];
            BX.closeWait();
            document.getElementById('dpsavebuttonform').disabled = false;
            document.getElementById('dpcancelbuttonform').disabled = false;
            BPRIAChangeFieldSubtype(window.currentType, v);
        }
    );
}

function BPHide() {}

function BPRIAChangeFieldSubtype(type, value) {
    BX.showWait();
    if (typeof value === 'undefined') value = '';
    objFields.GetFieldInputControl4Subtype(type, value, { Field: 'fri_default', Form: '<?= $formName ?>' },
        function (v) {
            document.getElementById('id_td_document_value').innerHTML = v || '';
            BX.closeWait();
        }
    );
}

// ---- save field from edit form ----
function BPRIAParamSaveForm() {
    if (!document.getElementById('id_fri_title').value.replace(/^\s+|\s+$/g, '').length) {
        alert('<?= GetMessageJS("BPSFA_PD_EMPTY_TITLE") ?>');
        document.getElementById('id_fri_title').focus();
        return;
    }
    if (!document.getElementById('id_fri_name').value.replace(/^\s+|\s+$/g, '').length) {
        alert('<?= GetMessageJS("BPSFA_PD_EMPTY_NAME") ?>');
        document.getElementById('id_fri_name').focus();
        return;
    }
    if (!document.getElementById('id_fri_name').value.match(/^[A-Za-z_][A-Za-z0-9_]*$/g)) {
        alert('<?= GetMessageJS("BPSFA_PD_WRONG_NAME") ?>');
        document.getElementById('id_fri_name').focus();
        return;
    }

    BX.showWait();
    var N = lastEd;
    if (!lastEd) {
        lastEd = document.getElementById('id_fri_id').value.replace(/^\s+|\s+$/g, '');
        BPRIAParams[lastEd] = {};
    }

    BPRIAParams[lastEd]['Title']    = document.getElementById('id_fri_title').value.replace(/^\s+|\s+$/g, '');
    BPRIAParams[lastEd]['Name']     = document.getElementById('id_fri_name').value.replace(/^\s+|\s+$/g, '');
    BPRIAParams[lastEd]['Description'] = document.getElementById('id_fri_description').value;
    BPRIAParams[lastEd]['Type']     = document.getElementById('id_fri_type').options[document.getElementById('id_fri_type').selectedIndex].value;
    BPRIAParams[lastEd]['Multiple'] = document.getElementById('id_fri_multiple').checked ? 'Y' : 'N';

    if (objFields.arFieldTypes[BPRIAParams[lastEd]['Type']] && objFields.arFieldTypes[BPRIAParams[lastEd]['Type']]['Complex'] === 'Y') {
        BPRIAParams[lastEd]['Options'] = window.currentType['Options'];
    } else {
        delete BPRIAParams[lastEd]['Options'];
    }

    // --- conditional required ---
    var reqMode = document.getElementById('id_fri_required_mode').value;
    if (reqMode === 'always') {
        BPRIAParams[lastEd]['Required']     = 'Y';
        BPRIAParams[lastEd]['DependOnField'] = '';
        BPRIAParams[lastEd]['DependOnValue'] = '';
    } else if (reqMode === 'conditional') {
        var depField = document.getElementById('id_fri_depend_field').value;
        var depValEl = document.getElementById('id_fri_depend_value');
        var depVal   = depValEl ? depValEl.value : '';

        if (!depField) {
            alert('<?= GetMessageJS("BPRIDC_PD_F_COND_SELECT_FIELD") ?>');
            BX.closeWait();
            return;
        }
        BPRIAParams[lastEd]['Required']      = 'N';
        BPRIAParams[lastEd]['DependOnField'] = depField;
        BPRIAParams[lastEd]['DependOnValue'] = depVal;
    } else {
        BPRIAParams[lastEd]['Required']      = 'N';
        BPRIAParams[lastEd]['DependOnField'] = '';
        BPRIAParams[lastEd]['DependOnValue'] = '';
    }

    objFields.GetFieldInputValue(BPRIAParams[lastEd], { Field: 'fri_default', Form: '<?= $formName ?>' },
        function (v) {
            if (typeof v === 'object') v = v[0];
            BPRIAParams[lastEd]['Default'] = v;
            if (N === false) {
                BPRIAParamAddParam(lastEd, BPRIAParams[lastEd]);
            } else {
                BPRIAParamFillParam(lastEd, BPRIAParams[lastEd]);
            }
            BPRIAEditForm(false);
            BX.closeWait();
        }
    );
}

function moveRowUp(a) {
    var row = a.parentNode.parentNode;
    if (row.previousSibling && row.previousSibling.previousSibling) {
        row.parentNode.insertBefore(row, row.previousSibling);
    }
}

function moveRowDown(a) {
    var row = a.parentNode.parentNode;
    if (row.nextSibling) {
        if (row.nextSibling.nextSibling) {
            row.parentNode.insertBefore(row, row.nextSibling.nextSibling);
        } else {
            row.parentNode.appendChild(row);
        }
    }
}

function BPRIAStart() {
    for (var id in BPRIAParams) {
        BPRIAParamAddParam(id, BPRIAParams[id]);
    }
}

setTimeout(BPRIAStart, 0);

// ============================================================
// BPRIDC — conditional required field management
// ============================================================

// Show/hide the conditional config block based on selected mode
function BPRIDCToggleCondBlock() {
    var mode     = document.getElementById('id_fri_required_mode').value;
    var condRow  = document.getElementById('id_tr_bpridc_cond');
    condRow.style.display = (mode === 'conditional') ? '' : 'none';

    if (mode === 'conditional') {
        BPRIDCRefreshDependFieldList();
    }
}

// Rebuild the "field" select with all OTHER fields currently in BPRIAParams
function BPRIDCRefreshDependFieldList(selectedField, selectedValue) {
    var sel         = document.getElementById('id_fri_depend_field');
    var currentName = document.getElementById('id_fri_name').value;

    // Clear and add placeholder
    sel.innerHTML = '<option value=""><?= GetMessageJS("BPRIDC_PD_F_COND_SELECT_FIELD") ?></option>';

    for (var id in BPRIAParams) {
        var p = BPRIAParams[id];
        if (!p.Name || p.Name === currentName) { continue; }
        var opt = document.createElement('option');
        opt.value   = p.Name;
        opt.text    = (p.Title || p.Name) + '  [' + p.Name + ']';
        if (selectedField && selectedField === p.Name) { opt.selected = true; }
        sel.appendChild(opt);
    }

    // Populate values for the currently selected trigger field
    BPRIDCRefreshDependValue(document.getElementById('id_fri_depend_field').value, selectedValue);
}

// Rebuild the "value" control for the selected trigger field
function BPRIDCRefreshDependValue(triggerFieldName, preselect) {
    var container = document.getElementById('id_bpridc_depend_value_wrap');
    var targetParam = null;
    for (var id in BPRIAParams) {
        if (BPRIAParams[id].Name === triggerFieldName) { targetParam = BPRIAParams[id]; break; }
    }

    if (targetParam && targetParam.Options && typeof targetParam.Options === 'object') {
        // Build a select from the Options of the trigger field
        var html = '<select id="id_fri_depend_value"><option value=""><?= GetMessageJS("BPRIDC_PD_F_COND_SELECT_VALUE") ?></option>';
        var opts = targetParam.Options;
        if (Array.isArray(opts)) {
            opts.forEach(function (item, idx) {
                var val   = (item.ID   !== undefined) ? item.ID   : String(idx);
                var label = (item.VALUE !== undefined) ? item.VALUE : String(item);
                html += '<option value="' + HTMLEncode(String(val)) + '"' +
                    (preselect === String(val) ? ' selected' : '') + '>' +
                    HTMLEncode(String(label)) + '</option>';
            });
        } else {
            for (var k in opts) {
                html += '<option value="' + HTMLEncode(k) + '"' +
                    (preselect === k ? ' selected' : '') + '>' +
                    HTMLEncode(opts[k]) + '</option>';
            }
        }
        html += '</select>';
        container.innerHTML = html;
    } else {
        // Fallback: plain text input
        container.innerHTML =
            '<input type="text" id="id_fri_depend_value" size="25"' +
            ' placeholder="<?= GetMessageJS("BPRIDC_PD_F_COND_VALUE") ?>"' +
            ' value="' + HTMLEncode(preselect || '') + '">';
    }
}
</script>

<?php
/** @var \Bitrix\Bizproc\Activity\PropertiesDialog $dialog */

$renderName = function ($field) {
    return isset($field['Required']) && $field['Required']
        ? sprintf('<span class="adm-required-field">%s:</span>', htmlspecialcharsbx($field['Name']))
        : htmlspecialcharsbx($field['Name']) . ':';
};

$renderField = function (array $field, bool $allowSelection) use ($dialog) {
    $fieldType = $dialog->getFieldTypeObject($field);
    return $fieldType->renderControl(
        ['Form' => $dialog->getFormName(), 'Field' => $field['FieldName']],
        $dialog->getCurrentValue($field['FieldName']),
        $allowSelection,
        0
    );
};
?>

<!-- ============================================================
     LIST VIEW — shown when not editing a field
     ============================================================ -->
<tr id="ria_pd_list_form">
    <td colspan="2">
        <table width="100%" class="adm-detail-content-table edit-table">
            <?php foreach ($dialog->getMap() as $fieldId => $field): ?>
                <?php
                if ($fieldId === 'TimeoutDurationType') { continue; }
                if (!empty($field['Settings']['Hidden'])) { continue; }
                ?>
                <tr>
                    <td align="right" width="40%" class="adm-detail-content-cell-l"><?= $renderName($field) ?></td>
                    <td width="60%" class="adm-detail-content-cell-r">
                        <?php
                        echo $renderField($field, true);
                        if ($fieldId === 'TimeoutDuration')
                        {
                            echo $renderField($dialog->getMap()['TimeoutDurationType'], false);
                            echo \CBPViewHelper::renderDelayLimitsInfo();
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="2"><br><b><?= GetMessage('BPSFA_PD_FIELDS') ?></b><br><br></td>
            </tr>
        </table>

        <table width="100%" id="ria_pd_list_table" class="internal">
            <tr class="heading">
                <td><?= GetMessage('BPSFA_PD_F_NAME') ?></td>
                <td><?= GetMessage('BPSFA_PD_F_TITLE') ?></td>
                <td><?= GetMessage('BPSFA_PD_F_TYPE') ?></td>
                <td><?= GetMessage('BPSFA_PD_F_REQ') ?></td>
                <td><?= GetMessage('BPSFA_PD_F_MULT') ?></td>
                <td>&nbsp;</td>
            </tr>
        </table>
        <br>
        <span style="padding:10px;">
            <a href="javascript:void(0);" onclick="BPRIANewParam()"><?= GetMessage('BPSFA_PD_F_ADD') ?></a>
        </span>
    </td>
</tr>


<!-- ============================================================
     EDIT FORM — shown when adding / editing a field
     ============================================================ -->
<tr id="ria_pd_edit_form">
    <td colspan="2">
        <table width="100%" class="adm-detail-content-table edit-table">

            <tr>
                <td align="right" width="40%" class="adm-detail-content-cell-l"></td>
                <td width="60%" class="adm-detail-content-cell-r">
                    <br><br><b><?= GetMessage('BPSFA_PD_FIELD') ?></b>
                </td>
            </tr>

            <tr>
                <td align="right" width="40%" class="adm-detail-content-cell-l">
                    <span class="adm-required-field"><?= GetMessage('BPSFA_PD_F_TITLE') ?>:</span>
                </td>
                <td width="60%" class="adm-detail-content-cell-r">
                    <input type="text" size="50" name="fri_title" id="id_fri_title" value="">
                </td>
            </tr>

            <tr>
                <td align="right" width="40%" class="adm-detail-content-cell-l">
                    <span class="adm-required-field"><?= GetMessage('BPSFA_PD_F_NAME') ?>:</span>
                </td>
                <td width="60%" class="adm-detail-content-cell-r">
                    <input type="text" size="20" name="fri_name" id="id_fri_name" value="">
                </td>
            </tr>

            <tr>
                <td align="right" width="40%" class="adm-detail-content-cell-l">
                    <?= GetMessage('BPSFA_PD_F_DESCR') ?>:
                </td>
                <td width="60%" class="adm-detail-content-cell-r">
                    <textarea cols="50" rows="2" name="fri_description" id="id_fri_description"></textarea>
                </td>
            </tr>

            <tr>
                <td align="right" width="40%" class="adm-detail-content-cell-l">
                    <span class="adm-required-field"><?= GetMessage('BPSFA_PD_F_TYPE') ?>:</span>
                </td>
                <td width="60%" class="adm-detail-content-cell-r">
                    <select name="fri_type" id="id_fri_type"
                            onchange="BPRIASwitchTypeControl(this.options[this.selectedIndex].value)">
                        <?php foreach ($arFieldTypes as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v['Name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr id="id_tr_pbria_options" style="display:none">
                <td align="right" width="40%" class="adm-detail-content-cell-l" valign="top"
                    id="id_td_fri_options_promt"><?= GetMessage('BPSFA_PD_F_VLIST') ?>:</td>
                <td width="60%" id="id_td_fri_options" class="adm-detail-content-cell-r"></td>
            </tr>

            <tr>
                <td align="right" width="40%" class="adm-detail-content-cell-l"><?= GetMessage('BPSFA_PD_F_DEF') ?>:</td>
                <td width="60%" id="id_td_document_value" class="adm-detail-content-cell-r"></td>
            </tr>

            <!-- ---- REQUIRED MODE (replaces checkbox) ---- -->
            <tr>
                <td align="right" width="40%" class="adm-detail-content-cell-l">
                    <?= Loc::getMessage('BPRIDC_PD_F_REQ_MODE') ?>:
                </td>
                <td width="60%" class="adm-detail-content-cell-r">
                    <select id="id_fri_required_mode" onchange="BPRIDCToggleCondBlock()">
                        <option value="no"><?= Loc::getMessage('BPRIDC_PD_F_REQ_NO') ?></option>
                        <option value="always"><?= Loc::getMessage('BPRIDC_PD_F_REQ_ALWAYS') ?></option>
                        <option value="conditional"><?= Loc::getMessage('BPRIDC_PD_F_REQ_COND') ?></option>
                    </select>
                </td>
            </tr>

            <!-- ---- CONDITIONAL CONFIG (hidden unless mode=conditional) ---- -->
            <tr id="id_tr_bpridc_cond" style="display:none; background:#fffbe6;">
                <td align="right" width="40%" class="adm-detail-content-cell-l" valign="top">
                    <?= Loc::getMessage('BPRIDC_PD_F_COND_FIELD') ?>:
                </td>
                <td width="60%" class="adm-detail-content-cell-r">
                    <select id="id_fri_depend_field"
                            onchange="BPRIDCRefreshDependValue(this.value, '')">
                        <option value=""><?= Loc::getMessage('BPRIDC_PD_F_COND_SELECT_FIELD') ?></option>
                    </select>
                    <br>
                    <span style="margin-top:4px;display:inline-block;">
                        <?= Loc::getMessage('BPRIDC_PD_F_COND_VALUE') ?>:&nbsp;
                        <span id="id_bpridc_depend_value_wrap">
                            <input type="text" id="id_fri_depend_value" size="25"
                                   placeholder="<?= htmlspecialcharsbx(Loc::getMessage('BPRIDC_PD_F_COND_VALUE')) ?>">
                        </span>
                    </span>
                    <br>
                    <small style="color:#888;"><?= Loc::getMessage('BPRIDC_PD_F_COND_HINT') ?></small>
                </td>
            </tr>

            <tr>
                <td align="right" width="40%" class="adm-detail-content-cell-l"><?= GetMessage('BPSFA_PD_F_MULT') ?>:</td>
                <td width="60%" class="adm-detail-content-cell-r">
                    <input type="checkbox" name="fri_multiple" id="id_fri_multiple" value="Y">
                </td>
            </tr>

            <tr>
                <td align="right" width="40%" class="adm-detail-content-cell-l"></td>
                <td width="60%" class="adm-detail-content-cell-r">
                    <input type="hidden" name="fri_id" id="id_fri_id">
                    <input type="button" value="<?= GetMessage('BPSFA_PD_SAVE') ?>"
                           onclick="BPRIAParamSaveForm()" id="dpsavebuttonform"
                           title="<?= GetMessage('BPSFA_PD_SAVE_HINT') ?>">
                    <input type="button" value="<?= GetMessage('BPSFA_PD_CANCEL') ?>"
                           onclick="BPRIAEditForm(false);" id="dpcancelbuttonform"
                           title="<?= GetMessage('BPSFA_PD_CANCEL_HINT') ?>">
                </td>
            </tr>

        </table>
    </td>
</tr>

<script>
document.getElementById('ria_pd_edit_form').style.display = 'none';
try { document.getElementById('ria_pd_list_form').style.display = 'table-row'; }
catch(e) { document.getElementById('ria_pd_list_form').style.display = 'inline'; }
</script>
