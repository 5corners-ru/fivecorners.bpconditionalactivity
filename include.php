<?php
use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('fivecorners.bpconditionalactivity', [
    'FiveCorners\\BpConditionalActivity\\EventHandler' => 'lib/EventHandler.php',
]);
