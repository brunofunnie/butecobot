<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddColumnAmountToRouletteTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('roulette');

        if (!$table->hasColumn('amount')) {
            $table->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
                  ->update();
        }
    }
}
