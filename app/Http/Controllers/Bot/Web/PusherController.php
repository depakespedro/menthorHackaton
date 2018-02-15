<?php

namespace App\Http\Controllers\Bot\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Survey;
use App\Models\Room;
use App\Models\Question;
use App\Models\Respondent;
use App\Models\Fingerprint;
use App\Models\Conversation;

use App\Services\BotObjects\Messages\Output\Message as OutputMessage;
use App\Services\BotObjects\Messages\Output\TextMessage as OutputTextMessage;
use App\Services\BotObjects\Messages\Output\KeyboardMessage as OutputKeyboardMessage;
use App\Services\BotObjects\Messages\Output\ImageMessage as OutputImageMessage;

class PusherController extends Controller
{
    protected $pusher;
    protected $bot;
    protected $respondent;
    
    public function __construct()
    {
        $this->pusher = new \Pusher(env('PUSHER_KEY'), env('PUSHER_SECRET'), env('PUSHER_APP_ID'), [
            'cluster' => 'eu',
            'encrypted' => true,
        ]);
    }

    public function authRoom(Request $request)
    {
        if (!$request->fp || !$request->widget_id || !$request->survey_id || !$request->room_id) {
            return header("Status: 401 Not authenticated");
        }

        $respondent = $this->detectRespondent($request);

        $room = Room::find($request->room_id);
        $allRespondents = $room->respondents;

        if (!$allRespondents->contains($respondent)) {
            return header("Status: 401 Not authenticated");
        }

        $custom_data['user_id'] = $respondent->id;
        $custom_data['user_info'] = [];
        
        $survey = $room->survey;

        $conversation_id = sha1($room->id . $survey->id);
        
        $custom_data['user_info']['id'] = $respondent->id;
        $custom_data['user_info']['fp'] = $respondent->fingerprint;
        $custom_data['user_info']['survey_id'] = $request->survey_id;
        $custom_data['user_info']['survey_title'] = $survey->title;
        $custom_data['user_info']['conversation_id'] = $conversation_id;
        $custom_data['user_info']['respondents_names'] = $allRespondents->pluck('first_name', 'id')->toArray();

        $messages = $this->findRoomMessages($room, $conversation_id);

        if ($messages) {
            $custom_data['user_info']['messages'] = $messages;
        }

        return $this->pusher->socket_auth($request->channel_name, $request->socket_id, json_encode($custom_data));
    }

    public function authPresence(Request $request)
    {
        $user = User::find(1);

        $presence_data = ['name' => $user->name];

        return $this->pusher->presence_auth($request->channel_name, $request->socket_id, $user->id, $presence_data);
    }

    public function authPrivate(Request $request)
    {
        if (!$request->fp || !$request->widget_id || !$request->survey_id)
            return header("Status: 401 Not authenticated");

        $respondent = $this->detectRespondent($request);

        $custom_data['user_id'] = $respondent->id;
        $custom_data['user_info'] = [];
        
        $survey = Survey::find($request->survey_id);
        
        $custom_data['user_info']['conversation_id'] = sha1($request->fp . $respondent->latlng . $request->survey_id);
        
        $custom_data['user_info']['id'] = $respondent->id;
        $custom_data['user_info']['survey_id'] = $request->survey_id;
        $custom_data['user_info']['survey_title'] = $survey->title;

        $chatId = $custom_data['user_info']['conversation_id'];
        $cacheDataKey = 'chat' . $chatId;
        
        // Получение последних диалогов респондента
        $lastConversation = $respondent->getLastConversation();
        $dialogs = $this->getLastDialogs($lastConversation);
        $custom_data['user_info']['dialogs'] = $dialogs;

        if (!Cache::has($cacheDataKey)) {
            return $this->pusher->socket_auth($request->channel_name, $request->socket_id, json_encode($custom_data));
        }
        
        $cacheDataString = Cache::get($cacheDataKey);
        $cacheData = unserialize($cacheDataString);
        
        $messages = $this->findExistedAnswers($respondent, $survey, $cacheData, $chatId);

        if ($messages) {
            $custom_data['user_info']['messages'] = $messages;
        }

        return $this->pusher->socket_auth($request->channel_name, $request->socket_id, json_encode($custom_data));
    }

