<?php

namespace Chorume\Application\Commands\Picasso;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;

class PaintCommand extends Command
{
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;
    private int $cooldownSeconds;
    private int $cooldownTimes;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private $userRepository,
        private $userCoinHistoryRepository
    ) {
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
        $this->cooldownSeconds = getenv('COMMAND_COOLDOWN_TIMER');
        $this->cooldownTimes = getenv('COMMAND_COOLDOWN_LIMIT');
    }

    public function handle(Interaction $interaction): void
    {
        $prompt = $interaction->data->options['pinte']->value;
        $askCost = $originalAskCost = getenv('PICASSO_COINS_COST');

        if (
            !$this->redisHelper->cooldown(
                'cooldown:picasso:paint:' . $interaction->member->user->id,
                $this->cooldownSeconds,
                $this->cooldownTimes
            )
        ) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: 'HMMMM....',
                    message: 'Não confunda a pica de aço do mestre de obras com a obra de arte do mestre Picasso! Aguarde 1 minuto para fazer outra pergunta!',
                    image: $this->config['images']['gonna_press']
                ),
                true
            );
            return;
        }

        if (!$this->userCoinHistoryRepository->hasAvailableCoins($interaction->member->user->id, $askCost)) {
            $message = sprintf(
                "Pelo que vejo aqui sua carteira anda meio vazia. O mestre das artes plásticas não vale meros **%s coins**?",
                $originalAskCost
            );

            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: 'ARTE NÃO É DE GRAÇA',
                    message: $message,
                    image: $this->config['images']['nomoney']
                ),
                true
            );
            return;
        }

        try {
            $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $prompt, $askCost) {
                $art = $this->requestArt($prompt);

                if (!$art) {
                    $interaction->updateOriginalResponse(
                        $this->messageComposer->embed(
                            'ERRO, UM ERRO TERRÍVEL!',
                            'Deu ruim na arte, tenta de novo aí, mas descreve melhor o que você quer! Ah, o texto tem que ser em inglês e não me venha pedir para desenhar putaria que eu não sou o Picasso do pornô!'
                        )
                    );
                    return;
                }

                $message = sprintf("A arte é minha mas se deu ruim é por que você não sabe descrever o que quer, tenho culpa de nada não.\n\n**Me pediram isso:** %s\n\n**Custo:** %s coins", $prompt, $askCost);

                $interaction->updateOriginalResponse(
                    $this->messageComposer->embed(
                        'CONTEMPLE MINHA ARTE',
                        $message,
                        '#1D80C3',
                        $art->data[0]->url
                    )
                );

                $user = $this->userRepository->getByDiscordId($interaction->member->user->id);
                $this->userCoinHistoryRepository->create($user[0]['id'], -$askCost, 'Picasso');
            });

            return;
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }

    private function requestArt(string $prompt)
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY'),
        ];

        $body = [
            "model" => getenv('PICASSO_IMAGE_GENERATION_MODEL'),
            "prompt" => $prompt,
            "n" => (int) getenv('PICASSO_IMAGE_QTY'),
        ];

        try {
            $request = new Request('POST', 'https://api.openai.com/v1/images/generations', $headers, json_encode($body));
            $response = $client->send($request);
            $data = json_decode($response->getBody()->getContents());

            $this->discord->getLogger()->debug('OpenAI Response: ' . json_encode($data));

            return $data;
        } catch (\Exception $e) {

            $this->discord->getLogger()->error($e->getMessage());
        }
    }
}
