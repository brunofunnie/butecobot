<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRouletteTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('roulette');
        $table->addColumn('choice', 'integer', ['null' => true])
              ->addColumn('status', 'integer', ['default' => 0])
              ->addColumn('description', 'string', ['limit' => 255])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['id'], ['unique' => true])
              ->create();
    }
}
