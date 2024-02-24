<?php

namespace Chorume\Application\Commands\Generic;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Repository\User;
use Chorume\Repository\UserCoinHistory;

class TopForbesCommand extends Command
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
        $topBettersImage = $this->config['images']['top_forbes_rectangular'];

        $users = '';
        $acc = '';

        foreach ($top10list as $bet) {
            $users .= sprintf("<@%s>\n", $bet['discord_user_id']);
            $acc .= sprintf("%s \n", $bet['total_coins']);
        }

        $interaction->respondWithMessage($this->messageComposer->embed(
            title: 'TOP 10 FORBES',
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
