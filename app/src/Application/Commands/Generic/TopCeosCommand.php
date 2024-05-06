<?php

namespace ButecoBot\Application\Commands\Generic;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Channel\Attachment;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Application\Discord\MessageComposer;
use ButecoBot\Repository\User;
use ButecoBot\Repository\UserCoinHistory;
use function ButecoBot\Helpers\format_money;

class TopCeosCommand extends Command
{
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private UserCoinHistory $userCoinHistoryRepository
    ) {
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function handle(Interaction $interaction): void
    {
        $top10list = $this->userCoinHistoryRepository->listTop10();
        $topBettersImage = $this->config['images']['top_ceos_rectangular'];

        $users = '';
        $acc = '';

        foreach ($top10list as $key => $bet) {
            $username = substr($bet['discord_global_name'] ?? $bet['discord_username'], 0, 25);

            if ($key === 0) {
                $users .= sprintf(":first_place: %s\n", $username);
            } elseif ($key === 1) {
                $users .= sprintf(":second_place: %s\n", $username);
            } elseif ($key === 2) {
                $users .= sprintf(":third_place: %s\n", $username);
            } else {
                $users .= sprintf(":medal: %s\n", $username);
            }

            $acc .= sprintf("C$ %s \n", format_money($bet['total_coins']));
        }

        $interaction->respondWithMessage($this->messageComposer->embed(
            title: 'TOP 10 PATRÕES',
            message: '',
            color: '#F5D920',
            image: $topBettersImage,
            fields: [
                ['name' => 'Usuário', 'value' => $users, 'inline' => true],
                ['name' => 'Acumulado', 'value' => $acc, 'inline' => true],
            ]
        ), false);
    }
}
