<?php

namespace ButecoBot\Application\Commands\Master;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use ButecoBot\Application\Commands\Command;
use ButecoBot\Application\Discord\MessageComposer;
use ButecoBot\Helpers\RedisHelper;

class AskCommand extends Command
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
        $askCost = $originalAskCost = getenv('MASTER_COINS_COST');
        $boost = $interaction->data->options['boost'];
        $boostValue = 0;
        $boostMessage = '';
        $questionLimit = $boost ? getenv('MASTER_QUESTION_SIZE_LIMIT') * 20 : getenv('MASTER_QUESTION_SIZE_LIMIT');

        if (
            !$this->redisHelper->cooldown(
                'cooldown:master:ask:' . $interaction->member->user->id,
                $this->cooldownSeconds,
                $this->cooldownTimes
            )
        ) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: 'MESTRE ODEIA REPETIÇÕES',
                    message: 'Muito mais devagar aí cnpjoto, calabreso! Aguarde 1 minuto para fazer outra pergunta!',
                    image: $this->config['images']['gonna_press']
                ),
                true
            );
            return;
        }

        if ($boost) {
            $askCost += $boostValue = $boost->value;
            $boostMessage = sprintf("\n\nAlém do **Boost** de  **%s** coins", $boost->value);
        }

        if (!$this->userCoinHistoryRepository->hasAvailableCoins($interaction->member->user->id, $askCost)) {
            $message = sprintf(
                "Tu não tem dinheiro pra pagar o mestre, vai trabalhar!\n\nO mestre cobra singelos **%s coins** por pergunta!%s",
                $originalAskCost,
                $boostMessage
            );

            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: 'MESTRE NÃO É OTÁRIO',
                    message: $message,
                    image: $this->config['images']['nomoney']
                ),
                true
            );
            return;
        }

        if (strlen($question) > $questionLimit) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    title: 'MESTRE FICOU PUTO',
                    message: 'Tu é escritor por acaso? Escreve menos na moralzinha!',
                    image: $this->config['images']['typer']
                ),
                true
            );
            return;
        }

        try {
            if ($boost) {
                $interaction->acknowledgeWithResponse(true)->then(
                    function () use ($interaction, $question, $askCost, $questionLimit, $boostValue) {
                        $data = $this->makeQuestion($question, $questionLimit, true);

                        $message = "**Pergunta:**\n$question\n\n**Resposta:**\n";
                        $message .= $data->choices[0]->message->content;

                        if ($data->choices[0]->finish_reason === 'length') {
                            $message .= '... e bla bla bla.';
                        }

                        $message .= sprintf("\n\n**Custo:** %s coins", $askCost);

                        $interaction->user->sendMessage(
                            $this->messageComposer->embed(
                                'SABEDORIA DO MESTRE',
                                $message,
                                '#1D80C3'
                            )
                        );

                        $interaction->updateOriginalResponse(
                            $this->messageComposer->embed(
                                'SABEDORIA DO MESTRE',
                                "Respostas com **boost** vão para DM. Cheque sua DM, e a resposta está lá!",
                                '#1D80C3'
                            )
                        );

                        $user = $this->userRepository->getByDiscordId($interaction->member->user->id);
                        $this->userCoinHistoryRepository->create(
                            $user[0]['id'],
                            -$askCost,
                            'Master',
                            null,
                            "Boost: $boostValue"
                        );
                    }
                );

                return;
            }

            $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $question, $askCost, $boost) {
                $questionData = $this->makeQuestion($question, getenv('MASTER_DEFAULT_TOKENS_AMOUNT_RESPONSE'));

                $message = "**Pergunta:**\n$question\n\n**Resposta:**\n";
                $message .= $questionData->choices[0]->message->content;

                if ($questionData->choices[0]->finish_reason === 'length') {
                    $message .= '... etc e tals já tá bom né?!';
                }

                $message .= sprintf("\n\n**Custo:** %s coins", $askCost);

                $interaction->updateOriginalResponse(
                    $this->messageComposer->embed(
                        'SABEDORIA DO MESTRE',
                        $message,
                        '#1D80C3'
                    )
                );

                $voiceEnabled = (bool) getenv('MASTER_VOICE_ENABLED');

                if ($voiceEnabled) {
                    $this->discord->getLogger()->info('Generating voice for question: ' . $question);

                    $audioFilename = $this->generateVoice($questionData->choices[0]->message->content);

                    $interaction->updateOriginalResponse(
                        $this->messageComposer->embed(
                            title: 'SABEDORIA DO MESTRE',
                            message: $message,
                            color: '#1D80C3',
                            file: $audioFilename
                        )
                    );
                }

                $user = $this->userRepository->getByDiscordId($interaction->member->user->id);
                $this->userCoinHistoryRepository->create($user[0]['id'], -$askCost, 'Master');
            });

            return;
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }

    public function generateVoice(string $text)
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            "xi-api-key" => getenv('ELEVENLABS_API_KEY'),
        ];

        $body = [
            'model_id' => 'eleven_multilingual_v2',
            'text' => $text,
            'voice_settings' => [
                'similarity_boost' => 1,
                'stability' => 1,
                'style' => 1,
                'use_speaker_boost' => true,
            ],
        ];

        try {
            $request = new Request('POST', 'https://api.elevenlabs.io/v1/text-to-speech/' . getenv('MASTER_VOICE_ID'), $headers, json_encode($body));
            $response = $client->send($request);
            $data = $response->getBody()->getContents();
            $filename = sprintf("%s/storage/audios/%s.mp3", realpath(__DIR__ . '/../../../../'), date('d-m-Y_H-i-s-m-u'));

            $this->discord->getLogger()->info('Saving audio file: ' . $filename);

            file_put_contents($filename, $data);

            return $filename;
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }

    private function makeQuestion(string $question, int $tokens, bool $boost = false)
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
                "role" => "user",
                "content" => $question
            ]
        ];

        if (!$boost) {
            $messages[] = [
                "role" => "system",
                "content" => getenv('MASTER_HUMOR')
            ];
        }

        $body = [
            "model" => getenv('OPENAI_COMPLETION_MODEL'),
            "messages" => $messages,
            "temperature" => 1.2,
            "top_p" => 1,
            "n" => 1,
            "stream" => false,
            "max_tokens" => $tokens,
            "presence_penalty" => 0,
            "frequency_penalty" => 0
        ];

        try {
            $request = new Request('POST', 'https://api.openai.com/v1/chat/completions', $headers, json_encode($body));
            $response = $client->send($request);
            $data = json_decode($response->getBody()->getContents());
            return $data;
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }
}
