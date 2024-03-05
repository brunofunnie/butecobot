<?php

namespace Chorume\Repository;

class UserChangeHistory extends Repository
{
    public function create(int $userId, string $info, string $event_label): bool
    {
        return $this->db->query(
            'INSERT INTO users_changes_history (user_id, info, event_label) VALUES (:user_id, :info, :event_label)',
            [
                'user_id' => $userId,
                'info' => $info,
                'event_label' => $event_label
            ]
        );
    }
}
