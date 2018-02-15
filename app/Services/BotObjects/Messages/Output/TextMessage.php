<?php

namespace App\Services\BotObjects\Messages\Output;

class TextMessage extends Message
{
    public function __construct($text, $chatId = null)
    {
        $this->setChatId($chatId);

        parent::__construct($text);
    }
}