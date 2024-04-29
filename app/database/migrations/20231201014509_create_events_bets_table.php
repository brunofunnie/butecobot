<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEventsBetsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('events_bets');
        $table->addColumn('user_id', 'integer', ['signed' => false])
              ->addColumn('event_id', 'integer', ['signed' => false])
              ->addColumn('choice_id', 'integer', ['signed' => false])
              ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['id'], ['unique' => true])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->addForeignKey('choice_id', 'events_choices', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
