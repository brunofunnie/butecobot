<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEventsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('events');
        $table->addColumn('winner_choice_id', 'integer', ['null' => true, 'signed' => false])
              ->addColumn('name', 'string', ['limit' => 255, 'collation' => 'utf8mb4_general_ci'])
              ->addColumn('status', 'boolean')
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['id'], ['unique' => true])
              ->create();
    }
}
