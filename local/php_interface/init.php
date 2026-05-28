<?php

use Bitrix\Main\EventManager;

EventManager::getInstance()->addEventHandler('main', 'OnEpilog', function () {
    global $APPLICATION;
    $APPLICATION->AddHeadScript('/local/js/bpridc-conditional.js');

    // For /bizproc/<taskId>/ pages: inject dependency rules so IIFE-2 can apply
    // show/hide logic. WorkflowInfo renders the form via getTaskControls() without
    // calling ShowTaskForm, so #bpridc-task-rules is never in the HTML otherwise.
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (!preg_match('#/bizproc/(\d+)#', $uri, $m)) {
        return;
    }

    $taskId = (int)$m[1];
    if ($taskId <= 0 || !\Bitrix\Main\Loader::includeModule('bizproc')) {
        return;
    }

    $taskDb = \CBPTaskService::GetList(
        [],
        ['ID' => $taskId],
        false,
        ['nTopCount' => 1],
        ['ID', 'PARAMETERS']
    );
    if (!$taskDb || !($task = $taskDb->GetNext())) {
        return;
    }

    $params = $task['PARAMETERS'] ?? [];
    if (is_string($params)) {
        $params = @unserialize($params) ?: [];
    }

    $request = is_array($params) ? ($params['REQUEST'] ?? []) : [];
    if (empty($request)) {
        return;
    }

    // Same prefix as CBPRequestInformationOptionalActivity::CONTROLS_PREFIX
    $prefix = 'bprioact_';
    $rules  = [];

    foreach ($request as $param) {
        if (
            empty($param['DependOnField'])
            || !isset($param['DependOnValue'])
            || (string)$param['DependOnValue'] === ''
        ) {
            continue;
        }

        $rules[] = [
            'depField'     => $prefix . $param['Name'],
            'depTitle'     => $param['Title'] ?? $param['Name'],
            'triggerField' => $prefix . $param['DependOnField'],
            'triggerValue' => (string)$param['DependOnValue'],
        ];
    }

    if (empty($rules)) {
        return;
    }

    $json = json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    $APPLICATION->AddHeadString(
        '<div id="bpridc-task-rules" data-rules="' . htmlspecialchars($json, ENT_QUOTES) . '" style="display:none"></div>'
    );
});
