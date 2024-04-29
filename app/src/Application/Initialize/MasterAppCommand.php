<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'mestre',
    'description' => 'Pergunte ao mestre',
    'options' => [
        [
            'type' => Option::STRING,
            'name' => 'pergunta',
            'description' => 'Pergunta para o mestre',
            'required' => true,
        ],
        [
            'type' => Option::INTEGER,
            'name' => 'boost',
            'description' => 'Boost de coins',
            'required' => false,
            'choices' => [
                [
                    'name' => '+50 coins (+500 tokens)',
                    'value' => '50',
                ],
                [
                    'name' => '+100 coins (+1000 tokens)',
                    'value' => '100',
                ],
                [
                    'name' => '+150 coins (+1500 tokens)',
                    'value' => '150',
                ]
            ]
        ],
    ]
];
