<?php

namespace ButecoBot\Repository;

use PDOStatement;

class UserCoinHistory extends Repository
{
    public function all() : array
    {
        $result = $this->db->query("SELECT * FROM users_coins_history");

        return $result;
    }

    public function create(int $userId, float $amount, string $type, int $entityId = null, string|null $description = null) : bool
    {
        return $this->db->query(
            "INSERT INTO users_coins_history (user_id, entity_id, amount, type, description) VALUES (:user_id, :entity_id, :amount, :type, :description)",
            [
                "user_id" => $userId,
                "entity_id" => $entityId,
                "amount" => $amount,
                "type" => $type,
                "description" => $description,
            ]
        );
    }

    public function listTop10() : array
    {
        $result = $this->db->query(
            "SELECT
                SUM(uch.amount) AS total_coins,
                u.discord_user_id,
                u.discord_username,
                u.discord_global_name
            FROM users_coins_history uch
            JOIN users u ON uch.user_id = u.id
            GROUP BY uch.user_id
            ORDER BY total_coins DESC
            LIMIT 10
        ");

        return $result;
    }

    /**
     * do not performs any validation here, so be careful as this method can be used to "steal" coins
     */
    public function transfer(int $fromId, float $amount, int $toId) : bool
    {
        $type = 'Transfer';

        return $this->db->query(
            "INSERT INTO users_coins_history (user_id, entity_id, amount, type)
            VALUES
                (:from_user_id, :from_entity_id, :from_amount, :from_type),
                (:to_user_id, :to_entity_id, :to_amount, :to_type)
            ",
            [
                'from_user_id' => $fromId,
                'from_entity_id' => $toId,
                'from_amount' => -$amount,
                'from_type' => $type,
                'to_user_id' => $toId,
                'to_entity_id' => $fromId,
                'to_amount' => $amount,
                'to_type' => $type,
            ]
        );
    }

    public function hasAvailableCoins(int $discordUserId, float $amount) : bool
    {
        $result = $this->db->query(
            "SELECT
                SUM(uch.amount) AS total_coins
            FROM users_coins_history uch
            JOIN users u ON u.id = uch.user_id
            WHERE u.discord_user_id = :discord_user_id
            ",
            [
                'discord_user_id' => $discordUserId,
            ]
        );

        $totalCoins = $result[0]['total_coins'] ?? 0;

        return $totalCoins >= $amount;
    }

    public function reachedMaximumAirplanesToday() : bool
    {
        $result = $this->db->query(
            "SELECT
                sum(uch.amount) AS total_coins
            FROM users_coins_history uch
                INNER JOIN users u ON u.id = uch.user_id
            WHERE
                `type` like '%Airplane%'
            AND DATE(uch.created_at) = DATE(NOW())
        ");

        $totalCoins = $result[0]['total_coins'] ?? 0;

        return $totalCoins > getenv('LITTLE_AIRPLANES_MAXIMUM_AMOUNT_DAY');
    }
}
