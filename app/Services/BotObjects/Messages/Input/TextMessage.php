<?php

namespace App\Services\BotObjects\Messages\Input;

class TextMessage extends Message
{
    public function __construct()
    {
        $this->messageType = self::TYPE_TEXT;
    }
}
