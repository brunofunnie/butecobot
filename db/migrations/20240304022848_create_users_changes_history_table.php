<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersChangesHistoryTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users_changes_history');
        $table->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('info', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('event_label', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'id', array('delete'=> 'NO_ACTION', 'update'=> 'NO_ACTION'))
            ->addIndex(['id'], ['unique' => true])
            ->create();
    }
}
