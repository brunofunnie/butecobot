<?php

namespace Chorume\Application\Commands\Roulette;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;
use function Chorume\Helpers\find_role_array;

class ListCommand extends Command
{
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
        $this->messageComposer = new MessageComposer($discord);
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
                $this->discord->logger->debug('Não há roletas abertas');
                $interaction->updateOriginalResponse(
                    $this->messageComposer->embed(
                        title: "Roleta",
                        message: "Não há Roletas abertas!"
                    )
                );
                return;
            }

            foreach ($roulettes as $event) {
                $roulettesDescription .= sprintf(
                    ":game_die: **#%s**\n:label: %s\n:chorumecoin: C$ %s**\n**STATUS: %s**\n\n",
                    $event['roulette_id'],
                    strtoupper($event['description']),
                    strtoupper($event['amount']),
                    $this->rouletteRepository::LABEL_LONG[(int) $event['status']]
                );
            }

            $this->discord->logger->debug('Listando Roletas', ['roulettesDescription' => $roulettesDescription]);
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
