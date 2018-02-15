<?php

namespace App\Models;

use App\Model\Condition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;
use phpDocumentor\Reflection\Types\Boolean;

class Respondent extends Model
{
    protected $fillable = [
        'messenger_user_id',
        'username',
        'first_name',
        'last_name',
        'provider',
        'last_survey',
        'email',
        'phone',
        'full_years',
        'birth_date',
        'sex',
        'fingerprint',
        'latlng',
        'city',
        'education',
    ];

    public function channels()
    {
        return $this->belongsToMany(Respondent::class, 'respondents_channels', 'respondent_id', 'channel_id');
    }

    public function vk_users()
    {
        return $this->belongsToMany(\App\Models\SocialNetworks\Vk\User::class, 'respondent_vk_user', 'respondent_id', 'user_id');
    }

    public function surveys()
    {
        return $this->belongsToMany(Survey::class)->withPivot('completed', 'created_at', 'updated_at');
    }

    public function answers()
    {
        return $this->belongsToMany(Answer::class)->withPivot('survey_id');
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_respondent');
    }

    public function targets()
    {
        return $this->belongsToMany(Target::class)->withPivot('survey_id');
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'respondent_event')->orderBy('created_at', 'desc')->withPivot('replied')->withTimestamps();
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function timelogs()
    {
        return $this->hasMany(Timelog::class);
    }

    public function setProviderAttribute($value)
    {
        $this->attributes['provider'] = $value ? $value : null;
    }

    public function getLastConversation()
    {
        $channels = $this->channels->pluck('id')->toArray();

        return Conversation::whereIn('respondent_id', $channels)->whereNull('parent_id')->orderBy('created_at', 'desc')->first();
    }

    public function fullName()
    {
        //if ($this->provider === 'webbot')
        //    return 'anonimus';
        // return 'respondent_id: ' . $this->id;

        if ($this->first_name && $this->last_name) {
            return implode(' ', [$this->first_name, $this->last_name]);
        } else {
            return 'anonimus';
        }
    }

    public function avatar($size = 45)
    {
        return 'https://www.gravatar.com/avatar/' . md5($this->id) . '?s=' . $size . '&d=mm';
    }

    public function getLastSurvey()
    {
        $survey = Survey::find($this->last_survey);

        if (!$survey)
            $survey = Survey::find(220); // опрос по умолчанию

        return $survey;
    }

    public function getTargets($survey)
    {
        $this->targets()->wherePivot('survey_id', $survey->id)->detach();

        $targets = [];
        $targetDefault = null;

        if (!$survey->targets->count())
            return $targets;

        foreach ($survey->targets as $target) {
            if ($target->default) {
                $targetDefault = $target;
                continue;
            }

            $targets[$target->id] = $target;

            foreach ($target->questions as $question) {
                $answer = $this->answers()->wherePivot('survey_id', $survey->id)->find($question->pivot->answer_id);

                if (!$answer || (int)$question->pivot->answer_id !== (int)$answer->id) {
                    unset($targets[$target->id]);
                    break;
                }
            }
        }

        if (!empty($targets)) {
            $this->targets()->sync(array_fill_keys(array_keys($targets), ['survey_id' => $survey->id]));
            return $targets;
        }

        if ($targetDefault) {
            $target = $targetDefault;
            $targets[$target->id] = $target;
            $this->targets()->attach($target->id, ['survey_id' => $survey->id]);
        }

        return $targets;
    }

    public function getFullName()
    {
        $first_name = null;
        if (!is_null($this->first_name) and $this->first_name != '' and $this->first_name != ' ') {
            $first_name = $this->first_name;
        }

        $last_name = null;
        if (!is_null($this->last_name) and $this->last_name != '' and $this->last_name != ' ') {
            $last_name = $this->last_name;
        }

        if (!is_null($first_name) and !is_null($last_name)) {
            return $first_name . ' ' . $last_name;
        }

        if (is_null($first_name) and !is_null($last_name)) {
            return $last_name;
        }

        if (is_null($last_name) and !is_null($first_name)) {
            return $first_name;
        }
    }

    public function getPhone()
    {
        return $this->phone;
    }

