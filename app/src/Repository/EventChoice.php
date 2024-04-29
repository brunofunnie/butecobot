<?php

namespace Chorume\Repository;

class EventChoice extends Repository
{
    public function all() : array
    {
        return $this->db->query("SELECT * FROM events_choices");
    }

    public function getByEventIdAndChoice(int $eventId, string $choiceKey) : array
    {
        return $this->db->query(
            'SELECT
                    *
                FROM events_choices
                WHERE
                    event_id = :event_id
                    AND choice_key = :choice_key
            ',
            [
                'event_id' => $eventId,
                'choice_key' => $choiceKey
            ]
        );
    }

    public function getChoiceByEventIdAndKey(int $eventId, string $choiceKey) : array
    {
        return $this->db->query(
            "SELECT
                    *
                FROM events_choices
                WHERE
                    event_id = :event_id
                    AND choice_key = :choice_key
            ",
            [
                'event_id' => $eventId,
                'choice_key' => $choiceKey
            ]
        );
    }
}
