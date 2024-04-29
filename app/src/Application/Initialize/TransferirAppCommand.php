<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'transferir',
    'description' => 'Transfere coins para outro usuário',
    'options' => [
        [
            'type' => Option::USER,
            'name' => 'usuario',
            'description' => 'Nome do usuário',
            'required' => true,
        ],
        [
            'type' => Option::NUMBER,
            'name' => 'coins',
            'description' => 'Quantidade de coins para transferir',
            'required' => true,
        ],
    ]
];
