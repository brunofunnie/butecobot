<?php

namespace Chorume\Application\Events;

use Discord\Discord;
use Chorume\Repository\User;
use Discord\Parts\User\Member;

class GuildMemberUpdate
{
    public function __invoke(Member $message): void
    {
        $this->handle($message);
    }

    public function __construct(
        private Discord $discord,
        private $config,
        private $redis,
        private User $userRepository
    ) {
    }

    public function handle(Member $member): void
    {
        // $user = $this->userRepository->getByDiscordId($member->user->id);

        // if (
        //     $user['discord_username'] !== $member->user->username
        //     || $user['discord_global_name'] !== $member->user->global_nome
        // ) {
            
        // }

        // $this->discord->getLogger()->debug("User: " . json_encode($user));

    }
}
