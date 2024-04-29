<?php

namespace Chorume\Application\Discord;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;

class MessageComposer
{
    public function __construct(private Discord $discord)
    {
    }

    public function embed(
        string $title,
        string $message,
        string $color = null,
        string $image = null,
        string $file = null,
        string $thumbnail = null,
        array $fields = []
    ): MessageBuilder
    {
        $embed = new Embed($this->discord);
        $embed
            ->setTitle($title)
            ->setDescription($message);

        if ($thumbnail) {
            $embed->setThumbnail($thumbnail);
        }

        if ($image) {
            $embed->setImage($image);
        }

        if ($color) {
            $embed->setColor($color);
        }

        if (count($fields) > 0) {
            foreach ($fields as $field) {
                $embed->addField($field);
            }
        }

        $messageBuilded = MessageBuilder::new()->addEmbed($embed);

        if ($file) $messageBuilded->addFile($file);

        return $messageBuilded;
    }
}
