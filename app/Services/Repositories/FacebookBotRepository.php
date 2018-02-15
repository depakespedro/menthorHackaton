<?php

namespace App\Services\Repositories;

use App\Models\Menubutton;
use App\Models\Question;
use App\Models\Respondent;
use App\Services\BotObjects\Messages\Output\ImageMessage;
use App\Services\BotObjects\Messages\Output\KeyboardMessage;
use App\Services\BotObjects\Messages\Output\TextMessage;
use App\Services\Contracts\SurveybotContract;
use Illuminate\Support\Facades\Cache;
use pimax\Menu\LocalizedMenu;
use pimax\Menu\MenuItem;
use pimax\Messages\MessageButton;
use pimax\FbBotApp as FacebookApi;
use pimax\Messages\MessageElement;

use pimax\Messages\QuickReplyButton;
use pimax\Messages\StructuredMessage;
use pimax\Messages\Message;
use pimax\Messages\ImageMessage as FacebookImageMessage;
use pimax\Messages\SenderAction;

use App\Services\BotObjects\Messages\Output\ImageMessage as OutputImageMessage;
use App\Services\BotObjects\Messages\Output\KeyboardMessage as OutputKeyboardMessage;
use App\Services\BotObjects\Messages\Output\TextMessage as OutputTextMessage;
use App\Services\BotObjects\Messages\Output\DialogMessage as OutputDialogMessage;
use App\Services\BotObjects\Messages\Output\Message as OutputMessage;

use App\Services\BotObjects\Messages\Input\CallbackMessage as InputCallbackMessage;
use App\Services\BotObjects\Messages\Input\TextMessage as InputTextMessage;
use App\Services\BotObjects\Messages\Input\Message as InputMessage;

use App\Services\Contracts\CommandsManagerContract;

use Illuminate\Support\Facades\Log;

use pimax\Messages\QuickReply;

use App\Events\Webbot\ConversationCreated;
use App\Events\Webbot\ConversationReplyCreated;

class FacebookBotRepository implements SurveybotContract
{
    private $bot = null;

    private $verify_token;
    private $token;

    protected $typeData = null;
    protected $update = null;

    protected $inputMessage = null;
    protected $inputCallback = null;

    protected $chat = null;
    protected $dialogMessage = null;
    protected $respondent = null;

    public function __construct(array $params = [])
    {
        $this->verify_token = config('facebook.verify_token');
        $this->token = config('facebook.token');

        $this->bot = new FacebookApi($this->token);

        $this->parseUpdate();
    }

    private function parseUpdate()
    {
        if (!is_null($this->update) or !empty($this->update)) {
            return null;
        }

        $this->update = request()->toArray();

        if (isset($this->update['body']) && isset($this->update['respondent_id'])) {
            $this->dialogMessage = $this->update;
            $this->respondent = Respondent::find($this->update['respondent_id']);

            $message = [
                'message' => ['text' => $this->update['body']],
                'sender' => ['id' => $this->respondent->messenger_user_id],
            ];

        } elseif (isset($this->update['entry'])) {
            $message = $this->update['entry'][0]['messaging'][0];
        } else {
            return null;
        }

        Log::info('parseUpdate $message = ' . print_r($this->update, true));

        $this->chat = [
            'id' => $message['sender']['id']
        ];

        if (!empty($message['message'])) {

            if(isset($message['message']['quick_reply'])){
                $this->inputCallback = $message;
                $this->typeData = self::CALLBACK;
            }else{
                $this->inputMessage = $message;
                $this->typeData = self::MESSAGE;
            }

        } elseif (!empty($message['postback'])) {

            $this->inputCallback = $message;
            $this->typeData = self::CALLBACK;

        } elseif (!empty($message['referral'])) {

            $this->inputCallback = $message;
            $this->typeData = self::CALLBACK;

        } else {
            $this->callback = [];
        }
    }

    public function getChatId()
    {
        return $this->chat['id'];
    }

    public function getBot()
    {
        return $this->bot;
    }

