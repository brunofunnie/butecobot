<?php

namespace Chorume\Application\Commands\Test;

use GuzzleHttp\Client;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\UserSelect;
use Discord\Builders\Components\StringSelect;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\TextInput;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Voice\VoiceClient;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Discord\Parts\Interactions\Interaction;

class TestCommand extends Command
{
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis
    ) {
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function handle(Interaction $interaction): void
    {
        $builder = MessageBuilder::new();

        $embed = new Embed($this->discord);
        $embed->setTitle(":moneybag: APOSTEM NA ROLETA")
            ->setColor('#5266ED')
            ->setDescription(sprintf("Descricao da Roleta aqui"))
            ->setFooter("Últimos giros: giros");

        $messageAction = ActionRow::new();
        $messageAction->addComponent(Button::new(Button::STYLE_PRIMARY)
            ->setLabel("Apostar")
            ->setListener(
                function (Interaction $interaction) {
                    $actions = [];
                    $eventListSelect = StringSelect::new("escolha_a_cor");

                    $eventListSelect->addOption(Option::new("Vermelho", "red"));
                    $eventListSelect->addOption(Option::new("Preto", "black"));
                    $eventListSelect->addOption(Option::new("Verde", "green"));

                    // $actions[] = ActionRow::new()->addComponent($eventListSelect);
                    $actions[] = $eventListSelect;
                    $actions[] = ActionRow::new()->addComponent(TextInput::new("Digite aqui o valor", TextInput::STYLE_SHORT)->setPlaceholder("0.00"));
                    $actions[] = ActionRow::new()->addComponent(TextInput::new("Digite aqui o valor", TextInput::STYLE_SHORT)->setPlaceholder("0.00"));
                    $actions[] = ActionRow::new()->addComponent(TextInput::new("Digite aqui o valor", TextInput::STYLE_SHORT)->setPlaceholder("0.00"));

                    $interaction->showModal("Digite a quantidade", "roulette_amount", $actions, function (Interaction $interaction, Collection $collection) {
                        $this->discord->getLogger()->info('Aqui depois do submit');
                        $interaction->acknowledge();
                    });
                },
                $this->discord
            ));

        $builder = new MessageBuilder();
        $builder->addEmbed($embed);
        $builder->addComponent($messageAction);
        $interaction->respondWithMessage($builder, false);
    }

    // public function handle(Interaction $interaction): void
    // {
    //     // Little Airplanes Spinning Sound
    //     $channel = $this->discord->getChannel($interaction->channel_id);
    //     $audio = __DIR__ . '/../../../Assets/Sounds/avioeszinhos.mp3';

    //     $this->discord->getLogger()->info($audio);

    //     $voice = $this->discord->getVoiceClient($channel->guild_id);

    //     if ($voice) {
    //         $this->discord->getLogger()->info('Voice client already exists, playing audio...');
    //         $voice
    //             ->playFile($audio)
    //             ->done(function () use ($voice) {
    //                 $voice->close();
    //             });
    //         return;
    //     }

    //     $interaction->respondWithMessage(MessageBuilder::new()->setContent('Teste!'));
    // }

    // public function getAllRoulettesResults(): void
    // {
    //     $result = $db->getInstance()->query('SELECT * FROM roulette ORDER BY id DESC LIMIT 100');

    //     foreach ($result as $row) {
    //          echo match($row['choice']) {
    //             Roulette::RED => ':red_square:',
    //             Roulette::BLACK => ':blue_square:',
    //             Roulette::GREEN => ':green_square:',
    //             null => ''
    //          };
    //     }

    //     exit;
    // }
}
