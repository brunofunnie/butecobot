<?php

namespace Chorume\Repository;

class User extends Repository
{
    public function all()
    {
        return $this->db->query("SELECT * FROM users");
    }

    public function create(array $data): bool|int
    {
        $query = $this->db->query(
            'INSERT INTO users (discord_user_id, discord_username, discord_global_name, discord_avatar, discord_joined_at, received_initial_coins) VALUES (:discord_user_id, :discord_username, :discord_global_name, :discord_avatar, :discord_joined_at, :received_initial_coins)',
            $data
        );

        if ($query) {
            return $this->db->getLastInsertId();
        }

        return false;
    }

    public function getByDiscordId(int $discordId): array
    {
        return $this->db->query(
            "SELECT * FROM users WHERE discord_user_id = :discord_user_id",
            [
                "discord_user_id" => $discordId
            ]
        );
    }

    public function getUsersByDiscordIds(array $ids)
    {
        $idsKeys = implode(', ', array_map(fn ($item) => ":{$item}", array_keys($ids)));

        $result = $this->db->query(
            "
                SELECT
                    *
                FROM users uch
                WHERE
                    uch.id IN ($idsKeys)
            ",
            $ids
        );

        return empty($result);
    }

    public function registerAndGiveInitialCoins(int $discordId, $discordUsername): bool
    {
        $createUser = $this->db->query(
            'INSERT INTO users (discord_user_id, discord_username, received_initial_coins) VALUES (:discord_user_id, :discord_username, :received_initial_coins)',
            [
                'discord_user_id' => $discordId,
                'discord_username' => $discordUsername,
                'received_initial_coins' => 1
            ]
        );

        $giveCoins = $this->db->query(
            'INSERT INTO users_coins_history (user_id, amount, type) VALUES (:user_id, :amount, :type)',
            [
                'user_id' => $this->db->getLastInsertId(),
                'amount' => 100,
                'type' => 'Initial'
            ]
        );

        return $giveCoins;
    }

    public function giveCoins(int $discordId, float $amount, string $type, string $description = null): bool
    {
        $user = $this->getByDiscordId($discordId);

        if (empty($user)) {
            return false;
        }

        return $this->db->query(
            'INSERT INTO users_coins_history (`user_id`, `amount`, `type`, `description`) VALUES (:user_id, :amount, :type, :description)',
            [
                'user_id' => $user[0]['id'],
                'amount' => $amount,
                'type' => $type,
                'description' => $description
            ]
        );
    }

    public function canReceivedDailyCoins(int $discordId): bool
    {
        $result = $this->db->query(
            "SELECT
                    *
                FROM users_coins_history uch
                JOIN users u ON u.id = uch.user_id
                WHERE
                    u.discord_user_id = :discord_user_id
                    AND uch.type = 'Daily'
                    AND DATE(uch.created_at) = CURDATE();
            ",
            [
                'discord_user_id' => $discordId
            ]
        );

        return empty($result);
    }

    public function getCurrentCoins(int $discordId): array
    {
        return $this->db->query(
            "SELECT
                    SUM(amount) AS total
                FROM users_coins_history uch
                JOIN users u ON u.id = uch.user_id
                WHERE u.discord_user_id = :discord_user_id
            ",
            [
                'discord_user_id' => $discordId
            ]
        );
    }

    public function hasAvailableCoins(int $discordId, int $amount): bool
    {
        $result = $this->db->query(
            "SELECT
                    SUM(amount) AS total
                FROM users_coins_history uch
                JOIN users u ON u.id = uch.user_id
                WHERE
                    u.discord_user_id = :discord_user_id
            ",
            [
                'discord_user_id' => $discordId
            ]
        );

        return $result[0]['total'] >= $amount;
    }

    public function userExistByDiscordId(int $discordId): bool
    {
        $result = $this->db->query(
            "SELECT * FROM users WHERE discord_user_id = :discord_user_id",
            [
                'discord_user_id' => $discordId
            ]
        );

        return !empty($result);
    }

    public function update(int $id, array $data): bool
    {
        $fields = array_map(fn ($item) => "{$item} = :{$item}", array_keys($data));

        return $this->db->query(
            "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id",
            array_merge($data, ['id' => $id])
        );
    }
}
