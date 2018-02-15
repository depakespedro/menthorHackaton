<?php

namespace App\Services\Repositories;

use App\Models\Respondent;
use App\Models\Event;
use App\Services\BotObjects\Messages\Output\ImageMessage;
use App\Services\BotObjects\Messages\Output\KeyboardMessage;
use App\Services\Contracts\SurveybotContract;
use App\Services\Contracts\CommandsManagerContract;
use App\Events\Webbot\MessageWasSended;
use App\Events\Webbot\ConversationCreated;
use App\Events\Webbot\ConversationReplyCreated;
use App\Events\Webbot\TriggerEventWasFired;

use App\Services\BotObjects\Messages\Output\ImageMessage as OutputImageMessage;
use App\Services\BotObjects\Messages\Output\KeyboardMessage as OutputKeyboardMessage;
use App\Services\BotObjects\Messages\Output\TextMessage as OutputTextMessage;
use App\Services\BotObjects\Messages\Output\DialogMessage as OutputDialogMessage;
use App\Services\BotObjects\Messages\Output\Message as OutputMessage;

use App\Services\BotObjects\Messages\Input\CallbackMessage as InputCallbackMessage;
use App\Services\BotObjects\Messages\Input\TextMessage as InputTextMessage;
use App\Services\BotObjects\Messages\Input\Message as InputMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

use App\Models\Question;
use App\Models\Room;

use Request;

class WebBotRepository implements SurveybotContract
{
    protected $bot;
    protected $events;
    protected $event;
    protected $message;

    protected $widgetId;
    protected $chatId;
    protected $respondentId;
    protected $textMessage;

    public function __construct(array $params = [])
    {

    }

    public function setEvent(array $event)
    {
        $this->event = $event;
    }

    public function getChatId()
    {
        $event = $this->event;

        $dataEvent = json_decode($event['data'], true);
        $rawMessage = $dataEvent['message'];

        $chatId = $rawMessage['conversation_id'];

        return $chatId;
    }

    /**
     * @return InputCallbackMessage|InputTextMessage
     */
    public function getMessage()
    {
        $event = $this->event;

        $dataEvent = json_decode($event['data'], true);
        $rawMessage = $dataEvent['message'];

        // Событие отслеживания веб-ботом действий пользователя на сайте App\Models\Event
        if (isset($event['event']) && $event['event'] === 'client-TriggerEventWasFired') {
            $triggerEvent = Event::find($rawMessage['id']);
            $respondent = Respondent::find($rawMessage['respondent_id']);

            event(new TriggerEventWasFired($triggerEvent, $respondent));
            return null;
        }

        // Общение респондентов в комнате
        if (isset($rawMessage['room_id'])) {
            broadcast(new MessageWasSended([
                'message' => [
                    'type' => 'text-my',
                    'text' => $rawMessage['text_body'],
                    'buttons' => null,
                    'respondent_id' => (int)$rawMessage['respondent_id'],
                    'conversation_id' => $rawMessage['conversation_id']
                ]
            ]))->toOthers();

            $room = Room::find($rawMessage['room_id']);
            $room->messages()->create([
                'respondent_id' => (int)$rawMessage['respondent_id'],
                'body' => $rawMessage['text_body'],
            ]);

            return null;
        }

        if (Cache::has('chat' . $rawMessage['conversation_id'] . 'delayed')) {
            Log::info('chat delayed...');
            return null;
        }

        $this->chatId = $rawMessage['conversation_id'];
        $respondentId = $rawMessage['respondent_id'];
        $this->respondentId = $respondentId;

        $widgetId = isset($rawMessage['widget_id']) ? $rawMessage['widget_id'] : null;
        $surveyId = isset($rawMessage['survey_id']) ? $rawMessage['survey_id'] : null;
        $text = $rawMessage['text'];
        $textBody = isset($rawMessage['text_body']) ? $rawMessage['text_body'] : null;
        $this->textMessage = $text;

        $args = $rawMessage['params'];

        Log::info('$rawMessage=' . print_r($rawMessage, true));
        Log::info('$args=' . print_r($args, true));

        $args['widget_id'] = $widgetId;

        $args['survey_id'] = isset($args['survey_id']) ? $args['survey_id'] : $surveyId;

        if ($rawMessage['type'] == 'command') {

            $message = new InputCallbackMessage();
            $commandName = ltrim($text, '/');

            $message->setChatId($this->chatId)
                ->setCommandName($commandName)
                ->setMessengerName('webbot')
                ->setArguments($args);

        } elseif ($rawMessage['type'] == 'text-my') {

            $message = new InputTextMessage();
            $this->message = $message->setText($text)->setChatId($this->chatId);

            // Проверяем является ли текст коммандой
            $commandData = $this->detectCommandFromText();

            if (is_array($commandData)) {
                $commandData['arguments']['widget_id'] = $widgetId;
                $commandData['arguments']['survey_id'] = $surveyId;

                $message = new InputCallbackMessage();
                $message->setCommandName($commandData['name'])
                    ->setArguments($commandData['arguments']);
            }
        } elseif ($rawMessage['type'] == 'trigger') {

            // $message = new InputTextMessage();
            // $this->message = $message->setText($text)->setChatId($this->chatId);
        }

        $message->setChatId($this->chatId)
            ->setMessengerName('webbot')
            ->setRespondentId($respondentId)
            ->setText($text);

        if ($textBody) {
            broadcast(new MessageWasSended([
                'message' => [
                    'type' => 'text-my',
                    'text' => $textBody,
                    'buttons' => null,
                    'conversation_id' => $this->chatId,
                ]
            ]))->toOthers();
        }

        $this->message = $message;

        return $message;
    }

