<?php

namespace Chorume\Application\Commands\Roulette;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Voice\VoiceClient;
use Chorume\Application\Commands\Command;
use Chorume\Application\Commands\Roulette\RouletteBuilder;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;
use Predis\Client as RedisClient;

class CreateCommand extends Command
{
    private RouletteBuilder $rouletteBuilder;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
        $this->rouletteBuilder = new RouletteBuilder(
            $this->discord,
            $this->config,
            $this->redis,
            $this->userRepository,
            $this->rouletteRepository,
            $this->rouletteBetRepository
        );
    }

    public function handle(Interaction $interaction): void
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        $eventName = $interaction->data->options['criar']->options['nome']->value;
        $value = $interaction->data->options['criar']->options['valor']->value;

        $this->createRoulette($interaction, $eventName, $value);
    }

    public function createRoulette(Interaction $interaction, string $eventName, float $value): void
    {
        $rouletteId = $this->rouletteRepository->createEvent(strtoupper($eventName), $value);

        if (!$rouletteId) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent("Não foi possível criar a roleta!"), true);
            return;
        }

        if ($value < 1) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent("Só é possível criar roletas a partir de 1 coin!"), true);
            return;
        }

        // Create roulette Sound
        $channel = $this->discord->getChannel($interaction->channel_id);
        $audio = __DIR__ . '/../../../Audio/roulette_create_' . rand(1, 5) . '.mp3';
        $voice = $this->discord->getVoiceClient($channel->guild_id);

        if ($channel->isVoiceBased()) {
            if ($voice) {
                $this->discord->getLogger()->debug('Voice client already exists, playing Roulette Create audio...');

                $voice->playFile($audio);
            } else {
                $this->discord->joinVoiceChannel($channel)->done(function (VoiceClient $voice) use ($audio) {
                    $this->discord->getLogger()->debug('Playing Roulette Create audio...');

                    $voice->playFile($audio);
                });
            }
        }

        $this->rouletteBuilder->build($interaction, $rouletteId);
    }
}
