<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEventsChoicesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('events_choices');
        $table->addColumn('event_id', 'integer', ['signed' => false])
              ->addColumn('choice_key', 'string', ['limit' => 255, 'collation' => 'utf8mb4_general_ci'])
              ->addColumn('description', 'string', ['limit' => 255, 'collation' => 'utf8mb4_general_ci'])
              ->addIndex(['id'], ['unique' => true])
              ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
