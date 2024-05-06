<?php

namespace ButecoBot\Application\Commands\Code;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Application\Discord\MessageComposer;
use ButecoBot\Helpers\RedisHelper;

class CodeCommand extends Command
{
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis
    ) {
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function handle(Interaction $interaction): void
    {
        $interaction->respondWithMessage(
            $this->messageComposer->embed(
                'Aí manolo o código do bot tá aqui ó, não palpite, commit!',
                'https://github.com/brunofunnie/butecobot'
            ),
        );
    }
}
