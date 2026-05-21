<?php

use Bitrix\Main\EventManager;

// Load global JS for conditional BP task form fields
EventManager::getInstance()->addEventHandler('main', 'OnEpilog', function () {
    global $APPLICATION;
    $APPLICATION->AddHeadScript('/local/js/bpridc-conditional.js');
});