    private function detectRespondent(Request $request)
    {
        $ip = get_client_ip();

        $location = \Location::get($ip);
        $latlng = is_object($location) && $location->latitude ? "$location->latitude,$location->longitude" : config('location.default.latlng');
        $city = is_object($location) && $location->cityName ? $location->cityName : null;

        $respondent = Respondent::firstOrCreate([
            'fingerprint' => $request->fp,
            'latlng' => $latlng,
            'provider' => 'webbot',
        ], ['city' => $city]);

        if (!$respondent->city && $city)
            $respondent->update(['city' => $city]);

        return $respondent;
    }

    private function findExistedAnswers($respondent, $survey, $cacheData, $chatId)
    {
        $respondetSurvey =  $respondent->surveys()->whereId($survey->id)->wherePivot('completed', false)->first();

        if (!$respondetSurvey) {
            return null;
        }

        $answers = $respondent->answers()->wherePivot('survey_id', $survey->id)->get();
        $questionsAnswers = $answers->pluck('id', 'question_id')->toArray();
        $questionsIds = array_keys($questionsAnswers);
        $questions = Question::find($questionsIds);

        $group = null;
        $maxOrder = 1;
        foreach ($questions as $question) {
            $maxOrder = max($maxOrder, $question->order);
            $group = $question->group;
        }

        if (is_null($group)) {
            $group = $survey->groups()->first();
            // return null;
        }

        $messages = [];
        $lastQuestion = null;
        $lastAnswer = null;
        foreach ($group->questions()->where('order', '<=', $maxOrder)->orderBy('order')->get() as $question) {
            
            if (!$question->mandatory && $question->order !== $maxOrder) {
                $messages[] = [
                    'type' => 'text-bot',
                    'text' => $question->title,
                    'image' => $question->image ? url($question->image) : null,
                ];

                $lastQuestion = $question;
                $lastAnswer = null;
                continue;
            }

            if (!isset($questionsAnswers[$question->id])) {
                continue;
            }

            $answer = $answers->find($questionsAnswers[$question->id]);

            $messages[] = [
                'type' => 'text-bot',
                'text' => $question->title,
                'image' => $question->image ? url($question->image) : null,
            ];

            $messages[] = [
                'type' => 'text-my',
                'text' => $answer->answer_text,
            ];

            $lastQuestion = $question;
            $lastAnswer = $answer;
        }

        $this->bot = bot(['botType' => 'web']);
        $this->respondent = $respondent;
        $this->step = (int)$cacheData['step'];

        // $nextQuestion = $survey->nextQuestion($this->step, $cacheData, $chatId, $lastAnswer->id);
        if ($lastQuestion) {
            $nextQuestion = ($lastAnswer && $lastAnswer->next_question) ? Question::find($lastAnswer->next_question) : $lastQuestion->group->questions()->whereOrder($lastQuestion->order + 1)->first();
        } else {
            $messages[] = [
                'type' => 'text-my',
                'text' => 'Здесь',
            ];

            $group = $survey->groups()->first();
            $nextQuestion = $group->questions()->first();
        }
        
        if (!$nextQuestion) {
            return $messages;
        }

        if (!$nextQuestion->mandatory) {
            $messages[] = [
                'type' => 'text-bot',
                'text' => $nextQuestion->title,
                'image' => $nextQuestion->image ? url($nextQuestion->image) : null,
            ];

            $group = $nextQuestion->group;

            $nextQuestion = $group->questions()->whereOrder($nextQuestion->order + 1)->first();
            
            if ($nextQuestion && !$nextQuestion->mandatory) {
                $messages[] = [
                    'type' => 'text-bot',
                    'text' => $nextQuestion->title,
                    'image' => $nextQuestion->image ? url($nextQuestion->image) : null,
                ];

                $nextQuestion = $group->questions()->whereOrder($nextQuestion->order + 1)->first();
            }
            if ($nextQuestion && !$nextQuestion->mandatory) {
                $messages[] = [
                    'type' => 'text-bot',
                    'text' => $nextQuestion->title,
                    'image' => $nextQuestion->image ? url($nextQuestion->image) : null,
                ];

                $nextQuestion = $group->questions()->whereOrder($nextQuestion->order + 1)->first();
            }
            if ($nextQuestion && !$nextQuestion->mandatory) {
                $messages[] = [
                    'type' => 'text-bot',
                    'text' => $nextQuestion->title,
                    'image' => $nextQuestion->image ? url($nextQuestion->image) : null,
                ];

                $nextQuestion = $group->questions()->whereOrder($nextQuestion->order + 1)->first();
            }
            if ($nextQuestion && !$nextQuestion->mandatory) {
                $messages[] = [
                    'type' => 'text-bot',
                    'text' => $nextQuestion->title,
                    'image' => $nextQuestion->image ? url($nextQuestion->image) : null,
                ];

                $nextQuestion = $group->questions()->whereOrder($nextQuestion->order + 1)->first();
            }

            if (!$nextQuestion) {
                return $messages;
            }
        }

        $keyboard = $this->buildQuestionButtons($nextQuestion, $chatId, $lastAnswer);

        $nextMessage = $this->transformMessage($keyboard, $chatId);

        $messages[] = $nextMessage['message'];

        return $messages;
    }

