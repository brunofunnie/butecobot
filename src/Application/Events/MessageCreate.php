<?php

namespace Chorume\Application\Events;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Chorume\Repository\Talk;

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
            $textTriggers = json_decode($this->redis->get('talks'), true);
        } else {
            $textTriggers = $this->talkRepository->listAllTriggers();
            $this->redis->set('talks', json_encode($textTriggers), 'EX', 60);
        }

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
