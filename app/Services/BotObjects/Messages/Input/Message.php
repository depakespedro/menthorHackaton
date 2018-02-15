<?php

namespace App\Services\BotObjects\Messages\Input;

use Illuminate\Support\Facades\Cache;
use App\Models\Respondent;

abstract class Message
{
    const TYPE_CALLBACK = 'callback';
    const TYPE_TEXT = 'text';

    // тип входящего сообщения - callback или text
    protected $messageType;

    // идентификатор сообщения
    protected $messageId;

    // идентификатор чата
    protected $chatId;

    // идентификатор отправителя сообщения
    protected $respondentId;

    // текст сообщения
    protected $text;

    // название мессенджера, с которого пришло сообщение
    protected $messengerName;

    // данные пользователя из мессенджера ['username', 'first_name', 'last_name']
    protected $userData = [];

    public function setUserData(array $data)
    {
        $this->userData = $data;
        return $this;
    }

    public function setMessageId($id)
    {
        $this->messageId = $id;
        return $this;
    }

    public function getMessageId()
    {
        return $this->messageId;
    }

    public function setChatId($id)
    {
        $this->chatId = $id;
        return $this;
    }

    public function getChatId()
    {
        return $this->chatId;
    }

    public function getMessageType()
    {
        return $this->messageType;
    }

    public function setRespondentId($id)
    {
        $this->respondentId = $id;
        return $this;
    }

    public function getRespondentId()
    {
        return $this->respondentId;
    }

    public function setMessengerName($name)
    {
        $this->messengerName = $name;
        return $this;
    }

    public function surveyIsRunning()
    {
        $cacheKey = $this->chatId ? 'chat' . $this->chatId : '';

        if (!$cacheKey)
            return false;

        return Cache::has($cacheKey);
    }

    public function getSurveyCacheData()
    {
        if (!$this->surveyIsRunning())
            return [];

        $cacheKey = $this->chatId ? 'chat' . $this->chatId : '';

        return unserialize(Cache::get($cacheKey));
    }

    public function setSurveyCacheData(array $data, $chatId = null)
    {
        $this->chatId = $chatId ?: $this->chatId;

        if (!$this->chatId) {
            return false;
        }

        return Cache::put('chat' . $this->chatId, serialize($data), 300);
    }

    public function getMessengerName()
    {
        return $this->messengerName;
    }

    public function getRespondent()
    {
        // Если есть respondentId, то сразу возвращаем респондента
        if ($this->respondentId)
            return Respondent::findOrFail($this->respondentId);

        $userData = $this->userData;

        // Ищем респондента по chatId и messengerName
        $respondents = Respondent::where('messenger_user_id', '=', $this->chatId)
            ->where('provider', '=', $this->getMessengerName())
            ->get();

        if (!$respondents->isEmpty()) {
            return $respondents->first();
        }

        //если респондента не нашли, то создаем нового (в случае фейсюука то запрашива доп параметры из апи)
        if ($this->getMessengerName() === 'facebook') {
            $facebookData = $this->getStartDataFacebook($this->chatId);

            $username = $facebookData['username'];
            $first_name = $facebookData['first_name'];
            $last_name = $facebookData['last_name'];
        } else {
            $username = isset($userData['username']) ? $userData['username'] : null;
            $first_name = isset($userData['first_name']) ? $userData['first_name'] : null;
            $last_name = isset($userData['last_name']) ? $userData['last_name'] : null;
        }

        $respondent = new Respondent();
        $respondent->messenger_user_id = $this->chatId;
        $respondent->provider = $this->getMessengerName();
        $respondent->username = $username;
        $respondent->first_name = remove_emoji($first_name);
        $respondent->last_name = remove_emoji($last_name);
        $respondent->save();

        return $respondent;
    }

    public function setText($text)
    {
        $this->text = $text;
        return $this;
    }

    public function getText()
    {
        return $this->text;
    }

    protected function getStartDataFacebook($chatId)
    {
        try {
            $token = config('facebook.token');
            $rawData = file_get_contents('https://graph.facebook.com/v2.6/' . $chatId . '?fields=first_name,last_name,profile_pic&access_token=' . $token);
            $data = (array)json_decode($rawData);

            $username = isset($data['username']) ? $data['username'] : null;
            $first_name = isset($data['first_name']) ? remove_emoji($data)['first_name'] : null;
            $last_name = isset($data['last_name']) ? $data['last_name'] : null;
        } catch (\Exception $exception) {
            $username = null;
            $first_name = null;
            $last_name = null;
        } catch (\Throwable $exception) {
            $username = null;
            $first_name = null;
            $last_name = null;
        }

        return [
            'username' => $username,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ];
    }
}
