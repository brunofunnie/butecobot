<?php

namespace Chorume\Application\Commands\Event;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Chorume\Application\Commands\Command;
use Chorume\Repository\User;
use Chorume\Repository\Event;
use Chorume\Repository\EventBet;
use Chorume\Application\Discord\MessageComposer;

class BetCommand extends Command
{
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private User $userRepository,
        private Event $eventRepository,
        private EventBet $eventBetsRepository
    ) {
        $this->messageComposer = new MessageComposer($discord);
    }

    public function handle(Interaction $interaction): void
    {
        $discordId = $interaction->member->user->id;
        $eventId = $interaction->data->options['evento']->value;
        $choiceKey = $interaction->data->options['opcao']->value;
        $coins = $interaction->data->options['coins']->value;
        $event = $this->eventRepository->listEventById($eventId);

        if (!$discordId) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Aposta',
                    'Aconteceu um erro com seu usuário, encha o saco do admin do bot!'
                ),
                true
            );
            return;
        }

        if (!$this->userRepository->userExistByDiscordId($discordId)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Aposta',
                    'Você ainda não coleteu suas coins iniciais! Digita **/coins** e pegue suas coins! :coin::coin::coin:'
                ),
                true
            );
            return;
        }

        if (empty($event)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Aposta',
                    sprintf('O evento #%d não existe! :crying_cat_face:', $eventId)
                ),
                true
            );
            return;
        }

        if ($this->eventRepository->canBet($eventId)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Aposta',
                    'Evento fechado para apostas! :crying_cat_face:'
                ),
                true
            );
            return;
        }

        if ($this->eventBetsRepository->alreadyBetted($discordId, $eventId)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Aposta',
                    'Você já apostou neste evento!'
                ),
                true
            );
            return;
        }

        if ($coins <= 0) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Aposta',
                    'Valor da aposta inválido'
                ),
                true
            );
            return;
        }

        if (!$this->userRepository->hasAvailableCoins($discordId, $coins)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Aposta',
                    'Você não possui coins suficientes! :crying_cat_face:'
                ),
                true
            );
            return;
        }

        if ($this->eventBetsRepository->create($discordId, $eventId, $choiceKey, $coins)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: 'Aposta',
                    message: sprintf(
                        "Valor: **%s** coins\nOpção: **%s**\n\n",
                        $coins,
                        $choiceKey
                    ),
                    color: '#F5D920',
                    thumbnail: $this->config['images']['place_bet']
                ),
                true
            );
        }
    }
}
