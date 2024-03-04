<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDiscordGlobalNameJoinedDateToUsersTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('users');

        if (!$table->hasColumn('discord_global_name')) {
            $table->addColumn('discord_global_name', 'varchar', ['limit' => 255, 'after' => 'discord_username'])
                  ->update();
        }

        if (!$table->hasColumn('joined_at')) {
            $table->addColumn('joined_at', 'datetime', ['after' => 'discord_username'])
                  ->update();
        }
    }
}
