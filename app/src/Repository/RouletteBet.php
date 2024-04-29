<?php

namespace Chorume\Repository;

use Chorume\Repository\UserCoinHistory;

class RouletteBet extends Repository
{
    public const OPEN = 1;
    public const CLOSED = 2;
    public const CANCELED = 3;
    public const PAID = 4;

    public const LABEL = [
        self::OPEN => 'Aberto',
        self::CLOSED => 'Fechado',
        self::CANCELED => 'Cancelado',
        self::PAID => 'Pago',
    ];

    public const LABEL_LONG = [
        self::OPEN => 'Aberto para apostas',
        self::CLOSED => 'Fechado para apostas',
        self::CANCELED => 'Cancelado',
        self::PAID => 'Apostas pagas',
    ];

    public function __construct(
        $db,
        protected UserCoinHistory|null $userCoinHistoryRepository = null
    ) {
        parent::__construct($db);
        $this->userCoinHistoryRepository = $userCoinHistoryRepository ?? new UserCoinHistory($db);
    }

    public function createRouletteBet(int $userId, int $rouletteId, int $betAmount, int $choice) : bool
    {
        $createBetEvent = $this->db->query(
            'INSERT INTO roulette_bet (user_id, roulette_id, bet_amount, choice) VALUES (:user_id, :roulette_id, :bet_amount, :choice)',
            [
                'user_id' => $userId,
                'roulette_id' => $rouletteId,
                'bet_amount' => $betAmount,
                'choice' => $choice,
            ]
        );

        $createUserBetHistory = $this->userCoinHistoryRepository->create($userId, -$betAmount, 'RouletteBet', $rouletteId);

        return $createBetEvent && $createUserBetHistory;
    }

    public function getChoiceByRouletteIdAndKey(int $rouletteId, string $choice) : array
    {
        return $this->db->query(
            'SELECT
                    *
                FROM roulette_bet
                WHERE
                    roulette_id = :roulette_id
                    AND choice = :choice
            ',
            [
                'roulette_id' => $rouletteId,
                'choice' => $choice,
            ]
        );
    }

    public function getBetsByEventId(int $eventId) : array
    {
        return $this->db->query(
            'SELECT
                    rb.user_id AS user_id,
                    u.discord_user_id AS discord_user_id,
                    u.discord_username AS discord_username,
                    rb.choice AS choice_key,
                    SUM(rb.bet_amount) AS amount
                FROM roulette_bet rb
                JOIN users u ON u.id = rb.user_id
                WHERE
                    rb.roulette_id = :roulette_id
                GROUP BY rb.user_id, u.discord_user_id, u.discord_username, rb.choice
            ',
            [
                'roulette_id' => $eventId
            ]
        );
    }
}