    public function getLocation()
    {
        $coordinate = $this->latlng;
        try {
            $content = file_get_contents('https://geocode-maps.yandex.ru/1.x/?format=json&geocode=' . $coordinate);
            $content = json_decode($content);
            $area = $content->response->GeoObjectCollection->featureMember[0]->GeoObject->name;
            $region = $content->response->GeoObjectCollection->featureMember[1]->GeoObject->name;
        } catch (\Exception $exception) {
            return '';
        }

        return $area . ', ' . $region;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getSex()
    {
        $sex = $this->sex;

        if (is_null($sex)) {
            return 'не указан';
        }

        if (in_array($sex, [0, 1, 2])) {
            if ($sex == 0) {
                return 'не указан';
            } elseif ($sex == 1) {
                return 'Женский';
            } elseif ($sex == 2) {
                return 'Мужской';
            }
        }

        return $this->sex;
    }

    public function getFamily()
    {
        return $this->family_status;
    }

    public function getChildrens()
    {
        return $this->children;
    }

    public function getEducation()
    {
        return $this->education;
    }

    public function getWage()
    {
        return $this->wage;
    }

    //метод позволяет, по почте найти респондента и перенести на переданного респондента
    public function mapRespondentByEmail($email)
    {
        //ищем респондента с такой почтой
        $respondentMap = Respondent::where('email', '=', $email)->get();
        $respondentMap = $respondentMap->last();

        if (is_null($respondentMap)) {
            return $this;
        }

        //если нашелся респондент с такой почтой, то переносим его данные к текущему респонденту
        $this->copyRespondent($respondentMap);

        //привязываем к данному респонденту аккаунт соц сетей, которые привязаны к найденному респонденту
        $socialAccaunts = $respondentMap->vk_users;

        foreach ($socialAccaunts as $account) {
            $this->vk_users()->attach($account);
        }

        $this->save();

        return $this;
    }

    public function copyRespondent(Respondent $respondentFrom)
    {
        $this->username = $respondentFrom->username;
        $this->first_name = $respondentFrom->first_name;
        $this->last_name = $respondentFrom->last_name;
        $this->city = $respondentFrom->city;
        $this->email = $respondentFrom->email;
        $this->phone = $respondentFrom->phone;
        $this->full_years = $respondentFrom->full_years;
        $this->birth_date = $respondentFrom->birth_date;
        $this->sex = $respondentFrom->sex;
        $this->children = $respondentFrom->children;
        $this->family_status = $respondentFrom->family_status;
        $this->revenue = $respondentFrom->revenue;
        $this->working = $respondentFrom->working;
        $this->education = $respondentFrom->education;
        $this->type_social_auth = $respondentFrom->type_social_auth;

        return $this;
    }

    public function isActiveTelegram()
    {
        return true;
    }

    public function isActiveFacebook()
    {
        return false;
    }

    // Сюда добавляем фильтры для работы с аудиториями
    // Так же обязательно дублируем данные фильтра в БД в таблицу auditories_filters

    /**
     * Фильтр по наличию электронной почты
     *
     * @param $query
     * @return mixed
     */
    public function scopeHasEmail($query)
    {
        return $query->where('email', '!=', '')
            ->where('email', '!=', ' ')
            ->whereNotNull('email');

    }

    /**
     * Фильтр по отсутствию электронной почты
     *
     * @param $query
     * @return mixed
     */
    public function scopeNotHasEmail($query)
    {
        return $query->where('email', '=', '')
            ->orWhere('email', '=', ' ')
            ->orWhereNull('email');
    }

    /**
     * Фильтр по наличию телефона
     *
     * @param $query
     * @return mixed
     */
    public function scopeHasPhone($query)
    {
        return $query->where('phone', '!=', '')
            ->where('phone', '!=', ' ')
            ->whereNotNull('phone');
    }

    /**
     * Фильтр по отсутствию телефона
     *
     * @param $query
     * @return mixed
     */
    public function scopeNotHasPhone($query)
    {
        return $query->where('phone', '=', '')
            ->orWhere('phone', '=', ' ')
            ->orWhereNull('phone');

    }

    /**
     * Фильтр по авторизации в ВК
     *
     * @param $query
     * @return mixed
     */
    public function scopeHasAuthVk($query)
    {
        return $query->where('type_social_auth', '!=', '')
            ->where('type_social_auth', '!=', ' ')
            ->where('type_social_auth', '=', 'vk')
            ->whereNotNull('type_social_auth');
    }

    /**
     * Фильтр по отсутвию авторизации в ВК
     *
     * @param $query
     * @return mixed
     */
    public function scopeNotHasAuthVk($query)
    {
        return $query->whereNull('type_social_auth')
            ->orWhere('type_social_auth', '=', '')
            ->orWhere('type_social_auth', '=', ' ');

    }

    /**
     * Фильтр по авторизации в Facebook
     *
     * @param $query
     * @return mixed
     */
    public function scopeHasAuthFb($query)
    {
        return $query->where('type_social_auth', '!=', '')
            ->where('type_social_auth', '!=', ' ')
            ->where('type_social_auth', '=', 'fb')
            ->whereNotNull('type_social_auth');
    }

    /**
     * Фильтр по отсутвию авторизации в Facebook
     *
     * @param $query
     * @return mixed
     */
    public function scopeNotHasAuthFb($query)
    {
        return $query->whereNull('type_social_auth')
            ->orWhere('type_social_auth', '=', '')
            ->orWhere('type_social_auth', '=', ' ');

    }

    /**
     * Фильтрует по факту прохождения переданного опроса с условиями (==, !=)
     *
     * @param $query
     * @param $argument
     * @param Condition $condition
     * @return mixed
     */
    public function scopeSurveyCompleted($query, $argument, Condition $condition)
    {
        $surveyId = $argument;
        $survey = Survey::find($surveyId);

        if (is_null($survey)) {
            throw new Exception('No find "survey" in scopeSurveyCompleted');
        }

        $surveyId = $survey->id;
        $condition = $condition->code;

        if ($condition == '==') {
            return $query->whereHas('surveys', function ($query) use ($surveyId) {
                $query->where('respondent_survey.completed', '=', true)
                    ->where('respondent_survey.survey_id', '=', $surveyId);
            });
        } elseif ($condition == '!=') {
            return $query->whereHas('surveys', function ($query) use ($surveyId) {
                $query->where('respondent_survey.completed', '=', true)
                    ->where('respondent_survey.survey_id', '!=', $surveyId);
            });
        }
    }

    /**
     * Фильтрует по факту непрохождения переданного опроса с условиями (==, !=)
     *
     * @param $query
     * @param $argument
     * @param Condition $condition
     * @return mixed
     */
    public function scopeSurveyNotCompleted($query, $argument, Condition $condition)
    {
        $surveyId = $argument;
        $survey = Survey::find($surveyId);

        if (is_null($survey)) {
            throw new Exception('No find "survey" in scopeSurveyCompleted');
        }

        $surveyId = $survey->id;
        $condition = $condition->code;

        if ($condition == '==') {
            return $query->whereHas('surveys', function ($query) use ($surveyId) {
                $query->where('respondent_survey.completed', '=', false)
                    ->where('respondent_survey.survey_id', '=', $surveyId);
            });
        } elseif ($condition == '!=') {
            return $query->whereHas('surveys', function ($query) use ($surveyId) {
                $query->where('respondent_survey.completed', '=', false)
                    ->where('respondent_survey.survey_id', '!=', $surveyId);
            });
        }
    }

    /**
     * Фильтрует по номеру телефона с условиями (==, !=)
     *
     * @param $query
     * @param $argument
     * @param Condition $condition
     * @return mixed
     */
    public function scopePhoneCondition($query, $argument, Condition $condition)
    {
        $phone = $argument;
        $condition = $condition->code;

        if ($condition == '==') {
            return $query->where('phone', '=', $phone);
        } elseif ($condition == '!=') {
            return $query->where('phone', '!=', $phone);
        }

        return $query;
    }

    // todo подумать над реализацией
    public function scopeHasSocialShare($query, $has)
    {
        return $query;
    }

    /**
     * Фильтрует респондентов по вопросу и ответу которые указаны в вопросе галочка
     *
     * @param $query
     * @param array $argument (пример: ['answersId' => [158, 157], 'questionId' => 50, 'surveyId' => 5])
     * @param Condition $condition
     * @return mixed
     */
    public function scopeChoiceQuestion($query, $argument, Condition $condition)
    {
        $answersId = $argument['answersId'];

        $surveyId = $argument['surveyId'];
        $survey = Survey::find($surveyId);

        $questionId = $argument['questionId'];
        $question = Question::find($questionId);

        //достаем респондентов которые проходили переданный опрос
        $query = $query->whereHas('surveys', function ($query) use ($survey) {
            $query->where('survey_id', '=', $survey->id);
        });

        //достаем респондентов которые ответили на данный опрос как передано в параметре
        $query = $query->whereHas('answers', function ($query) use ($answersId, $question, $condition) {
            $query->where('question_id', '=', $question->id);

            $condition = $condition->code;
            if ($condition == '==') {
                $query->whereIn('answer_id', $answersId);
            } elseif ($condition == '!=') {
                $query->whereNotIn('answer_id', $answersId);
            }
        });

        return $query;
    }

    /**
     * Фильтрует респондентов по вопросу и ответу которые указаны в вопросе малитпл
     *
     * @param $query
     * @param array $argument (пример: ['answersId' => [158, 157], 'questionId' => 50, 'surveyId' => 5])
     * @param Condition $condition
     * @return mixed
     */
    public function scopeMultipleQuestion($query, $argument, Condition $condition)
    {
        $answersId = $argument['answersId'];

        $surveyId = $argument['surveyId'];
        $survey = Survey::find($surveyId);

        $questionId = $argument['questionId'];
        $question = Question::find($questionId);

        //достаем респондентов которые проходили переданный опрос
        $query = $query->whereHas('surveys', function ($query) use ($survey) {
            $query->where('survey_id', '=', $survey->id);
        });

        //достаем респондентов которые ответили на данный опрос как передано в параметре
        $query = $query->whereHas('answers', function ($query) use ($answersId, $question, $condition) {
            $query->where('question_id', '=', $question->id);

            $condition = $condition->code;
            if ($condition == '==') {
                $query->whereIn('answer_id',  $answersId);
            } elseif ($condition == '!=') {
                $query->whereNotIn('answer_id', $answersId);
            }
        });

        return $query;
    }

    /**
     * Фильтрует респондентов по вопросу и ответу которые указаны в вопросе свободный ответ
     *
     * @param $query
     * @param array $argument (пример: ['answersText' => ['На сайтах с кооооотиками', 'blogs.hbr.org'], 'questionId' => 62, 'surveyId' => 5])
     * @param Condition $condition
     * @return mixed
     */
    public function scopeFreeTextQuestion($query, $argument, Condition $condition)
    {
        $answersText = $argument['answersText'];

        $surveyId = $argument['surveyId'];
        $survey = Survey::find($surveyId);

        $questionId = $argument['questionId'];
        $question = Question::find($questionId);

        //достаем респондентов которые проходили переданный опрос
        $query = $query->whereHas('surveys', function ($query) use ($survey) {
            $query->where('survey_id', '=', $survey->id);
        });

        //достаем респондентов которые ответили на данный опрос как передано в параметре
        $query = $query->whereHas('answers', function ($query) use ($answersText, $question, $condition) {
            $query->where('question_id', '=', $question->id);

            $condition = $condition->code;
            if ($condition == '==') {
                $query->whereIn('answer_text', $answersText);
            } elseif ($condition == '!=') {
                $query->whereNotIn('answer_text', $answersText);
            }
        });

        return $query;
    }

    /**
     * Фильтрует респондентов по вопросу и ответу которые указаны в вопросе квест
     *
     * @param $query
     * @param array $argument (пример: ['answersText' => ['На сайтах с кооооотиками', 'blogs.hbr.org'], 'questionId' => 62, 'surveyId' => 5])
     * @param Condition $condition
     * @return mixed
     */
    public function scopeGuessQuestion($query, $argument, Condition $condition)
    {
        $answersText = $argument['answersText'];

        $surveyId = $argument['surveyId'];
        $survey = Survey::find($surveyId);

        $questionId = $argument['questionId'];
        $question = Question::find($questionId);

        //достаем респондентов которые проходили переданный опрос
        $query = $query->whereHas('surveys', function ($query) use ($survey) {
            $query->where('survey_id', '=', $survey->id);
        });

        //достаем респондентов которые ответили на данный опрос как передано в параметре
        $query = $query->whereHas('answers', function ($query) use ($answersText, $question, $condition) {
            $query->where('question_id', '=', $question->id);

            $condition = $condition->code;
            if ($condition == '==') {
                $query->whereIn('answer_text', $answersText);
            } elseif ($condition == '!=') {
                $query->whereNotIn('answer_text', $answersText);
            }
        });

        return $query;
    }
}
