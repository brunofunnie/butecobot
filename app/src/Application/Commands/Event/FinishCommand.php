<?php

namespace ButecoBot\Application\Commands\Event;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Repository\Event;
use ButecoBot\Repository\EventChoice;
use ButecoBot\Application\Discord\MessageComposer;
use function ButecoBot\Helpers\find_role_array;

class FinishCommand extends Command
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
            $interaction->respondWithMessage($this->messageComposer->embed(
                'Evento',
                'Você não tem permissão para usar este comando!'
            ), true);
            return;
        }

        $eventId = $interaction->data->options['encerrar']->options['id']->value;
        $choiceKey = $interaction->data->options['encerrar']->options['opcao']->value;
        $event = $this->eventRepository->getEventById($eventId);

        if (empty($event)) {
            $interaction->respondWithMessage($this->messageComposer->embed(
                'Evento',
                'Evento não existe!'
            ), true);
            return;
        }

        if ($event[0]['status'] !== $this->eventRepository::CLOSED) {
            $interaction->respondWithMessage($this->messageComposer->embed(
                'Evento',
                'Evento precisa estar fechado para ser finalizado!'
            ), true);
            return;
        }

        if ($choiceKey === 'Empate') {
            if (!$this->eventRepository->drawEvent($eventId)) {
                $interaction->respondWithMessage($this->messageComposer->embed(
                    'Evento',
                    'Erro ao encerrar evento'
                ), true);
                return;
            }

            $interaction->respondWithMessage($this->messageComposer->embed(
                'Evento',
                sprintf("Evento: #%s %s\nResultado: Empate!\n\nApostas devolvidas", $eventId, $event[0]['name'])
            ), false);
            return;
        }

        $choice = $this->eventChoiceRepository->getChoiceByEventIdAndKey($eventId, $choiceKey);
        $bets = $this->eventRepository->payoutEvent($eventId, $choiceKey);

        if (count($bets) === 0) {
            $this->eventRepository->finishEvent($eventId);
            $interaction->respondWithMessage($this->messageComposer->embed(
                'Evento',
                'Não houveram apostas neste evento!'
            ), false);
            return;
        }

        $eventsDescription = sprintf(
            "**Evento:** %s \n **Vencedor**: %s \n \n \n",
            $event[0]['name'],
            $choice[0]['description'],
        );

        $winnersImage = $this->config['images']['winners'][array_rand($this->config['images']['winners'])];
        $embed = new Embed($this->discord);
        $embed
            ->setTitle('Evento')
            ->setColor('#F5D920')
            ->setDescription($eventsDescription)
            ->setImage($winnersImage);

        $winners = '';
        $earnings = '';

        foreach ($bets as $bet) {
            if ($bet['choice_key'] == $choiceKey) {
                $winners .= sprintf("<@%s> \n", $bet['discord_user_id']);
                $earnings .= sprintf("%s %s \n", $bet['earnings'], $bet['extraLabel']);
            }
        }

        $embed
            ->addField([ 'name' => 'Ganhador', 'value' => $winners, 'inline' => 'true' ])
            ->addField([ 'name' => 'Valor', 'value' => $earnings, 'inline' => 'true' ]);

        $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed));
    }
}
