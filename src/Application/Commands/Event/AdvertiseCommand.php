<?php

namespace Chorume\Application\Commands\Event;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Discord\Voice\VoiceClient;
use Chorume\Application\Commands\Command;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;
use Chorume\Application\Discord\MessageComposer;

class AdvertiseCommand extends Command
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
        $eventId = $interaction->data->options['anunciar']->options['id']->value;
        $bannerKey = $interaction->data->options['anunciar']->options['banner']->value;

        $event = $this->eventRepository->listEventById($eventId);

        if (empty($event)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Esse evento não existe!'), true);
            return;
        }

         // Rumble Sound
         $channel = $this->discord->getChannel($interaction->channel_id);
         $audio = __DIR__ . '/../../../Assets/Sounds/rumble.mp3';
         $voice = $this->discord->getVoiceClient($channel->guild_id);

         if ($channel->isVoiceBased() && $bannerKey === 'UFC') {
             if ($voice) {
                 $this->discord->getLogger()->debug('Voice client already exists, playing Rumble audio...');

                 $voice->playFile($audio);
             } else {
                 $this->discord->joinVoiceChannel($channel)->done(function (VoiceClient $voice) use ($audio) {
                     $this->discord->getLogger()->debug('Playing Rumble audio...');

                     $voice->playFile($audio);
                 });
             }
         }

        $eventOdds = $this->eventRepository->calculateOdds($eventId);
        $eventsDescription = sprintf(
            "**Status do Evento:** %s \n **A**: %s \n **B**: %s \n \n",
            $this->eventRepository::LABEL[$event[0]['event_status']],
            sprintf('%s (x%s)', $event[0]['choices'][0]['choice_description'], number_format($eventOdds['odds_a'], 2)),
            sprintf('%s (x%s)', $event[0]['choices'][1]['choice_description'], number_format($eventOdds['odds_b'], 2))
        );

        $embed = new Embed($this->discord);
        $embed
            ->setTitle(sprintf('[#%s] %s', $event[0]['event_id'], $event[0]['event_name']))
            ->setColor('#F5D920')
            ->setDescription($eventsDescription)
            ->setImage($this->config['images']['events'][$bannerKey]);
        $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), false);
    }
}
