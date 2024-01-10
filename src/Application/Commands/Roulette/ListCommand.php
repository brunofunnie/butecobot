<?php

namespace Chorume\Application\Commands\Roulette;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Discord\Parts\Embed\Embed;
use Chorume\Repository\User;
use Predis\Client as RedisClient;

class ListCommand extends Command
{
    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
    }

    public function handle(Interaction $interaction): void
    {
        $interaction->acknowledgeWithResponse()->then(function () use ($interaction) {
            $roulettesOpen = $this->rouletteRepository->listEventsOpen();
            $roulettesClosed = $this->rouletteRepository->listEventsClosed();

            if (!is_array($roulettesOpen)) {
                $roulettesOpen = [];
            }

            if (!is_array($roulettesClosed)) {
                $roulettesClosed = [];
            }

            $roulettes = [...$roulettesOpen, ...$roulettesClosed];

            $ephemeralMsg = true;

            if (find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
                $ephemeralMsg = false;
            }

            $roulettesDescription = "\n";

            if (empty($roulettes)) {
                $interaction->updateOriginalResponse(
                    MessageBuilder::new()->setContent("Não há Roletas abertas!")
                );
                return;
            }

            foreach ($roulettes as $event) {
                $roulettesDescription .= sprintf(
                    "**[#%s] %s (Bet: C$ %s)**\n**Status: %s**\n \n \n",
                    $event['roulette_id'],
                    strtoupper($event['description']),
                    strtoupper($event['amount']),
                    $this->rouletteRepository::LABEL_LONG[(int) $event['status']]
                );
            }

            /**
             * @var Embed $embed
             */
            $embed = new Embed($this->discord);
            $embed
                ->setTitle("ROLETAS")
                ->setColor('#F5D920')
                ->setDescription($roulettesDescription)
                ->setImage($this->config['images']['roulette']['list']);
            $interaction->updateOriginalResponse(MessageBuilder::new()->addEmbed($embed), $ephemeralMsg);
        });
    }
}
