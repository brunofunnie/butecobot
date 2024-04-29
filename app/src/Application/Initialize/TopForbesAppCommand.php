<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'top',
    'description' => 'Lista de TOPs',
    'options' => [
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'forbes',
            'description' => 'Lista dos mais ricos do servidor',
        ],
    ]
];
