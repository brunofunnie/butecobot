<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTalksTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('talks');
        $table->addColumn('triggertext', 'string', ['limit' => 255, 'collation' => 'utf8mb4_general_ci'])
              ->addColumn('type', 'string', ['limit' => 100, 'collation' => 'utf8mb4_general_ci'])
              ->addColumn('answer', 'json')
              ->addColumn('status', 'boolean')
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['id'], ['unique' => true])
              ->create();
    }
}
