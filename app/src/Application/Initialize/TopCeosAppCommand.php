<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'top',
    'description' => 'Lista de TOPs',
    'options' => [
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'patroes',
            'description' => 'Lista dos mais ricos do buteco',
        ],
    ]
];
