<?php

namespace App\Services\Repositories;

use App\Services\BotObjects\Messages\Output\ImageMessage;
use App\Services\Contracts\SurveybotContract;
use App\Services\Contracts\CommandsManagerContract;
use Telegram\Bot\Api as TelegramApi;
use Telegram\Bot\Actions as TelegramActions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

use App\Services\BotObjects\Messages\Output\ImageMessage as OutputImageMessage;
use App\Services\BotObjects\Messages\Output\KeyboardMessage as OutputKeyboardMessage;
use App\Services\BotObjects\Messages\Output\TextMessage as OutputTextMessage;
use App\Services\BotObjects\Messages\Output\DialogMessage as OutputDialogMessage;
use App\Services\BotObjects\Messages\Output\Message as OutputMessage;

use App\Services\BotObjects\Messages\Input\CallbackMessage as InputCallbackMessage;
use App\Services\BotObjects\Messages\Input\TextMessage as InputTextMessage;
use App\Services\BotObjects\Messages\Input\Message as InputMessage;

use App\Models\Menubutton;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Respondent;

use Telegram\Bot\Exceptions\TelegramResponseException;
use Illuminate\Http\Request;

use App\Events\Webbot\ConversationCreated;
use App\Events\Webbot\ConversationReplyCreated;

class TelegramBotRepository implements SurveybotContract
{
    protected $bot;

    protected $typeData;

    protected $update;
    protected $message;
    protected $from;
    protected $chat;
    protected $callback;
    protected $messageInput;

    public function __construct(array $params = [])
    {
        //инициализируем бота
        $this->bot = new TelegramApi();

        //достаем переданные данные
        $this->update = $this->bot->commandsHandler(true);

        Log::info('TelegramBotRepository update = ' . print_r($this->update, true));

        //определяем тип входящих данных
        if ($this->update->getMessage()) {

            $this->initForMessage();

        } elseif ($this->update->getCallbackQuery()) {

            $this->initForCallback();
        }
    }

    private function initForMessage()
    {
        $this->typeData = self::MESSAGE;

        $this->message = $this->update->getMessage();

        $this->chat = $this->update->getMessage()->getChat();

        $this->from = $this->update->getMessage()->getFrom();
    }

    private function initForCallback()
    {
        $this->typeData = self::CALLBACK;

        $this->callback = $this->update->getCallbackQuery();

        $this->message = $this->update->getCallbackQuery()->getMessage();

        $this->chat = $this->update->getCallbackQuery()->getMessage()->getChat();

        $this->from = $this->update->getCallbackQuery()->getMessage()->getFrom();
    }

    public function getChatId()
    {
        return $this->chat->getId();
    }

    public function sendMessage(OutputMessage $message, $chat = '')
    {
        if (!$chat) {
            $chat = $this->chat->getId();
        }

        if ($message->getType() === OutputMessage::TYPE_DIALOG)
            return $this->sendDialog($message);

        $message = $this->transformMessage($message, $chat);

        if (isset($message['typeMessage']) && ($message['typeMessage'] == 'imageMessage')) {
            unset($message['text']);
            unset($message['typeMessage']);

            try {
                $this->bot->sendPhoto($message);
            } catch (TelegramResponseException $e) {
                $errorData = $e->getResponseData();
                if ($errorData['ok'] === false) {
                    $this->bot->sendMessage([
                        'text' => 'Ошибка ' . $errorData['error_code'] . ': ' . $errorData['description'],
                        'chat_id' => $chat,
                    ]);
                }
            }

        } else {

            try {
                $this->bot->sendMessage($message);
            } catch (TelegramResponseException $e) {
                $errorData = $e->getResponseData();

                if ($errorData['ok'] === false) {
                    $this->bot->sendMessage([
                        'text' => 'Ошибка ' . $errorData['error_code'] . ': ' . $errorData['description'],
                        'chat_id' => $chat,
                    ]);
                }
            }
        }

        return null;
    }

