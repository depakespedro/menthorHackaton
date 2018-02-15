<?php

namespace App\Services\BotObjects\Messages\Output;

use App\Models\Conversation;
use App\Models\Widget;

class DialogMessage extends Message
{
    protected $conversation;

    protected $reply = null;

    protected $widget = null;

    protected $keyboard = [];

    public function __construct(Conversation $conversation, $text = '')
    {
        parent::__construct($text);
        $this->setType(parent::TYPE_DIALOG);

        $this->conversation = $conversation;
    }

    public function addUrlButton($title, $url)
    {
        $this->keyboard[] = [
            'title' => $title,
            'type' => 'url',
            'data' => $url,
        ];

        return $this;
    }

    public function addCallbackButton($title, $callback)
    {
        $this->keyboard[] = [
            'title' => $title,
            'type' => 'callback',
            'data' => $callback,
        ];

        return $this;
    }

    public function getKeyboard()
    {
        return $this->keyboard;
    }

    public function getConversation()
    {
        return $this->conversation;
    }

    public function setReply(Conversation $reply)
    {
        $this->reply = $reply;
        return $this;
    }

    public function getReply()
    {
        return $this->reply;
    }

    public function isReply()
    {
        return !empty($this->reply);
    }

    public function setWidget(Widget $widget)
    {
        $this->widget = $widget;
        return $this;
    }

    public function getWidget()
    {
        return $this->widget;
    }
}
