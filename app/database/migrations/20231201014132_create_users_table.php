<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->addColumn('discord_user_id', 'string', ['limit' => 255])
              ->addColumn('discord_username', 'string', ['limit' => 255])
              ->addColumn('received_initial_coins', 'boolean', ['default' => true])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['id'], ['unique' => true])
              ->create();
    }
}
