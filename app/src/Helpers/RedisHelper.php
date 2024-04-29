<?php

namespace Chorume\Helpers;

use Predis\Client as RedisClient;

class RedisHelper
{
    private RedisClient $redis;

    public function __construct(RedisClient $redis)
    {
        $this->redis = $redis;
    }

    public function cooldown(string $key, int $seconds = 60, int $times = 2): bool
    {
        if ($curThreshold = $this->redis->get($key)) {
            if ($curThreshold < $times) {
                $this->redis->set($key, ++$curThreshold, 'EX', $seconds);
                return true;
            }
            return false;
        }

        $this->redis->set($key, 1, 'EX', $seconds);
        return true;
    }
}
