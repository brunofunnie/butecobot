<?php

namespace Chorume\Application\Commands\Event;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Parts\Embed\Embed;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;

class ListCommand extends Command
{
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private Event $eventRepository,
        private EventChoice $eventChoiceRepository
    ) {
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function handle(Interaction $interaction): void
    {
        $eventsOpen = $this->eventRepository->listEventsOpen();
        $eventsClosed = $this->eventRepository->listEventsClosed();
        $events = array_merge($eventsOpen, $eventsClosed);
        $totalEvents = count($events);
        $currentPage = 1;

        if (empty($events)) {
            $interaction->respondWithMessage($this->messageComposer->embed(
                'Eventos',
                'Nenhum evento encontrado'
            ), true);
            return;
        }

        usort($events, function ($a, $b) {
            return $a['event_id'] < $b['event_id'];
        });

        $eventActionRow = ActionRow::new();

        $prevButton = Button::new(Button::STYLE_SECONDARY)
            ->setLabel("<")
            ->setListener(
                function () use ($interaction, $events, $totalEvents, &$currentPage)
                {
                    $previousPage = $currentPage - 1;

                    if ($previousPage === 0) {
                        $interaction->acknowledge();
                        return;
                    }

                    $currentPage = $previousPage;
                    $messageEmbed = $this->buildEmbedMessage($events, $currentPage, $totalEvents);
                    $interaction->updateOriginalResponse(MessageBuilder::new()->addEmbed($messageEmbed), true);
                },
                $this->discord
            );

        $nextButton = Button::new(Button::STYLE_SECONDARY)
            ->setLabel(">")
            ->setListener(
                function () use ($interaction, $events, $totalEvents, &$currentPage)
                {
                    $nextPage = $currentPage + 1;

                    if ($nextPage > $totalEvents) {
                        $interaction->acknowledge();
                        return;
                    }

                    $currentPage = $nextPage;
                    $messageEmbed = $this->buildEmbedMessage($events, $currentPage, $totalEvents);
                    $interaction->updateOriginalResponse(MessageBuilder::new()->addEmbed($messageEmbed), true);
                },
                $this->discord
            );

        $eventActionRow->addComponent($prevButton);
        $eventActionRow->addComponent($nextButton);
        $messageEmbed = $this->buildEmbedMessage($events, $currentPage, $totalEvents);

        $message = MessageBuilder::new()
            ->addEmbed($messageEmbed)
            ->addComponent($eventActionRow);

        $interaction->respondWithMessage($message, true);
    }

    public function buildEmbedMessage($events, $currentPage, $totalEvents): Embed
    {
        $event = $events[$currentPage - 1];
        $eventOdds = $this->eventRepository->calculateOdds($event['event_id']);
        $eventsDescription = sprintf(
            "**%s** \n **Status: %s** \n\n **A**: %s \n **B**: %s",
            strtoupper($event['event_name']),
            $this->eventRepository::LABEL_LONG[(int) $event['event_status']],
            sprintf('%s (x%s)', $event['choices'][0]['choice_description'], number_format($eventOdds['odds_a'], 2)),
            sprintf('%s (x%s)', $event['choices'][1]['choice_description'], number_format($eventOdds['odds_b'], 2))
        );

        $messageEmbed = new Embed($this->discord);
        $messageEmbed
            ->setTitle(sprintf('Evento **#%s**', $event['event_id']))
            ->setDescription($eventsDescription)
            ->setColor('#F5D920')
            ->setThumbnail($this->config['images']['event'])
            ->setFooter(sprintf('Página %s de %s', $currentPage, $totalEvents));

        return $messageEmbed;
    }
}
