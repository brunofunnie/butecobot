<?php

namespace ButecoBot\Application\Commands\Event;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Repository\Event;
use ButecoBot\Application\Discord\MessageComposer;
use function ButecoBot\Helpers\find_role_array;

class CreateCommand extends Command
{
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private Event $eventRepository
    ) {
        $this->messageComposer = new MessageComposer($discord);
    }

    public function handle(Interaction $interaction): void
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Evento',
                    'Você não tem permissão para usar este comando!'
                ), true);
            return;
        }

        $discordId = $interaction->member->user->id;
        $eventName = $interaction->data->options['criar']->options['nome']->value;
        $optionA = $interaction->data->options['criar']->options['a']->value;
        $optionB = $interaction->data->options['criar']->options['b']->value;

        if ($this->eventRepository->create(strtoupper($eventName), $optionA, $optionB, $discordId)) {
            $interaction->respondWithMessage($this->messageComposer->embed(
                'Evento',
                'Evento criado com sucesso!'
            ), true);
        }
    }
}
