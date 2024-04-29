<?php

namespace Chorume\Application\Commands\Code;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;

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
                'https://github.com/brunofunnie/chorumebot'
            ),
        );
    }
}
