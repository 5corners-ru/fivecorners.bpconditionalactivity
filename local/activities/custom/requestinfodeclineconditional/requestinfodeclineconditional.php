<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Bizproc;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Load parent activity
$runtime = CBPRuntime::GetRuntime();
$runtime->IncludeActivityFile('RequestInformationOptionalActivity');

class CBPRequestInfoDeclineConditional extends CBPRequestInformationOptionalActivity
{
    const ACTIVITY = 'RequestInfoDeclineConditional';

    // -------------------------------------------------------------------------
    // ShowTaskForm — renders the HTML form the user fills in when executing task
    // Adds client-side JavaScript for conditional required fields
    // -------------------------------------------------------------------------
    public static function ShowTaskForm($arTask, $userId, $userName = '', $arRequest = null)
    {
        [$form, $buttons] = parent::ShowTaskForm($arTask, $userId, $userName, $arRequest);

        $depRules = static::extractDepRules($arTask);

        if (!empty($depRules))
        {
            // Inject rules as a data attribute, NOT a <script> tag.
            // Bitrix renders this form via innerHTML, which browsers refuse to execute injected scripts.
            // bpridc-conditional.js (loaded globally via init.php) polls for this div and applies logic.
            $rulesJson = json_encode($depRules, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
            $form = '<div id="bpridc-task-rules" data-rules="' . htmlspecialchars($rulesJson, ENT_QUOTES) . '" style="display:none"></div>' . $form;
        }

        return [$form, $buttons];
    }

    // -------------------------------------------------------------------------
    // getTaskControls — modern API (Bitrix24 responsive interface)
    // Passes dependency metadata so future front-end enhancements can use it
    // -------------------------------------------------------------------------
    public static function getTaskControls($task)
    {
        $controls = parent::getTaskControls($task);

        $depMap = [];
        if (!empty($task['PARAMETERS']['REQUEST']))
        {
            $paramMap = [];
            foreach ($task['PARAMETERS']['REQUEST'] as $param)
            {
                $paramMap[$param['Name']] = $param;
            }

            foreach ($task['PARAMETERS']['REQUEST'] as $param)
            {
                if (!empty($param['DependOnField']) && isset($param['DependOnValue']) && $param['DependOnValue'] !== '')
                {
                    $triggerValue = (string)$param['DependOnValue'];

                    // Resolve UF:iblock_element display names to element IDs so the
                    // value matches what Bitrix stores in the hidden input (numeric ID).
                    $triggerParam = $paramMap[$param['DependOnField']] ?? null;
                    if (
                        $triggerParam !== null
                        && ($triggerParam['Type'] ?? '') === 'UF:iblock_element'
                        && !ctype_digit($triggerValue)
                        && \Bitrix\Main\Loader::includeModule('iblock')
                    )
                    {
                        $iblockId = (int)($triggerParam['Options'] ?? 0);
                        $resolved = $iblockId > 0 ? static::resolveIblockElementId($iblockId, $triggerValue) : null;
                        if ($resolved !== null)
                        {
                            $triggerValue = $resolved;
                        }
                    }

                    $depMap[$param['Name']] = [
                        'DependOnField' => $param['DependOnField'],
                        'DependOnValue' => $triggerValue,
                    ];
                }
            }
        }

        if (!empty($depMap) && !empty($controls['FIELDS']))
        {
            foreach ($controls['FIELDS'] as &$field)
            {
                $fieldId = $field['Id'] ?? '';
                if (isset($depMap[$fieldId]))
                {
                    $field['DependOnField'] = $depMap[$fieldId]['DependOnField'];
                    $field['DependOnValue'] = $depMap[$fieldId]['DependOnValue'];
                    // Don't mark as statically required — the JS/server handles it
                    $field['Required'] = false;
                }
            }
            unset($field);
        }

        return $controls;
    }

    // -------------------------------------------------------------------------
    // validateTaskEventParameters — server-side conditional required validation
    // Called after the user submits the task form
    // -------------------------------------------------------------------------
    protected static function validateTaskEventParameters($arTask, $eventParameters)
    {
        parent::validateTaskEventParameters($arTask, $eventParameters);

        if (empty($arTask['PARAMETERS']['REQUEST']) || !is_array($arTask['PARAMETERS']['REQUEST']))
        {
            return true;
        }

        $responce = $eventParameters['RESPONCE'] ?? [];
        $isCancel  = !empty($eventParameters['CANCEL']);

        // Build param map once for trigger field type lookups
        $paramMap = [];
        foreach ($arTask['PARAMETERS']['REQUEST'] as $param)
        {
            $paramMap[$param['Name']] = $param;
        }

        foreach ($arTask['PARAMETERS']['REQUEST'] as $param)
        {
            if (empty($param['DependOnField']) || !isset($param['DependOnValue']) || $param['DependOnValue'] === '')
            {
                continue;
            }

            $triggerFieldName = $param['DependOnField'];
            $triggerValue     = (string)$param['DependOnValue'];

            // Resolve UF:iblock_element display name to element ID — the form submits
            // the numeric ID, not the text name stored in DependOnValue.
            $triggerParam = $paramMap[$triggerFieldName] ?? null;
            if (
                $triggerParam !== null
                && ($triggerParam['Type'] ?? '') === 'UF:iblock_element'
                && !ctype_digit($triggerValue)
                && \Bitrix\Main\Loader::includeModule('iblock')
            )
            {
                $iblockId = (int)($triggerParam['Options'] ?? 0);
                $resolved = $iblockId > 0 ? static::resolveIblockElementId($iblockId, $triggerValue) : null;
                if ($resolved !== null)
                {
                    $triggerValue = $resolved;
                }
            }

            $currentTrigger = $responce[$triggerFieldName] ?? null;

            $isTriggered = false;
            if (is_array($currentTrigger))
            {
                $isTriggered = in_array($triggerValue, array_map('strval', $currentTrigger), true);
            }
            else
            {
                $isTriggered = ((string)$currentTrigger === $triggerValue);
            }

            // Skip validation on cancel unless SaveVariables is set
            if ($isCancel && !CBPHelper::getBool($arTask['PARAMETERS']['SaveVariables'] ?? 'N'))
            {
                continue;
            }

            if ($isTriggered && CBPHelper::isEmptyValue($responce[$param['Name']] ?? null))
            {
                // Find trigger field title for the error message
                $triggerTitle = $triggerFieldName;
                foreach ($arTask['PARAMETERS']['REQUEST'] as $p)
                {
                    if ($p['Name'] === $triggerFieldName)
                    {
                        $triggerTitle = $p['Title'];
                        break;
                    }
                }

                self::$errors->setError(
                    new Error(
                        Loc::getMessage('BPRIDC_COND_REQUIRED_ERROR', [
                            '#PARAM#'    => $param['Title'],
                            '#TRIGGER#'  => $triggerTitle . ' = ' . $triggerValue,
                        ]),
                        0,
                        $param['Name']
                    )
                );
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // GetPropertiesDialog — returns the PropertiesDialog using OUR properties_dialog.php
    // Must re-implement rather than calling parent to pass __FILE__ from this directory
    // -------------------------------------------------------------------------
    public static function GetPropertiesDialog(
        $documentType,
        $activityName,
        $arWorkflowTemplate,
        $arWorkflowParameters,
        $arWorkflowVariables,
        $arCurrentValues = null,
        $formName = '',
        $popupWindow = null,
        $siteId = ''
    )
    {
        // Fallback for older Bitrix24 that doesn't have PropertiesDialog API
        if (!class_exists('Bitrix\Bizproc\Activity\PropertiesDialog'))
        {
            return static::getPropertiesDialogLegacy(
                $documentType,
                $activityName,
                $arWorkflowTemplate,
                $arWorkflowParameters,
                $arWorkflowVariables,
                $arCurrentValues,
                $formName,
                $popupWindow,
                $siteId
            );
        }

        $documentService = CBPRuntime::getRuntime()->getDocumentService();

        $dialog = new Bizproc\Activity\PropertiesDialog(__FILE__, [
            'documentType'      => $documentType,
            'activityName'      => $activityName,
            'workflowTemplate'  => $arWorkflowTemplate,
            'workflowParameters'=> $arWorkflowParameters,
            'workflowVariables' => $arWorkflowVariables,
            'currentValues'     => $arCurrentValues,
            'formName'          => $formName,
            'siteId'            => $siteId,
        ]);

        $dialog->setMap(static::getPropertiesDialogMap());

        $currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $requestedInformation =
            isset($currentActivity['Properties']['RequestedInformation'])
            && is_array($currentActivity['Properties']['RequestedInformation'])
                ? $currentActivity['Properties']['RequestedInformation']
                : []
        ;

        $requestedVariables = [];
        foreach ($requestedInformation as $variable)
        {
            if ($variable['Name'] === '')
            {
                continue;
            }

            $variable['Required'] = CBPHelper::getBool($variable['Required']) ? 'Y' : 'N';
            $variable['Multiple'] = CBPHelper::getBool($variable['Multiple']) ? 'Y' : 'N';
            // DependOnField / DependOnValue are preserved as-is

            $requestedVariables[] = $variable;
        }

        $arFieldTypes = $documentService->getDocumentFieldTypes($documentType);
        unset($arFieldTypes['N:Sequence'], $arFieldTypes['UF:resourcebooking']);

        $arDocumentFields  = $documentService->getDocumentFields($documentType);
        $javascriptFunctions = $documentService->getJSFunctionsForFields(
            $documentType,
            'objFields',
            $arDocumentFields,
            $arFieldTypes
        );

        $dialog->setRuntimeData([
            'requestedInformation' => $requestedVariables,
            'arDocumentFields'     => $arDocumentFields,
            'arFieldTypes'         => $arFieldTypes,
            'javascriptFunctions'  => $javascriptFunctions,
            'formName'             => $formName,
            'popupWindow'          => &$popupWindow,
        ]);

        return $dialog;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    // Legacy dialog for Bitrix24 without Bitrix\Bizproc\Activity\PropertiesDialog.
    // Uses ob_start + include pattern. Standard activity fields (Subject, User, Timeout)
    // are not rendered — only the RequestedInformation table with our DependOnField UI.
    private static function getPropertiesDialogLegacy(
        $documentType,
        $activityName,
        $arWorkflowTemplate,
        $arWorkflowParameters,
        $arWorkflowVariables,
        $arCurrentValues = null,
        $formName = '',
        $popupWindow = null,
        $siteId = ''
    )
    {
        try
        {
            $runtime = CBPRuntime::GetRuntime();
            $documentService = $runtime->GetDocumentService();

            $currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName(
                $arWorkflowTemplate, $activityName
            );

            if (!is_array($arCurrentValues))
            {
                $arCurrentValues = is_array($currentActivity) ? ($currentActivity['Properties'] ?? []) : [];
            }

            $requestedInformation = [];
            if (!empty($currentActivity['Properties']['RequestedInformation']))
            {
                foreach ((array)$currentActivity['Properties']['RequestedInformation'] as $variable)
                {
                    if (($variable['Name'] ?? '') === '') { continue; }
                    $variable['Required'] = CBPHelper::getBool($variable['Required'] ?? '') ? 'Y' : 'N';
                    $variable['Multiple'] = CBPHelper::getBool($variable['Multiple'] ?? '') ? 'Y' : 'N';
                    $requestedInformation[] = $variable;
                }
            }

            $arFieldTypes = $documentService->GetDocumentFieldTypes($documentType);
            unset($arFieldTypes['N:Sequence'], $arFieldTypes['UF:resourcebooking']);

            $arDocumentFields  = $documentService->GetDocumentFields($documentType);
            $javascriptFunctions = $documentService->GetJSFunctionsForFields(
                $documentType,
                'objFields',
                $arDocumentFields,
                $arFieldTypes
            );

            $dialog = null; // Signal to properties_dialog.php: legacy mode

            ob_start();
            /** @noinspection PhpIncludeInspection */
            include __DIR__ . '/properties_dialog.php';
            return ob_get_clean();
        }
        catch (\Throwable $e)
        {
            if (ob_get_level() > 0) { ob_end_clean(); }
            return '<tr><td colspan="2" style="color:red;padding:10px;font-family:monospace;">'
                . '<b>BPRIDC Legacy Dialog Error:</b><br>'
                . htmlspecialchars($e->getMessage()) . '<br>'
                . '<small>' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</small><br><br>'
                . '<pre style="font-size:11px;overflow:auto;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
                . '</td></tr>';
        }
    }

    private static function extractDepRules(array $arTask): array
    {
        $rules = [];
        if (empty($arTask['PARAMETERS']['REQUEST']) || !is_array($arTask['PARAMETERS']['REQUEST']))
        {
            return $rules;
        }

        // Build param map for trigger field type lookups
        $paramMap = [];
        foreach ($arTask['PARAMETERS']['REQUEST'] as $param)
        {
            $paramMap[$param['Name']] = $param;
        }

        foreach ($arTask['PARAMETERS']['REQUEST'] as $param)
        {
            if (empty($param['DependOnField']) || !isset($param['DependOnValue']) || $param['DependOnValue'] === '')
            {
                continue;
            }

            $triggerValue = (string)$param['DependOnValue'];

            // For UF:iblock_element trigger fields the designer stores display name (e.g. "Низкая ЗП"),
            // but the runtime hidden input holds the element ID (e.g. "78"). Resolve here.
            $triggerParam = $paramMap[$param['DependOnField']] ?? null;
            if (
                $triggerParam !== null
                && ($triggerParam['Type'] ?? '') === 'UF:iblock_element'
                && !ctype_digit($triggerValue)
                && \Bitrix\Main\Loader::includeModule('iblock')
            )
            {
                $iblockId = (int)($triggerParam['Options'] ?? 0);
                $resolved = $iblockId > 0 ? static::resolveIblockElementId($iblockId, $triggerValue) : null;
                if ($resolved !== null)
                {
                    $triggerValue = $resolved;
                }
            }

            $rules[] = [
                'depField'     => static::CONTROLS_PREFIX . $param['Name'],
                'depTitle'     => $param['Title'],
                'triggerField' => static::CONTROLS_PREFIX . $param['DependOnField'],
                'triggerValue' => $triggerValue,
            ];
        }

        return $rules;
    }

    private static function resolveIblockElementId(int $iblockId, string $name): ?string
    {
        $rs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, '=NAME' => $name, 'ACTIVE' => 'Y'],
            false,
            ['nTopCount' => 1],
            ['ID']
        );
        $el = $rs->GetNext();
        return $el ? (string)$el['ID'] : null;
    }

    private static function buildConditionalScript(array $depRules): string
    {
        $rulesJson = json_encode($depRules, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
<script>
(function () {
    'use strict';

    var BPRIDC_RULES = {$rulesJson};

    // Returns array of string values currently selected in the named field(s)
    function getFieldValues(fieldName) {
        var values = [];
        var inputs = document.querySelectorAll(
            '[name="' + fieldName + '"], [name="' + fieldName + '[]"]'
        );
        inputs.forEach(function (inp) {
            if (inp.type === 'checkbox' || inp.type === 'radio') {
                if (inp.checked) { values.push(String(inp.value)); }
            } else if (inp.tagName === 'SELECT') {
                for (var i = 0; i < inp.options.length; i++) {
                    if (inp.options[i].selected && inp.options[i].value !== '') {
                        values.push(String(inp.options[i].value));
                    }
                }
            } else if (inp.value !== '') {
                values.push(String(inp.value));
            }
        });
        return values;
    }

    // Returns the <tr> that visually contains the given field
    function getFieldRow(fieldName) {
        var inp = document.querySelector(
            '[name="' + fieldName + '"], [name="' + fieldName + '[]"]'
        );
        return inp ? inp.closest('tr') : null;
    }

    // Update visual * indicator for one rule
    function updateStar(row, isRequired) {
        if (!row) { return; }
        var nameCell = row.querySelector('td.bizproc-field-name');
        if (!nameCell) { return; }
        var star = nameCell.querySelector('.bpridc-cond-star');
        if (isRequired && !star) {
            star = document.createElement('span');
            star.className = 'bpridc-cond-star';
            star.style.cssText = 'color:red;margin-right:2px;';
            star.textContent = '*';
            nameCell.insertBefore(star, nameCell.firstChild);
        } else if (!isRequired && star) {
            star.parentNode.removeChild(star);
        }
    }

    // Evaluate all rules and update UI state
    function applyAllRules() {
        BPRIDC_RULES.forEach(function (rule) {
            var triggered = getFieldValues(rule.triggerField).indexOf(rule.triggerValue) !== -1;
            var row = getFieldRow(rule.depField);
            if (row) {
                row.style.display = triggered ? '' : 'none';
                row.setAttribute('data-bpridc-required', triggered ? '1' : '0');
                if (!triggered) {
                    row.style.outline = '';
                }
            }
            updateStar(row, triggered);
        });
    }

    // Client-side validation before submit (UX only — server also validates)
    function clientValidate() {
        var errors = [];
        document.querySelectorAll('tr[data-bpridc-required="1"]').forEach(function (row) {
            var inputs = row.querySelectorAll(
                'input:not([type="hidden"]):not([type="submit"]):not([type="button"]),' +
                'select, textarea'
            );
            var hasValue = false;
            inputs.forEach(function (inp) {
                if (inp.type === 'checkbox' || inp.type === 'radio') {
                    if (inp.checked) { hasValue = true; }
                } else if (inp.tagName === 'SELECT') {
                    for (var i = 0; i < inp.options.length; i++) {
                        if (inp.options[i].selected && inp.options[i].value !== '') {
                            hasValue = true;
                        }
                    }
                } else if (inp.value && inp.value.trim()) {
                    hasValue = true;
                }
            });
            if (!hasValue) {
                row.style.outline = '2px solid red';
                var nc = row.querySelector('td.bizproc-field-name');
                errors.push(nc ? nc.textContent.replace('*', '').trim() : '?');
            } else {
                row.style.outline = '';
            }
        });
        if (errors.length > 0) {
            alert('Заполните обязательные поля:\\n\\u2022 ' + errors.join('\\n\\u2022 '));
            return false;
        }
        return true;
    }

    function attachListeners() {
        // Standard events for real <select>, checkbox, radio, text inputs
        BPRIDC_RULES.forEach(function (rule) {
            document.querySelectorAll(
                '[name="' + rule.triggerField + '"],' +
                '[name="' + rule.triggerField + '[]"]'
            ).forEach(function (el) {
                el.addEventListener('change', applyAllRules);
                el.addEventListener('input', applyAllRules);
            });
        });

        // MutationObserver: catches setAttribute('value', x) on hidden inputs
        // and any DOM changes from Bitrix's custom dropdown re-rendering
        if (typeof MutationObserver !== 'undefined') {
            var form = document.querySelector('form');
            if (form) {
                new MutationObserver(function (mutations) {
                    for (var i = 0; i < mutations.length; i++) {
                        var m = mutations[i];
                        if (m.type === 'attributes' || m.type === 'childList') {
                            applyAllRules();
                            return;
                        }
                    }
                }).observe(form, {
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['value', 'class', 'selected'],
                    childList: true,
                });
            }
        }

        // Polling fallback: catches .value = x assignments that bypass
        // both events and attribute mutations (Bitrix hidden-input pattern)
        var _lastSnapshot = {};
        setInterval(function () {
            var changed = false;
            BPRIDC_RULES.forEach(function (rule) {
                var snap = JSON.stringify(getFieldValues(rule.triggerField));
                if (_lastSnapshot[rule.triggerField] !== snap) {
                    _lastSnapshot[rule.triggerField] = snap;
                    changed = true;
                }
            });
            if (changed) { applyAllRules(); }
        }, 250);

        // Hook submit buttons (approve only — cancel skips required check)
        document.querySelectorAll('input[name="approve"]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (!clientValidate()) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, true);
        });
    }

    function init() {
        attachListeners();
        applyAllRules();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 0);
    }
}());
</script>
HTML;
    }
}
