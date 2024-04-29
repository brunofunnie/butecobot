<?php

namespace Chorume\Repository;

use Chorume\Repository\EventBet;
use Chorume\Repository\EventChoice;
use Chorume\Repository\User;
use Chorume\Repository\UserCoinHistory;

class Event extends Repository
{
    public const OPEN = 1;
    public const CLOSED = 2;
    public const CANCELED = 3;
    public const PAID = 4;
    public const DRAW = 5;

    public const LABEL = [
        self::OPEN => 'Aberto',
        self::CLOSED => 'Fechado',
        self::CANCELED => 'Cancelado',
        self::PAID => 'Pago',
        self::DRAW => 'Empate',
    ];

    public const LABEL_LONG = [
        self::OPEN => 'Aberto para apostas',
        self::CLOSED => 'Fechado para apostas',
        self::CANCELED => 'Cancelado',
        self::PAID => 'Apostas pagas',
        self::DRAW => 'Empate, não houve vencedor',
    ];

    private int $eventExtraLuckyChance;

    public function __construct(
        $db,
        protected EventBet|null $eventBetRepository = null,
        protected EventChoice|null $eventChoiceRepository = null,
        protected UserCoinHistory|null $userCoinHistoryRepository = null,
        protected User|null $userRepository = null
    ) {
        $this->eventBetRepository = $eventBetRepository ?? new EventBet($db);
        $this->eventChoiceRepository = $eventChoiceRepository ?? new EventChoice($db);
        $this->userCoinHistoryRepository = $userCoinHistoryRepository ?? new UserCoinHistory($db);
        $this->userRepository = $userRepository ?? new User($db);
        $this->eventExtraLuckyChance = getenv('EVENT_EXTRA_LUCKY_CHANCE') * 100;

        parent::__construct($db);
    }

    public function all(): array
    {
        return $this->db->query("SELECT * FROM events");
    }

    public function getEventById(int $eventId): array
    {
        return $this->db->query(
            "SELECT * FROM events WHERE id = :event_id",
            [
                'event_id' => $eventId
            ]
        );
    }

    public function create(string $eventName, string $optionA, string $optionB, int $discordId): bool
    {
        $this->db->beginTransaction();

        $user = $this->userRepository->getByDiscordId($discordId);
        $userId = $user[0]['id'];

        $createEvent = $this->db->query(
            'INSERT INTO events (created_by, name, status) VALUES (:created_by, :name, :status)',
            [
                'created_by' => $userId,
                'name' => $eventName,
                'status' => self::OPEN,
            ]
        );

        $lastInsertId = $this->db->getLastInsertId();

        $this->db->query(
            'INSERT INTO events_choices (`event_id`, `choice_key`, `description`) VALUES (:event_id, :choice_key, :description)',
            [
                'event_id' => $lastInsertId,
                'choice_key' => 'A',
                'description' => $optionA,
            ]
        );

        $this->db->query(
            'INSERT INTO events_choices (`event_id`, `choice_key`, `description`) VALUES (:event_id, :choice_key, :description)',
            [
                'event_id' => $lastInsertId,
                'choice_key' => 'B',
                'description' => $optionB,
            ]
        );

        $this->db->commit();

        return $createEvent;
    }

    public function closeEvent(int $eventId): bool
    {
        return $this->db->query(
            'UPDATE events SET status = :status WHERE id = :event_id',
            [
                'status' => self::CLOSED,
                'event_id' => $eventId,
            ]
        );
    }

    public function finishEvent(int $eventId): bool
    {
        return $this->db->query(
            'UPDATE events SET status = :status WHERE id = :event_id',
            [
                'status' => self::PAID,
                'event_id' => $eventId,
            ]
        );
    }

    public function canBet(int $eventId): bool
    {
        $result = $this->db->query(
            "SELECT * FROM events WHERE id = :event_id AND status NOT IN (:status_closed, :status_canceled, :status_paid, :status_draw)",
            [
                'event_id' => $eventId,
                'status_closed' => self::CLOSED,
                'status_canceled' => self::CANCELED,
                'status_paid' => self::PAID,
                'status_draw' => self::DRAW,
            ]
        );

        return empty($result);
    }

    public function listEventsChoicesByStatus(array $status): array
    {
        $statusKeys = implode(',', array_map(fn ($item) => ":{$item}", array_keys($status)));

        return $this->db->query(
            "SELECT
                e.id AS event_id,
                e.name AS event_name,
                e.status AS event_status,
                ec.choice_key AS choice_option,
                ec.description AS choice_description
            FROM events_choices ec
            JOIN events e ON e.id = ec.event_id
            WHERE e.status IN ({$statusKeys})
            ",
            $status
        );
    }

    public function getEventDataById(int $eventId): array
    {
        return $this->db->query(
            "SELECT
                e.id AS event_id,
                e.name AS event_name,
                e.status AS event_status,
                ec.choice_key AS choice_option,
                ec.description AS choice_description
            FROM events_choices ec
            JOIN events e ON e.id = ec.event_id
            WHERE e.id = :event_id
            ",
            [
                'event_id' => $eventId
            ]
        );
    }

    public function listEventsOpen(): array
    {
        return $this->normalizeEventChoices($this->listEventsChoicesByStatus(['status_open' => self::OPEN]));
    }

    public function listEventsClosed(): array
    {
        return $this->normalizeEventChoices($this->listEventsChoicesByStatus(['status_closed' => self::CLOSED]));
    }

