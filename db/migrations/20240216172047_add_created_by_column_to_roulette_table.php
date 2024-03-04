<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCreatedByColumnToRouletteTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('roulette');

        if (!$table->hasColumn('created_by')) {
            $table->addColumn('created_by', 'integer', ['signed' => false, 'after' => 'id'])
                  ->addForeignKey('created_by', 'users', 'id', ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION'])
                  ->update();
        }
    }
}
