<?php

namespace App\Services\Repositories;

use App\Services\Contracts\SurveybotContract;
use App\Services\Contracts\QuestionsManagerContract;
use App\Services\BotObjects\Messages\Input\Message as InputMessage;
use App\Services\BotObjects\Messages\Output\TextMessage as OutputTextMessage;
use App\Services\BotObjects\Messages\Output\KeyboardMessage as OutputKeyboardMessage;
use App\Services\BotObjects\Messages\Output\ImageMessage as OutputImageMessage;

use App\Models\Survey;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Respondent;
use App\Models\Widget;
use Carbon\Carbon;
use Illuminate\Mail\Transport\LogTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Class QuestionsManagerRepository. Менеджер логики прохождения опроса
 */
class QuestionsManagerRepository implements QuestionsManagerContract
{

    protected $education = [
        'ed1' => 'Среднее',
        'ed2' => 'Неоконченное высшее',
        'ed3' => 'Высшее',
        'ed4' => 'Ученая степень / два или более высших',
    ];

    protected $gender = [
        'grm' => 'Мужчина',
        'grf' => 'Женщина',
    ];

    protected $working = [
        'wy' => 'Да',
        'wn' => 'Нет',
    ];

    protected $revenue = [
        'r1' => 'До 10 000 рублей',
        'r2' => '10 000 – 15 000 рублей',
        'r3' => '15 000 – 25 000 рублей',
        'r4' => '25 000 – 35 000 рублей',
        'r5' => '35 000 – 50 000 рублей',
        'r6' => '50 000 – 70 000 рублей',
        'r7' => '70 000 – 90 000 рублей',
        'r8' => 'Свыше 90 000 рублей',
    ];

    protected $family_status = [
        'fs1' => 'Я женат / замужем',
        'fs2' => 'У меня есть пара',
        'fs3' => 'Я свободен холост',
        'fs4' => 'Не хочу говорить об этом',
    ];

    protected $childrens = [
        'chy' => 'Да',
        'chn' => 'Нет',
    ];

    /**
     * [$survey текущий опрос]
     * @var App\Models\Survey
     */
    protected $survey;

    /**
     * [$prevQuestion предыдущий вопрос, который был задан пользователю]
     * @var App\Models\Question
     */
    protected $prevQuestion = null;

    /**
     * [$prevQuestion следующий вопрос для отправки]
     * @var App\Models\Question
     */
    protected $nextQuestion = null;

    /**
     * [$currentAnswer ответ, который дал пользователь на предыдущий вопрос]
     * @var App\Models\Answer | null
     */
    protected $currentAnswer = null;

    /**
     * [$isChekboxes ответ был дан в виде чекбоксов]
     * @var boolean
     */
    protected $isChekboxes = false;

    /**
     * [$respondent пользователь, который проходит опрос]
     * @var App\Models\Respondent
     */
    protected $respondent;

    /**
     * [$cacheData данные текущего опроса, который проходит пользователь]
     * @var array
     */
    protected $cacheData = [];

    /**
     * [$step порядковый номер шага]
     * @var int
     */
    protected $step;

    /**
     * [$message обновление с вебхука]
     * @var App\Services\BotObjects\Messages\Input\Message
     */
    protected $message;

    /**
     * [$chatId идентификатор пользователя в мессенджере]
     * @var string
     */
    protected $chatId;

    /**
     * [$arguments аргументы комманды]
     * @var array
     */
    protected $arguments;

    /**
     * current messenger API
     * @var mixed
     */
    protected $bot;

    /**
     * ключ кэша чата с данными об опросе который проходит пользователь
     * @var string
     */
    protected $chatCacheKey;

    /**
     * название комманды, которая выполняется в данный момент
     * @var string
     */
    protected $currentCommand;

    /**
     * Конструктор
     */
    public function __construct(SurveybotContract $bot, InputMessage $inputMessage)
    {
        $this->bot = $bot;
        $this->message = $inputMessage;
        $this->setArguments();

        $this->chatId = $this->message->getChatId();
        $this->respondent = $this->message->getRespondent();
        $this->cacheData = $this->message->getSurveyCacheData();

        $this->setSurvey();

        if (!$this->prevQuestion && isset($this->cacheData['question_id'])) {
            $this->prevQuestion = Question::find($this->cacheData['question_id']);
        }
    }

    public function setCurrentCommand($commandName)
    {
        $this->currentCommand = $commandName;
    }

