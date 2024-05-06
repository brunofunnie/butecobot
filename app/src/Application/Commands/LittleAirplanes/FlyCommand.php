<?php

namespace ButecoBot\Application\Commands\LittleAirplanes;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Voice\VoiceClient;
use Discord\Parts\Interactions\Interaction;
use Discord\Builders\MessageBuilder;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Application\Discord\MessageComposer;
use ButecoBot\Helpers\RedisHelper;
use ButecoBot\Repository\User;
use ButecoBot\Repository\UserCoinHistory;
use function ButecoBot\Helpers\find_role_array;
use Exception;

class FlyCommand extends Command
{
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;
    private int $cooldownTimer;
    private int $cooldownLimit;
    private float $extraValueProbability;
    private float $boostedValueProbability;
    private float $minValue;
    private float $maxValue;
    private float $boostedValue;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private UserCoinHistory $userCoinHistoryRepository
    ) {
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
        $this->cooldownTimer = getenv('LITTLE_AIRPLANES_COOLDOWN_TIMER');
        $this->cooldownLimit = getenv('LITTLE_AIRPLANES_COOLDOWN_LIMIT');
        $this->extraValueProbability = getenv('LITTLE_AIRPLANES_PROBABILITY');
        $this->boostedValueProbability = getenv('LITTLE_AIRPLANES_PROBABILITY_BOOSTED');
        $this->minValue = getenv('LITTLE_AIRPLANES_PROBABILITY_VALUE_MIN');
        $this->maxValue = getenv('LITTLE_AIRPLANES_PROBABILITY_VALUE_MAX');
        $this->boostedValue = getenv('LITTLE_AIRPLANES_PROBABILITY_VALUE_BOOSTED');
    }

    public function handle(Interaction $interaction): void
    {
        try {
            if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
                $this->discord->getLogger()->info(sprintf(
                    'Little Airplanes command not allowed for user #%s (%s - %s)',
                    $interaction->member->user->id,
                    $interaction->member->user->username,
                    $interaction->member->user->global_name
                ));

                $interaction->respondWithMessage(
                    MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'),
                    true
                );

                return;
            }

            if (
                !$this->redisHelper->cooldown(
                    'cooldown:littleairplanes:fly:' . $interaction->member->user->id,
                    $this->cooldownTimer,
                    $this->cooldownLimit
                )
            ) {
                $this->discord->getLogger()->info(sprintf(
                    'Little Airplanes cooldown reached for user #%s (%s - %s)',
                    $interaction->member->user->id,
                    $interaction->member->user->username,
                    $interaction->member->user->global_name
                ));

                $interaction->respondWithMessage(
                    $this->messageComposer->embed(
                        title: 'MAH ÔÊÊ!',
                        message: sprintf('Aguarde %s minutos para mandar mais aviõeszinhos... ôêê!', $this->cooldownTimer / 60),
                        image: $this->config['images']['gonna_press']
                    ),
                    true
                );

                return;
            }

            if ($this->userCoinHistoryRepository->reachedMaximumAirplanesToday()) {
                $this->discord->getLogger()->info(sprintf(
                    'Little Airplanes reached maximum amount of airplanes for user #%s (%s - %s)',
                    $interaction->member->user->id,
                    $interaction->member->user->username,
                    $interaction->member->user->global_name
                ));

                $interaction->respondWithMessage(
                    $this->messageComposer->embed(
                        'MAH ÔÊÊ!',
                        sprintf(
                            'Foram **%s coins** em :airplane_small: aviõeszinhos hoje que não dava pra ver o céu oêê! Agora só amanhã rá rá ê hi hi!',
                            getenv('LITTLE_AIRPLANES_MAXIMUM_AMOUNT_DAY')
                        ),
                        '#FF0000',
                        $this->config['images']['see_you_tomorrow']
                    )
                );
                return;
            }

            $members = array_keys($this->discord->getChannel($interaction->channel_id)->members->toArray());

            if (empty($members)) {
                $this->discord->getLogger()->info(sprintf('Little Airplanes no members found'));

                $interaction->respondWithMessage($this->messageComposer->embed(
                    'MAH ÔÊÊ!',
                    'Ma, ma, ma, mas tem ninguém nessa sala, não tem como eu jogar meus :airplane_small:aviõeszinhos... ôêê!'
                ), true);
            }

            $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $members) {
                $this->discord->getLogger()->info(sprintf('Little Airplanes started for %s members', count($members)));

                $interaction->updateOriginalResponse($this->messageComposer->embed(
                    title: 'MAH ÔÔÊ!',
                    message: 'Olha só quero ver, quero ver quem vai pegar os aviõeszinhos... ôêê!',
                    image: $this->config['images']['airplanes']
                ));

                // Little Airplanes Sound
                $channel = $this->discord->getChannel($interaction->channel_id);
                $audio = __DIR__ . '/../../../Assets/Sounds/avioeszinhos.mp3';
                $voice = $this->discord->getVoiceClient($channel->guild_id);

                if ($channel->isVoiceBased()) {
                    if ($voice) {
                        $this->discord->getLogger()->debug('Voice client already exists, playing Little Airplanes audio...');

                        $voice->playFile($audio);
                    } else {
                        $this->discord->joinVoiceChannel($channel)->done(function (VoiceClient $voice) use ($audio) {
                            $this->discord->getLogger()->debug('Playing Little Airplanes audio...');

                            $voice->playFile($audio);
                        });
                    }
                }

                $loop = $this->discord->getLoop();
                $loop->addTimer(6, function () use ($members, $interaction) {
                    $airplanes = [];

                    foreach ($members as $member) {
                        $isDeaf = $this->discord->getChannel($interaction->channel_id)->members[$member]->self_deaf;

                        if ($isDeaf) continue;

                        if (mt_rand(0, 99) < $this->extraValueProbability * 100) {
                            if (!$this->userRepository->userExistByDiscordId($member)) continue;

                            $extraValue = mt_rand(0, 99) < $this->boostedValueProbability * 100
                                ? $this->boostedValue
                                : mt_rand($this->minValue, $this->maxValue);

                            $airplanes[] = [
                                'discord_user_id' => $member,
                                'amount' => $extraValue
                            ];

                            $user = $this->userRepository->getByDiscordId($member);
                            $this->userCoinHistoryRepository->create($user[0]['id'], $extraValue, 'Airplane');

                            $this->discord->getLogger()->debug(sprintf(
                                'Little Airplanes won %s coins for user #%s',
                                $extraValue,
                                $member
                            ));
                        }
                    }

                    if (empty($airplanes)) {
                        $this->discord->getLogger()->info(sprintf('Little Airplanes no one won :('));

                        $interaction->updateOriginalResponse($this->messageComposer->embed(
                            title: 'MAH ÔÔÊ!',
                            message: 'Acho que o Roque esqueceu de fazer meus :airplane_small:aviõeszinhos... ôêê!',
                            image: $this->config['images']['silvio_thats_ok'],
                        ));
                        return;
                    }

                    $airports = '';
                    $amount = '';

                    foreach ($airplanes as $airplane) {
                        $airports .= sprintf("<@%s> \n", $airplane['discord_user_id']);
                        $amount .= sprintf(
                            "%s %s \n",
                            $airplane['amount'] < $this->boostedValue
                                ? ':airplane_small:'
                                : ':airplane:',
                            $airplane['amount']
                        );
                    }

                    $this->discord->getLogger()->info(sprintf('Little Airplanes finished with %s winners', count($airplanes)));

                    $interaction->updateOriginalResponse(
                        $this->messageComposer->embed(
                            title: 'MAH ÔÔÊ!',
                            message: 'Os :airplane_small:aviõeszinhos voaram pelo auditório e caíram em cima de:',
                            color: '#8FCE00',
                            image: $this->config['images']['silvio_cheers'],
                            fields: [
                                ['name' => 'Nome', 'value' => $airports, 'inline' => 'true'],
                                ['name' => 'Valor (C$)', 'value' => $amount, 'inline' => 'true']
                            ]
                        )
                    );
                });

                return;
            });
        } catch (Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }
}
