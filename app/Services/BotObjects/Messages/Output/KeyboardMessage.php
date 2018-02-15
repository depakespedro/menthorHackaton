<?php

namespace App\Services\BotObjects\Messages\Output;

class KeyboardMessage extends Message
{
    const MARKUP_DEFAULT = 'default'; // простая разметка (массив кнопок)
    const MARKUP_LINES = 'lines'; // линейная разметка (массив линий с кнопками)

    const BUTTONS_TYPE_CHOICE = 'choice'; // один выбор (кнопки)
    const BUTTONS_TYPE_CHECKBOX = 'checkbox'; // множественный выбор

    protected $markup;

    protected $buttonsType;

    protected $keyboard = [];

    protected $inline;

    public function __construct($text, array $keyboard = [], $inline = true)
    {
        parent::__construct($text);

        $this->markup = self::MARKUP_DEFAULT;
        $this->buttonsType = self::BUTTONS_TYPE_CHOICE;

        $this->setKeyboard($keyboard);
        $this->inline = $inline;
    }

    public function setKeyboard(array $keyboard)
    {
        $this->keyboard = $keyboard;

        return $this;
    }

    public function setInline($isInline)
    {
        $this->inline = (bool)$isInline;

        return $this;
    }

    public function isInline()
    {
        return $this->inline;
    }

    public function getKeyboard()
    {
        return $this->keyboard;
    }

    public function getMarkup()
    {
        return $this->markup;
    }

    public function setMarkup($markup)
    {
        $this->markup = $markup;

        return $this;
    }

    public function getButtonsType()
    {
        return $this->buttonsType;
    }

    public function setButtonsType($buttonsType)
    {
        $this->buttonsType = $buttonsType;

        return $this;
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

    // todo избавиться от линейной разметки через репозиторий Телеграма
    public function addUrlButtonsLine(array $buttonsLine)
    {
        $this->keyboard[] = $buttonsLine;

        return $this;
    }

    // todo избавиться от линейной разметки через репозиторий Телеграма
    public function addCallbackButtonsLine(array $buttonsLine)
    {
        $this->keyboard[] = $buttonsLine;

        return $this;
    }
}