    public function setArguments(array $arguments = [])
    {
        if (!empty($arguments))
            return $this->arguments = $arguments;

        $messageType = $this->message->getMessageType();

        if (in_array($messageType, ['text', 'location']))
            return [];

        $this->arguments = $this->message->getArguments();
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function answerIsCheckoxes()
    {
        return $this->isChekboxes;
    }

    public function getCurrentAnswer()
    {
        return $this->currentAnswer;
    }

    public function getNextQuestion($hold = null)
    {
        $answerId = $this->currentAnswer ? $this->currentAnswer->id : null;
        $nextQuestionsFromCheckboxes = $this->getNextQuestionsFromCheckboxes();
        $nextSurveysFromCheckboxes = $this->getNextSurveysFromCheckboxes();

//        telegramLog('$nextQuestionsFromCheckboxes=' . print_r($nextQuestionsFromCheckboxes, true));

        if (is_array($nextQuestionsFromCheckboxes)) {
            $nextQuestionsCacheKey = $this->getNextQuestionsCacheKey();

            $nextQuestionArr = array_shift_assoc($nextQuestionsFromCheckboxes);

            //достаем ответ который содержит переход на вопрос
            $answerId = (int)key($nextQuestionArr);

//            telegramLog('$answerId=' . print_r($answerId, true));

            //кладем в кеш пометку что переход идет на вопрос
            Cache::put('chat' . $this->chatId . 'transferQuestionNow' . $this->survey->id, array_shift($nextQuestionArr), 60);
//            telegramLog('Cache put transferQuestionNow=' . print_r('chat' . $this->chatId . 'transferQuestionNow' . $this->survey->id, true));
//            telegramLog('Cache put transferQuestionNow=' . print_r(array_shift($nextQuestionArr), true));

            $hold = $this->cacheData['hold'];

            if (count($nextQuestionsFromCheckboxes) === 0)
                Cache::forget($nextQuestionsCacheKey);
            else
                Cache::put($nextQuestionsCacheKey, serialize($nextQuestionsFromCheckboxes), 300);
        } elseif (is_array($nextSurveysFromCheckboxes)) {
            $nextSurveysCacheKey = $this->getNextSurveysCacheKey();

            $nextSurveyArr = array_shift_assoc($nextSurveysFromCheckboxes);
            $answerId = (int)key($nextSurveyArr);
            $hold = $this->cacheData['hold'];

            if (count($nextSurveysFromCheckboxes) === 0) {
                Cache::forget($nextSurveysCacheKey);
            } else {
                Cache::put($nextSurveysCacheKey, serialize($nextSurveysFromCheckboxes), 300);
            }
        }

        $this->nextQuestion = $this->survey->nextQuestion($this->step, $this->message->getSurveyCacheData(), $this->chatId, $answerId, $hold);

        //проверка вывода вопроса - локация для веббота, для веббота мы его пропускаем, а координаты автоматом заюираем у него
        if (($this->bot->getProvider() === 'webbot') and !is_null($this->nextQuestion) and ($this->nextQuestion->type == 'location')) {
            //имитируем ответ
            $answer = $this->saveCustomAnswer($this->respondent->latlng, $this->nextQuestion->id);
            $this->respondent->answers()->syncWithoutDetaching([$answer->id => ['survey_id' => $this->survey->id]]);

            //переключаем на следующий вопрос
            $this->step += 1;
            $this->nextQuestion = $this->survey->nextQuestion($this->step, $this->message->getSurveyCacheData(), $this->chatId);
        }

        //проверка вывода вопроса - соц авторизация для фейсбука, для фейсбука мы его пропускаем
        if(in_array($this->bot->getProvider(), ['facebook', 'telegram']) and !is_null($this->nextQuestion) and ($this->nextQuestion->type == 'social_auth')){
            //имитируем ответ
            if ($this->bot->getProvider() === 'facebook') {
                $customAnswer = 'fb';
            } elseif ($this->bot->getProvider() === 'telegram') {
                $customAnswer = 'tlg';
            } else {
                $customAnswer = '';
            }

            $answer = $this->saveCustomAnswer($customAnswer, $this->nextQuestion->id);

            $this->respondent->answers()->syncWithoutDetaching([$answer->id => ['survey_id' => $this->survey->id]]);

            //переключаем на следующий вопрос
            $this->step += 1;
            $this->nextQuestion = $this->survey->nextQuestion($this->step, $this->message->getSurveyCacheData(), $this->chatId);
        }

        //проверка вывода вопроса - авторизации, если указана регистарация через Фб, ВК, либо все поля заполнены - пропускаем
        telegramLog('type_social_auth=' . print_r($this->respondent->type_social_auth, true));
        if (!is_null($this->nextQuestion) and ($this->nextQuestion->type == 'social_auth')) {

            $checkComletedFields = $this->checkCompletedAuthFields();
            telegramLog('$checkComletedFields =' . print_r($checkComletedFields, true));

            if ($checkComletedFields or !is_null($this->respondent->type_social_auth)) {
                //имитируем ответ
                $answer = $this->saveCustomAnswer('social_auth', $this->nextQuestion->id);
                $this->respondent->answers()->syncWithoutDetaching([$answer->id => ['survey_id' => $this->survey->id]]);

                //переключаем на следующий вопрос
                $this->step += 1;
                $this->nextQuestion = $this->survey->nextQuestion($this->step, $this->message->getSurveyCacheData(), $this->chatId);
            }
        }

        //проверка вопросов регистрации, если вопрос был отвечен, то пропускаем
        $nameColumnRespondent = $this->checkCompletedQuestion($this->nextQuestion, $this->respondent);

        while ($nameColumnRespondent) {
            telegramLog('$nameColumnRespondent=' . print_r($nameColumnRespondent, true));

            //имитируем ответ
            $answer = $this->saveCustomAnswer($this->respondent->$nameColumnRespondent, $this->nextQuestion->id);
            $this->respondent->answers()->syncWithoutDetaching([$answer->id => ['survey_id' => $this->survey->id]]);

            //переключаем на следующий вопрос
            $this->step += 1;
            $this->nextQuestion = $this->survey->nextQuestion($this->step, $this->message->getSurveyCacheData(), $this->chatId);

            $nameColumnRespondent = $this->checkCompletedQuestion($this->nextQuestion, $this->respondent);
        }

        if (!$this->nextQuestion) {
            $this->respondent->surveys()->updateExistingPivot($this->survey->id, ['completed' => 1]);
        }

        return $this->nextQuestion;
    }

    //проверяет что переданный вопрос, был отвечен у респондента
    protected function checkCompletedQuestion($question, $respondent)
    {
        if (is_null($question))
            return false;

        $questionsArray = [
            'name_respondent' => 'first_name',
            'phone_respondent' => 'phone',
            'bdate_respondent' => 'birth_date',
            'gender_respondent' => 'sex',
            'mail_respondent' => 'email',
            'education_respondent' => 'education',
            'working_respondent' => 'working',
            'revenue_respondent' => 'revenue',
            'children_respondent' => 'children',
            'family_status_respondent' => 'family_status',
        ];

        $type = $question->type;

        if (!isset($questionsArray[$type])) {
            return false;
        }

        $fieldRespondent = $questionsArray[$type];

        if (!is_null($respondent->$fieldRespondent) and !empty($respondent->$fieldRespondent)) {
            return $fieldRespondent;
        }

        return false;
    }

    // проверяет что регистрационные поля заполнены
    protected function checkCompletedAuthFields()
    {
        $questionsArray = [
            'first_name',
            'phone',
            'birth_date',
            'sex',
            'email',
            'education',
            'working',
            'revenue',
            'children',
            'family_status',
        ];

        foreach ($questionsArray as $field) {
            if (is_null($this->respondent->$field) or empty($this->respondent->$field)) {
                return false;
            }
        }

        return true;
    }

    public function getPrevQuestion()
    {
        return $this->prevQuestion;
    }

    public function setPrevQuestion(Question $question)
    {
        $this->prevQuestion = $question;
    }

    public function forgetPrevQuestion()
    {
        $this->prevQuestion = null;
    }

    /**
     * Достижения пользователя
     * @return collection App\Models\Target
     */
    public function getRespondentTargets()
    {
        return $this->respondent->getTargets($this->survey);
    }

    public function getStep()
    {
        return $this->step;
    }

    public function setStep($step = null)
    {
        if ($step) {
            $this->step = (int)$step;
            return $this->step;
        }

        if (isset($this->arguments['step'])) {
            $step = (int)$this->arguments['step'] + 1;
        } elseif (isset($this->cacheData['step'])) {
            $step = (int)$this->cacheData['step'] + 1;
        }

        $this->step = $step;

        return $this->step;
    }

    public function getSurvey()
    {
        return $this->survey;
    }

    public function getAdminSurvey()
    {
        return Survey::find(220);
    }

    public function getAdminWidget()
    {
        return Widget::find(344);
    }

    public function setSurvey($survey_id = null)
    {
        if (!is_null($survey_id)) {
            $surveyId = $survey_id;
        } else {
            $surveyId = isset($this->arguments['survey_id']) ? $this->arguments['survey_id'] : null;

            if (!$surveyId) {
                $surveyId = isset($this->cacheData['survey_id']) ? $this->cacheData['survey_id'] : null;
            }
        }

        $this->survey = $surveyId ? Survey::whereId($surveyId)->whereActive(1)->first() : null;
    }

    public function surveyIsRunning()
    {
        if (!$this->chatCacheKey)
            return false;

        return Cache::has($this->chatCacheKey);
    }

    public function getChatId()
    {
        return $this->chatId;
    }

    public function getRespondent()
    {
        return $this->respondent;
    }

    public function getCacheData()
    {
        return $this->cacheData;
    }

    public function setCacheData(array $data)
    {
        $this->cacheData = $data;
    }

    public function getCheckboxesCacheKey()
    {
        if (!isset($this->cacheData['question_id']))
            return null;

        return 'chat' . $this->chatId . 'question' . $this->cacheData['question_id'] . 'checkboxes';
    }

    public function getNextQuestionsCacheKey()
    {
        if (!isset($this->cacheData['hold']))
            return null;

        return 'chat' . $this->chatId . 'checkedAnswersNextQuestions' . $this->cacheData['hold'];
    }

    public function getNextSurveysCacheKey()
    {
        if (is_null($this->getCurrentSurvey())) {
            return null;
        }

        return 'chat' . $this->chatId . 'checkedAnswersNextSurveys' . $this->getCurrentSurvey()->id;
    }

    /**
     * Возвращает массив с id вопросов на которые надо перейти
     * Если в отмеченных чекбоксах были ответы с переходами на вопросы
     * @return array
     */
    public function getNextQuestionsFromCheckboxes()
    {
        if (!isset($this->cacheData['hold']))
            return null;

        $key = 'chat' . $this->chatId . 'checkedAnswersNextQuestions' . $this->cacheData['hold'];

        if (!Cache::has($key))
            return null;

        $nextQuestionsDataString = Cache::get($key);
        $checkedAnswersNextQuestions = unserialize($nextQuestionsDataString);

        return $checkedAnswersNextQuestions;
    }

    /**
     * Возвращает массив с id опросов на которые надо перейти
     * Если в отмеченных чекбоксах были ответы с переходами на опросы
     * @return array
     */
    public function getNextSurveysFromCheckboxes()
    {
//        if (!isset($this->cacheData['hold'])) {
//            telegramLog('hold null=' . print_r(1, true));
//            return null;
//        }

        if (is_null($this->getCurrentSurvey())) {
            return [];
        }

        $key = 'chat' . $this->chatId . 'checkedAnswersNextSurveys' . $this->getCurrentSurvey()->id;

//        telegramLog('getNextSurveysFromCheckboxes key=' . print_r($key, true));

        if (!Cache::has($key))
            return null;

        $nextSurveysDataString = Cache::get($key);
        $checkedAnswersNextSurveys = unserialize($nextSurveysDataString);

//        telegramLog('getNextSurveysFromCheckboxes=' . print_r($checkedAnswersNextSurveys, true));

        return $checkedAnswersNextSurveys;
    }

    public function forgetCacheData()
    {
        if (!$this->chatId)
            return null;

        return Cache::forget('chat' . $this->chatId);
    }

    public function getStartSurvey()
    {
        if ($this->currentCommand !== 'start')
            return null;

        if ($survey = $this->getSurvey())
            return $survey;

        if ($defaultSurvey = Survey::whereDefault(1)->first())
            return $defaultSurvey;

        return null;
    }

    public function buildAnswerQuestion($question = null)
    {
        if (!$this->nextQuestion && !$question) {
            return null;
        }

        if ($question) {
            $this->nextQuestion = $question;
        }

        // Использование шаблонов в тексте вопроса
        $questionTitle = $this->formatQuestionTitle($this->nextQuestion);

        // Текст вопроса с описанием
        $questionText = $this->nextQuestion->description ? $questionTitle . PHP_EOL . PHP_EOL . $this->nextQuestion->description . PHP_EOL : $questionTitle . PHP_EOL;

        $keyboard = new OutputKeyboardMessage($questionText);

        if (in_array($this->nextQuestion->type, ['choice', 'yes_no'])) {
            $keyboard = $this->bot->addOneChoiceButtons($keyboard, $this->nextQuestion, $this->step);
        } elseif ($this->nextQuestion->type === 'multiple_choice') {
            $keyboard = $this->bot->addMultipleChoiceButtons($keyboard, $this->nextQuestion, $this->step);
        } elseif ($this->nextQuestion->type === 'social_share') {
            $keyboard = $this->bot->addSocialShareButtons($keyboard, $this->nextQuestion, $this->step);
        } elseif ($this->nextQuestion->type === 'short_free_text') {
            $keyboard = new OutputTextMessage($questionText);
        } elseif ($this->nextQuestion->type === 'social_auth') {
            $keyboard = $this->bot->addSocialAuthButtons($keyboard, $this->nextQuestion, $this->step, $this->respondent);
        } elseif ($this->nextQuestion->type === 'location') {
            $keyboard = $this->bot->addLocationButton($keyboard, $this->nextQuestion, $this->step, $this->respondent);
        } elseif ($this->nextQuestion->type === 'gender_respondent') {
            $keyboard = $this->bot->addGenderRespondentButtons($keyboard, $this->nextQuestion, $this->step, $this->respondent);
        } elseif ($this->nextQuestion->type === 'education_respondent') {
            $keyboard = $this->bot->addEducationRespondentButtons($keyboard, $this->nextQuestion, $this->step, $this->respondent);
        } elseif ($this->nextQuestion->type === 'working_respondent') {
            $keyboard = $this->bot->addWorkingRespondentButtons($keyboard, $this->nextQuestion, $this->step, $this->respondent);
        } elseif ($this->nextQuestion->type === 'revenue_respondent') {
            $keyboard = $this->bot->addRevenueRespondentButtons($keyboard, $this->nextQuestion, $this->step, $this->respondent);
        } elseif ($this->nextQuestion->type === 'children_respondent') {
            $keyboard = $this->bot->addChildrenRespondentButtons($keyboard, $this->nextQuestion, $this->step, $this->respondent);
        } elseif ($this->nextQuestion->type === 'family_status_respondent') {
            $keyboard = $this->bot->addFamilyStatusRespondentButtons($keyboard, $this->nextQuestion, $this->step, $this->respondent);
        } elseif ($this->nextQuestion->type === 'guess') {
            $keyboard = new OutputTextMessage($questionText);
        } elseif ($this->nextQuestion->answersVisible()->count() > 0) {
            $keyboard = $this->bot->addOneChoiceButtons($keyboard, $this->nextQuestion, $this->step);
        } else {
            $keyboard = new OutputTextMessage($questionText);
        }

        if (!$this->nextQuestion->mandatory) {
            $keyboard = new OutputTextMessage($questionText);
            $keyboard
                ->setMandatory(false)
                ->setDelay($this->nextQuestion->delay);
        }

        if ($this->nextQuestion->image) {
            $questionText = $keyboard->getText();
            $image = new OutputImageMessage($questionText, $this->nextQuestion->image, $questionText);

            $keyboard->setImage($image);
        }

        return $keyboard;
    }

    public function buildBonusMessage()
    {
        $text = $this->survey->end_text ?: null;

        // $targets = $this->respondent->getTargets($this->survey);

        // $image = null;

        // if (!empty($targets)) {
        //     $text = $text . PHP_EOL . PHP_EOL;
        //     foreach ($targets as $target) {
        //         if ($target->image)
        //             $image = url($target->image);

        //         $text = $text . $target->title . PHP_EOL;
        //         if ($target->description)
        //             $text = $text . $target->description . PHP_EOL;
        //     }
        // }

        $bonus = $this->survey->bonuses->first();

        if (!$bonus) {
            if (!$text) {
                return null;
            }

            $message = new OutputTextMessage($text);
            return $message;
        }

        $keyboard = new OutputKeyboardMessage($text);

        if ($bonus->type == 'custom') {
            $keyboard = $this->bot->addBonusButton($keyboard, $bonus, $bonus->button_title);
        } else {
            $keyboard = $this->bot->addBonusButton($keyboard, $bonus);
        }

        return $keyboard;
    }

    public function getAvailableBonuses()
    {
        $completedSurveys = $this->respondent ? $this->respondent->surveys()->wherePivot('completed', 1)->get() : null;

        if (!$completedSurveys || count($completedSurveys) < 1) {
            return new OutputTextMessage('Вы пока не завершили полностью ни одного опроса');
        }

        $keyboard = new OutputKeyboardMessage('Список пройденных вами опросов:');

        foreach ($completedSurveys as $survey) {

            $text = "$survey->title" . PHP_EOL;

            if ($survey->bonuses->count()) {
                foreach ($survey->bonuses as $bonus) {
                    $bonusTitle = $text . "($bonus->title)";
                    $keyboard = $this->bot->addBonusButton($keyboard, $bonus, $bonusTitle);
                }
            }
        }

        $bonusesButtons = $keyboard->getKeyboard();

        if (empty($bonusesButtons)) {
            $keyboard->setText('Вы пока не прошли ни одного опроса, у которого есть призы');
        }

        return $keyboard;
    }

    public function detachRespondentAnswers()
    {
        $this->respondent->surveys()->syncWithoutDetaching([$this->survey->id]);
        $this->respondent->surveys()->updateExistingPivot($this->survey->id, ['completed' => 0]);
        $this->respondent->answers()->wherePivot('survey_id', $this->survey->id)->detach();
        $this->respondent->targets()->wherePivot('survey_id', $this->survey->id)->detach();
    }

    public function setCurrentAnswer()
    {
        if (isset($this->arguments['checked']))
            return null;
        if (isset($this->arguments['skip']) && is_null($this->arguments['answer_id'])) {
            $this->currentAnswer = null;
            return null;
        }

        $answerId = isset($this->arguments['answer_id']) ? $this->arguments['answer_id'] : null;

        $customAnswer = isset($this->arguments['custom_answer']) ? $this->arguments['custom_answer'] : null;

//        telegramLog('setCurrentAnswer $answerId=' . print_r($answerId, true));
//        telegramLog('setCurrentAnswer $customAnswer=' . print_r($customAnswer, true));

//        //замена коротких обозначений на развернутые ответы
//        $customAnswer = 'Не известно';
//        if ($answerId == 'sa') {
//            $customAnswer = 'social_auth';
//        } elseif ($answerId == 'ssh') {
//            $customAnswer = 'social_share';
//        } elseif (in_array($answerId, ['grm', 'grf'])) {
//            if (isset($this->gender[$answerId])) {
//                $customAnswer = $this->gender[$answerId];
//            }
//        } elseif (in_array($answerId, ['ed1', 'ed2', 'ed3', 'ed4'])) {
//            if (isset($this->education[$answerId])) {
//                $customAnswer = $this->education[$answerId];
//            }
//        } elseif (in_array($answerId, ['wy', 'wn'])) {
//            if (isset($this->working[$answerId])) {
//                $customAnswer = $this->working[$answerId];
//            }
//        } elseif (in_array($answerId, ['r1', 'r2', 'r3', 'r4', 'r5', 'r6', 'r7', 'r8'])) {
//            if (isset($this->revenue[$answerId])) {
//                $customAnswer = $this->revenue[$answerId];
//            }
//        } elseif (in_array($answerId, ['fs1', 'fs2', 'fs3', 'fs4'])) {
//            if (isset($this->family_status[$answerId])) {
//                $customAnswer = $this->family_status[$answerId];
//            }
//        } elseif (in_array($answerId, ['chy', 'chn'])) {
//            if (isset($this->childrens[$answerId])) {
//                $customAnswer = $this->childrens[$answerId];
//            }
//        }

        //todo заменить массиво либо как то продумтаь более оптимально
        if ($answerId == 'sa') {
            $customAnswer = 'social_auth';
        } elseif ($answerId == 'ssh') {
            $customAnswer = 'social_share';
        } elseif (in_array($answerId, ['grm', 'grf'])) {
            if ($answerId == 'grm') {
                $customAnswer = 'Мужчина';
            } elseif ($answerId == 'grf') {
                $customAnswer = 'Женщина';
            } else {
                $customAnswer = 'Не известно';
            }
        } elseif (in_array($answerId, ['ed1', 'ed2', 'ed3', 'ed4'])) {
            if ($answerId == 'ed1') {
                $customAnswer = 'Среднее';
            } elseif ($answerId == 'ed2') {
                $customAnswer = 'Неоконченное высшее';
            } elseif ($answerId == 'ed3') {
                $customAnswer = 'Высшее';
            } elseif ($answerId == 'ed4') {
                $customAnswer = 'Ученая степень / два или более высших';
            } else {
                $customAnswer = 'Не известно';
            }
        } elseif (in_array($answerId, ['wy', 'wn'])) {
            if ($answerId == 'wy') {
                $customAnswer = 'Да';
            } elseif ($answerId == 'wn') {
                $customAnswer = 'Нет';
            } else {
                $customAnswer = 'Не известно';
            }
        } elseif (in_array($answerId, ['r1', 'r2', 'r3', 'r4', 'r5', 'r6', 'r7', 'r8'])) {
            if ($answerId == 'r1') {
                $customAnswer = 'До 10 000 рублей';
            } elseif ($answerId == 'r2') {
                $customAnswer = '10 000 – 15 000 рублей';
            } elseif ($answerId == 'r3') {
                $customAnswer = '15 000 – 25 000 рублей';
            } elseif ($answerId == 'r4') {
                $customAnswer = '25 000 – 35 000 рублей';
            } elseif ($answerId == 'r5') {
                $customAnswer = '35 000 – 50 000 рублей';
            } elseif ($answerId == 'r6') {
                $customAnswer = '50 000 – 70 000 рублей';
            } elseif ($answerId == 'r7') {
                $customAnswer = '70 000 – 90 000 рублей';
            } elseif ($answerId == 'r8') {
                $customAnswer = 'Свыше 90 000 рублей';
            } else {
                $customAnswer = 'Не известно';
            }
        } elseif (in_array($answerId, ['fs1', 'fs2', 'fs3', 'fs4'])) {
            if ($answerId == 'fs1') {
                $customAnswer = 'Я женат / замужем';
            } elseif ($answerId == 'fs2') {
                $customAnswer = 'У меня есть пара';
            } elseif ($answerId == 'fs3') {
                $customAnswer = 'Я свободен холост';
            } elseif ($answerId == 'fs4') {
                $customAnswer = 'Не хочу говорить об этом';
            } else {
                $customAnswer = 'Не известно';
            }
        } elseif (in_array($answerId, ['chy', 'chn'])) {
            if ($answerId == 'chy') {
                $customAnswer = 'Да';
            } elseif ($answerId == 'chn') {
                $customAnswer = 'Нет';
            } else {
                $customAnswer = 'Не известно';
            }
        }

        $checkedAnswers = isset($this->arguments['checked_answers']) ? $this->arguments['checked_answers'] : null;

//        telegramLog('setCurrentAnswer $checkedAnswers=' . print_r($checkedAnswers, true));

        if ($this->prevQuestion && $this->prevQuestion->type === 'multiple_choice') {
            return $this->saveAnswersFromCheckboxes($checkedAnswers, $customAnswer);
        } elseif ($customAnswer) {

            if (!$this->prevQuestion->mandatory) {
//                telegramLog('setCurrentAnswer =' . print_r('mandatory', true));
                Log::info('попытка дать ответ на необязательный вопрос');
                Log::info('customAnswer = ' . $customAnswer);
                $this->currentAnswer = $customAnswer;
                return null;
            }

            // если ответ дан на открытый вопрос (квест)
            if ($this->prevQuestion && $this->prevQuestion->type === 'guess') {
//                telegramLog('setCurrentAnswer =' . print_r('guess', true));
                // проверка правильности ответа на загадку
                $result = $this->isCorrectGuess($customAnswer);
                if ($result['correct']) {
                    $this->currentAnswer = $result['answer'];
                } else {
                    //если ответ не распознали и у нераспознанного ответа есть переходы то вохвращаем его
                    //достанем нерасп ответ данного вопроса
                    $uncnowGuessAnswer = $this->prevQuestion->answers()->where('code', '=', 'unknown_guess')->first();
                    if (!is_null($uncnowGuessAnswer)
                        and (!is_null($uncnowGuessAnswer->next_question)
                            or !is_null($uncnowGuessAnswer->next_survey)
                            or !is_null($uncnowGuessAnswer->end_survey))) {
                        $this->currentAnswer = $uncnowGuessAnswer;
                    } else {
                        $this->currentAnswer = null;
                    }

                    return null;
                }
            } else {
//                telegramLog('setCurrentAnswer =' . print_r('else', true));
                $this->saveCustomAnswer($customAnswer);
            }
        } elseif ($answerId) {

            if ($this->prevQuestion && !$this->prevQuestion->mandatory) {
                $this->currentAnswer = null;
                return null;
            }

            $this->currentAnswer = Answer::find($answerId);
        }


        if (!$this->isChekboxes && $this->currentAnswer && $this->currentAnswer->question->mandatory) {
            $this->respondent->answers()->syncWithoutDetaching([$this->currentAnswer->id => ['survey_id' => $this->survey->id]]);
        }

        //telegramLog(print_r('setCurrentAnswer=' . $this->currentAnswer, true));
        return $this->currentAnswer;
    }

    public function validateAnswer($answer, $question)
    {
        $text = is_object($answer) ? $answer->answer_text : null;

        if (!$question || !$text) {
            return true;
        }

        $type = $question->type;

        if ($type == 'mail_respondent') {
            return filter_var($text, FILTER_VALIDATE_EMAIL);
        } elseif ($type == 'phone_respondent') {
            return strlen($text) <= 12;
        } elseif ($type == 'bdate_respondent') {
            try {
                $date = Carbon::parse($text);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    public function isTransferSurvey()
    {
//        telegramLog(print_r('isTransferSurvey surveyId=' . $this->survey->id, true));
//        telegramLog(print_r('isTransferSurvey chatId=' . $this->chatId, true));

        $key = 'chat' . $this->chatId;
        $rawDataCache = Cache::get($key);

//        telegramLog(print_r('isTransferSurvey $rawDataCache=' . $rawDataCache, true));

        if ($rawDataCache) {
            $data = unserialize($rawDataCache);
        }

//        telegramLog(print_r('isTransferSurvey $data=' . print_r($data, true), true));

        if (isset($data['parent']) and !empty($data['parent'])) {
            return true;
        }

        return false;
    }

    public function setParentSurveyCacheData()
    {
        $key = 'chat' . $this->chatId;
        $rawDataCache = Cache::get($key);

        if ($rawDataCache) {
            $data = unserialize($rawDataCache);
        }

        if (isset($data['parent']) and !empty($data['parent'])) {
            $parent = $data['parent'];

            Cache::put($key, serialize($parent), 300);

            $this->setCacheData($parent);
            $this->setSurvey($parent['survey_id']);

            return null;
        }

        return null;
    }

    public function getCurrentSurvey()
    {
        return $this->survey;
    }

    // PRIVATE AREA:

    private function saveAnswersFromCheckboxes($checkedAnswers = null, $customAnswer = null)
    {
        $this->isChekboxes = true;

        $checkboxesCacheKey = $this->getCheckboxesCacheKey();

        $answers = $this->bot->transformCheckboxesToAnswers($checkedAnswers, $checkboxesCacheKey, $customAnswer);

//        telegramLog(print_r('$answers=' . print_r($answers, true), true));

        $checkedAnswersNextQuestions = [];
        $checkedAnswersNextSurveys = [];

//        telegramLog('current survey=' . print_r($this->survey->id, true));

        if (is_object($answers) && $answers->count()) {

            foreach ($answers as $answer) {
                $currentQuestion = $answer->question;

                if ($currentQuestion->type === 'multiple_choice' && $answer->next_question && !in_array($answer->next_question, $checkedAnswersNextQuestions)) {
                    $checkedAnswersNextQuestions[$answer->id] = $answer->next_question;
                }

                if ($currentQuestion->type === 'multiple_choice' && $answer->next_survey && !in_array($answer->next_survey, $checkedAnswersNextSurveys)) {
                    $checkedAnswersNextSurveys[$answer->id] = $answer->next_survey;
                }

                if ($answer->question->mandatory) {
                    $this->respondent->answers()->syncWithoutDetaching([$answer->id => ['survey_id' => $this->survey->id]]);
                }
            }

            $this->currentAnswer = $answer;
        } else {
            return null;
        }

//        telegramLog(print_r('$checkedAnswersNextQuestions=' . print_r($checkedAnswersNextQuestions, true), true));

        if (!empty($checkedAnswersNextQuestions)) {
            $this->cacheData['hold'] = $currentQuestion->id;
            Cache::put('chat' . $this->chatId . 'checkedAnswersNextQuestions' . $currentQuestion->id, serialize($checkedAnswersNextQuestions), 60);
        }

        if (!empty($checkedAnswersNextSurveys)) {
            $this->cacheData['hold'] = $currentQuestion->id;
            Cache::put('chat' . $this->chatId . 'checkedAnswersNextSurveys' . $this->getCurrentSurvey()->id, serialize($checkedAnswersNextSurveys), 60);
        }

        return $this->currentAnswer;
    }

    /**
     *  метод проверяет текущий вопрос, нужно ли его пропускать так как он числится в малтипале
     *  если переход на вопрос был не с малтипала то его пропускаем (true) если переход с малтипала то переходим на него
     *
     * @param Question $question
     * @param Question $prevQuestion
     * @return bool
     */
    public function checkQuestionsTransfersMultiple(Question $question, Question $prevQuestion)
    {

        $questionsTransfersMultiple = $this->getCacheTransfersQuestionsMultiple();
        if (empty($questionsTransfersMultiple)) {
            return false;
        }

//        telegramLog('check $questionsTransfersMultiple=' . print_r($questionsTransfersMultiple, true));
//        telegramLog('check $question type=' . print_r($question->type, true));
//        telegramLog('check $question id=' . print_r($question->id, true));
//        telegramLog('check $prevQuestion=' . print_r($prevQuestion->type, true));

        //достаем вопрос который сейчас на переходе стоит
        $transferQuestionNow = Cache::get('chat' . $this->chatId . 'transferQuestionNow' . $this->survey->id, null);
//        telegramLog('check $transferQuestionNow=' . print_r('chat' . $this->chatId . 'transferQuestionNow' . $this->survey->id, true));
//        telegramLog('check $transferQuestionNow=' . print_r($transferQuestionNow, true));

        //если пред вопрос не малтипл и переданный вопрос есть в списке и он является на данный момент переходом с малтипала, то мы гео не пропускаем
        if ($prevQuestion->type != 'multiple_choice' and $question->id == $transferQuestionNow) {
//            telegramLog('check if =' . print_r(1.5, true));
            //после перехода на этот вопрос, удаляем из кеша инфу
            Cache::forget('chat' . $this->chatId . 'transferQuestionNow' . $this->survey->id);
            return false;
        }

        //если пред вопрос не малтипл и переданный вопрос есть в списке то мы его пропускаем
        if ($prevQuestion->type != 'multiple_choice' and in_array($question->id, $questionsTransfersMultiple)) {
//            telegramLog('check if =' . print_r(1, true));
            return true;
        }



        //если пред вопрос малтипл и переданный вопрос есть в списке на пропуск
        if ($prevQuestion->type == 'multiple_choice' and in_array($question->id, $questionsTransfersMultiple)) {
            //нужно проверить, был ли переход на этот вопрос из ответа малтипала или он пришел в порядке следования ответов
            //для это проверяем ответы на пред вопрос (малтипл) на наличие ответа с переходом на этот вопрос
//            telegramLog('check if =' . print_r(2, true));
            //достаем ответы на малтипл
            $answersMultiple = $prevQuestion->getAnswersQuestionRespondent($this->respondent);

//            telegramLog('check $answersMultiple=' . print_r($answersMultiple, true));

            //достаем переходы на вопросы из данных ответов
            $transfersQuestions = $this->getTransfersAnswersQuestion($answersMultiple);

//            telegramLog('check $transfersQuestions=' . print_r($transfersQuestions, true));

            //проверяем содержится ли вопрос, если содержится то не пропускаем его
            if (in_array($question->id, $transfersQuestions)) {
//                telegramLog('check if =' . print_r(3, true));
                return false;
            }
//            telegramLog('check if =' . print_r(4, true));
            //если вопроса не нашли в списке то мы на него попали простым переходом по порядку поэтому пропускаем его
            return true;
        }
    }

    //достает переходы на вопросы из переданной коллеции ответов
    public function getTransfersAnswersQuestion($answersMultiple)
    {
        //достаем переходы на вопросы из данных ответов
        $transfersQuestions = [];
        foreach ($answersMultiple as $answer) {
            if (!is_null($answer->next_question)) {
                $transfersQuestions[] = $answer->next_question;
            }
        }

        $transfersQuestions = collect($transfersQuestions)->unique();

        return $transfersQuestions->toArray();
    }

    //вытащить все переходы на вопросы из всех малтипалов переданного опроса
    public function getAllTransfersQuestionsMultipleSurvey(Survey $survey)
    {
        $questions = $survey->questions;
        $transfers = [];
        foreach ($questions as $question) {
            if ($question->type == 'multiple_choice') {
                $answers = $question->answers()->isVisible()->get();

                foreach ($answers as $answer) {
                    if (!is_null($answer->next_question)) {
                        $transfers[] = $answer->next_question;
                    }
                }
            }
        }

        $transfers = collect($transfers)->unique();

        return $transfers->toArray();
    }

    //кладет в кеш данные о переходов малтипалов по вопросам
    public function setCacheTransfersQuestionsMultiple($transfers)
    {
        if (!is_array($transfers)) {
            $transfers = $transfers->toArray();
        }

//        telegramLog('setCacheTransfersQuestionsMultiple=' . print_r($transfers, true));
        Cache::put('chat' . $this->chatId . 'questionsTransfersMultiple' . $this->survey->id, serialize($transfers), 60);
    }

    //выдет в кеш данные о переходов малтипалов по вопросам
    public function getCacheTransfersQuestionsMultiple()
    {
        if (Cache::has('chat' . $this->chatId . 'questionsTransfersMultiple' . $this->survey->id)) {
            $transfers =  unserialize(Cache::get('chat' . $this->chatId . 'questionsTransfersMultiple' . $this->survey->id));
        } else {
            $transfers = [];
        }

//        telegramLog('getCacheTransfersQuestionsMultiple=' . print_r($transfers, true));

        return $transfers;
    }

    private function saveCustomAnswer($customAnswer, $question_id = null)
    {
        Log::info('run saveCustomAnswer $customAnswer = ' . print_r($customAnswer, true));

        $this->currentAnswer = Answer::firstOrCreate([
            'question_id' => is_null($question_id) ? $this->cacheData['question_id'] : $question_id,
            'answer_text' => remove_emoji($customAnswer),
            'visible' => 0,
            'provider' => $this->bot->getProvider()
        ]);

//        telegramLog(print_r('saveCustomAnswer $this->currentAnswer=' . $this->currentAnswer->id, true));

        if (!is_null($question_id)) {
//            telegramLog(print_r('saveCustomAnswer !is_null($question_id)=' . !is_null($question_id) ,true));
            return $this->currentAnswer;
        }

        //telegramLog(print_r('$this->prevQuestion=' . $this->prevQuestion, true));

        //сохраннеие инфы при регистрации через бота
        $prevQuestionType = $this->prevQuestion->type;
        if ($prevQuestionType == 'name_respondent') {
            $respondent = $this->respondent;
            $respondent->first_name = $customAnswer;
            $respondent->save();
        } elseif ($prevQuestionType == 'phone_respondent') {
            $respondent = $this->respondent;
            $respondent->phone = $customAnswer;
            $respondent->save();
        } elseif ($prevQuestionType == 'bdate_respondent') {
            $respondent = $this->respondent;

            try {
                $date = Carbon::parse($customAnswer);
            } catch (\Exception $e) {
                $date = null;
            }

            $respondent->birth_date = $date;
            $respondent->save();
        } elseif ($prevQuestionType == 'mail_respondent') {

            if ($respondentMap = $this->respondent->mapRespondentByEmail($customAnswer)) {
                $this->respondent = $respondentMap;
            }

            $respondent = $this->respondent;
            $respondent->email = $customAnswer;
            $respondent->save();
        } elseif ($prevQuestionType == 'gender_respondent') {
            $respondent = $this->respondent;
            if ($customAnswer == 'Мужчина') {
                $respondent->sex = 'Мужчина';
            } elseif ($customAnswer == 'Женщина') {
                $respondent->sex = 'Женщина';
            } else {
                $respondent->sex = 'Не известно';
            }
            $respondent->save();
        } elseif ($prevQuestionType == 'education_respondent') {
            $respondent = $this->respondent;
            $respondent->education = $customAnswer;
            $respondent->save();
        } elseif ($prevQuestionType == 'working_respondent') {
            $respondent = $this->respondent;
            $respondent->working = $customAnswer;
            $respondent->save();
        } elseif ($prevQuestionType == 'revenue_respondent') {
            $respondent = $this->respondent;
            $respondent->revenue = $customAnswer;
            $respondent->save();
        } elseif ($prevQuestionType == 'family_status_respondent') {
            $respondent = $this->respondent;
            $respondent->family_status = $customAnswer;
            $respondent->save();
        } elseif ($prevQuestionType == 'children_respondent') {
            $respondent = $this->respondent;
            $respondent->children = $customAnswer;
            $respondent->save();
        } elseif ($prevQuestionType == 'location') {
            $respondent = $this->respondent;
            $respondent->latlng = $customAnswer;
            $respondent->save();
        }

        //telegramLog(print_r('saveCustomAnswer $this->currentAnswer =' . $this->currentAnswer->id, true));

        return $this->currentAnswer;
    }

    private function formatQuestionTitle($question)
    {
        $questionTitle = $question->title;

        // Использование шаблона имени пользователя в тексте вопроса
        if (mb_strpos($questionTitle, '%first_name%', 0, 'utf-8') !== false) {
            $firstName = $this->respondent->first_name;
            $questionTitle = str_replace('%first_name%', $firstName, $questionTitle);
        }

        // Использование шаблона предыдущего ответа в тексте вопроса
        if (mb_strpos($question->title, '%last_answer%', 0, 'utf-8') !== false and is_object($this->currentAnswer)) {
            $questionTitle = str_replace('%last_answer%', $this->currentAnswer->answer_text, $questionTitle);
        }

        return $questionTitle;
    }

    /**
     * Проверяет, правильно ли пользователь ответил на вопрос
     * @param string $text
     * @return array
     */
    private function isCorrectGuess($text)
    {
        $correctAnswers = $this->prevQuestion->answersVisible();

        foreach ($correctAnswers as $answer) {

            if ($this->prevQuestion->id === 2545) {
                Log::info('compareStrings ' . print_r([$answer->answer_text, $text], true));
            }

            if ($this->compareStrings($answer->answer_text, $text)) {
                return [
                    'correct' => true,
                    'answer' => $answer,
                ];
            }
        }

        return [
            'correct' => false,
            'answer' => null,
        ];
    }

    /**
     * Неточное сравнение строк
     * @param string $str1
     * @param string $str2
     * @return boolean
     */
    private function compareStrings($str1, $str2)
    {
        if (!$str1 || !$str2)
            return false;

        $str1 = mb_strtolower($str1, "utf-8");
        $str2 = mb_strtolower($str2, "utf-8");

        $str1 = preg_replace('/([^0-9a-zа-яйё\s]+)/iu', '', $str1);
        $str2 = preg_replace('/([^0-9a-zа-яйё\s]+)/iu', '', $str2);

        if ($str1 === $str2)
            return true;

        $similar = similar_text($str1, $str2, $percent);
        $lev = levenshtein($str1, $str2);

        if ($percent > 65 && $lev < 15) {
            return true;
        }

        return false;
    }
}
