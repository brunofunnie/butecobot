<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersCoinsHistoryTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users_coins_history');
        $table->addColumn('user_id', 'integer', ['signed' => false])
              ->addColumn('entity_id', 'integer', ['null' => true])
              ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
              ->addColumn('type', 'string', ['limit' => 255, 'collation' => 'utf8mb4_general_ci'])
              ->addColumn('description', 'text', ['collation' => 'utf8mb4_general_ci', 'null' => true])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['id'], ['unique' => true])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
