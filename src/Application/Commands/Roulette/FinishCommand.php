<?php

namespace Chorume\Application\Commands\Roulette;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button; 
use Discord\Parts\Interactions\Interaction;
use Discord\Voice\VoiceClient;
use Chorume\Application\Commands\Command;
use Chorume\Application\Commands\Roulette\CreateCommand;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Discord\Parts\Embed\Embed;
use Chorume\Repository\User;
use Discord\Parts\Channel\Message;
use Predis\Client as RedisClient;

class FinishCommand extends Command
{
    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
    }

    public function handle(Interaction $interaction): void
    {
        $rouletteId = $interaction->data->options['girar']->options['id']->value;

        if ($this->redis->get("roulette:{$rouletteId}:spinning")) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    sprintf('Roleta **#%s** já está girando!', $rouletteId)
                ),
                true
            );
            return;
        }

        $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $rouletteId) {
            $this->spinRoulette($rouletteId, $interaction);
        });
    }

    public function spinRoulette(int $rouletteId, Interaction $interaction): void
    {
        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);

        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'),
                true
            );
            return;
        }

        if (empty($roulette)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Roleta não existe!'),
                true
            );
            return;
        }


        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);
        $status = (int) $roulette[0]['status'];

        if ($status === $this->rouletteRepository::PAID) {
            $message = sprintf('Roleta **#%s** já foi finalizada!', $rouletteId);

            $interaction->sendFollowUpMessage(
                MessageBuilder::new()->setContent($message),
                true
            );
            return;
        }

        // Fecha roleta para apostas
        if (!$this->rouletteRepository->closeEvent($rouletteId)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    sprintf('Ocorreu um erro ao finalizar Roleta **#%s**', $rouletteId)
                )
            );
            return;
        }

        $imageRouletteSpin = $this->config['images']['roulette']['spin'];

        $embedLoop = new Embed($this->discord);
        $embedLoop->setImage($imageRouletteSpin);
        $embedLoop->setTitle(":moneybag: ROLETA ENCERRADA");
        $embedLoop->setDescription(sprintf(
            "**Girador:** <@%s>\n**Roleta**: [#%s] %s\n**Sorteando um número!**",
            $interaction->user->id,
            $rouletteId,
            $roulette[0]['description']
        ));

        $builderLoop = new MessageBuilder();
        $builderLoop->addEmbed($embedLoop);
        $followUp = $interaction->sendFollowUpMessage($builderLoop, false);
        $followUp->done(function ($followUpMessage) use ($rouletteId) {
            $this->redis->set("roulette:{$rouletteId}:lastfollowup", $followUpMessage->id);
        });
        $this->redis->set("roulette:{$rouletteId}:spinning", true);

        // Roulette Spinning Sound
        $channel = $this->discord->getChannel($interaction->channel_id);
        $audio = __DIR__ . '/../../../Audio/roulette.mp3';

        $voice = $this->discord->getVoiceClient($channel->guild_id);

        if ($channel->isVoiceBased()) {
            if ($voice) {
                $this->discord->getLogger()->info('Voice client already exists, playing roulette spin audio...');
                $voice->playFile($audio);
            } else {
                $this->discord->joinVoiceChannel($channel)->done(function (VoiceClient $voice) use ($audio, $interaction) {
                    $this->discord->getLogger()->info('Playing Little Airplanes audio...');
                    $voice->playFile($audio);
                });
            }
        }

        $loop = $this->discord->getLoop();
        $loop->addTimer(6, function () use ($interaction, $roulette) {
            $rouletteId = $roulette[0]['id'];
            $followUpMessageId = $this->redis->get("roulette:{$rouletteId}:lastfollowup");

            // Inicia os números de 1 a 14
            $numbers = [];
            for ($i = 1; $i <= 14; $i++) $numbers[] = $i;
            
            // Adicionando o número 0 (branco)
            $numbers[] = 0;
            
            // Sorteia o número dentro do array
            $winnerNumber = array_rand($numbers);
            
            $winnerResult = null;
            $choice = null;

            if ($winnerNumber == 0) {
                $winnerResult = Roulette::GREEN;
                $choice = "🟩 G[$winnerNumber]";

                // Brasil Sound
                $channel = $this->discord->getChannel($interaction->channel_id);
                $audio = __DIR__ . '/../../../Audio/brasil.mp3';
                $voice = $this->discord->getVoiceClient($channel->guild_id);

                if ($channel->isVoiceBased()) {
                    if ($voice) {
                        $this->discord->getLogger()->debug('Voice client already exists, playing Roulette Number Zero audio...');

                        $voice->playFile($audio);
                    } else {
                        $this->discord->joinVoiceChannel($channel)->done(function (VoiceClient $voice) use ($audio) {
                            $this->discord->getLogger()->debug('Playing Roulette Number Zero audio...');

                            $voice->playFile($audio);
                        });
                    }
                }
            } elseif ($winnerNumber % 2 == 0) {
                $winnerResult = Roulette::BLACK;
                $choice = "⬛ BL[$winnerNumber]";
            } else {
                $winnerResult = Roulette::RED;
                $choice = "🟥 R[$winnerNumber]";
            }

            $bets = $this->rouletteRepository->payoutRoulette($roulette[0]['id'], $winnerResult);
            $this->redis->del("roulette:{$rouletteId}");
            $this->redis->del("roulette:{$rouletteId}:lastfollowup");
            $this->redis->del("roulette:{$rouletteId}:spinning");

            $roulettesDescription = sprintf(
                "**Girador:** <@%s>\n**Roleta:** [#%s] %s\n **Vencedor**: %s \n \n \n",
                $interaction->user->id,
                $rouletteId,
                $roulette[0]['description'],
                "{$choice}",
            );

            $winnersImage = $this->config['images']['winners'][array_rand($this->config['images']['winners'])];

            $embed = new Embed($this->discord);
            $embed
                ->setTitle(":moneybag: ROLETA ENCERRADA")
                ->setColor('#00FF00')
                ->setDescription($roulettesDescription)
                ->setImage($winnersImage);

            $earningsByUser = [];

            foreach ($bets as $bet) {
                if ($bet['choice_key'] == $winnerResult) {
                    if (!isset($earningsByUser[$bet['discord_user_id']])) {
                        $earningsByUser[$bet['discord_user_id']] = 0;
                    }

                    $earningsByUser[$bet['discord_user_id']] += intval($bet['earnings']);
                }
            }

            $awarded = '';
            $amount = '';

            foreach ($earningsByUser as $userId => $earnings) {
                $awarded .= sprintf("<@%s>\n", $userId);
                $amount .= sprintf("🪙 %s\n", $earnings);
            }

            $embed
                ->addField(['name' => 'Premiação', 'value' => $awarded, 'inline' => 'true'])
                ->addField(['name' => 'Valor (C$)', 'value' => $amount, 'inline' => 'true']);

            if (count($bets) === 0) {
                $embednovo = new Embed($this->discord);
                $embednovo
                    ->setTitle(":moneybag: ROLETA ENCERRADA")
                    ->setColor('#FF0000')
                    ->setDescription(sprintf(
                        "**Girador:** <@%s>\n**Roleta:** [#%s] %s\n**Resultado**: Não houveram vencedores.",
                        $interaction->user->id,
                        $rouletteId,
                        $roulette[0]['description']
                    ));
                $embed = $embednovo;
            }

            $descriptions = $this->config['images']['roulette']['numbers'];
            $embed->setImage($descriptions[$winnerNumber]);
            $builder = new MessageBuilder();
            $builder->addEmbed($embed);
            $interaction->updateFollowUpMessage($followUpMessageId, $builder);
        });
    }
}