    public function sendDialog(OutputDialogMessage $message)
    {
        // Диалог
        $conversation = $message->getConversation();

        // Если сообщение является новым ответом в диалоге
        if ($message->isReply()) {

            $reply = $message->getReply();

            // Сохраняем новый ответ в диалоге
            $conversation->replies()->save($reply);

            broadcast(new ConversationReplyCreated($reply))->toOthers();

        } else { // Если это не новый ответ, значит это новый диалог

            if ($notifyTextForRespondent = $message->getText())
                $this->sendMessage(new OutputTextMessage($notifyTextForRespondent));

            $widget = $message->getWidget();
            broadcast(new ConversationCreated($conversation, $widget))->toOthers();
        }

        // Обновляем время последнего ответа
        $conversation->touchLastReply();

        return true;
    }

    public function getMessage()
    {
        if (is_null($this->chat)) {
            return null;
        }

        $chatId = $this->chat->getId();

        if ($chatId == '380866') {
            return null;
        }

        $text = $this->message->getText();
        $respondentId = null;

        if ($this->typeData == self::CALLBACK) {

            $callbackString = $this->callback->getData();
            $explodedCallbackString = explode(' ', $callbackString);
            $commandName = ltrim($explodedCallbackString[0], '/');
            $arguments = isset($explodedCallbackString[1]) ? json_decode($explodedCallbackString[1], true) : [];

            $message = new InputCallbackMessage();
            $message->setMessengerName('telegram');
            $message->setArguments($arguments);
            $message->setCommandName($commandName);

            $this->bot->answerCallbackQuery([
                'callback_query_id' => $this->callback->getId(),
                'text' => '',
                'show_alert' => false,
            ]);

        } elseif ($this->typeData == self::MESSAGE) {

            $message = new InputTextMessage();
            $message->setMessengerName('telegram');
            $message->setChatId($chatId);

            // Проверяем является ли текст коммандой
            $commandData = $this->detectCommandFromText();

            // Проверка для перехода в диалог с оператором
            if (!is_array($commandData) && !$message->surveyIsRunning()) {
                $commandData = [
                    'name' => 'dialog_mode',
                    'arguments' => []
                ];
            }

            if (is_array($commandData)) {

                if ($commandData['name'] === 'dialog_mode') {
                    $respondent = $this->getRespondent();
                    $lastSurvey = $respondent->getLastSurvey();
                    $widget = $lastSurvey->widget;

                    $commandData['arguments'] = [
                        'widget_id' => $widget ? $widget->id : 344,
                        'survey_id' => $lastSurvey ? $lastSurvey->id : null,
                    ];
                }

                $message = new InputCallbackMessage();
                $message->setMessengerName('telegram');
                $message->setCommandName($commandData['name']);
                $message->setArguments($commandData['arguments']);
            }
        } else {
            return null;
        }

        if ($message->getMessageType() === $message::TYPE_CALLBACK) {
            $arguments = $message->getArguments();
            $respondentId = isset($arguments['respondent_id']) ? $arguments['respondent_id'] : null;
        }

        $message->setChatId($chatId)
            ->setMessengerName('telegram')
            ->setRespondentId($respondentId)
            ->setText($text)
            ->setUserData([
                'username' => $this->chat->getUsername(),
                'first_name' => $this->chat->getFirstName(),
                'last_name' => $this->chat->getLastName(),
            ]);

        $this->messageInput = $message;

        return $message;
    }

