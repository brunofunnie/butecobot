<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRouletteBetTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('roulette_bet');
        $table->addColumn('user_id', 'integer', ['signed' => false])
              ->addColumn('roulette_id', 'integer', ['signed' => false])
              ->addColumn('bet_amount', 'integer')
              ->addColumn('choice', 'integer')
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['id'], ['unique' => true])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->addForeignKey('roulette_id', 'roulette', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
