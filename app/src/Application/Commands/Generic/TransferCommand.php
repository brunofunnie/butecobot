<?php

namespace ButecoBot\Application\Commands\Generic;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Application\Discord\MessageComposer;
use ButecoBot\Repository\User;
use ButecoBot\Repository\UserCoinHistory;

class TransferCommand extends Command
{
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private UserCoinHistory $userCoinHistoryRepository
    ) {
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function handle(Interaction $interaction): void
    {
        $fromDiscordId = $interaction->member->user->id;
        $coins = $interaction->data->options['coins']->value;
        $toDiscordId = $interaction->data->options['usuario']->value;
        $fromUser = $this->userRepository->getByDiscordId($fromDiscordId);
        $toUser = $this->userRepository->getByDiscordId($toDiscordId);
        $embed = new Embed($this->discord);

        $daysActiveAccount = (new \DateTime())->diff(new \DateTime($fromUser[0]['created_at']))->days;

        if ($coins <= 0 || $coins > $_ENV['TRANSFER_LIMIT']) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Valor inválido',
                    'Quantidade inválida. Valor deve ser entre 1 e 1000 coins',
                ),
                true
            );

            return;
        }

        if ($daysActiveAccount <= 15) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Conta nova',
                    'Sua conta no ButecoBot Coins precisa ter mais de 15 dias para transferir coins',
                ),
                true
            );

            return;
        }

        if (!$this->userRepository->hasAvailableCoins($fromDiscordId, $coins)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Trasferência não realizada',
                    'Saldo insuficiente!',
                ),
                true
            );

            return;
        }

        if ($fromDiscordId === $toDiscordId) {
            $this->userCoinHistoryRepository->create($fromUser[0]['id'], -$coins, 'Troll');

            $message = sprintf("Nossa mas você é engraçado mesmo né. Por ter sido troll por transferir para você mesmo, acabou de perder **%s** coins pela zoeira!\n\nInclusive tá todo mundo vendo essa merda aí que tu ta fazendo!\n\nHA! HA! HA! ENGRAÇADÃO! 👹👹👹", -$coins);
            $image = $this->config['images']['sefodeu'];

            $interaction->respondWithMessage($this->messageComposer->embed(
                title: 'TROLL',
                message: $message,
                color: '#44f520',
                image: $image
            ), false);
            return;
        }

        if (!$this->userRepository->userExistByDiscordId($fromDiscordId)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Trasferência não realizada',
                    'Remetente não encontrado!',
                ),
                true
            );

            return;
        }

        if (!$this->userRepository->userExistByDiscordId($toDiscordId)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Beneficiário não encontrado'), true);
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Trasferência não realizada',
                    'Beneficiário não encontrado!',
                ),
                true
            );

            return;
        }

        if (!$this->userCoinHistoryRepository->transfer($fromUser[0]['id'], $coins, $toUser[0]['id'])) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'Trasferência não realizada',
                    'Erro inesperado ao transferir!',
                ),
                true
            );

            return;
        }

        $interaction->respondWithMessage(
            $this->messageComposer->embed(
                'Trasferência realizada',
                sprintf("Valor: **%s** coins\nDestinatário: <@%s>", $coins, $toDiscordId),
            ),
            true
        );
    }
}