    /**
     * Возвращает имя команды и аргументы, если команду пытаются вызвать текстом
     * @return array ['name' => string, 'arguments' => array] or null
     */
    public function detectCommandFromText()
    {
        $isCommand = false;

        if ($entities = $this->message->getEntities()) {
            foreach ($entities as $entity) {
                if ($entity['type'] === 'bot_command') {
                    $isCommand = true;
                    break;
                }
            }
        }

        $text = $this->message->getText();
        $explodedText = explode(' ', $text);
        $firstWord = trim($explodedText[0]);
        $firstWordTrimmed = ltrim($firstWord, '/');

        foreach (Menubutton::getAll() as $mbutton) {
            if ($text === "-$mbutton->title-") {
                return [
                    'name' => $mbutton->command->name,
                    'arguments' => []
                ];
            }
        }

        if ($isCommand || (isset($firstWord[0]) && $firstWord[0] === '/' && method_exists(CommandsManagerContract::class, $firstWordTrimmed))) {

            $arguments = [];

            // Пробуем найти id стартового опроса
            if ($firstWordTrimmed === 'start' && isset($explodedText[1])) {

                //todo команда - start=cmd-getChatInfo--foo-bar--foo1-bar1
                Log::info('detectCommandFromText = ' . print_r($explodedText, true));

                $commandRaw = $explodedText[1];
                $command = explode('-', $commandRaw);

                Log::info('detectCommandFromText2 = ' . print_r($command, true));

                if ($command[0] == 'cmd') {
                    $commaName = $command[1];

                    $args = explode('--', $commandRaw);

                    Log::info('$argsParse = ' . print_r($args, true));

                    $arguments = [];
                    if (isset($args[0])) {
                        unset($args[0]);

                        foreach ($args as $arg) {
                            $argRaw = explode('-', $arg);
                            if (isset($argRaw[0])) {
                                if (isset($argRaw[1])) {
                                    $arguments[$argRaw[0]] = $argRaw[1];
                                } else {
                                    $arguments[$argRaw[0]] = null;
                                }
                            }
                        }
                    }

                    Log::info('$arguments = ' . print_r($arguments, true));

                    return [
                        'name' => $commaName,
                        'arguments' => $arguments
                    ];
                }

                $explodedArgs = explode('survey', $explodedText[1]);
                $surveyId = isset($explodedArgs[1]) ? $explodedArgs[1] : null;
                $arguments = $surveyId ? ['survey_id' => (int)$surveyId] : [];
            } else {
                $arguments = isset($explodedText[1]) ? json_decode($explodedText[1], true) : [];
            }

            return [
                'name' => $firstWordTrimmed,
                'arguments' => $arguments
            ];
        }

        return null;
    }

    /**
     * Имитирует действие печатания...
     * @return bool
     */
    public function sendActionTyping($chat = null)
    {
        if (!is_null($chat)) {
            $chatId = $chat;
        } else {
            $chatId = $this->chat->getId();
        }

        return $this->bot->sendChatAction([
            'action' => TelegramActions::TYPING,
            'chat_id' => $chatId
        ]);
    }

    public function stopTyping($chatId = null)
    {
        return null;
    }