    public function sendMessage(OutputMessage $message, $chat = '')
    {
        if(empty($chat)){
            $chat = $this->chat['id'];
        }

        if ($message->getType() === OutputMessage::TYPE_DIALOG)
            return $this->sendDialog($message);

        $messages = $this->transformMessage($message, $chat);

        foreach($messages as $message){
            Log::info('sendMessage $message = '.print_r($message,true));
            $result = $this->bot->send($message);
            Log::info('sendMessage $result = '.print_r($result,true));
        }

        $this->stopTyping();
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

    public function stopTyping($chatId = null)
    {
        if (empty($chatId))
            $chatId = $this->chat['id'];

        $this->bot->send(new SenderAction($chatId, SenderAction::ACTION_TYPING_OFF));
    }

    public function getMessage()
    {
        $message = null;

        if ($this->typeData == self::MESSAGE) {

            if ($this->dialogMessage) {
                $respondent = $this->respondent;
                $chatId = $respondent->messenger_user_id;

                $message = new InputTextMessage();
                $message->setChatId($chatId)
                        ->setText($this->dialogMessage['body'])
                        ->setRespondentId($respondent->id)
                        ->setMessengerName('facebook');
            }

            $inputMessage = $this->inputMessage;
            $chatId = $inputMessage['sender']['id'];
            $this->stopTyping($chatId);
            $respondent = $this->getRespondent();

            $message = new InputTextMessage();
            $message->setChatId($chatId)->setRespondentId($respondent->id);

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
                    $lastSurvey = $respondent->getLastSurvey();
                    $widget = $lastSurvey->widget;

                    $commandData['arguments'] = [
                        'widget_id' => $widget ? $widget->id : 344,
                        'survey_id' => $lastSurvey ? $lastSurvey->id : null,
                    ];
                }

                $text = $inputMessage['message']['text'];

                $message = new InputCallbackMessage();

                $message->setCommandName($commandData['name'])
                    ->setText($text)
                    ->setRespondentId($respondent->id)
                    ->setArguments($commandData['arguments'])
                    ->setChatId($chatId);

            } else {

                if (!isset($inputMessage['message']['text'])) {
                    return null;
                }
                
                $text = $inputMessage['message']['text'];

                $user = $this->bot->userProfile($inputMessage['sender']['id']);

                $message = new InputTextMessage();
                $message->setChatId($chatId)
                    ->setMessengerName('facebook')
                    ->setText($text)
                    ->setUserData([
                        'username' => null,
                        'first_name' => $user->getFirstName(),
                        'last_name' => $user->getLastName(),
                    ]);
            }

            Log::info('$commandData = ' . print_r($commandData, true));

        } elseif ($this->typeData == self::CALLBACK) {
            
            $inputCallback = $this->inputCallback;

            $chatId = $inputCallback['sender']['id'];
            $this->stopTyping($chatId);

            if (isset($inputCallback['referral']) or 
                (isset($inputCallback['postback']) && isset($inputCallback['postback']['referral']))) {

                $args = isset($inputCallback['referral']) ? $inputCallback['referral']['ref'] : $inputCallback['postback']['referral']['ref'];

                //todo пример команды ref=cmd-getChatInfo
                //ищем команды при переходе реф ссылке
                Log::info('detectCommandFromText = '.print_r($args,true));
                $command = explode('-', $args);
                if($command[0] == 'cmd'){
                    $commaName = $command[1];
                    Log::info('$commaName = '.print_r($commaName,true));

                    $args = explode('--', $args);

                    Log::info('$argsParse = '.print_r($args,true));

                    $arguments = [];
                    if(isset($args[0])){
                        unset($args[0]);

                        foreach($args as $arg){
                            $argRaw = explode('-', $arg);
                            if(isset($argRaw[0])){
                                if(isset($argRaw[1])){
                                    $arguments[$argRaw[0]] = $argRaw[1];
                                }else{
                                    $arguments[$argRaw[0]] = null;
                                }
                            }
                        }
                    }

                    Log::info('$arguments = '.print_r($arguments,true));

                    $message = new InputCallbackMessage();
                    $message->setChatId($chatId)
                        ->setMessengerName('facebook')
                        ->setArguments($arguments)
                        ->setCommandName($commaName);
                }else{
                    $explodedCallbackString = explode('survey', $args);

                    $message = new InputCallbackMessage();
                    $message->setChatId($chatId)
                        ->setMessengerName('facebook')
                        ->setArguments([
                            'survey_id' => $explodedCallbackString[1]
                        ])
                        ->setCommandName('start')
                        ->setText('Старт');
                }

            } else {

                Log::info('getMessage $inputCallback = '.print_r($inputCallback,true));

                if (isset($inputCallback['postback'])) {
                    $text = $inputCallback['postback']['title'];
                    $args = $inputCallback['postback']['payload'];
                } elseif (isset($inputCallback['message']['quick_reply'])) {
                    $text = $inputCallback['message']['text'];
                    $args = $inputCallback['message']['quick_reply']['payload'];
                } else {
                    return null;
                }

                //парсим аргументы
                $explodedCallbackString = explode(' ', $args);
                $commandName = ltrim($explodedCallbackString[0], '/');

                $arguments = [];
                if (isset($explodedCallbackString[1])) {
                    $arguments = json_decode($explodedCallbackString[1], true);
                    if (is_null($arguments)) {
                        $arguments = [];
                    }
                }

                $message = new InputCallbackMessage();
                $message->setChatId($chatId)
                    ->setMessengerName('facebook')
                    ->setArguments($arguments)
                    ->setCommandName($commandName)
                    ->setText($text);
                }
            }