    public function sendMessage(OutputMessage $message, $chat = '')
    {
        if (empty($chat)) {
            $chat = $this->chatId;
        }

        if ($message->getType() === OutputMessage::TYPE_DIALOG)
            return $this->sendDialog($message);

        $otherParams = $message->getOtherParams();

        $image = $message->getImage();

        $message = $this->transformMessage($message, $chat);

        if (is_object($image)) {
            $message['message']['image'] = $image->getUrlImage();
        }
        if (isset($otherParams['action'])) {
            $message['message']['action'] = $otherParams['action'];
        }

        Log::info('WebBot transformed message: ' . print_r($message, true));

        broadcast(new MessageWasSended($message));
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

            if ($notifyTextForRespondent = $message->getText()){

                $otherParams = $message->getOtherParams();
                if(!isset($otherParams['respondent_id'])){
                    $this->sendMessage(new OutputTextMessage($notifyTextForRespondent));
                }

                $respondentId = $otherParams['respondent_id'];

                $outpuMessage = new KeyboardMessage('Не могу быстро ответить. Оставь контакт, отвечу чуть позже');

                $outpuMessage->setButtonsType(OutputKeyboardMessage::TYPE_URL);

                //телеграм не дает длинную строку передеавть, поэтому чат айди кладу в кеш и передаю только ключ
                $cacheKey = 'temp_cache_chat_id_'.$respondentId;
                Cache::forever($cacheKey, $this->chatId);

                $buttonsData = [
                    [
                        'width' => 3,
                        'link' => 'https://telegram.me/testsurveybot?start=cmd-linkRespondetChannels--respondent-'.$respondentId,
                        'params' =>
                            [
                                'answer_id' => 'lr',
                                'type' => 'link_respondent',
                            ]
                    ],
                    [
                        'width' => 3,
                        'link' => 'https://m.me/dev.surveybot.me?ref=cmd-linkRespondetChannels--respondent-'.$respondentId,
                        'params' =>
                            [
                                'answer_id' => 'lr',
                                'type' => 'link_respondent',
                            ]
                    ],
                    [
                        'width' => 3,
                        'params' =>
                            [
                                'answer_id' => 'ge',
                                'type' => 'choice',
                            ]
                            
                    ],
                ];

                $outpuMessage->addUrlButton('Телеграм', 'https://telegram.me/testsurveybot?start=cmd-linkRespondetChannels--respondent-'.$respondentId.'--chatId-'.$this->chatId);
                $outpuMessage->addUrlButton('Фейсбук', 'https://m.me/dev.surveybot.me?ref=cmd-linkRespondetChannels--respondent-'.$respondentId.'--chatId-'.$this->chatId);
                $outpuMessage->addCallbackButton('Email', 'get_email');

                $outpuMessage->setOtherParams([
                    'type_message' => 'link_respondent',
                    'buttonsData' => $buttonsData,
                ]);

                $this->sendMessage($outpuMessage);
            }

            $widget = $message->getWidget();
            broadcast(new ConversationCreated($conversation, $widget))->toOthers();
        }

