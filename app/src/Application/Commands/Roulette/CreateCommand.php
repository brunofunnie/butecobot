<?php

namespace ButecoBot\Application\Commands\Roulette;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Voice\VoiceClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Application\Commands\Roulette\RouletteBuilder;
use ButecoBot\Application\Discord\MessageComposer;
use ButecoBot\Repository\Roulette;
use ButecoBot\Repository\RouletteBet;
use ButecoBot\Repository\User;
use function ButecoBot\Helpers\find_role_array;

class CreateCommand extends Command
{
    private RouletteBuilder $rouletteBuilder;
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
        $this->rouletteBuilder = new RouletteBuilder(
            $this->discord,
            $this->config,
            $this->redis,
            $this->userRepository,
            $this->rouletteRepository,
            $this->rouletteBetRepository
        );

        $this->messageComposer = new MessageComposer($discord);
    }

    public function handle(Interaction $interaction): void
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        if (empty($interaction->data->options['criar']->options['nome'])) {
            $rouletteName = $this->makeRouletteName();
        } else {
            $rouletteName = $interaction->data->options['criar']->options['nome']->value;
        }

        $value = $interaction->data->options['criar']->options['valor']->value;

        $this->createRoulette($interaction, $rouletteName, $value);
    }

    public function createRoulette(Interaction $interaction, string $eventName, int $value): void
    {
        $discordId = $interaction->member->user->id;

        if ($value < 1 || $value > $_ENV['ROULETTE_LIMIT']) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: "Roleta",
                    message: sprintf("Só é possível criar roletas entre %s e %s coin!", 1, $_ENV['ROULETTE_LIMIT'])
                )
                , true
            );
            return;
        }

        // Create roulette
        $rouletteId = $this->rouletteRepository->createEvent(strtoupper($eventName), $value, $discordId);

        if (!$rouletteId) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: "Roleta",
                    message: "Não foi possível criar a roleta!"
                )
                , true
            );
            return;
        }

        // Create roulette Sound
        $channel = $this->discord->getChannel($interaction->channel_id);
        $audio = __DIR__ . '/../../../Assets/Sounds/roulette_create_' . rand(1, 5) . '.mp3';
        $voice = $this->discord->getVoiceClient($channel->guild_id);

        if ($channel->isVoiceBased()) {
            if ($voice) {
                $this->discord->getLogger()->debug('Voice client already exists, playing Roulette Create audio...');

                $voice->playFile($audio);
            } else {
                $this->discord->joinVoiceChannel($channel)->done(function (VoiceClient $voice) use ($audio) {
                    $this->discord->getLogger()->debug('Playing Roulette Create audio...');

                    $voice->playFile($audio);
                });
            }
        }

        $this->rouletteBuilder->build($interaction, $rouletteId);
    }

    private function makeRouletteName(): string
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY'),
        ];
        $messages = [
            [
                "role" => "system",
                "content" => "Usando o prefixo Roleta crie um nome com no máximo 5 palavras de forma engracada e criativa! Use quaisquer temas que desejar e seja o mais aleatório possível!"
            ],
            [
                "role" => "user",
                "content" => "Crie o nome para um evento de roleta, com no máximo 5 palavras, me surpreenda!"
            ],
        ];
        $body = [
            "model" => getenv('OPENAI_COMPLETION_MODEL'),
            "messages" => $messages,
            "temperature" => 1.2,
            "top_p" => 1,
            "n" => 1,
            "stream" => false,
            "max_tokens" => 50,
            "presence_penalty" => 0,
            "frequency_penalty" => 0
        ];

        try {
            $request = new Request('POST', 'https://api.openai.com/v1/chat/completions', $headers, json_encode($body));
            $response = $client->send($request);
            $data = json_decode($response->getBody()->getContents());

            var_dump($data);

            if (count($data->choices) > 0) {
                return str_replace(['"', "'"], "", $data->choices[0]->message->content);
            }

            return 'Roleta do ButecoBot';
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());

            return "Roleta do ButecoBot";
        }
    }
}
