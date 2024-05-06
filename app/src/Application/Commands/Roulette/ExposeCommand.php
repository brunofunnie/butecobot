<?php

namespace ButecoBot\Application\Commands\Roulette;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Application\Commands\Roulette\RouletteBuilder;
use ButecoBot\Repository\Roulette;
use ButecoBot\Repository\RouletteBet;
use ButecoBot\Repository\User;

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
