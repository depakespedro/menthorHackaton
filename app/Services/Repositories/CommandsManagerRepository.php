<?php 

namespace App\Services\Repositories;

use App\Events\Webbot\MessageWasSended;
use App\Models\Respondent;
use App\Models\Survey;
use App\Services\BotObjects\Messages\Output\Message;
use App\Services\Contracts\SurveybotContract;
use App\Services\Contracts\CommandsManagerContract;
use App\Services\Contracts\QuestionsManagerContract;

use App\Services\BotObjects\Messages\Input\Message as InputMessage;
use App\Services\BotObjects\Messages\Output\TextMessage;
use App\Services\BotObjects\Messages\Output\ImageMessage;
use App\Services\BotObjects\Messages\Output\KeyboardMessage;
use App\Services\BotObjects\Messages\Output\DialogMessage;

use App\Models\Question;
use App\Models\Menubutton;
use App\Models\Bonus;
use App\Models\Conversation;
use App\Models\Widget;
use App\Events\SurveyFinish;
use App\Events\Webbot\ConversationCreated;
use App\Events\Webbot\ConversationReplyCreated;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Events\HelpForBot;
use Zend\Diactoros\Request;

/**
 * Class CommandsManagerRepository. Менеджер комманд ботов
 */
class CommandsManagerRepository implements CommandsManagerContract
{
    protected $bot;

    /**
     * @var QuestionsManagerRepository;
     *
     */
    public $questionsManager;

    protected $inputMessage;

    protected $respondent;

    protected $chatId;

    public function __construct(SurveybotContract $bot, InputMessage $inputMessage)
    {
        $this->bot = $bot;
        $this->inputMessage = $inputMessage;

        $this->questionsManager = app(QuestionsManagerContract::class, [$this->bot, $inputMessage]);
    }

    /**
     * Находит комманду в списке методов этого класса и запускает ее выполнение
     * 
     * @param string $commandName
     * @param  array  $arguments
     * 
     * @return OutputMessage
     */
    public function run($commandName, array $arguments = []) {

        if (!method_exists(__CLASS__, $commandName)) {
            return new TextMessage("Команда $commandName не найдена");
        }

        $this->questionsManager->setCurrentCommand($commandName);

        $this->respondent = $this->questionsManager->getRespondent();
        $this->chatId = $this->questionsManager->getChatId();

        if (empty($this->questionsManager->getArguments())) {
            $this->questionsManager->setArguments($arguments);
        }

        $this->questionsManager->setCurrentAnswer();

        return $this->$commandName($arguments);
    }

    /**
     * Комманда /start - для запуска общения с ботом (telegram, facebook)
     * 
     * @param array $arguments
     * 
     * @return App\Services\BotObjects\Messages\Output\Message         
     */
    public function start($arguments) {

        $this->questionsManager->forgetCacheData(); // удаляем и кэша данные о текущем опросе

        $greetingText = 'Привет, '. $this->respondent->first_name .'!' . PHP_EOL;

        $startSurvey = $this->questionsManager->getStartSurvey();

        if ($startSurvey) {
            $this->respondent->update(['last_survey' => $startSurvey->id]);      
        }

        // Установка закрепленного меню
        $this->bot->setMenu($greetingText, $this->chatId, $startSurvey);

        return null;
    }

    public function start_survey($arguments, $survey = null) {

        if ($this->inputMessage->surveyIsRunning()) {
            $cacheData = $this->inputMessage->getSurveyCacheData();
            $prevQuestion = $this->questionsManager->getPrevQuestion();
            $provider = $this->inputMessage->getMessengerName();

            // перезапуск опроса для веббота
            if ($prevQuestion && $provider === 'webbot') {
                // обнуляем данные
                $this->questionsManager->forgetCacheData();
                $this->questionsManager->forgetPrevQuestion();
            }
        }

        $survey = $survey ?: $this->questionsManager->getSurvey();

        if (!$survey) {
            $this->questionsManager->forgetCacheData();
            return new TextMessage('Опрос не найден или приостановлен');
        }

        // Запоминаем, какой опрос респондент проходил последним
        $this->respondent->update(['last_survey' => $survey->id]);

        telegramLog('Respondent start survey =' . print_r($this->respondent->id, true));

        $this->questionsManager->setStep(1);

        //достаем все переходы по вопросам из малтипалов и кладем в кеш
        $transfers = $this->questionsManager->getAllTransfersQuestionsMultipleSurvey($survey);
        $this->questionsManager->setCacheTransfersQuestionsMultiple($transfers);

        // Удаляет предыдущие ответы, если пользователь уже проходил этот опрос 
        $this->questionsManager->detachRespondentAnswers();

        // Следующий опрос
        $question = $this->questionsManager->getNextQuestion();

        // Вопрос и клавиатура ответов OutputMessage
        $answerQuestion = $this->questionsManager->buildAnswerQuestion();

        // Вывод вопроса с задержкой
        if (!$question->mandatory) {
            // Задержка времени с меткой в кеше, чтобы на вебхуке можно было игнорировать обновления во время задержки
            Cache::put('chat' . $this->chatId . 'delayed', true, 1);

            $this->bot->sendMessage($answerQuestion);
            $this->bot->sendActionTyping();
            
            // Остановка на время задержки вопроса в секундах
            sleep($question->delay);

            $arguments = [
                'survey_id' => $survey->id,
                'answer_id' => null,
                'skip' => true,
                'step' => $this->questionsManager->getStep(),
            ];
            
            $this->questionsManager->setArguments($arguments);
            $this->questionsManager->setPrevQuestion($question);

            Cache::forget('chat' . $this->chatId . 'delayed');
            return $this->run('next_question', $arguments);
        }

        return $answerQuestion;
    }

