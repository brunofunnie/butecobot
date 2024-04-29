<?php

namespace Chorume\Application\Commands\Roulette;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Discord\Parts\Embed\Embed;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\ActionRow;
use Chorume\Repository\User;
use Predis\Client as RedisClient;

class CloseCommand extends Command
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
        $interaction->acknowledgeWithResponse(true)->then(function() use ($interaction) {
            if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
                $interaction->updateOriginalResponse(
                    MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!')
                );
                return;
            }

            $rouletteId = $interaction->data->options['fechar']->options['id']->value;
            $event = $this->rouletteRepository->getRouletteById($rouletteId);

            if ($event[0]['status'] !== $this->rouletteRepository::OPEN) {
                $interaction->updateOriginalResponse(MessageBuilder::new()->setContent(
                    sprintf("Roleta **#%s** precisa estar aberta para ser fechada!", $rouletteId)
                ));
                return;
            }

            if (empty($event)) {
                $interaction->updateOriginalResponse(
                    MessageBuilder::new()->setContent(
                        sprintf('Roleta **#%s** não existe!', $rouletteId)
                    )
                );
                return;
            }

            if (!$this->rouletteRepository->closeEvent($rouletteId)) {
                $interaction->updateOriginalResponse(
                    MessageBuilder::new()->setContent(
                        sprintf('Ocorreu um erro ao finalizar Roleta **#%s**', $rouletteId)
                    )
                );
                return;
            }

            $interaction->updateOriginalResponse(
                MessageBuilder::new()->setContent(
                    sprintf('Roleta **#%s** fechada! Esse evento não recebe mais apostas!', $rouletteId)
                )
            );

            return;
        });
    }
}
