<?php

namespace Chorume\Application\Commands\Roulette\Entities;

class GameData
{
    public int $AmountRed = 0;
    public int $AmountGreen = 0;
    public int $AmountBlack = 0;
    public int $AmountTotal = 0;
    public array $jogadores;

    public function __construct(
        public int $rouletteId = 0
    )
    {
        $this->AmountRed = 0;
        $this->AmountGreen = 0;
        $this->AmountBlack = 0;
        $this->AmountTotal = 0;
        $this->jogadores = [];
    }
}
