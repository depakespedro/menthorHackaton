<?php

namespace App\Services\BotObjects\Messages\Input;

class CallbackMessage extends Message
{
    // входящие аргументы
    protected $arguments;

    // имя команды
    protected $commandName;

    public function __construct()
    {
        $this->messageType = self::TYPE_CALLBACK;
    }

    public function setArguments(array $args)
    {
        $this->arguments = $args;
        return $this;
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function setCommandName($name)
    {
        $this->commandName = $name;
        return $this;
    }

    public function getCommandName()
    {
        return $this->commandName;
    }

}
