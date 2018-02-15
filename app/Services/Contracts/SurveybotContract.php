<?php

namespace App\Services\Contracts;

use App\Services\BotObjects\Messages\Output\Message;

interface SurveybotContract
{

    const TYPE_TEXT_MESSAGE = 'text';
    const TYPE_KEYBOARD_MESSAGE = 'keyboard';
    const TYPE_IMAGE_MESSAGE = 'image';

    const CALLBACK = 'callback';
    const MESSAGE = 'message';

    /**
     * Возвращает сообщение полученное с webhook
     * 
     * @return Message
     */
    public function getMessage();

    /**
     * Отпрака сообщения
     *
     * @param Message $message
     * @return mixed
     */
    public function sendMessage(Message $message, $chat = '');

    /**
     * Возвращает инициализированного бота
     * @return mixed
     */
    public function getBot();

    /**
     * Возвращает индетификатор чата текущего
     * @return mixed
     */
    public function getChatId();

}
