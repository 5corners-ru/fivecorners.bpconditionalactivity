<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Bizproc\Activity\ActivityDescription;
use Bitrix\Bizproc\Activity\Enum\ActivityType;
use Bitrix\Bizproc\Activity\Enum\ActivityNodeType;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arActivityDescription = (new ActivityDescription(
    name: Loc::getMessage('BPRIDC_DESCR_NAME'),
    description: Loc::getMessage('BPRIDC_DESCR_DESCR'),
    type: [ActivityType::ACTIVITY->value],
))
    ->setCategory([
        'ID' => 'other',
    ])
    ->setClass('RequestInfoDeclineConditional')
    ->setJsClass('RequestInformationOptionalActivity')
    ->setReturn([
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
    ])
    ->setNodeType(ActivityNodeType::COMPLEX->value)
    ->setNodeSettings(new \Bitrix\Bizproc\Activity\Dto\NodeSettings(
        ports: new \Bitrix\Bizproc\Activity\Dto\NodePorts(
            input: new \Bitrix\Bizproc\Activity\Dto\PortCollection(
                new \Bitrix\Bizproc\Activity\Dto\Port('i0'),
            ),
            output: new \Bitrix\Bizproc\Activity\Dto\PortCollection(
                new \Bitrix\Bizproc\Activity\Dto\Port('o0', 1, 'ok'),
                new \Bitrix\Bizproc\Activity\Dto\Port('o1', 2, 'cancel'),
            ),
        )
    ))
    ->toArray()
;
