<?php

namespace Chorume\Application\Commands;

use Discord\Parts\Interactions\Interaction;

abstract class Command
{
    public function __invoke(Interaction $interaction): void
    {
        $this->handle($interaction);
    }

    abstract public function handle(Interaction $interaction): void;
}