        // Обновляем время последнего ответа
        $conversation->touchLastReply();

        return true;
    }

    public function sendActionTyping()
    {
        $message = [
            'message' => [
                'type' => 'action_typing',
                'text' => null,
                'conversation_id' => $this->chatId,
            ]
        ];

        broadcast(new MessageWasSended($message));

        return response()->make('ok', 200);
    }

    public function stopTyping($chatId = null)
    {
        $message = [
            'message' => [
                'type' => 'empty',
                'text' => null,
                'conversation_id' => $this->chatId,
            ]
        ];

        broadcast(new MessageWasSended($message));

        return response()->make('ok', 200);
    }

    public function getBot()
    {
        return $this->bot;
    }

    public function setMenu($greetingText, $chatId, $startSurvey = null)
    {
        return null;
    }

    /**
     * Возвращает имя команды и аргументы, если команду пытаются вызвать текстом
     * @return array ['name' => string, 'arguments' => array] or null
     */
    public function detectCommandFromText()
    {
        if (!$this->message->surveyIsRunning()) {

            //если опрос не запущен
            //проверяем пометку в кеше, что данное входящие сообщение является ответом на запрос получения Email от бота
            $respondentId = $this->respondentId;

            Log::info('detectCommandFromText $respondentId = '.print_r($respondentId,true));
            Log::info('detectCommandFromText email = '.print_r($this->textMessage,true));
            Log::info('detectCommandFromText Cache::has = '.print_r(Cache::has('get_email_'.$respondentId),true));

            if(Cache::has('get_email_'.$respondentId)) {
                //если пометка то формируем команду
                $commandName = 'set_email';
                $args = ['email' => $this->textMessage];

                return [
                    'name' => $commandName,
                    'arguments' => $args
                ];
            }

            return [
                'name' => 'dialog_mode',
                'arguments' => []
            ];
        }

        $text = $this->message->getText();
        $explodedText = explode(' ', $text);
        $firstWord = trim($explodedText[0]);
        $firstWordTrimmed = ltrim($firstWord, '/');

        if (isset($firstWord[0]) && $firstWord[0] === '/' && method_exists(CommandsManagerContract::class, $firstWordTrimmed)) {

            $arguments = isset($explodedText[1]) ? json_decode($explodedText[1], true) : [];

            return [
                'name' => $firstWordTrimmed,
                'arguments' => $arguments
            ];
        }

        return null;
    }

    public function toggleCheckbox(array $arguments, array $cacheData)
    {
        return null;
    }

    public function transformCheckboxesToAnswers($checkedAnswers = null, $cacheKey = null, $customAnswer = null)
    {
        // Получаем цифры из строки, которую ввел пользователь
        preg_match_all('!\d!', $customAnswer, $matches);

        $answerOrders = null;
        if (is_array($checkedAnswers) && !empty($checkedAnswers)) {
            $answerOrders = $checkedAnswers;
        } elseif (isset($matches[0])) {
            $answerOrders = array_values(array_unique($matches[0]));
        }

        if ($answerOrders) {
            // Меняем 0 на 10
            foreach ($answerOrders as $key => $value) {
                if ((int)$value === 0) {
                    $answerOrders[$key] = 10;
                    break;
                }
            }

            $cacheData = $this->getSurveyCacheData();
            $question = isset($cacheData['question_id']) ? Question::find($cacheData['question_id']) : null;

            $answers = $question ? $question->answers()->whereIn('order', $answerOrders)->get() : null;

            return $answers;
        }

        return null;
    }

    /**
     * Добавляет в клавиатуру кнопки для одиночного выбора (BUTTONS_TYPE_CHOICE)
     * @param OutputKeyboardMessage $keyboard
     * @param App/Models/Question $question
     * @param int $step
     *
     * @return OutputKeyboardMessage
     */
    public function addOneChoiceButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHOICE);

        $surveyId = $question->group->survey->id;

        $buttonsData = [];
        foreach ($question->answersVisible() as $key => $answer) {

            $buttonsData[$key] = [
                'width' => $answer->width,
                'params' => [
                    'survey_id' => $surveyId,
                    'answer_id' => $answer->id,
                    'step' => $step,
                ]
            ];

            $keyboard->addCallbackButton($answer->answer_text, 'next_question');
        }

        $keyboard->setOtherParams([
            'type_message' => 'choice',
            'survey' => $surveyId,
            'buttonsData' => $buttonsData,
        ]);

        return $keyboard;
    }

    /**
     * Добавляет в клавиатуру кнопки для множественного выбора (чекбоксы)
     * @param OutputKeyboardMessage $keyboard
     * @param App/Models/Question $question
     * @param int $step
     *
     * @return OutputKeyboardMessage
     */
    public function addMultipleChoiceButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);

        $surveyId = $question->group->survey->id;

        $buttonsData = [];
        $answerKey = 0;
        foreach ($question->getAnswersLinesWithCheckboxes() as $answersLine) {
            $buttonsWidth = count($answersLine);

            foreach ($answersLine as $answer) {
                $buttonsData[$answerKey] = [
                    'width' => $buttonsWidth,
                    'checkbox' => true,
                    'params' => [
                        'ref_step' => $step,
                        'checked' => false,
                        'order' => $answer->order,
                    ]
                ];

                $keyboard->addCallbackButton($answer->order, 'toggle_checkbox');

                $answerKey++;
            }
        }

        $buttonsData[$answerKey] = [
            'width' => 1,
            'params' => [
                'survey_id' => $surveyId,
                'checked_answers' => [],
                'step' => $step,
            ]
        ];

        $keyboard->addCallbackButton('Продолжить', 'next_question');

        $keyboard->setOtherParams([
            'type_message' => 'multiple_choice',
            'survey' => $surveyId,
            'buttonsData' => $buttonsData,
        ]);

        if (!$question->description) {
            $multiChoiceDescription = '';
            foreach ($question->answersVisible() as $answer) {
                $multiChoiceDescription = $multiChoiceDescription . $answer->order . ' - ' . $answer->answer_text . PHP_EOL;
            }

            $question->description = $multiChoiceDescription;
        }

        $questionText = $keyboard->getText();

        $questionText = $question->description ? $questionText . PHP_EOL . $question->description : $questionText . PHP_EOL;

        $keyboard->setText($questionText);

        return $keyboard;
    }

    /**
     * Добавляет в клавиатуру кнопки "Поделиться"
     * @param OutputKeyboardMessage $keyboard
     * @param App/Models/Question $question
     * @param int $step
     *
     * @return OutputKeyboardMessage
     */
    public function addSocialShareButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);

        $socialUrl = $question->url_social_network;

        $questionText = $keyboard->getText();
        $imageUrl = '';
        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
            $imageUrl = $imageMessage->getUrlImage();
        }

        $buttonsData = [
            ['width' => 1, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'ssh',
                'type' => 'social_share',
                'step' => $step,
            ]],
        ];

        // todo сделать проверку на то что по нажатии на "Продолжить" пользователь поделился ссылкой

        $keyboard->addCallbackButton('Продолжить', 'next_question');

        $keyboard->setOtherParams([
            'type_message' => 'social_share',
            'survey' => $question->group->survey->id,
            'buttonsData' => $buttonsData,
            'url' => $socialUrl
        ]);

        return $keyboard;
    }

    public function addSocialAuthButtons($keyboard, $question, $step, Respondent $respondent, $chatId = null)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::TYPE_URL);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $chatId = $chatId ?: $this->getChatId();

        $params = [
            'respondent' => $respondent->id,
            'surveyId' => $question->group->survey->id,
            'typeMessendger' => 'web',
            'typeAnswer' => 'sa',
            'chatId' => $chatId,
        ];

        $urlAuthVk = route('auth.social.vk', $params);
        $urlAuthFb = route('auth.social.fb', $params);

        $buttonsData = [
            [
                'width' => 2,
                'link' => $urlAuthVk,
                'params' =>
                    [
                        'answer_id' => 'sa',
                        'type' => 'sa',
                    ]
            ],
            [
                'width' => 2,
                'link' => $urlAuthFb,
                'params' =>
                    [
                        'answer_id' => 'sa',
                        'type' => 'sa',
                    ]
            ],
            [
                'width' => 1,
                'params' =>
                    [
                        'answer_id' => 'sa',
                        'type' => 'choice',
                        'step' => $step
                    ]

            ],
        ];

        $keyboard->addUrlButton('VK', $urlAuthVk);
        $keyboard->addUrlButton('FB', $urlAuthFb);
        $keyboard->addCallbackButton('Несколько вопросов', 'next_question');

        $keyboard->setOtherParams([
            'type_message' => 'choice',
            'buttonsData' => $buttonsData,
            'survey' => $question->group->survey->id,
        ]);

        return $keyboard;
    }

    /**
     * Добавляет в клавиатуру кнопки Пола
     * @param OutputKeyboardMessage $keyboard
     * @param App/Models/Question $question
     * @param int $step
     *
     * @return OutputKeyboardMessage
     */
    public function addGenderRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);

        $questionText = $keyboard->getText();
        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $buttonsData = [
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'grm',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'grf',
                'type' => 'choice',
                'step' => $step,
            ]],
        ];

        $keyboard->addCallbackButton('Мужчина', 'next_question');
        $keyboard->addCallbackButton('Женщина', 'next_question');

        $keyboard->setOtherParams([
            'type_message' => 'choice',
            'survey' => $question->group->survey->id,
            'buttonsData' => $buttonsData,
        ]);

        return $keyboard;
    }

    public function addChildrenRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);

        $questionText = $keyboard->getText();
        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $buttonsData = [
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'chy',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'chn',
                'type' => 'choice',
                'step' => $step,
            ]],
        ];

        $keyboard->addCallbackButton('Да', 'next_question');
        $keyboard->addCallbackButton('Нет', 'next_question');

        $keyboard->setOtherParams([
            'type_message' => 'choice',
            'survey' => $question->group->survey->id,
            'buttonsData' => $buttonsData,
        ]);

        return $keyboard;
    }

    public function addEducationRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);

        $questionText = $keyboard->getText();
        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $buttonsData = [
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'ed1',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'ed2',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'ed3',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'ed4',
                'type' => 'choice',
                'step' => $step,
            ]],
        ];

        $keyboard->addCallbackButton('Среднее', 'next_question');
        $keyboard->addCallbackButton('Неоконченное высшее', 'next_question');
        $keyboard->addCallbackButton('Высшее', 'next_question');
        $keyboard->addCallbackButton('Ученая степень/два или более высших', 'next_question');

        $keyboard->setOtherParams([
            'type_message' => 'choice',
            'survey' => $question->group->survey->id,
            'buttonsData' => $buttonsData,
        ]);

        return $keyboard;
    }

    public function addFamilyStatusRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);

        $questionText = $keyboard->getText();
        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $buttonsData = [
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'fs1',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'fs2',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'fs3',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'fs4',
                'type' => 'choice',
                'step' => $step,
            ]],
        ];

        $keyboard->addCallbackButton('Я женат/замужем', 'next_question');
        $keyboard->addCallbackButton('У меня есть пара', 'next_question');
        $keyboard->addCallbackButton('Я свободен холост', 'next_question');
        $keyboard->addCallbackButton('Не хочу говорить об этом', 'next_question');

        $keyboard->setOtherParams([
            'type_message' => 'choice',
            'survey' => $question->group->survey->id,
            'buttonsData' => $buttonsData,
        ]);

        return $keyboard;
    }

    public function addRevenueRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);

        $questionText = $keyboard->getText();
        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $buttonsData = [
            ['width' => 4, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r1',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 4, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r2',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 4, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r3',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 4, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r4',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 4, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r5',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 4, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r6',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 4, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r7',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 4, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r8',
                'type' => 'choice',
                'step' => $step,
            ]],
        ];

        $keyboard->addCallbackButton('1', 'next_question');
        $keyboard->addCallbackButton('2', 'next_question');
        $keyboard->addCallbackButton('3', 'next_question');
        $keyboard->addCallbackButton('4', 'next_question');
        $keyboard->addCallbackButton('5', 'next_question');
        $keyboard->addCallbackButton('6', 'next_question');
        $keyboard->addCallbackButton('7', 'next_question');
        $keyboard->addCallbackButton('8', 'next_question');

        $keyboard->setOtherParams([
            'type_message' => 'choice',
            'survey' => $question->group->survey->id,
            'buttonsData' => $buttonsData,
        ]);

        return $keyboard;
    }

    public function addWorkingRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);

        $questionText = $keyboard->getText();
        if (!empty($question->image)) {
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $buttonsData = [
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'wy',
                'type' => 'choice',
                'step' => $step,
            ]],
            ['width' => 2, 'params' => [
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'wn',
                'type' => 'choice',
                'step' => $step,
            ]],

        ];

        $keyboard->addCallbackButton('Да', 'next_question');
        $keyboard->addCallbackButton('Нет', 'next_question');

        $keyboard->setOtherParams([
            'type_message' => 'choice',
            'survey' => $question->group->survey->id,
            'buttonsData' => $buttonsData,
        ]);

        return $keyboard;
    }

    /**
     * Добавляет в клавиатуру кнопку для получения бонуса
     * @param OutputKeyboardMessage $keyboard
     * @param App/Models/Bonus $bonus
     * @param string $customTitle
     *
     * @return OutputKeyboardMessage
     */
    public function addBonusButton($keyboard, $bonus, $customTitle = null)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHOICE);

        $title = $customTitle ?: $bonus->title;

        $existedOtherParams = $keyboard->getOtherParams();

        if (is_array($existedOtherParams) && isset($existedOtherParams['buttonsData'])) {
            array_push($existedOtherParams['buttonsData'], ['width' => 1, 'params' => ['bonus_id' => $bonus->id]]);

            $buttonsData = $existedOtherParams['buttonsData'];
        } else {
            $buttonsData = [
                ['width' => 1, 'params' => ['bonus_id' => $bonus->id]],
            ];
        }

        $keyboard->addCallbackButton($title, 'bonuses');

        $keyboard->setOtherParams([
            'type_message' => 'choice',
            'survey' => $bonus->survey->id,
            'buttonsData' => $buttonsData,
        ]);

        return $keyboard;
    }

    private function transformMessage(OutputMessage $message, $chat)
    {
        $otherParams = $message->getOtherParams();

        $class_message = get_class($message);

        if ($class_message === OutputTextMessage::class) {
            return $this->transformFromTextMessage($message, $chat, $otherParams);
        } elseif ($class_message === OutputKeyboardMessage::class) {
            return $this->transformFromKeyboardMessage($message, $chat, $otherParams);
        } elseif ($class_message === OutputImageMessage::class) {
            return $this->transformImageMessage($message, $chat, $otherParams);
        }

        return null;
    }

    private function transformFromTextMessage(OutputTextMessage $message, $chat, $otherParams)
    {
        $url = isset($otherParams['url']) ? $otherParams['url'] : null;

        return [
            'message' => [
                'type' => 'text-bot',
                'text' => $message->getText(),
                'conversation_id' => $chat ?: $this->chatId,
                'url' => $url
            ],
        ];
    }

    private function transformFromKeyboardMessage(OutputKeyboardMessage $message, $chat, $otherParams)
    {
//        $type_message = $otherParams['type_message'] ?: 'text-bot';
        $survey = isset($otherParams['survey']) ? $otherParams['survey'] : null;
        $url = isset($otherParams['url']) ? $otherParams['url'] : null;

        if (!$survey) {
            $cacheData = $this->getSurveyCacheData();
            $survey = isset($cacheData['survey_id']) ? $cacheData['survey_id'] : null;
        }

        $buttonsData = $otherParams['buttonsData'];

        $buttons = [];
        foreach ($message->getKeyboard() as $key => $button) {

            $type_button = $button['type'];

            if ($type_button == OutputMessage::TYPE_CALLBACK) {
                $resultButton = [
                    'text' => $button['title'],
                    'command' => $button['data'],
                    'width' => isset($buttonsData[$key]) ? $buttonsData[$key]['width'] : 1,
                    'params' => isset($buttonsData[$key]) ? $buttonsData[$key]['params'] : []
                ];

                if (isset($buttonsData[$key]['checkbox']) && $buttonsData[$key]['checkbox']) {
                    $resultButton['checkbox'] = true;
                }

                $buttons[] = $resultButton;
            } elseif ($type_button == OutputMessage::TYPE_URL) {

                Log::info('$buttonsData = '.print_r($buttonsData[$key],true));

                $rawButton = [
                    'text' => $button['title'],
                    'command' => $button['data'],
                    'width' => isset($buttonsData[$key]) ? $buttonsData[$key]['width'] : 1,
                    'params' => isset($buttonsData[$key]) ? $buttonsData[$key]['params'] : []
                ];


                if (isset($buttonsData[$key]['link']) && $buttonsData[$key]['link']) {
                    $rawButton['link'] = $buttonsData[$key]['link'];
                }

                $buttons[] = $rawButton;
            }
        }

        Log::info('$buttons = '.print_r($buttons,true));

        return [
            'message' => [
                'type' => $otherParams['type_message'],
                'text' => $message->getText(),
                'buttons' => $buttons,
                'conversation_id' => $chat ?: $this->chatId,
                'url' => $url
            ]
        ];
    }

    private function transformImageMessage(OutputImageMessage $message, $chat, $otherParams)
    {
        $url = $otherParams['url'] ?: null;

        return [
            'message' => [
                'type' => 'image',
                'image' => $message->getUrlImage(),
                'text' => $message->getText(),
                'buttons' => [],
                'url' => $url,
                'conversation_id' => $chat ?: $this->chatId,
            ]
        ];
    }

    private function getSurveyCacheData()
    {
        $cacheKey = $this->chatId ? 'chat' . $this->chatId : '';

        if (!$cacheKey)
            return [];

        return unserialize(Cache::get($cacheKey));
    }

    public function getProvider()
    {
        return 'webbot';
    }
}
