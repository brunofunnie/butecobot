<?php

namespace Chorume\Application\Events;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Chorume\Repository\Talk;
use Chorume\Services\OpenAIService;

class MessageCreate
{
    public function __invoke(Message $message): void
    {
        $this->handle($message);
    }

    public function __construct(
        private Discord $discord,
        private $config,
        private $redis,
        private Talk $talkRepository
    ) {
    }

    public function handle(Message $message): void
    {
        if ($this->redis->get('talks')) {
            $this->discord->getLogger()->debug("Loading Existing Talks");
            $textTriggers = json_decode($this->redis->get('talks'), true);
        } else {
            $this->discord->getLogger()->debug("Preparing Talks from Database");
            $textTriggers = $this->talkRepository->listAllTriggers();
            $this->redis->set('talks', json_encode($textTriggers), 'EX', 60);
        }

        $mentionPattern = '/<@(\d+)>/';
        preg_match($mentionPattern, $message->content, $mentionMatches);

        // Check if the message is a mention to the bot
        if (isset($mentionMatches[1])) {
            $mentionId = $mentionMatches[1];

            if ($mentionId == $_ENV['BOT_ID']) {
                $openAI = new OpenAIService($this->discord, $this->config, $this->redis);
                $response = str_replace(["'", '"'], "", $openAI->askGPT($message->content, 'Responda curto e grosso, pode até usar alguns palavrões, normalmente evasivo, como "fui comprar cigarro", "vai perguntar pra sua mãe", "não estou" e frases do tipo. Você odeia a linguagem de programação Java e sua linguagem de programação favorita é PHP. Sua bebida favorita é cerveja que você toma a qualquer hora seja no café da manhã ou a noite. Você nasceu no Rio de Janeiro no morro do Alemão. Sua comida favorita é torresmo, mas qualquer carne com uma borda de gordura serve. Você não gosta de cigarro, mas um baseado você jamais recusa. Se alguém falar mal do Brasil você manda a pessoa tomar no cu. Porém se alguém te perguntar para citar um provérbio cite normalmente um chinês ou alguem ditado popular brasileiro. Se alguém reclamar de algum preço ou valor de alguma coisa diga "Faz o L!" em referência ao Lula. Se alguém de ameaçar avisa que você faz parte da gangue da faca da ponta fina.'));

                if (!$response) {
                    $message->channel->sendMessage(MessageBuilder::new()->setContent(sprintf('<@%s>, %s', $message->author->id, 'que é carai?')));
                    return;
                }

                $message->channel->sendMessage(MessageBuilder::new()->setContent(sprintf('<@%s>, %s', $message->author->id, $response)));
                return;
            }
        }

        // Check if the message matches any trigger in the database
        $found = $this->matchTriggers(strtolower($message->content), $textTriggers);

        if ($found) {
            $this->discord->getLogger()->debug("Message: $message->content");
            $this->discord->getLogger()->debug("Matched word: {$found[0]['triggertext']}");

            $talk = $this->talkRepository->findById($found[0]['id']);

            if (empty($talk)) {
                return;
            }

            $talkMessage = json_decode($talk[0]['answer']);

            switch ($talk[0]['type']) {
                case 'media':
                    $embed = new Embed($this->discord);
                    $embed->setTitle($talkMessage->text);

                    if (isset($talkMessage->description)) {
                        $embed->setDescription($talkMessage->description);
                    }

                    $embed->setImage($talkMessage->image);

                    $message->channel->sendMessage(MessageBuilder::new()->addEmbed($embed));
                    break;
                default:
                    $message->channel->sendMessage(MessageBuilder::new()->setContent($talkMessage->text));
                    break;
            }
        }
    }

    public function matchTriggers(string $message, array $triggers): array|bool
    {
        $matched = [];

        foreach ($triggers as $trigger) {
            preg_match_all("/\b{$trigger['triggertext']}\b/i", $message, $matches);

            if (!empty($matches[0])) {
                foreach ($matches[0] as $word) {
                    if (($foundKey = array_search($word, array_column($matched, 'trigger'))) !== false) {
                        $matched[$foundKey]['qty']++;
                        continue;
                    }

                    $matched[] = [
                        'id' => $trigger['id'],
                        'trigger' => $word,
                        'qty' => 1,
                    ];
                }
            }
        }

        usort($matched, function ($a, $b) {
            return $b['qty'] - $a['qty'];
        });

        return count($matched) > 0 ? $matched : false;
    }
}