    private function buildQuestionButtons($nextQuestion, $chatId, $lastAnswer)
    {
        // Использование шаблонов в тексте вопроса
        $questionTitle = $this->formatQuestionTitle($nextQuestion, $lastAnswer);

        // Текст вопроса с описанием
        $questionText = $nextQuestion->description ? $questionTitle . PHP_EOL . PHP_EOL . $nextQuestion->description . PHP_EOL : $questionTitle . PHP_EOL;

        $keyboard = new OutputKeyboardMessage($questionText);

        if (in_array($nextQuestion->type, ['choice', 'yes_no'])) {
            $keyboard = $this->bot->addOneChoiceButtons($keyboard, $nextQuestion, $this->step);
        } elseif ($nextQuestion->type === 'multiple_choice') {
            $keyboard = $this->bot->addMultipleChoiceButtons($keyboard, $nextQuestion, $this->step);
        } elseif ($nextQuestion->type === 'social_share') {
            $keyboard = $this->bot->addSocialShareButtons($keyboard, $nextQuestion, $this->step);
        } elseif ($nextQuestion->type === 'short_free_text') {
            $keyboard = new OutputTextMessage($questionText);
        } elseif ($nextQuestion->type === 'social_auth') {
            $keyboard = $this->bot->addSocialAuthButtons($keyboard, $nextQuestion, $this->step, $this->respondent, $chatId);
        } elseif ($nextQuestion->type === 'gender_respondent') {
            $keyboard = $this->bot->addGenderRespondentButtons($keyboard, $nextQuestion, $this->step, $this->respondent);
        } elseif ($nextQuestion->type === 'education_respondent') {
            $keyboard = $this->bot->addEducationRespondentButtons($keyboard, $nextQuestion, $this->step, $this->respondent);
        } elseif ($nextQuestion->type === 'working_respondent') {
            $keyboard = $this->bot->addWorkingRespondentButtons($keyboard, $nextQuestion, $this->step, $this->respondent);
        } elseif ($nextQuestion->type === 'revenue_respondent') {
            $keyboard = $this->bot->addRevenueRespondentButtons($keyboard, $nextQuestion, $this->step, $this->respondent);
        } elseif ($nextQuestion->type === 'children_respondent') {
            $keyboard = $this->bot->addChildrenRespondentButtons($keyboard, $nextQuestion, $this->step, $this->respondent);
        } elseif ($nextQuestion->type === 'family_status_respondent') {
            $keyboard = $this->bot->addFamilyStatusRespondentButtons($keyboard, $nextQuestion, $this->step, $this->respondent);
        } elseif ($nextQuestion->type === 'guess') {
            $keyboard = new OutputTextMessage($questionText);
        } elseif ($nextQuestion->answersVisible()->count() > 0) {
            $keyboard = $this->bot->addOneChoiceButtons($keyboard, $nextQuestion, $this->step);
        } else {
            $keyboard = new OutputTextMessage($questionText);
        }

        if (!$nextQuestion->mandatory) {
            $keyboard = new OutputTextMessage($questionText);
            $keyboard
                ->setMandatory(false)
                ->setDelay($nextQuestion->delay);
        }

        if ($nextQuestion->image) {
            $questionText = $keyboard->getText();
            $image = new OutputImageMessage($questionText, $nextQuestion->image, $questionText);

            $keyboard->setImage($image);
        }

        return $keyboard;
    }

