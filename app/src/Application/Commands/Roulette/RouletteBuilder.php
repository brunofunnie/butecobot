<?php

namespace Chorume\Application\Commands\Roulette;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Roulette\Entities\GameData;
use Chorume\Application\Commands\Roulette\Entities\Player;
use Chorume\Application\Commands\Roulette\FinishCommand;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;

class RouletteBuilder
{
    private FinishCommand $finishCommand;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
        $this->finishCommand = new FinishCommand(
            $this->discord,
            $this->config,
            $this->redis,
            $this->userRepository,
            $this->rouletteRepository,
            $this->rouletteBetRepository
        );
    }

    public function build(Interaction $interaction, int $rouletteId): void
    {
        $builder = MessageBuilder::new();
        $roulleteBetRow = ActionRow::new();
        $roulleteBetRow2 = ActionRow::new();
        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);
        $amountBet = (int) $roulette[0]['amount'];
        $status = (int) $roulette[0]['status'];

        if (empty($roulette)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Roleta não existe!'),
                true
            );

            return;
        }

        if ($status !== $this->rouletteRepository::OPEN) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Roleta precisa estar aberta para apostar!'),
                true
            );

            return;
        }

        $gameDataCache = $this->redis->get("roulette:{$rouletteId}");
        $gameData = unserialize($gameDataCache ?? '');

        if (!$gameData) {
            $gameData = new GameData($rouletteId);
            $serializado = serialize($gameData);
            $this->redis->set("roulette:{$rouletteId}", $serializado);
        }

        $buttonRed = Button::new(Button::STYLE_DANGER)
            ->setLabel("R +{$amountBet}")
            ->setListener(
                function (Interaction $interactionUser)
                use ($interaction, $rouletteId, $roulette, $amountBet, &$gameData) {
                    $fromDiscordId = $interactionUser->member->user->id;
                    $userDiscord = $interactionUser->member->user;

                    $this->apostarRoleta(
                        $fromDiscordId,
                        Roulette::RED,
                        $rouletteId,
                        $interaction,
                        $roulette,
                        $amountBet,
                        $gameData,
                        $userDiscord,
                        $interactionUser
                    );
                },
                $this->discord
            );

        $buttonGreen = Button::new(Button::STYLE_SUCCESS)
            ->setLabel("G +{$amountBet}")
            ->setListener(
                function (Interaction $interactionUser)
                use ($interaction, $rouletteId, $roulette, $amountBet, &$gameData) {
                    $fromDiscordId = $interactionUser->member->user->id;
                    $userDiscord = $interactionUser->member->user;

                    $this->apostarRoleta(
                        $fromDiscordId,
                        Roulette::GREEN,
                        $rouletteId,
                        $interaction,
                        $roulette,
                        $amountBet,
                        $gameData,
                        $userDiscord,
                        $interactionUser
                    );
                },
                $this->discord
            );

        $buttonBlack = Button::new(Button::STYLE_SECONDARY)
            ->setLabel("BL +{$amountBet}")
            ->setListener(
                function (Interaction $interactionUser)
                use ($interaction, $rouletteId, $roulette, $amountBet, &$gameData) {
                    $fromDiscordId = $interactionUser->member->user->id;
                    $userDiscord = $interactionUser->member->user;

                    $this->apostarRoleta(
                        $fromDiscordId,
                        Roulette::BLACK,
                        $rouletteId,
                        $interaction,
                        $roulette,
                        $amountBet,
                        $gameData,
                        $userDiscord,
                        $interactionUser
                    );
                },
                $this->discord
            );

        $buttonSpin = Button::new(Button::STYLE_PRIMARY)
            ->setLabel("Girar")
            ->setListener(
                function (Interaction $interactionUser) use ($rouletteId) {
                    $roulette = $this->rouletteRepository->getRouletteById($rouletteId);
                    $status = (int) $roulette[0]['status'];

                    if ($this->redis->get("roulette:{$rouletteId}:spinning")) {
                        $message = sprintf('Roleta **#%s** já está girando!', $rouletteId);

                        if ($status === $this->rouletteRepository::PAID) {
                            $message = sprintf('Roleta **#%s** já foi finalizada!', $rouletteId);
                        }

                        $interactionUser->respondWithMessage(
                            MessageBuilder::new()->setContent($message),
                            true
                        );

                        return;
                    }

                    $this->finishCommand->spinRoulette($rouletteId, $interactionUser);
                },
                $this->discord
            );

        $roulleteBetRow->addComponent($buttonSpin);
        $roulleteBetRow2->addComponent($buttonRed);
        $roulleteBetRow2->addComponent($buttonGreen);
        $roulleteBetRow2->addComponent($buttonBlack);

        $embed = $this->buildEmbedForRoulette($rouletteId, $roulette, $gameData);

        $builder = new MessageBuilder();
        $builder->addEmbed($embed);
        $builder->addComponent($roulleteBetRow);
        $builder->addComponent($roulleteBetRow2);

        $interaction->respondWithMessage($builder, false);
    }

    private function apostarRoleta(
        $userDiscordId,
        $choice,
        $rouletteId,
        Interaction $interaction,
        $roulette,
        $amountBet,
        &$gameData,
        $userDiscord,
        Interaction $interactionUser
    ): void {
        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);

        if (!$this->userRepository->userExistByDiscordId($userDiscordId)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    'Você ainda não coleteu suas coins iniciais! Digita **/coins** e pegue suas coins! :coin:'
                ),
                true
            );

            return;
        }

        if ($roulette[0]['status'] !== $this->rouletteRepository::OPEN) {
            $interactionUser->respondWithMessage(
                MessageBuilder::new()->setContent(
                    'Roleta precisa estar aberta para apostar!'
                ),
                true
            );

            return;
        }

        $user = $this->userRepository->getByDiscordId($userDiscordId);
        $userId = $user[0]['id'];

        if (!$this->userRepository->hasAvailableCoins($userDiscordId, $amountBet)) {
            $interactionUser->respondWithMessage(
                MessageBuilder::new()->setContent(
                    'Você não possui coins suficientes! :crying_cat_face:'
                ),
                true
            );

            return;
        }

        if ($choice == Roulette::RED) {
            $gameData->AmountRed = $gameData->AmountRed + $amountBet;
        } elseif ($choice == Roulette::GREEN) {
            $gameData->AmountGreen = $gameData->AmountGreen + $amountBet;
        } else {
            $gameData->AmountBlack = $gameData->AmountBlack + $amountBet;
        }

        $gameData->AmountTotal = $gameData->AmountTotal + $amountBet;
        $playerFound = false;

        foreach ($gameData->jogadores as &$existingPlayer) {
            if (
                (int) $existingPlayer->user === (int) $userId
                && (int) $existingPlayer->choice === (int) $choice
            ) {
                $existingPlayer->bet = $existingPlayer->bet + $amountBet;
                $playerFound = true;
                break;
            }
        }

        if (!$playerFound) {
            $player = new Player($userId, $amountBet, $choice, $userDiscord);
            $gameData->jogadores[] = $player;
        }

        $serializado = serialize($gameData);
        $this->redis->set("roulette:{$rouletteId}", $serializado);

        if ($this->rouletteBetRepository->createRouletteBet($userId, $rouletteId, $amountBet, $choice)) {
            $embed = $this->buildEmbedForRoulette($rouletteId, $roulette, $gameData);

            $builder = new MessageBuilder();
            $builder->addEmbed($embed);
            $interaction->updateOriginalResponse($builder);
        }
    }

    private function buildEmbedForRoulette($rouletteId, $roulette, GameData &$gameData): Embed
    {
        $playersRed = array_filter($gameData->jogadores, function ($player) {
            return $player->choice == Roulette::RED;
        });

        $playersGreen = array_filter($gameData->jogadores, function ($player) {
            return $player->choice == Roulette::GREEN;
        });

        $playersBlack = array_filter($gameData->jogadores, function ($player) {
            return $player->choice == Roulette::BLACK;
        });

        $embed = new Embed($this->discord);
        $embed->setTitle(":moneybag: " . $roulette[0]['description'])
            ->setColor('#5266ED')
            ->setDescription(sprintf(
                "**Roleta: #%s**\n**Total:** %s",
                $rouletteId,
                $gameData->AmountTotal
            ))
            ->setFooter("Últimos giros:\n" . $this->buildLastRoulettesChoices());

        $embed
            ->addFieldValues(sprintf("🟥 RED %sx", Roulette::RED_MULTIPLIER),'', true)
            ->addFieldValues(sprintf("🟩 GREEN  %sx", Roulette::GREEN_MULTIPLIER), '', true)
            ->addFieldValues(sprintf("⬛ BLACK  %sx", Roulette::BLACK_MULTIPLIER), '', true);

        $embed->addFieldValues(
            '',
            implode("\n", array_map(function ($player) {
                return "{$player->userName}\nBet: {$player->bet}";
            }, $playersRed)),
            true
        );

        $embed->addFieldValues(
            '',
            implode("\n", array_map(function ($player) {
                return "{$player->userName}\nBet: {$player->bet}";
            }, $playersGreen)),
            true
        );

        $embed->addFieldValues(
            '',
            implode("\n", array_map(function ($player) {
                return "{$player->userName}\nBet: {$player->bet}";
            }, $playersBlack)),
            true
        );

        return $embed;
    }

    private function buildLastRoulettesChoices(): string
    {
        $lastRoulettes = $this->rouletteRepository->listEventsPaid(15);

        $choices = array_map(function ($arr) {
            $result = $this->rouletteRepository->getWinnerChoiceByNumber($arr['result']);

            return match ($result['choice']) {
                Roulette::RED => "🟥",
                Roulette::GREEN => "🟩",
                Roulette::BLACK => "⬛",
                null => ""
            };
        }, $lastRoulettes);

        return '➡' . implode(' ', $choices);
    }
}