    public function listEventById(int $eventId): array
    {
        return $this->normalizeEventChoices($this->getEventDataById($eventId));
    }

    public function normalizeEventChoices(array $eventChoices): array
    {
        return array_reduce($eventChoices, function ($acc, $item) {
            if (($subItem = array_search($item['event_id'], array_column($acc, 'event_id'))) === false) {
                $acc[] = [
                    'event_id' => $item['event_id'],
                    'event_name' => $item['event_name'],
                    'event_status' => $item['event_status'],
                    'choices' => [
                        [
                            'choice_option' => $item['choice_option'],
                            'choice_description' => $item['choice_description'],
                        ]
                    ]
                ];

                return $acc;
            }

            $acc[$subItem]['choices'][] = [
                'choice_option' => $item['choice_option'],
                'choice_description' => $item['choice_description'],
            ];

            return $acc;
        }, []);
    }

    public function updateEventWithWinner(int $choiceId, int $eventId): bool
    {
        return $this->db->query(
            'UPDATE events SET status = :status, winner_choice_id = :winner_choice_id WHERE id = :event_id',
            [
                'status' => self::PAID,
                'winner_choice_id' => $choiceId,
                'event_id' => $eventId,
            ]
        );
    }

    private function smoothProbability($amount)
    {
        $base = 10;
        return log($amount + 1, $base);
    }

    public function calculateOdds(int $eventId): array
    {
        $bets = $this->eventBetRepository->getBetsByEventId($eventId);

        $totalBetsArrayBase = [
            'total' => 0,
            'count' => 0
        ];
        $totalBetsA = array_reduce($bets, function ($acc, $item) {
            $acc['total'] += $item['choice_key'] === 'A' ? $this->smoothProbability($item['amount']) : 0;
            $acc['count'] += 1;

            return $acc;
        }, $totalBetsArrayBase);
        $totalBetsB = array_reduce($bets, function ($acc, $item) {
            $acc['total'] += $item['choice_key'] === 'B' ? $this->smoothProbability($item['amount']) : 0;
            $acc['count'] += 1;

            return $acc;
        }, $totalBetsArrayBase);

        $oddsA = $totalBetsA['total'] !== 0 ? ($totalBetsB['total'] / $totalBetsA['total']) + 1 : 1;
        $oddsB = $totalBetsB['total'] !== 0 ? ($totalBetsA['total'] / $totalBetsB['total']) + 1 : 1;

        return [
            'odds_a' => $oddsA,
            'odds_b' => $oddsB,
            'total_bets_a' => $totalBetsA['total'],
            'total_bets_b' => $totalBetsB['total'],
        ];
    }

    public function drawEvent(int $eventId): bool
    {
        try {
            $this->db->beginTransaction();

            $bets = $this->eventBetRepository->getBetsByEventId($eventId);

            foreach ($bets as $bet) {
                $rollbackBet = $this->userCoinHistoryRepository->create(
                    $bet['user_id'],
                    $bet['amount'],
                    'EventBet',
                    $eventId,
                    json_encode([
                        'status' => 'Draw'
                    ])
                );

                if (!$rollbackBet) {
                    throw new \Exception('Error rolling back bet');
                }
            }

            $updateEvent = $this->db->query(
                'UPDATE events SET status = :status WHERE id = :event_id',
                [
                    'status' => self::DRAW,
                    'event_id' => $eventId,
                ]
            );

            if (!$updateEvent) {
                throw new \Exception('Error updating event');
            }

            $this->db->commit();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function payoutEvent(int $eventId, string $winnerChoiceKey): array
    {
        $winners = [];
        $bets = $this->eventBetRepository->getBetsByEventId($eventId);
        $choiceId = $this->eventChoiceRepository->getChoiceByEventIdAndKey($eventId, $winnerChoiceKey);
        $odds = $this->calculateOdds($eventId);
        $this->updateEventWithWinner($choiceId[0]['id'], $eventId);

        foreach ($bets as $bet) {
            if ($bet['choice_key'] !== $winnerChoiceKey) {
                continue;
            }

            $extra = rand(0, 99) < $this->eventExtraLuckyChance ? $this->extraMultiplier() : 1;
            $ownExtra = $extra > 1;

            $oddMultiplier = $winnerChoiceKey === 'A' ? round($odds['odds_a'], 2) : round($odds['odds_b'], 2);
            $betPayout = $bet['amount'] * $oddMultiplier;
            $betPayoutFinal = round($betPayout * $extra, 2);

            $this->userCoinHistoryRepository->create(
                $bet['user_id'],
                $betPayoutFinal,
                'EventBet',
                $eventId,
                json_encode([
                    'betted' => $bet['amount'],
                    'choice' => $bet['choice_key'],
                    'odds' => $winnerChoiceKey === 'A' ? round($odds['odds_a'], 2) : round($odds['odds_b'], 2),
                    'extraLucky' => $ownExtra ? sprintf('Extra Lucky: %s', $extra) : null
                ])
            );

            $winners[] = [
                'discord_user_id' => $bet['discord_user_id'],
                'discord_username' => $bet['discord_username'],
                'choice_key' => $bet['choice_key'],
                'earnings' => $betPayoutFinal,
                'extraLabel' => $ownExtra ? sprintf(' (:rocket: %sx)', $extra) : false,
            ];
        }

        return $winners;
    }

    private function extraMultiplier(): float
    {
        return rand(15, 25) / 10;
    }
}