    public function restart_survey($arguments)
    {
        $currentSurvey = $this->questionsManager->getCurrentSurvey();
        if (!is_null($currentSurvey)) {
            $this->questionsManager->forgetPrevQuestion();
            return $this->start_survey([], $currentSurvey);
        } else {
            $message = new TextMessage('Опрос не запущен еще!');
        }

        return $message;
    }

    public function toggle_checkbox($arguments)
    {
        if (isset($arguments['checked'])) {
            $cacheData = $this->inputMessage->getSurveyCacheData();
            return $this->bot->toggleCheckbox($arguments, $cacheData);
        }

        return null;
    }



    public function next_question($arguments) {

        $survey = $this->questionsManager->getSurvey();

        if (!$this->inputMessage->surveyIsRunning()) {
            // return new TextMessage('Опрос завершен');
            return null;
        }

        if (!$survey) {
            $this->questionsManager->forgetCacheData();
            return new TextMessage('Опрос не найден или приостановлен');
        }

        telegramLog('Next question Respondent =' . print_r($this->respondent->id, true));

        $answer = $this->questionsManager->getCurrentAnswer();
        $prevQuestion = $this->questionsManager->getPrevQuestion();

        if(!$this->questionsManager->validateAnswer($answer, $prevQuestion)){
            $this->bot->sendMessage(new TextMessage('Попробуй ответить еще раз'), $this->chatId);
            // Повтор вопроса и выход
            $answerQuestion = $this->questionsManager->buildAnswerQuestion($prevQuestion);
            return $answerQuestion;
        }

        // если предыдущий тип вопроса - открытый вопрос (квест)
        if ($prevQuestion && $prevQuestion->type === 'guess') {
            // если пользователь неправильно ответил
            if (!is_object($answer)) {
                $this->bot->sendMessage(new TextMessage('Попробуй ответить еще раз'), $this->chatId);
                
                // Повтор вопроса и выход
                $answerQuestion = $this->questionsManager->buildAnswerQuestion($prevQuestion);
                return $answerQuestion;            
            } else {
                // $this->bot->sendMessage(new TextMessage('правильно!'), $this->chatId);
            }
        }

        // проверка что ответ был набран вручную на НЕобязательный вопрос
        if (is_string($answer) && $prevQuestion && !$prevQuestion->mandatory) {
            return null;
        }

        // проверка что был дан пустой ответ на обязательный вопрос
        if (empty($answer) && $prevQuestion && $prevQuestion->mandatory) {
            $this->bot->stopTyping($this->chatId);
            return null;
        }

        // проверка на то что ответ был дан не на предыдущий вопрос
        if ($answer && $prevQuestion && $answer->question->id !== $prevQuestion->id) {
            $answerQuestion = $this->questionsManager->buildAnswerQuestion($prevQuestion);
            return $answerQuestion;
        }

        $currentCacheData = $this->questionsManager->getCacheData();
        $inputMessageCacheData = $this->inputMessage->getSurveyCacheData();
        if (empty($currentCacheData)) {
            $this->questionsManager->setCacheData($inputMessageCacheData);
        }

        if (empty($inputMessageCacheData) && !empty($currentCacheData)) {
            $this->inputMessage->setSurveyCacheData($currentCacheData, $this->chatId);
        }

        if ($answer && $answer->conversation) {
            $this->bot->sendMessage(new TextMessage('Ok, я слушаю твой вопрос'), $this->chatId);
            $this->questionsManager->forgetCacheData();
            return $this->dialog_mode($arguments, $answer);
        }

        // boolean (если опрос вызван из другого опроса)
        $isTransferSurvey = $this->questionsManager->isTransferSurvey();

        if ($isTransferSurvey) {
            $surveyId = isset($arguments['survey_id']) ? $arguments['survey_id'] : null;
            $this->questionsManager->setSurvey($surveyId);
        }

        // Следующий вопрос
        $this->questionsManager->setStep();

        $question = $this->questionsManager->getNextQuestion();

        $isTransfer = null;
        if (!is_null($question) and !is_null($prevQuestion)) {

            $isTransfer = $this->questionsManager->checkQuestionsTransfersMultiple($question, $prevQuestion);
        }

        while (!is_null($question) and !is_null($prevQuestion) and $isTransfer) {
            $question = $this->questionsManager->getNextQuestion();
            $prevQuestion = $this->questionsManager->getPrevQuestion();

            if (is_null($question)) {
                break;
            }

            $isTransfer = $this->questionsManager->checkQuestionsTransfersMultiple($question, $prevQuestion);
        }

        //делаем проверку был ли этот законченный опрос запущен из под другого опроса
        while ($isTransferSurvey and !$question) {
            Cache::put('chat' . $this->chatId . 'delayed', true, 1);

            //так как опрос закончился, то записываем данные о том что респондент окончил этот опрос
            $currentSurvey = $this->questionsManager->getCurrentSurvey();
            $currentSurvey->respondents()
                ->updateExistingPivot($this->questionsManager->getRespondent()->id, ['completed' => true]);

            $this->questionsManager->setParentSurveyCacheData();
            $currentSurvey = $this->questionsManager->getCurrentSurvey();
            $question = $this->questionsManager->getNextQuestion();

            $isTransferSurvey = $this->questionsManager->isTransferSurvey();

            Cache::forget('chat' . $this->chatId . 'delayed');
        }

        if (is_null($question)) {

            event(new SurveyFinish($survey, $this->respondent));

            $this->questionsManager->forgetCacheData();

            // Достижения которые достиг пользователь проходя опрос (пока не используется)
            // $achievements = $this->questionsManager->getRespondentTargets();

            // Выводим бонус за прохождение опроса (если он есть)
            return $this->questionsManager->buildBonusMessage();
        }

        // Вопрос и клавиатура ответов OutputMessage
        $answerQuestion = $this->questionsManager->buildAnswerQuestion();

        // Вывод вопроса с задержкой
        if (!$question->mandatory) {
            // Задержка времени с меткой в кеше, чтобы на вебхуке можно было игнорировать обновления во время задержки
            Cache::put('chat' . $this->chatId . 'delayed', true, 1);

            if(isset($arguments['chat_id'])){
                $this->bot->sendMessage($answerQuestion, $arguments['chat_id']);
                $this->bot->sendActionTyping($arguments['chat_id']);
            }else{
                $this->bot->sendMessage($answerQuestion);
                $this->bot->sendActionTyping();
            }

            if ($isTransferSurvey) {
                $cacheData = $this->inputMessage->getSurveyCacheData();
                $this->questionsManager->setCacheData($cacheData);
            }

            // Остановка на время задержки вопроса в секундах
            sleep($question->delay);

            $arguments = [
                'survey_id' => $question->group->survey->id,
                'answer_id' => null,
                'skip' => true,
                'step' => $this->questionsManager->getStep(),
            ];

            $this->questionsManager->setArguments($arguments);
            $this->questionsManager->setPrevQuestion($question);

            Cache::forget('chat' . $this->chatId . 'delayed');
            return $this->run('next_question', $arguments);
        }

        return $answerQuestion;
    }