        $this->message = $message;

        return $message;
    }

    private function detectCommandFromText()
    {
        $inputMessage = $this->inputMessage;

        Log::info('$inputMessage = ' . print_r($inputMessage, true));

        if(!isset($inputMessage['message']['text'])){
            return null;
        }

        $text = $inputMessage['message']['text'];

        $explodedText = explode(' ', $text);
        $firstWord = $explodedText[0];
        $firstWordTrimmed = ltrim($firstWord, '/');

        if ($firstWord[0] === '/' && method_exists(CommandsManagerContract::class, $firstWordTrimmed)) {

            $arguments = [];

            // Пробуем найти id стартового опроса
            if ($firstWordTrimmed === 'start' && isset($explodedText[1])) {

                $commandRaw = $explodedText[1];
                $command = explode('-', $commandRaw);

                if($command[0] == 'cmd'){
                    $commaName = $command[1];

                    return [
                        'name' => $commaName,
                        'arguments' => []
                    ];
                }
                
                $explodedArgs = explode('survey', $explodedText[1]);
                $surveyId = isset($explodedArgs[1]) ? $explodedArgs[1] : null;
                $arguments = $surveyId ? ['survey_id' => (int)$surveyId] : [];
            } else {
                if (isset($explodedText[1])) {
                    $arguments = json_decode($explodedText[1], true);
                    if (is_null($arguments)) {
                        $arguments = [];
                    }
                }
            }

            return [
                'name' => $firstWordTrimmed,
                'arguments' => $arguments
            ];

        }

        return null;
    }

    public function setGreeting()
    {
        $message = $this->inputCallback;
        $user = $this->bot->userProfile($message['sender']['id']);
        $firstName = $user->getFirstName();

        $menuButtons = [];
        foreach (Menubutton::getAll() as $mbutton) {
            if ($mbutton->command->name !== 'start'){
                $menuButtons[] =  new MessageButton(
                    MessageButton::TYPE_POSTBACK,
                    $mbutton->title,
                    $mbutton->command->name
                );
            }
        }

        $this->bot->send(new StructuredMessage($message['sender']['id'],
            StructuredMessage::TYPE_GENERIC, [
                'elements' => [
                    new MessageElement(
                        "Меню",
                        "Привет, $firstName! Чем займемся сегодня?",
                        url('img/robot-friend-fb.png'),
                        $menuButtons
                    ),
                ]
            ]
        ));

        return null;
    }

    public function setMenu($text, $chatId, $startSurvey = null)
    {
        if (is_null($startSurvey)) {
            $this->setGreeting();
            $this->stopTyping($chatId);
            return null;
        }

        $buttons = [];
        $buttons[] = new MenuItem(
            MessageButton::TYPE_POSTBACK,
            'Начать заново',
            'restart_survey'
        );

        $menu = new LocalizedMenu('default', true, $buttons);
        $menuRus = new LocalizedMenu('ru_RU', false, $buttons);
        $menuEng = new LocalizedMenu('en_US', false, $buttons);

        $this->bot->setPersistentMenu([$menu, $menuRus, $menuEng]);

       //выстаялем картнику опроса и кнопку зпустить опрос
        $imageUrl = url('img/robot-friend-fb.png');

        // Заголовок, описание, приветственный текст опроса
        $text = "$startSurvey->title" . PHP_EOL;
        $subtitle = '';
        if ($startSurvey->description)
            $subtitle .= $startSurvey->description . PHP_EOL . PHP_EOL;

        if ($startSurvey->welcome_text) {
            $respondent = $this->getRespondent();
            $welcome_text = replacementText($startSurvey->welcome_text, ['%first_name%' => $respondent->first_name]);
            $subtitle .= $welcome_text . PHP_EOL;
        }

        if ($startSurvey->image) {
            $imageUrl = url($startSurvey->image);
        }

        Log::info('facebook setMenu $subtitle = '.print_r($subtitle,true));

        $message = $this->inputCallback;

        $this->bot->send(new StructuredMessage($message['sender']['id'],
            StructuredMessage::TYPE_GENERIC, [
                'elements' => [
                    new MessageElement(
                        $text,
                        $subtitle,
                        $imageUrl,
                        [
                            new MessageButton(
                                MessageButton::TYPE_POSTBACK,
                                'Запустить опрос',
                                '/start_survey '.json_encode(['survey_id' => $startSurvey->id])
                            )
                        ]
                    ),
                ]
            ]
        ));

        $this->stopTyping($chatId);
    }

    public function sendActionTyping()
    {
        $this->bot->send(new SenderAction($this->chat['id'], SenderAction::ACTION_TYPING_ON));
    }

    public function addOneChoiceButtons($keyboard, $question, $step)
    {
        $surveyId = $question->group->survey->id;

        Log::info('addOneChoiceButtons $question = '.print_r($question->toArray(),true));

        foreach ($question->answersVisible() as $answer) {

            $args = json_encode([
                'survey_id' => $surveyId,
                'answer_id' => $answer->id,
                'step' => $step,
            ]);

            $keyboard->addCallbackButton($answer->answer_text, '/next_question ' . $args);
        }

        return $keyboard;
    }

    public function addMultipleChoiceButtons($keyboard, $question, $step)
    {
        Log::info('addOneChoiceButtons $question = '.print_r($question->toArray(),true));

        if (!$question->description) {
            $multiChoiceDescription = '';
            foreach ($question->answersVisible() as $answer) {
                $multiChoiceDescription = $multiChoiceDescription . $answer->order . ' - ' . $answer->answer_text . PHP_EOL;
            }

            $question->description = $multiChoiceDescription;
        }

        $questionText = $keyboard->getText();

        if ($multiChoiceDescription) {
            $questionText .= $multiChoiceDescription . '(напиши номера всех подходящих ответов)';
        }

        Log::info('addMultipleChoiceButtons $questionText = '.print_r($questionText,true));

        $message = new TextMessage($questionText);

        return $message;
    }

    private function getSurveyCacheData()
    {
        $chatId = $this->chat['id'];

        $cacheKey = $chatId ? 'chat' . $chatId : '';

        if (!$cacheKey)
            return [];

        return unserialize(Cache::get($cacheKey));
    }

    public function transformCheckboxesToAnswers($checkedAnswers = null, $cacheKey = null, $customAnswer = null)
    {
        preg_match_all('!\d!', $customAnswer, $matches);

        if (isset($matches[0])) {
            $answer_orders = array_values(array_unique($matches[0]));
            foreach ($answer_orders as $key => $value) {
                if ((int)$value === 0) {
                    $answer_orders[$key] = 10;
                    break;
                }
            }

            $cacheData = $this->getSurveyCacheData();
            $questionId = $cacheData['question_id'];
            $question = Question::find($questionId);

            $answers = $question->answers()->whereIn('order', $answer_orders)->get();

            return $answers;
        }

        return null;
    }

    private function transformMessage(OutputMessage $message, $chat)
    {
        $params = $message->getOtherParams();

        $class_message = get_class($message);

        if ($class_message === OutputTextMessage::class) {
            return $this->transformFromTextMessage($message, $chat);
        } elseif ($class_message === OutputKeyboardMessage::class) {
            return $this->transformFromKeyboardMessage($message, $chat);
        } elseif ($class_message === OutputImageMessage::class) {
            return $this->transformImageMessage($message, $chat);
        }

        return null;
    }

    private function transformFromTextMessage(OutputTextMessage $message, $chat)
    {
        if(!is_null($message->getImage())){
            $image = $message->getImage()->getUrlImage();
            $messages[] = new FacebookImageMessage($chat, $image);
        }

        $messages[] = new Message($chat, $message->getText());

        return $messages;
    }

    private function transformFromKeyboardMessage(OutputKeyboardMessage $message, $chat)
    {
        $buttons_callback = [];
        $buttons_url = [];
        foreach ($message->getKeyboard() as $button) {
            $type_button = $button['type'];

            if ($type_button == OutputMessage::TYPE_CALLBACK) {

                if(in_array($button['title'], ['Продолжить', 'Несколько вопросов'])){
                    $buttons_url[] = new MessageButton(
                        MessageButton::TYPE_POSTBACK,
                        $button['title'],
                        $button['data']
                    );
                }else{
                    $buttons_callback[] = new QuickReplyButton(
                        QuickReplyButton::TYPE_TEXT,
                        $button['title'],
                        $button['data']
                    );
                }

            } elseif ($type_button == OutputMessage::TYPE_URL) {
                $buttons_url[] = new MessageButton(
                    MessageButton::TYPE_WEB,
                    $button['title'],
                    $button['data']
                );
            }
        }

        if(!is_null($message->getImage())){
            $image = $message->getImage()->getUrlImage();
            $messages[] = new FacebookImageMessage($chat, $image);
        }

        Log::info('$buttons_url='.print_r($buttons_url,true));
        Log::info('$buttons_callback='.print_r($buttons_callback,true));

        if(!empty($buttons_url)){
            $messages[] = new StructuredMessage($chat, StructuredMessage::TYPE_BUTTON, [
                    'text' => $message->getText(),
                    'buttons' => $buttons_url,
                ]
            );
        }else{
            $messages[] = new QuickReply($chat, $message->getText(), $buttons_callback);
        }

        Log::info('transformFromKeyboardMessage $messages = '.print_r($messages,true));
        
        return $messages;
    }

    public function addSocialAuthButtons($keyboard, $question, $step, Respondent $respondent)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::TYPE_URL);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $params = [
            'respondent' => $respondent->id,
            'surveyId' => $question->group->survey->id,
            'typeMessendger' => 'facebook',
            'typeAnswer' => 'sa',
            'chatId' => $this->getChatId(),
        ];

        $urlAuthVk = route('auth.social.vk', $params);
        $urlAuthFb = route('auth.social.fb', $params);


        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        $imageUrl = '';
        if(!empty($question->image)){
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
            $imageUrl = $imageMessage->getUrlImage();
        }

        $keyboard->addUrlButton('VK', $urlAuthVk);
        $keyboard->addUrlButton('Facebook', $urlAuthFb);

        $keyboard->addCallbackButton('Несколько вопросов',  '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'sa',
                'step' => $step,
            ])
        );

        return $keyboard;
    }

    function notifySimilarRespondentsKeyboard($keyboard, $room)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $url = route('web.bot.room.chat', $room->id);

        $keyboard->addUrlButton('Открыть комнату', $url);

        return $keyboard;
    }


    public function addSocialShareButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $socialUrl = $question->url_social_network;

        $questionText = $keyboard->getText();

        $imageUrl = '';
        if(!empty($question->image)){
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
            $imageUrl = $imageMessage->getUrlImage();
        }


        $keyboard->addUrlButton('VK', 'http://vk.com/share.php?url='.$socialUrl);

        $keyboard->addUrlButton('Facebook', 'https://www.facebook.com/sharer/sharer.php?u='.$socialUrl);

        $keyboard->addCallbackButton('Продолжить',  '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ssh',
                    'step' => $step,
                ])
        );

        return $keyboard;
    }

    public function addGenderRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if(!empty($question->image)){
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButton('Мужчина', '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'grm',
                    'step' => $step,
            ])
        );

        $keyboard->addCallbackButton('Женщина', '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'grf',
                    'step' => $step,
            ])
        );

        return $keyboard;
    }

    public function addEducationRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if(!empty($question->image)){
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButton('Среднее', '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ed1',
                    'step' => $step,
                ]));

        $keyboard->addCallbackButton('Неоконченное высшее', '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ed2',
                    'step' => $step,
            ]));

        $keyboard->addCallbackButton('Высшее', '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ed3',
                    'step' => $step,
            ]));

        $keyboard->addCallbackButton('Ученая степень/два или более высших', '/next_question ' . json_encode([
                    'survey_id' => $question->group->survey->id,
                    'answer_id' => 'ed4',
                    'step' => $step,
            ]));

        return $keyboard;
    }

    public function addFamilyStatusRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if(!empty($question->image)){
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButton('Я женат/замужем', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'fs1',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('У меня есть пара', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'fs2',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('Я свободен холост', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'fs3',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('Не хочу говорить об этом', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'fs4',
                'step' => $step,
            ]));

        return $keyboard;
    }

    public function addChildrenRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if(!empty($question->image)){
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButton('Да', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'chy',
                'step' => $step,
            ])
        );

        $keyboard->addCallbackButton('Нет', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'chn',
                'step' => $step,
            ])
        );

        return $keyboard;
    }

    public function addRevenueRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if(!empty($question->image)){
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButton('1', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r1',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('2', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r2',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('3', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r3',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('4', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r4',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('5', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r5',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('6', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r6',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('7', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r7',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('8', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'r8',
                'step' => $step,
            ]));

        return $keyboard;
    }

    public function addWorkingRespondentButtons($keyboard, $question, $step)
    {
        $keyboard->setButtonsType(OutputKeyboardMessage::BUTTONS_TYPE_CHECKBOX);
        $keyboard->setMarkup(OutputKeyboardMessage::MARKUP_LINES);

        $questionText = $keyboard->getText();

        if(!empty($question->image)){
            $imageMessage = new ImageMessage($questionText, $question->image);
            $keyboard->setImage($imageMessage);
        }

        $keyboard->addCallbackButton('Да', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'wy',
                'step' => $step,
            ]));

        $keyboard->addCallbackButton('Нет', '/next_question ' . json_encode([
                'survey_id' => $question->group->survey->id,
                'answer_id' => 'wn',
                'step' => $step,
            ]));


        return $keyboard;
    }

    private function transformImageMessage(OutputImageMessage $message, $chat)
    {
        $image = $message->getImage();
        $message = new FacebookImageMessage($chat, $image);
        return [$message];
    }

    public function addBonusButton($keyboard, $bonus, $customTitle = null)
    {
        $title = $customTitle ?: $bonus->title;

        $data = '/bonuses ' . json_encode(['bonus_id' => $bonus->id]);

        $keyboard->addCallbackButton($title, $data);

        return $keyboard;
    }

    private function getRespondent()
    {
        $user = $this->bot->userProfile($this->chat['id']);
        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();
        $gender = $user->getGender();

        $respondent = Respondent::firstOrCreate([
            'messenger_user_id' => $this->chat['id'],
            'provider' => 'facebook',
        ], [
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
        ]);

        if (!$respondent->first_name) {
            $respondent->update([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'sex' => ($gender === 'male') ? 0 : 1,
            ]);   
        }

        return $respondent;
    }

    public function getProvider()
    {
        return 'facebook';
    }
}
