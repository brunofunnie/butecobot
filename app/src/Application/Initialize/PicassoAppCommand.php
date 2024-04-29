<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'picasso',
    'description' => 'Grande pintor e escultor Picasso',
    'options' => [
        [
            'type' => Option::STRING,
            'name' => 'pinte',
            'description' => 'Descreva como quer sua arte e Picasso irá pinta-la (la ele) pra você',
            'required' => true,
        ],
    ]
];
