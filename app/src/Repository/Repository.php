<?php

namespace ButecoBot\Repository;

use ButecoBot\Database\Db;

abstract class Repository
{
    public function __construct(protected Db $db)
    {
    }
}
