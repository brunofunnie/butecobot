<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddForeignKeyToEventsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('events');
        $table->addForeignKey('winner_choice_id', 'events_choices', 'id', ['delete' => 'SET NULL', 'update' => 'NO_ACTION'])
              ->update();
    }
}