    /**
     * Устанавливает закрепленное меню (клавиатура в самом низу чата)
     * или предлагает запустить опрос, который открыт по ссылке
     * @param string $text
     * @param string $chatId
     * @return void
     */
    public function setMenu($text, $chatId, $startSurvey = null)
    {
        $buttons = [];
        foreach (Menubutton::getAll() as $mbutton) {
            if ($startSurvey) {
                $button = [
                    'text' => "Запустить опрос",
                    'callback_data' => "/start_survey " . json_encode([
                        'survey_id' => $startSurvey->id
                    ]),
                ];

                $buttons[] = [$button];
                break;
            }

            $button = ['text' => "-$mbutton->title-"];
            $buttons[] = [$button];
        }

        $markupParams = [
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];

        $imageUrl = url('img/robot-friend-fb.png');

        if ($startSurvey) {
            // Заголовок, описание, приветственный текст опроса
            $text = "*$startSurvey->title:*" . PHP_EOL;
            if ($startSurvey->description)
                $text .= "*$startSurvey->description*" . PHP_EOL . PHP_EOL;
            if ($startSurvey->welcome_text) {
                $respondent = $this->getRespondent();
                $welcome_text = replacementText($startSurvey->welcome_text, ['%first_name%' => $respondent->first_name]);
                $text .= $welcome_text . PHP_EOL;
            }

            $markupParams['inline_keyboard'] = $buttons;
            $replyMarkup = $this->bot->replyKeyboardMarkup($markupParams);

            if ($startSurvey->image) {
                $imageUrl = url($startSurvey->image);
            }

            Log::info('telegram setMenu $text = ' . print_r($text, true));

            try {
                $this->bot->sendPhoto([
                    'chat_id' => $chatId,
                    'caption' => '',
                    'photo' => $imageUrl,
                    'reply_markup' => json_encode(['hide_keyboard' => true])
                ]);

                $this->bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'markdown',
                    'reply_markup' => $replyMarkup
                ]);
            } catch (TelegramResponseException $e) {
                $errorData = $e->getResponseData();
                if ($errorData['ok'] === false) {
                    $this->bot->sendMessage([
                        'text' => 'Ошибка ' . $errorData['error_code'] . ': ' . $errorData['description'],
                        'chat_id' => '258029731', // Ernest chat id
                    ]);
                }
            }
        } else {
            $text .= 'Чем займемся сегодня?';
            $markupParams['keyboard'] = $buttons;
            $replyMarkup = $this->bot->replyKeyboardMarkup($markupParams);

            try {
                $this->bot->sendPhoto([
                    'chat_id' => $chatId,
                    'caption' => $text,
                    'photo' => $imageUrl,
                    'reply_markup' => $replyMarkup
                ]);
            } catch (TelegramResponseException $e) {
                $errorData = $e->getResponseData();
                if ($errorData['ok'] === false) {
                    $this->bot->sendMessage([
                        'text' => 'Ошибка ' . $errorData['error_code'] . ': ' . $errorData['description'],
                        'chat_id' => '258029731', // Ernest chat id
                    ]);
                }
            }
        }
    }

    /**
     * Действия перед стартом опроса (видимо этот метод не понадобится)
     * @param  object $survey
     * @param  string $chatId
     * @return void
     */
    public function beforeStartSurvey($survey, $chatId)
    {

    }

    /**
     * Имитирует переключение чекбоксов через редактирование клавиатуры
     * @param array $arguments
     * @param array $cacheData
     * @return null
     */
    public function toggleCheckbox(array $arguments, array $cacheData)
    {
        $chatId = $this->chat->getId();
        $answerValue = $arguments['val'];
        $cacheKey = 'chat' . $chatId . 'question' . $cacheData['question_id'] . 'checkboxes';

        $answersKeyboardString = Cache::get($cacheKey);
        $answersKeyboard = unserialize($answersKeyboardString);

        if (!$answersKeyboardString || !is_array($answersKeyboard))
            return null;

        foreach ($answersKeyboard as $lineKey => $lineButtons) {
            foreach ($lineButtons as $buttonKey => $button) {
                if (!isset($button['data']))
                    continue;

                $argsJson = explode(' ', $button['data'])[1];
                $args = json_decode($argsJson, true);

                if (!isset($args['val']))
                    continue;

                if ($args['val'] === $answerValue) {
                    $args['checked'] = abs((int)$args['checked'] - 1); // инвертирование 0 на 1 или наоборот 1 на 0
                } else {
                    $args['checked'] = (int)$args['checked'];
                }

                $answersKeyboard[$lineKey][$buttonKey]['text'] = $args['checked'] ? '✔ ' . $args['val'] : $args['val'];
                $answersKeyboard[$lineKey][$buttonKey]['data'] = '/toggle_checkbox ' . json_encode($args);
                $answersKeyboard[$lineKey][$buttonKey]['callback_data'] = $answersKeyboard[$lineKey][$buttonKey]['data'] = '/toggle_checkbox ' . json_encode($args);
            }
        }

        Cache::put($cacheKey, serialize($answersKeyboard), 300);

        $this->bot->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $this->message->getMessageId(),
            'reply_markup' => $this->bot->replyKeyboardMarkup([
                'inline_keyboard' => $answersKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ]),
        ]);

        return null;
    }

    public function addOneChoiceButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHOICE);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $surveyId = $question->group->survey->id;

        foreach ($question->getAnswersLines() as $answersLine) {
            $buttonsLine = [];

            foreach ($answersLine as $answer) {
                $args = json_encode([
                    'survey_id' => $surveyId,
                    'answer_id' => $answer->id,
                    'step' => $step,
                ]);

                $buttonsLine[] = [
                    'title' => $answer->answer_text,
                    'type' => 'callback',
                    'data' => '/next_question ' . $args,
                ];
            }

            $keyboard->addCallbackButtonsLine($buttonsLine);
        }

        return $keyboard;
    }

    public function addMultipleChoiceButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $cacheKey = 'chat' . $this->chat->getId() . 'question' . $question->id . 'checkboxes';

        if (Cache::has($cacheKey))
            Cache::forget($cacheKey);

        $questionText = $keyboard->getText();

        $multiChoiceDescription = '';
        $answersKeyboard = [];

        foreach ($question->getAnswersLinesWithCheckboxes() as $answersLine) {
            $buttonsLine = [];

            foreach ($answersLine as $answer) {
                $multiChoiceDescription = $multiChoiceDescription . $answer->order . ' - ' . $answer->answer_text . PHP_EOL;

                $args = json_encode([
                    'answer_id' => $answer->id,
                    'checked' => 0,
                    'val' => $answer->order,
                ]);

                $buttonsLine[] = [
                    'title' => $answer->order,
                    'type' => 'callback',
                    'data' => '/toggle_checkbox ' . $args,
                ];
            }

            $answersKeyboard[] = $buttonsLine;
            $keyboard->addCallbackButtonsLine($buttonsLine);
        }

        $answersKeyboard[] = [['text' => 'Продолжить', 'callback_data' => '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'cbx',
                'step' => $step,
            ])
        ]];

        Cache::add($cacheKey, serialize($answersKeyboard), 300);

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Продолжить', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'cbx',
                    'step' => $step,
                ])]
        ]);

        if (!$question->description)
            $questionText .= PHP_EOL . $multiChoiceDescription;

        $keyboard->setText($questionText);

        return $keyboard;
    }

    public function notifySimilarRespondentsKeyboard($keyboard, $room)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::TYPE_URL);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $url = route('web.bot.room.chat', $room->id);

        $keyboard->addUrlButtonsLine([
            ['title' => 'Открыть комнату', 'type' => 'url', 'data' => $url],
        ]);

        return $keyboard;
    }

    public function addSocialShareButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $socialUrl = $question->url_social_network;

        $questionText = $keyboard->getText();

        $imageUrl = '';
        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
            $imageUrl = $imageMessage->getUrlImage();
        }

        $keyboard->addUrlButtonsLine([
            ['title' => 'VK', 'type' => 'url', 'data' => 'http://vk.com/share.php?url=' . $socialUrl],
            ['title' => 'Facebook', 'type' => 'url', 'data' => 'https://www.facebook.com/sharer/sharer.php?u=' . $socialUrl],
        ]);

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Продолжить', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ssh',
                    'step' => $step,
                ])]
        ]);


        return $keyboard;
    }

    public function addGenderRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Мужчина', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'grm',
                    'step' => $step,
                ])],
            ['title' => 'Женщина', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'grf',
                    'step' => $step,
                ])],
        ]);

        return $keyboard;
    }

    public function addEducationRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Среднее', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ed1',
                    'step' => $step,
                ])],
            ['title' => 'Неоконченное высшее', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ed2',
                    'step' => $step,
                ])],
        ]);

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Высшее', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ed3',
                    'step' => $step,
                ])],
            ['title' => 'Ученая степень/два или более высших', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ed4',
                    'step' => $step,
                ])],
        ]);

        return $keyboard;
    }

    public function addFamilyStatusRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Я женат/замужем', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'fs1',
                    'step' => $step,
                ])],
            ['title' => 'У меня есть пара', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'fs2',
                    'step' => $step,
                ])],
        ]);

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Я свободен холост', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'fs3',
                    'step' => $step,
                ])],
            ['title' => 'Не хочу говорить об этом', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'fs4',
                    'step' => $step,
                ])],
        ]);

        return $keyboard;
    }

    public function addChildrenRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Да', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'chy',
                    'step' => $step,
                ])],
            ['title' => 'Нет', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'chn',
                    'step' => $step,
                ])],
        ]);

        return $keyboard;
    }

    public function addRevenueRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButtonsLine([
            ['title' => '1', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'r1',
                    'step' => $step,
                ])],
            ['title' => '2', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'r2',
                    'step' => $step,
                ])],
            ['title' => '3', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'r3',
                    'step' => $step,
                ])],
            ['title' => '4', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'r4',
                    'step' => $step,
                ])],
        ]);

        $keyboard->addCallbackButtonsLine([
            ['title' => '5', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'r5',
                    'step' => $step,
                ])],
            ['title' => '6', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'r6',
                    'step' => $step,
                ])],
            ['title' => '7', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'r7',
                    'step' => $step,
                ])],
            ['title' => '8', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'r8',
                    'step' => $step,
                ])],
        ]);

        return $keyboard;
    }

    public function addWorkingRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Да', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'wy',
                    'step' => $step,
                ])],
            ['title' => 'Нет', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'wn',
                    'step' => $step,
                ])],
        ]);

        return $keyboard;
    }

    public function addBonusButton($keyboard, $bonus, $customTitle = null)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHOICE);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $keyboard->addCallbackButtonsLine([
            ['title' => $customTitle ?: $bonus->title, 'type' => 'callback', 'data' => '/bonuses ' . json_encode([
                    'bonus_id' => $bonus->id,
                    // 'survey_id' => $survey ? $survey->id : null,
                ])]
        ]);

        return $keyboard;
    }

    /**
     * Возвращает набор отетов App\Models\Answer, достав из кеша id ответов соответствующие выбранным чекбоксам (работает только для чекбоксов в Телеграме)
     * @param  array $checkedAnswers
     * @param  string $cacheKey
     * @return App\Models\Answer collection
     */
    public function transformCheckboxesToAnswers($checkedAnswers = null, $cacheKey = null, $customAnswer = null)
    {
        if (!$cacheKey || !Cache::has($cacheKey))
            return null;

        $answersKeyboardString = Cache::get($cacheKey);
        $answersKeyboard = unserialize($answersKeyboardString);

        if (!$answersKeyboardString || !is_array($answersKeyboard))
            return null;

        foreach ($answersKeyboard as $lineButtons) {
            foreach ($lineButtons as $button) {
                if (!isset($button['data']))
                    continue;

                $argsJson = explode(' ', $button['data'])[1];
                $args = json_decode($argsJson, true);

                if (!isset($args['checked']))
                    continue;

                if ((int)$args['checked'] > 0)
                    $checkedAnswers[] = $args['answer_id'];
            }
        }

        if (!count($checkedAnswers))
            return null;

        Cache::forget($cacheKey);

        return Answer::find($checkedAnswers);
    }

    public function addSocialAuthButtons($keyboard, $question, $step, Respondent $respondent)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::TYPE_URL);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $params = [
            'respondent' => $respondent->id,
            'surveyId' => $question->group->survey->id,
            'typeMessendger' => 'telegram',
            'typeAnswer' => 'sa',
            'chatId' => $this->getChatId(),
        ];

        $urlAuthVk = route('auth.social.vk', $params);
        $urlAuthFb = route('auth.social.fb', $params);


        $keyboard->addUrlButtonsLine([
            ['title' => 'VK', 'type' => 'url', 'data' => $urlAuthVk],
            ['title' => 'FB', 'type' => 'url', 'data' => $urlAuthFb],
        ]);

        $keyboard->addCallbackButtonsLine([
            ['title' => 'Несколько вопросов', 'type' => 'callback', 'data' => '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'sa',
                    'step' => $step,
                ])]
        ]);

        return $keyboard;
    }

    public function getBot()
    {
        return $this->bot;
    }

    private function transformMessage(OutputMessage $message, $chat)
    {
        $params = $message->getOtherParams();

        $class_message = get_class($message);

        if ($class_message === OutputTextMessage::class) {

            $message = $this->transformFromTextMessage($message, $chat);

            return $message;

        } elseif ($class_message === OutputKeyboardMessage::class) {

            $message = $this->transformFromKeyboardMessage($message, $chat);

            return $message;

        } elseif ($class_message === OutputImageMessage::class) {

            $message = $this->transformImageMessage($message, $chat);

            return $message;
        }

        return null;
    }

    private function transformFromTextMessage(OutputTextMessage $message, $chat)
    {
        $data = [
            'text' => $message->getText(),
            'chat_id' => $chat
        ];

        if ($image = $message->getImage()) {
            $data['typeMessage'] = 'imageMessage';
            $data['photo'] = $image->getUrlImage();
            $data['caption'] = $image->getCaption();
        }

        return $data;
    }

    private function transformFromKeyboardMessage(OutputKeyboardMessage $message, $chat)
    {
        $buttons = [];
        foreach ($message->getKeyboard() as $keyboardLine) {

            $buttonsLine = [];

            foreach ($keyboardLine as $button) {

                $type_button = $button['type'];

                if ($type_button == OutputMessage::TYPE_CALLBACK) {
                    $buttonsLine[] = [
                        'text' => $button['title'],
                        'callback_data' => $button['data']
                    ];
                } elseif ($type_button == OutputMessage::TYPE_URL) {
                    $buttonsLine[] = [
                        'text' => $button['title'],
                        'url' => $button['data']
                    ];
                }
            }

            $buttons[] = $buttonsLine;
        }

        if ($message->isInline()) {
            $reply_markup = $this->bot->replyKeyboardMarkup([
                'inline_keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ]);
        } else {
            $reply_markup = $this->bot->replyKeyboardMarkup([
                'keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ]);
        }

        $data = [
            'text' => $message->getText(),
            'reply_markup' => $reply_markup,
            'chat_id' => $chat,
        ];

        if ($image = $message->getImage()) {
            $data['typeMessage'] = 'imageMessage';
            $data['photo'] = $image->getUrlImage();
            $data['caption'] = $image->getCaption();
        }

        return $data;
    }

    private function transformImageMessage(OutputImageMessage $message, $chat)
    {
        return [
            'typeMessage' => 'imageMessage',
            'text' => $message->getText(),
            'caption' => $message->getCaption(),
            'photo' => $message->getUrlImage(),
            'chat_id' => $chat
        ];
    }

    private function getSurveyCacheData()
    {
        $chatId = $this->chat->getId();

        $cacheKey = $chatId ? 'chat' . $chatId : '';

        if (!$cacheKey)
            return [];

        return unserialize(Cache::get($cacheKey));
    }

    private function getRespondent()
    {
        $respondents = Respondent::where('messenger_user_id', '=', $this->chat->getId())
            ->where('provider', '=', 'telegram')
            ->get();

        if (!$respondents->isEmpty()) {
            return $respondents->first();
        }

        $respondent = new Respondent();
        $respondent->messenger_user_id = $this->chat->getId();
        $respondent->provider = 'telegram';
        $respondent->username = $this->chat->getUsername();
        $respondent->first_name = $this->chat->getFirstName();
        $respondent->last_name = $this->chat->getLastName();
        $respondent->save();

        return $respondent;
    }

    public function getProvider()
    {
        return 'telegram';
    }
}
