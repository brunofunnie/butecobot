<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameChoiceColumnFromRoulette extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('roulette');
        $table->renameColumn('choice', 'result')
            ->update();
    }
}
