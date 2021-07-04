<?php declare(strict_types=1);

use phpOMS\Router\RouteVerb;

return [
    '^(\/[a-zA-Z]*\/*|\/)$' => [
        [
            'dest' => '\Web\{APPNAME}\Controller\AppController:viewFront',
            'verb' => RouteVerb::GET,
        ],
    ],
    '^(\/[a-zA-Z]*\/*|\/)/components(\?.*|$)$' => [
        [
            'dest' => '\Web\{APPNAME}\Controller\AppController:viewComponents',
            'verb' => RouteVerb::GET,
        ],
    ],
    '^(\/[a-zA-Z]*\/*|\/)/imprint(\?.*|$)$' => [
        [
            'dest' => '\Web\{APPNAME}\Controller\AppController:viewImprint',
            'verb' => RouteVerb::GET,
        ],
    ],
    '^(\/[a-zA-Z]*\/*|\/)/terms(\?.*|$)$' => [
        [
            'dest' => '\Web\{APPNAME}\Controller\AppController:viewTerms',
            'verb' => RouteVerb::GET,
        ],
    ],
    '^(\/[a-zA-Z]*\/*|\/)/privacy(\?.*|$)$' => [
        [
            'dest' => '\Web\{APPNAME}\Controller\AppController:viewDataPrivacy',
            'verb' => RouteVerb::GET,
        ],
    ],
    '^(\/[a-zA-Z]*\/*|\/)/list(\?.*|$)$' => [
        [
            'dest' => '\Web\{APPNAME}\Controller\AppController:viewList',
            'verb' => RouteVerb::GET,
        ],
    ],
    '^(\/[a-zA-Z]*\/*|\/)/item(\?.*|$)$' => [
        [
            'dest' => '\Web\{APPNAME}\Controller\AppController:viewItem',
            'verb' => RouteVerb::GET,
        ],
    ],
];
