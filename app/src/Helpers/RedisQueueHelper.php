<?php

namespace ButecoBot\Helpers;

use Predis\Client as RedisClient;

class RedisQueueHelper
{
    private RedisClient $redis;

    public function __construct(RedisClient $redis, public string $name = 'queue')
    {
        $this->redis = $redis;
    }

    public function enqueue(int $item): void
    {
        $this->redis->lpush($this->name, $item);
    }

    public function dequeue(): ?string
    {
        return $this->redis->rpop($this->name);
    }
}
