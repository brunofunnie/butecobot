<?php

namespace Chorume\Repository;

class UserChangeHistory extends Repository
{
    public function create(int $userId, string $discord_username, string $discord_global_name, string $discord_avatar): bool
    {
        return $this->db->query(
            'INSERT INTO users_changes_history (user_id, discord_username, discord_global_name, discord_avatar) VALUES (:user_id, :discord_username, :discord_global_name, :discord_avatar)',
            [
                'user_id' => $userId,
                'discord_username' => $discord_username,
                'discord_global_name' => $discord_global_name,
                'discord_avatar' => $discord_avatar,
            ]
        );
    }
}
