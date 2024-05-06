<?php

namespace ButecoBot\Services;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;

class OpenAIService
{
    public function __construct(
        private $discord,
        private $config,
        private $redis,
    ) {
    }

    public function askGPT($question, $humor = 'You are a helpful assistant.', $tokens = 50)
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY'),
        ];
        $messages = [
            [ "role" => "system", "content" => $humor ],
            [ "role" => "user", "content" => $question ],
        ];
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

            if (count($data->choices) > 0) {
                return $data->choices[0]->message->content;
            }

            return null;
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }
}