<?php


namespace App\Services\BotObjects\Messages\Output;

class ImageMessage extends Message
{
    protected $pathImage;

    protected $caption;

    public function __construct($text, $pathImage, $caption = '')
    {
        parent::__construct($text);

        $this->pathImage = $pathImage;
        $this->caption = $caption;
    }

    public function getPathImage()
    {
        return $this->pathImage;
    }

    public function getCaption()
    {
        return $this->caption;
    }

    public function getUrlImage()
    {
        return url($this->getPathImage());
    }
}