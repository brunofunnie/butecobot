<?php

namespace ButecoBot\Application\Commands\Event;

use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Repository\Event;
use ButecoBot\Repository\EventChoice;
use ButecoBot\Application\Discord\MessageComposer;
use function ButecoBot\Helpers\find_role_array;

class CloseCommand extends Command
{
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private Event $eventRepository,
        private EventChoice $eventChoiceRepository
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
                ),
                true
            );
            return;
        }

        $eventId = $interaction->data->options['fechar']->options['id']->value;
        $event = $this->eventRepository->getEventById($eventId);

        if (empty($event)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Evento',
                    sprintf('Evento **#%s** não existe!', $eventId)
                ),
                false
            );
            return;
        }

        if (!$this->eventRepository->closeEvent($eventId)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Evento',
                    sprintf('Ocorreu um erro ao finalizar evento **#%s**', $eventId)
                ),
                false
            );
            return;
        }

        $interaction->respondWithMessage(
            $this->messageComposer->embed(
                'Evento',
                sprintf('Evento **#%s** fechado! Esse evento não recebe mais apostas!', $eventId)
            ),
            false
        );
        return;
    }
}
