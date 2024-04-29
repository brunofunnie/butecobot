<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'evento',
    'description' => 'Gerencia eventos para apostas',
    'options' => [
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'criar',
            'description' => 'Cria evento',
            'options' => [
                [
                    'type' => Option::STRING,
                    'name' => 'nome',
                    'description' => 'Nome do evento',
                    'required' => true,
                ],
                [
                    'type' => Option::STRING,
                    'name' => 'a',
                    'description' => 'Opção A',
                    'required' => true,
                ],
                [
                    'type' => Option::STRING,
                    'name' => 'b',
                    'description' => 'Opção B',
                    'required' => true,
                ],
            ]
        ],
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'iniciar',
            'description' => 'Inicia evento',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'id',
                    'description' => 'ID do evento',
                    'required' => true,
                ],
            ]
        ],
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'fechar',
            'description' => 'Fecha evento e não recebe mais apostas',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'id',
                    'description' => 'ID do evento',
                    'required' => true,
                ],
            ]
        ],
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'encerrar',
            'description' => 'Encerra evento e paga as apostas',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'id',
                    'description' => 'ID do evento',
                    'required' => true,
                ],
                [
                    'type' => Option::STRING,
                    'name' => 'opcao',
                    'description' => 'Opção A ou B.',
                    'required' => true,
                    'choices' => [
                        [
                            'name' => 'A',
                            'value' => 'A'
                        ],
                        [
                            'name' => 'B',
                            'value' => 'B'
                        ],
                        [
                            'name' => 'Empate',
                            'value' => 'Empate'
                        ]
                    ]
                ],
            ]
        ],
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'anunciar',
            'description' => 'Anuncia o evento de forma personalizada',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'id',
                    'description' => 'ID do evento',
                    'required' => true,
                ],
                [
                    'type' => Option::STRING,
                    'name' => 'banner',
                    'description' => 'Imagem do banner para utilizar ',
                    'required' => true,
                    'choices' => [
                        [
                            'name' => 'UFC',
                            'value' => 'UFC'
                        ],
                        [
                            'name' => 'Genérica',
                            'value' => 'GENERIC'
                        ],
                        [
                            'name' => 'Libertadores',
                            'value' => 'LIBERTADORES'
                        ],
                        [
                            'name' => 'Zoeira',
                            'value' => 'ZOEIRA'
                        ]
                    ]
                ],
            ]
        ],
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'listar',
            'description' => 'Lista eventos criados e pendentes para iniciar',
        ]
    ]
];
