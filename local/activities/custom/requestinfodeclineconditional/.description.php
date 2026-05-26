<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arActivityDescription = [
    'NAME'        => Loc::getMessage('BPRIDC_DESCR_NAME'),
    'DESCRIPTION' => Loc::getMessage('BPRIDC_DESCR_DESCR'),
    'TYPE'        => ['activity'],
    'CLASS'       => 'RequestInfoDeclineConditional',
    'JSCLASS'     => 'RequestInformationOptionalActivity',
    'CATEGORY'    => ['ID' => 'other'],
    'NODE_TYPE'   => 2, // CBPActivityNodeType::Complex
    'RETURN'      => [
        'TaskId' => [
            'NAME' => 'ID',
            'TYPE' => 'int',
        ],
        'Comments' => [
            'NAME' => Loc::getMessage('BPRIDC_DESCR_CM_1'),
            'TYPE' => 'string',
        ],
        'IsTimeout' => [
            'NAME' => Loc::getMessage('BPRIDC_DESCR_TA1'),
            'TYPE' => 'int',
        ],
        'InfoUser' => [
            'NAME' => Loc::getMessage('BPRIDC_DESCR_LU'),
            'TYPE' => 'user',
        ],
    ],
];
