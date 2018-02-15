<?php

namespace App\Services\BotObjects\Messages\Output;

abstract class Message
{
    const TYPE_URL = 'url';
    const TYPE_CALLBACK = 'callback';
    const TYPE_DIALOG = 'dialog';

    protected $type;

    protected $text;

    protected $image = null;

    protected $mandatory = true;

    protected $delay = 0;

    protected $otherParams = [];

    protected $chatId;

    public function __construct($text)
    {
        $this->setText($text);
    }

    public function getText()
    {
        return $this->text;
    }

    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function isMandatory()
    {
        return (bool)$this->mandatory;
    }

    public function setMandatory($mandatory)
    {
        $this->mandatory = (bool)$mandatory;

        return $this;
    }

    public function getDelay()
    {
        return (int)$this->delay;
    }

    public function setDelay($delay)
    {
        $this->delay = (int)$delay;

        return $this;
    }

    public function setOtherParams(array $params)
    {
        $this->otherParams = $params;

        return $this->otherParams;
    }

    public function getOtherParams()
    {
        return $this->otherParams;
    }

    public function setImage(ImageMessage $image)
    {
        $this->image = $image;

        return $this;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function setChatId($chatId)
    {
        $this->chatId = $chatId;
    }

    public function getChatId()
    {
        $this->chatId;
    }
}
