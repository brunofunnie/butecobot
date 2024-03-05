<?php

namespace Chorume\Application\Events;

use Chorume\Repository\User;
use Discord\Discord;
use Discord\Parts\WebSockets\VoiceStateUpdate as WebSocketsVoiceStateUpdate;

class VoiceStateUpdate
{
    public function __invoke(WebSocketsVoiceStateUpdate $data, Discord $discord): void
    {
        $this->handle($data, $discord);
    }

    public function __construct(
        private Discord $discord,
        private $redis,
        private User $userRepository
    ) {
    }

    public function handle(
        WebSocketsVoiceStateUpdate $data,
        Discord $discord
    ): void {
        $channel = $data->channel;
        $user = $data->user;
        $isUserDeafened = $data->self_deaf || $data->deaf;
        $presenceCache = $this->redis->get("presence_coins:" . $user->id);

        // Joined a channel
        if ($channel) {
            // If there is previous data, delete it to prevent some nasty earnings.
            if ($presenceCache) {
                $this->redis->del("presence_coins:" . $user->id);
            }

            $this->redis->set("presence_coins:" . $user->id, json_encode([
                "entry_time" => time(),
                "id" => $user->id
            ]));
        }

        // if the user left a channel or is deaf, we remove it from the cache and pay their coins if he was there for more than 1 minute
        if ($channel == null || $isUserDeafened) {
            $presenceCache = $this->redis->get("presence_coins:" . $user->id);

            // If the user is not in the cache, we return
            if (!$presenceCache) {
                return;
            }

            $presenceCache = json_decode($presenceCache, true);

            $entry_time = $presenceCache["entry_time"];
            $elapsedSeconds = (time() - $entry_time);

            // Limit the accumulated gains to 1 hour
            if ($elapsedSeconds > 3600) {
                $elapsedSeconds = 3600;
            }

            // If the user was in the channel for more than 1 minute, we give him 5% of the time in coins
            if ($elapsedSeconds >= 60) {
                $coinPercentage = 5 / 100; // 5 percent
                $accumulatedAmount = $elapsedSeconds * $coinPercentage;
                $presenceDescription = [
                    'amount' => $accumulatedAmount,
                    'elapsedSeconds' => $elapsedSeconds,
                ];

                $this->userRepository->giveCoins($user->id, $accumulatedAmount, json_encode($presenceDescription));
                $discord->getLogger()->debug(
                    sprintf(
                        "Presence: username: %s - received: %s coins - elapsed seconds: %s",
                        $user->username,
                        $accumulatedAmount,
                        $elapsedSeconds
                    )
                );
            }

            $this->redis->del("presence_coins:" . $user->id);
            $discord->getLogger()->debug(
                sprintf(
                    "Presence: username: %s - left the channel",
                    $user->username
                )
            );
            return;
        }
    }
}
