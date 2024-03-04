<?php

namespace Chorume\Application\Commands\Test;

use GuzzleHttp\Client;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Voice\VoiceClient;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class TestCommand extends Command
{
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis
    ) {
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function handle(Interaction $interaction): void
    {
        // Little Airplanes Spinning Sound
        $channel = $this->discord->getChannel($interaction->channel_id);
        $audio = __DIR__ . '/../../../Assets/Sounds/avioeszinhos.mp3';

        $this->discord->getLogger()->info($audio);

        $voice = $this->discord->getVoiceClient($channel->guild_id);

        if ($voice) {
            $this->discord->getLogger()->info('Voice client already exists, playing audio...');
            $voice
                ->playFile($audio)
                ->done(function () use ($voice) {
                    $voice->close();
                });
            return;
        }

        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Teste!'));
    }

    public function getAllRoulettesResults(): void
    {
        // $result = $db->getInstance()->query('SELECT * FROM roulette ORDER BY id DESC LIMIT 100');

        // foreach ($result as $row) {
        //      echo match($row['choice']) {
        //         Roulette::RED => ':red_square:',
        //         Roulette::BLACK => ':blue_square:',
        //         Roulette::GREEN => ':green_square:',
        //         null => ''
        //      };
        // }

        // exit;
    }
}