    public function bonuses($arguments) {

        $bonusId = isset($arguments['bonus_id']) ? $arguments['bonus_id'] : null;
        // $surveyId = isset($arguments['survey_id']) ? $arguments['survey_id'] : null;

        if ($bonusId) {
            $bonus = Bonus::findOrFail($bonusId);
            return new TextMessage($bonus->description);
        }

        return $this->questionsManager->getAvailableBonuses();
    }

    public function create_survey() {

        return new TextMessage("Создать опрос со мной можно тут https://borisbot.com");
    }

    public function register() {

        return new TextMessage("register command...");
    }

    public function help() {

        $commands = [
            'restart' => 'Перезапустить бота',
            'start' => 'Запустить бота с начала',
            'create_survey' => 'Создать опрос',
            'bonuses' => 'Список доступных призов',
            'register' => 'Зарегистрироваться',
            'help' => 'Помощь',
        ];

        $text = '';
        foreach ($commands as $name => $description) {
            if (in_array($name, ['start_survey', 'next_question', 'toggle_checkbox']))
                continue;
            
            $text .= sprintf('/%s - %s'.PHP_EOL, $name, $description);
        }

        return new TextMessage($text);
    }

    public function select_survey() {

        return new TextMessage("select_survey command...");
    }

    public function testing() {

        return new TextMessage("testing command...");
    }

    public function getTextChannelsConnect() {

        return new TextMessage("Нажмите СТАРТ чтобы продолжить...");
    }

