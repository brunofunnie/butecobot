<?php

namespace Chorume\Repository;

use Chorume\Repository\EventBet;
use Chorume\Repository\EventChoice;
use Chorume\Repository\UserCoinHistory;

class Talk extends Repository
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 0;

    public function create(string $triggertext, string $type, string $answer) : bool
    {
        return $this->db->query(
            "INSERT INTO talks (triggertext, type, answer, status) VALUES (:triggertext, :type, :answer, :status)",
            [
                'triggertext' => $triggertext,
                'type' => $type,
                'answer' => $answer,
                'status' => self::STATUS_ACTIVE
            ]
        );
    }

    public function findById(int $id) : array
    {
        return $this->db->query(
            'SELECT * FROM talks WHERE id = :id',
            [
                'id' => $id
            ]
        );
    }

    public function findTrigger(string $triggertext) : array
    {
        return $this->db->query(
            'SELECT answer FROM talks WHERE triggertext = :triggertext WHERE status = :status',
            [
                'triggertext' => $triggertext,
                'status' => self::STATUS_ACTIVE
            ]
        );
    }

    public function listAllTriggers() : array
    {
        return $this->db->query(
            "SELECT id, triggertext FROM talks WHERE status = :status",
            [
                'status' => self::STATUS_ACTIVE
            ]
        );
    }
}
