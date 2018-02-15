<?php

namespace App\Http\Controllers\Bot\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Survey;
use App\Models\Widget;
use App\Models\Room;

class BotController extends Controller
{

    public function __construct(Request $request)
    {
        $this->middleware('locale');
    }

    public function index(Survey $survey)
    {
        $user = User::with(['roles' => function($q) {
            $q->where('name', 'admin');
        }])->first();

        return view('webbot.index', compact('user', 'survey'));
    }

    public function chat(Request $request, Survey $survey)
    {
        $timeStarted = $_SERVER['REQUEST_TIME_FLOAT']; // (int)round(microtime(true) * 1000); // для замера времени на клиенте

        $ip = get_client_ip();
        $location = \Location::get($ip);
        $latlng = is_object($location) && $location->latitude ? "$location->latitude,$location->longitude" : config('location.default.latlng');

        $widget = $survey->widget ?: Widget::find(344);

        $user = $survey->owner();

        $start_data['messages'] = [];

        if ($survey) {
                        
            $welcome_text = $survey->welcome_text ? $survey->welcome_text : $survey->title;
            $welcome_text = replacementText($welcome_text, ['%first_name%' => '']);

            if ($survey->show_welcome) {
                $start_data['messages'] = [
                    //['type' => 'text-bot', 'text' => "Привет, я Бот Борис) У меня к тебе несколько вопросов от проекта \"$survey->title\""],
                    ['type' => 'text-bot', 'text' => $welcome_text],
                ];   
            }

            // $bonus = $survey->bonuses()->first();

            //if ($bonus)
            //  $start_data['messages'][] = ['type' => 'text-bot', 'text' => "И, кстати, потом с меня ценный приз)"];

            if ($survey->show_channels) {
                $image = $survey->image ? url($survey->image) : null;
                $start_data['messages'][] = ['type' => 'choice', 'text' => "Где тебе удобно пообщаться?", 'image' => $image, 'buttons' => [
                        ['text' => 'Facebook', 'width' => 3, 'link' => $survey->facebook_link, 'button_type' => 'start_survey'],
                        ['text' => 'Telegram', 'width' => 3, 'link' => $survey->telegram_link, 'button_type' => 'start_survey'],
                        ['text' => 'Здесь', 'width' => 3, 'command' => 'start_survey', 'button_type' => 'start_survey', 'params' => [
                            'survey_id' => $survey->id
                        ]],
                        // ['text' => 'Тестирование', 'width' => 1, 'command' => 'testing'],
                ]];
            }
        }

        $start_data['persistent_menu'] = [
            ['text' => 'Начать заново', 'width' => 1, 'command' => 'restart_survey'],
//            ['text' => 'Призы', 'width' => 1, 'command' => 'bonuses'],
            // ['text' => 'Начать заново', 'width' => 1, 'command' => 'start_survey', 'params' => [
            //     'survey_id' => $survey->id,
            // ]],
        ];

        return response()
            ->view('webbot.chat', compact('user', 'survey', 'latlng', 'widget', 'timeStarted', 'start_data'), 200)
            ->header('P3P:CP', 'IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT');
    }

    public function room(Request $request, Room $room)
    {
        $survey = $room->survey;
        $user = $survey->owner();
        $widget = $survey->widget ?: Widget::find(344);

        $ip = get_client_ip();
        $location = \Location::get($ip);
        $latlng = is_object($location) && $location->latitude ? "$location->latitude,$location->longitude" : config('location.default.latlng');

        $start_data = [];
        $start_data['messages'] = [];

        $respondents = $room->respondents;
        $respondent_names = $respondents->pluck('first_name')->toArray();
        
        $start_data['messages'][] = ['type' => 'text-bot', 'text' => 'Привет, ' . implode(', ', $respondent_names) . ', это комната в которой вы можете обсудить проект ' . $survey->title . ', или договориться о встрече'];

        $start_data['persistent_menu'] = [
            ['text' => 'Помощь', 'width' => 1, 'command' => 'help'],
        ];

        return response()
            ->view('webbot.room', compact('user', 'room', 'latlng', 'survey', 'widget', 'start_data'), 200)
            ->header('P3P:CP', 'IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT');
    }
}
