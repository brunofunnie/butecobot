<?php

namespace Chorume\Application\Commands\Asking;

use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Predis\Client as RedisClient;

class AskingCommand extends Command
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
        $question = $interaction->data->options['pergunta']->value;
        $answerCost = getenv("ANSWER_COINS_COST", 20);

        if (
            !$this->redisHelper->cooldown(
                'cooldown:asking:content:' . $interaction->member->user->id,
                $this->cooldownSeconds,
                $this->cooldownTimes
            )
        ) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: 'MEU CÉREBRO ESTÁ FRITANDOOO...🥵🥵',
                    message: 'Calma aí calabreso, você está perguntando demais... dá uma segurada aí.',
                    image: $this->config['images']['gonna_press']
                ),
                true
            );
            return;
        }

        if (!$this->userCoinHistoryRepository->hasAvailableCoins($interaction->member->user->id, $answerCost)) {
            $message = sprintf(
                "Tu não tem dinheiro pra pagar meu ensino, vai trabalhar!\n\npreciso de **%s coins** para aprender isso!",
                $answerCost
            );

            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: 'EU TAMBÉM PAGO BOLETOS',
                    message: $message,
                    image: $this->config['images']['nomoney']
                ),
                true
            );
            return;
        }

        if (strlen($question) > 50) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: 'MUITA COISA! EU FAÇO SENAI, NÃO HARVARD.',
                    message: 'Tu é escritor por acaso? Escreve menos na moralzinha!',
                    image: $this->config['images']['typer']
                ),
                true
            );
            return;
        }

        $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $question, $answerCost) {
            $Answer = $this->asking($question, $interaction->member->user->global_name);

            $this->discord->getLogger()->info(json_encode($Answer));

            if (!$Answer->success){
                $interaction->updateOriginalResponse(
                    $this->messageComposer->embed(
                        'NÃO ENTENDI A SUA PERGUNTA',
                        "circuitos fritando, memoria em colapso, estou explodindo..."
                    )
                );
                return;
            }

            $message = "**Pergunta:**\n$question\n\n**Resposta:**\n";
            if (strlen($Answer->response) >= 326){
                $message .= substr($Answer->response, 0, 326) . "... e bla bla bla.";
            }else{
                $message .= $Answer->response;
            }
            $message .= sprintf("\n\n**Custo:** %s coins", $answerCost);

            $interaction->updateOriginalResponse(
                $this->messageComposer->embed(
                    'RECRUTA RESPONDE',
                    $message,
                    '#1D80C3'
                )
            );

            $user = $this->userRepository->getByDiscordId($interaction->member->user->id);
            $this->userCoinHistoryRepository->create($user[0]['id'], -$answerCost, 'Asking');
        });
    }

    private function asking(string $answer, string $username): object
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . getenv('LEARN_TOKEN'),
        ];

        $body = [
            "q" => $answer,
            "username" => $username,
        ];

        try {
            $request = new Request('POST', sprintf("%s/asking", getenv("LEARN_ENDPOINT")), $headers, json_encode($body));
            $response = $client->send($request);

            return json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
            return (object) ["success" => false];
        }
    }

}