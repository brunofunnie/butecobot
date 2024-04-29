<?php

namespace Chorume\Application\Commands\Generic;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Voice\VoiceClient;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Chorume\Repository\User;
use Chorume\Repository\UserCoinHistory;
use Exception;

class CoinsCommand extends Command
{
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;
    private int $cooldownSeconds;
    private int $cooldownTimes = 6;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private UserCoinHistory $userCoinHistoryRepository
    ) {
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
        $this->cooldownSeconds = getenv('COMMAND_COOLDOWN_TIMER');
        $this->cooldownTimes = getenv('COMMAND_COOLDOWN_LIMIT');
    }

    public function handle(Interaction $interaction): void
    {
        $interaction->acknowledgeWithResponse(true)->then(function () use ($interaction) {
            $loop = $this->discord->getLoop();
            $loop->addTimer(0.1, function () use ($interaction) {
                if (
                    !$this->redisHelper->cooldown(
                        'cooldown:generic:coins:' . $interaction->member->user->id,
                        $this->cooldownSeconds,
                        $this->cooldownTimes
                    )
                ) {
                    $interaction->updateOriginalResponse(
                        $this->messageComposer->embed(
                            title: 'Suas coins',
                            message: 'Não vai brotar dinheiro do nada! Aguarde 1 min para ver seu extrato!',
                            color: '#FF0000',
                            thumbnail: $this->config['images']['steve_no']
                        )
                    );
                    return;
                }

                // Check if user is registered, if not, register and give initial coins
                $discordId = $interaction->member->user->id;
                $user = $this->userRepository->getByDiscordId($discordId);

                if (empty($user)) {
                    if ($this->userRepository->registerAndGiveInitialCoins(
                        $interaction->member->user->id,
                        $interaction->member->user->username
                    )) {
                        $interaction->updateOriginalResponse(
                            $this->messageComposer->embed(
                                title: 'Bem vindo',
                                message: 'Você recebeu **100** coins iniciais! Aposte sabiamente :man_mage:',
                                color: '#F5D920',
                                thumbnail: $this->config['images']['one_coin']
                            )
                        );

                        return;
                    }
                }

                // Check if is registered user but didn't receive initial coins
                if ($user[0]['received_initial_coins'] === 0) {
                    $this->userRepository->giveCoins($interaction->member->user->id, 100, 'Initial');
                    $this->userRepository->update($user[0]['id'], ['received_initial_coins' => 1]);

                    $this->messageComposer->embed(
                        title: 'Bem vindo',
                        message: 'Você recebeu **100** coins iniciais! Aposte sabiamente :man_mage:',
                        color: '#F5D920',
                        thumbnail: $this->config['images']['one_coin']
                    );
                }

                $coinsQuery = $this->userRepository->getCurrentCoins($interaction->member->user->id);
                $currentCoins = $coinsQuery[0]['total'];
                $dailyCoins = 100;
                $message = '';

                // Check if user can receive daily coins
                if ($this->userRepository->canReceivedDailyCoins($interaction->member->user->id) && !empty($user)) {
                    $currentCoins += $dailyCoins;
                    $this->userRepository->giveCoins($interaction->member->user->id, $dailyCoins, 'Daily');

                    $message .= "**+%s diárias**\n";
                    $message = sprintf($message, $dailyCoins);
                }

                $message .= sprintf('**%s** coins', $currentCoins);
                $image = $this->config['images']['one_coin'];

                $interaction->updateOriginalResponse(
                    $this->messageComposer->embed(
                        title: 'Saldo',
                        message: $message,
                        color: $currentCoins === 0 ? '#FF0000' : '#00FF00',
                        thumbnail: $image
                    )
                );
            });
        });
    }
}
