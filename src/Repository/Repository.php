<?php

namespace Chorume\Repository;

use Chorume\Database\Db;

abstract class Repository
{
    public function __construct(protected Db $db)
    {
    }
}
