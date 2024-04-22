<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'roleta',
    'description' => 'Gerencia Roletas para apostas',
    'options' => [
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'criar',
            'description' => 'Cria Roletas',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'valor',
                    'description' => 'Quantidade de coins para apostar na roleta',
                    'required' => true,
                ],
                [
                    'type' => Option::STRING,
                    'name' => 'nome',
                    'description' => 'Nome da Roleta',
                    'required' => false,
                ]
            ]
        ],
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'fechar',
            'description' => 'Fecha Roleta e não recebe mais apostas',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'id',
                    'description' => 'ID da Roleta',
                    'required' => true,
                ],
            ]
        ],

        [
            'type' => Option::SUB_COMMAND,
            'name' => 'listar',
            'description' => 'Lista Roletas criados e pendentes para iniciar',
        ],

        [
            'type' => Option::SUB_COMMAND,
            'name' => 'girar',
            'description' => 'Gira a roleta e paga as apostas',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'id',
                    'description' => 'ID do Roleta',
                    'required' => true,
                ],

            ]
        ],
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'apostar',
            'description' => 'Abre para apostas',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'id',
                    'description' => 'ID do Roleta',
                    'required' => true,
                ],

            ]
        ]
    ]
];
