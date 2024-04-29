<?php

namespace Chorume\Application\Commands\Roulette;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Application\Commands\Roulette\RouletteBuilder;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;

class ExposeCommand extends Command
{
    private RouletteBuilder $rouletteBuilder;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
        $this->rouletteBuilder = new RouletteBuilder(
            $this->discord,
            $this->config,
            $this->redis,
            $this->userRepository,
            $this->rouletteRepository,
            $this->rouletteBetRepository
        );
    }

    public function handle(Interaction $interaction): void
    {
        $rouletteId = $interaction->data->options['apostar']->options['id']->value;

        $this->rouletteBuilder->build($interaction, $rouletteId);
    }
}