    private function formatQuestionTitle($question, $lastAnswer)
    {
        // Использование шаблона предыдущего ответа в тексте вопроса
        $questionTitle = $question->title;
        if (mb_strpos($question->title, '%last_answer%', 0, 'utf-8') !== false && $lastAnswer) {
            $questionTitle = str_replace('%last_answer%', $lastAnswer->answer_text, $questionTitle);
        }

        // Использование шаблона имени пользователя в тексте вопроса
        if (mb_strpos($questionTitle, '%first_name%', 0, 'utf-8') !== false) {
            $firstName = $this->respondent->first_name;
            $questionTitle = str_replace('%first_name%', $firstName, $questionTitle);
        }

        return $questionTitle;
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
                'conversation_id' => $chat,
                'url' => $url
            ],
        ];
    }

    private function transformFromKeyboardMessage(OutputKeyboardMessage $message, $chat, $otherParams)
    {
        $survey = isset($otherParams['survey']) ? $otherParams['survey'] : null;
        $url = isset($otherParams['url']) ? $otherParams['url'] : null;

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

        return [
            'message' => [
                'type' => $otherParams['type_message'],
                'text' => $message->getText(),
                'buttons' => $buttons,
                'conversation_id' => $chat,
                'url' => $url
            ]
        ];
    }

    private function transformImageMessage(OutputImageMessage $message, $chat, $otherParams)
    {
        $url = isset($otherParams['url']) ? $otherParams['url'] : null;

        return [
            'message' => [
                'type' => 'image',
                'image' => $message->getUrlImage(),
                'text' => $message->getText(),
                'buttons' => [],
                'url' => $url,
                'conversation_id' => $chat,
            ]
        ];
    }

    private function getLastDialogs($lastConversation)
    {
        if (empty($lastConversation)) {
            return [];
        }

        $respondent = $lastConversation->respondent;
        $respondentId = $respondent->id;

        $messages = [[
            'type' => $lastConversation->is_operator ? 'text-bot' : 'text-my',
            'text' => $lastConversation->body
        ]];

        if ($messages[0]['type'] === 'text-my') {
            $messages[] = [
                'type' => 'link_respondent',
                'text' => 'Не могу быстро ответить. Оставь контакт, отвечу чуть позже',
                'buttons' => [
                    [
                        'text' => 'Телеграм',
                        'width' => 3,
                        'link' => 'https://telegram.me/testsurveybot?start=cmd-linkRespondetChannels--respondent-'.$respondentId,
                        'params' =>
                            [
                                'answer_id' => 'lr',
                                'type' => 'link_respondent',
                            ]
                    ],
                    [
                        'text' => 'Фейсбук',
                        'width' => 3,
                        'link' => 'https://m.me/dev.surveybot.me?ref=cmd-linkRespondetChannels--respondent-'.$respondentId,
                        'params' =>
                            [
                                'answer_id' => 'lr',
                                'type' => 'link_respondent',
                            ]
                    ],
                    [
                        'text' => 'Email',
                        'command' => 'get_email',
                        'width' => 3,
                        'params' =>
                            [
                                'answer_id' => 'ge',
                                'type' => 'choice',
                            ]
                            
                    ],
                ],
            ];
        }

        foreach ($lastConversation->replies as $reply) {
            $messages[] = [
                'type' => $reply->is_operator ? 'text-bot' : 'text-my',
                'text' => $reply->body
            ];
        }

        return $messages;
    }

    private function findRoomMessages($room, $conversation_id)
    {
        if ($room->messages->count() === 0) {
            return null;
        }

        $messages = [];

        foreach ($room->messages as $message) {
            $messages[] = [
                'type' => 'text-my',
                'text' => $message->body,
                'buttons' => null,
                'respondent_id' => $message->respondent_id,
                'conversation_id' => $conversation_id,
            ];
        }

        return $messages;
    }
}
