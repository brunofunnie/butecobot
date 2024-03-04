<?php

namespace Chorume\Repository;

use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;
use Chorume\Repository\UserCoinHistory;

class Roulette extends Repository
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

    public const GREEN = 1;
    public const BLACK = 2;
    public const RED = 3;

    public const GREEN_MULTIPLIER = 14;
    public const BLACK_MULTIPLIER = 2;
    public const RED_MULTIPLIER = 2;

    public const LABEL_CHOICE = [
        self::GREEN => 'VERDE',
        self::BLACK => 'PRETO',
        self::RED => 'VERMELHO',
    ];

    private RouletteBet $rouletteBetRepository;
    private User $userRepository;
    private UserCoinHistory $userCoinHistoryRepository;

    public function __construct($db)
    {
        $this->rouletteBetRepository = new RouletteBet($db);
        $this->userRepository = new User($db);
        $this->userCoinHistoryRepository = new UserCoinHistory($db);

        parent::__construct($db);
    }

    public function createEvent(string $eventName, int $value, int $discordId): int|bool
    {
        $user = $this->userRepository->getByDiscordId($discordId);
        $userId = $user[0]['id'];

        $createEvent = $this->db->query(
            'INSERT INTO roulette (created_by, status, description, amount) VALUES (:created_by, :status, :description, :amount)',
            [
                'created_by' => $userId,
                'status' => self::OPEN,
                'description' => $eventName,
                'amount' => $value,
            ]
        );

        return $createEvent ? $this->db->getLastInsertId() : false;
    }

    public function close(int $eventId): bool
    {
        return $this->db->query(
            'UPDATE roulette SET status = :status WHERE id = :event_id',
            [
                'status' => self::CLOSED,
                'event_id' => $eventId,
            ]
        );
    }

    public function finish(int $eventId): bool
    {
        return $this->db->query(
            'UPDATE roulette SET status = :status WHERE id = :event_id',
            [
                'status' => self::PAID,
                'event_id' => $eventId,
            ]
        );
    }   

    public function listEventsOpen(int $limit = null): array
    {
        return $this->normalizeRoulette($this->listEventsByStatus(['status_open' => self::OPEN], $limit));
    }

    public function listEventsClosed(int $limit = null): array
    {

        return $this->normalizeRoulette($this->listEventsByStatus(['status_closed' => self::CLOSED], $limit));
    }

    public function listEventsPaid(int $limit = null): array
    {
        return $this->normalizeRoulette($this->listEventsByStatus(['status_paid' => self::PAID], $limit));
    }

    public function listEventsByStatus(array $status, int $limit = null): array
    {
        $statusKeys = implode(',', array_map(fn ($item) => ":{$item}", array_keys($status)));

        $params = [];
        $limitSQL = '';

        if ($limit) {
            $params['limit'] = (int) $limit;
            $limitSQL = "LIMIT 0, :limit";
        }

        return $this->db->query(
            "SELECT
                id AS roulette_id,
                description,
                status,
                result,
                amount
            FROM roulette
            WHERE status IN ({$statusKeys}) ORDER BY id DESC $limitSQL
            ",
            [...$status, ...$params]
        );
    }

    public function normalizeRoulette(array $roulette): array
    {
        return array_map(function ($item) {
            return [
                'roulette_id' => $item['roulette_id'],
                'description' => $item['description'],
                'amount' => $item['amount'],
                'result' => $item['result'],
                'status' => $item['status'],
            ];
        }, $roulette);
    }

    public function getRouletteById(int $rouletteId): array
    {
        return $this->db->query(
            "SELECT * FROM roulette WHERE id = :event_id",
            [
                'event_id' => $rouletteId
            ]
        );
    }

    public function closeEvent(int $rouletteId): bool
    {
        return $this->db->query(
            'UPDATE roulette SET status = :status WHERE id = :event_id',
            [
                'status' => self::CLOSED,
                'event_id' => $rouletteId,
            ]
        );
    }

    public function payoutRoulette(int $rouletteId, $winnerNumber): array
    {
        $winners = [];
        $bets = $this->rouletteBetRepository->getBetsByEventId($rouletteId);
        $choiceData = $this->getWinnerChoiceByNumber($winnerNumber);
        $odd = 2;

        if ($choiceData['choice'] === self::GREEN) {
            $odd = 14;
        }

        $this->updateRouletteWithWinner($winnerNumber, $rouletteId);

        foreach ($bets as $bet) {
            if ($bet['choice_key'] !== $choiceData['choice']) {
                continue;
            }

            $betPayout = $bet['amount'] * $odd;
            $winners[] = [
                'discord_user_id' => $bet['discord_user_id'],
                'discord_username' => $bet['discord_username'],
                'choice_key' => $bet['choice_key'],
                'earnings' => $betPayout,
            ];

            $this->userCoinHistoryRepository->create($bet['user_id'], $betPayout, 'RouletteBet', $rouletteId);
        }

        return $winners;
    }

    public function updateRouletteWithWinner(int $winnerNumber, int $eventId): bool
    {
        return $this->db->query(
            'UPDATE roulette SET status = :status, result = :result WHERE id = :event_id',
            [
                'status' => self::PAID,
                'result' => $winnerNumber,
                'event_id' => $eventId,
            ]
        );
    }

    public function getWinnerChoiceByNumber(int $number): array
    {
        if ($number == 0) {
            $winnerChoice = self::GREEN;
            $labelChoice = "🟩 G[$number]";
        } elseif ($number % 2 == 0) {
            $winnerChoice = self::BLACK;
            $labelChoice = "⬛ BL[$number]";
        } else {
            $winnerChoice = self::RED;
            $labelChoice = "🟥 R[$number]";
        }

        return [
            'choice' => $winnerChoice,
            'label' => $labelChoice,
        ];
    }
}