    public function getChatInfo() {

        $chatId = $this->bot->getChatId();
        return new TextMessage("Идентификатор вашего чата - ".$chatId);
    }

    public function getTestArgs() {
        $message = $this->bot->getMessage();

        $argsMessage = $message->getArguments();

        return new TextMessage("Args: ".print_r($argsMessage, true));
    }

    public function linkRespondetChannels() {
        $message = $this->bot->getMessage();

        $argsMessage = $message->getArguments();

        Log::info('$argsMessage = '.print_r($argsMessage,true));

        if(!isset($argsMessage['respondent'])){
            return new TextMessage('Не передан респондент!');
        }
        
        $respondentInput = Respondent::find($argsMessage['respondent']);

        if(is_null($respondentInput)){
            return new TextMessage('Респондент не найден!');
        }

        $currentRespondent = $this->respondent;

        //связываем каналы
        //делаем проверку есть ли эти каналы уже
        $channels = $respondentInput->channels()->where('channel_id', $currentRespondent->id)->get();
        if(!$channels->isEmpty()){
            return new TextMessage('Канал: '.$currentRespondent->id.' уже привязан!');
        }

        $respondentInput->channels()->attach($currentRespondent, ['provider' => $this->bot->getProvider()]);

        $message = new TextMessage('Ок, будем на связи');

        $cacheKey = 'temp_cache_chat_id_'.$argsMessage['respondent'];
        $webBotChatId = Cache::get($cacheKey);

        broadcast(new MessageWasSended([
            'message' => [
                'type' => 'text-bot',
                'text' => 'Ок, будем на связи',
                'conversation_id' => $webBotChatId,
            ],
        ]));

        return $message;
    }

    public function dialog_mode($arguments, $answer = null) {

        $textBody = $answer ? $answer->answer_text : $this->inputMessage->getText();
        $survey = $answer ? $answer->question->group->survey : $this->questionsManager->getSurvey();

        Log::info('body test fix = '.print_r($textBody,true));

        if (!$survey)
            $survey = $this->questionsManager->getAdminSurvey();

        $widget = $survey->widget;

        if (!$widget) {
            $widgetId = isset($arguments['widget_id']) ? $arguments['widget_id'] : null;
            $widget = $widgetId ? Widget::find($widgetId) : null;
        }

        if (!$widget) {
            $widget = $this->questionsManager->getAdminWidget();
        }

        if (!$textBody) {
            return null;
        }

        $textBody = remove_emoji($textBody);

        $surveyOwner = $survey->owner();

        // Получаем диалог с оператором
        $conversation = Conversation::firstOrCreate([
            'respondent_id' => $this->respondent->id,
            'user_id' => $surveyOwner->id,
            'widget_id' => $widget->id,
            'survey_id' => $survey->id,
            'parent_id' => null,
        ], ['body' => $textBody, 'provider' => $this->bot->getProvider()]);

        Log::info('dialog_mode Conversation provider = '.print_r($this->bot->getProvider(),true));

        $outputMessage = new DialogMessage($conversation);

        // Если диалог с оператором уже существует
        if ($conversation->last_reply) {

            $reply = new Conversation;
            $reply->body = $textBody;
            $reply->respondent_id = $this->respondent->id;
            $reply->user_id = $surveyOwner->id;
            $reply->widget_id = $widget->id;
            $reply->survey_id = $survey->id;
            $reply->provider = $this->bot->getProvider();

            $this->bot->stopTyping();

            $outputMessage->setReply($reply);

            return $outputMessage;
        }

        // Создан новый диалог с оператором
        event(new HelpForBot($survey, $conversation));

        $outputMessage->setWidget($widget);

        if (empty($answer)) {
            $outputMessage->setText('Сообщение перенаправлено оператору. Пожалуйста, подожди ответа');
            $outputMessage->setOtherParams(['respondent_id' => $this->respondent->id]);
        }

        return $outputMessage;
    }


    //методы для получения мыла через бота вне опроса
    public function get_email()
    {
        $respondent = $this->respondent;

        $expiresAt = Carbon::now()->addMinutes(10);

        Cache::put('get_email_'.$respondent->id, $respondent->id, $expiresAt);

        return new TextMessage("Введи свой email друг!)");
    }

    public function set_email()
    {
        $respondent = $this->respondent;
        $arguments = $this->questionsManager->getArguments();
        $email = $arguments['email'];

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return new TextMessage("Вы ввели некорректный email, повторите пожалуйста!");
        }

        //создаем респондента, для того чтобы использовать его как канал
        $respondentEmail = new Respondent();
        $respondentEmail->email = $email;
        $respondentEmail->provider = 'email';
        $respondentEmail->save();

        $respondent->channels()->attach($respondentEmail, ['provider' => 'email']);

        Cache::pull('get_email_'.$respondent->id);

        return new TextMessage("Ok, будем на связи");
    }
}

