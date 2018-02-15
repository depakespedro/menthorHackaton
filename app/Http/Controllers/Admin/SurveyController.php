<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Http\Requests\SurveyForm;
use App\Models\Survey;
use Carbon\Carbon;

class SurveyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $surveys = Survey::orderBy('created_at', 'desc')->get();

        return view('admin.surveys.index')
            ->with('surveys', $surveys);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $survey = new Survey;

        // $this->authorize('resource', $survey);

        return view('admin.surveys.create')
            ->with('allSurveys', Survey::all())
            ->with('survey', $survey);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SurveyForm $request, Survey $survey)
    {
        // $this->authorize('resource', $survey);
        
        if ($request->copy_survey)
            return $this->copySurvey($request);

        Survey::create([
            'slug' => $request->slug,
            'title' => $request->title,
            'description' => $request->description,
            'welcome_text' => $request->welcome_text,
            'end_text' => $request->end_text,
            'end_url' => $request->end_url,
            'admin_name' => $request->admin_name,
            'admin_email' => $request->admin_email,
            // 'allow_registration' => $request->has('allow_registration'),
            'active' => $request->active,
            'is_research' => $request->is_research,
            'is_template' => $request->is_template,
            'default' => $request->default,
            'anonymized' => $request->has('anonymized'),
            'starts_at' => Carbon::createFromFormat('d-m-Y', $request->starts_at),
            'expires_at' => Carbon::createFromFormat('d-m-Y', $request->expires_at),
        ]);

        return redirect()->route('surveys.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Survey $survey)
    {
        $this->authorize('resource', $survey);

        return view('admin.surveys.show')
            ->with('groups', $survey->groups)
            ->with('survey', $survey);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Survey $survey)
    {
        $this->authorize('resource', $survey);

        return view('admin.surveys.edit')
            ->with('survey', $survey);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(SurveyForm $request, Survey $survey)
    {
        $this->authorize('resource', $survey);

        $survey->update([
            'slug' => $request->slug,
            'title' => $request->title,
            'description' => $request->description,
            'welcome_text' => $request->welcome_text,
            'end_text' => $request->end_text,
            'end_url' => $request->end_url,
            'admin_name' => $request->admin_name,
            'admin_email' => $request->admin_email,
            // 'allow_registration' => $request->has('allow_registration'),
            'active' => $request->active,
            'is_research' => $request->is_research,
            'is_template' => $request->is_template,
            'default' => $request->default,
            'anonymized' => $request->has('anonymized'),
            'starts_at' => Carbon::createFromFormat('d-m-Y', $request->starts_at),
            'expires_at' => Carbon::createFromFormat('d-m-Y', $request->expires_at),
        ]);

        return redirect()->route('surveys.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Survey $survey)
    {
        $this->authorize('resource', $survey);

        // $survey->forceDelete();
        $survey->delete();

        return redirect()->route('surveys.index');
    }

    /**
     * Run testing survey.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function test(Survey $survey, $step = null)
    {
        $this->authorize('resource', $survey);

        $step = (int)$step;

        // \Cache::flush();

        $cacheData = $survey->getTestCacheData();

        // dd($cacheData);

        if (!$step && $cacheData) 
            return response()->json('Error: Test already running', 410);
        if ($step > 1 && !$cacheData) 
            return response()->json('Error: Wrong data', 410);

        $question = $step ? $survey->nextQuestion($step, $cacheData) : null;

        return view('admin.surveys.test')
            ->with('step', $step)
            ->with('survey', $survey)
            ->with('question', $question);
    }

    public function  constructor(Survey $survey)
    {
        return view('admin.surveys.constructor')
            ->with('survey', $survey);
    }

    public function copySurvey(Request $request)
    {
        $stamp = time();

        $survey = Survey::findOrFail($request->copy_survey);

        $dateNow = Carbon::now()->format('d-m H:i:s');
        $startsAt = $request->starts_at ?: $survey->starts_at;
        $expiresAt = $request->expires_at ?: $survey->expires_at;

        // Копирование опроса в новый опрос
        $newSurvey = Survey::create([
            'title' => $request->title ?: $survey->title . ' (копия '. $dateNow .')',
            'slug' => $request->slug ?: $survey->slug . '-copy-' . $stamp,
            'description' => $request->description ?: $survey->description,
            'welcome_text' => $request->welcome_text ?: $survey->welcome_text,
            'end_text' => $request->end_text ?: $survey->end_text,
            'end_url' => $request->end_url ?: $survey->end_url,
            'admin_name' => $request->admin_name ?: $survey->admin_name,
            'admin_email' => $request->admin_email ?: $survey->admin_email,
            'active' => $request->active ?: $survey->active,
            'default' => $request->default ?: $survey->default,
            'anonymized' => $request->has('anonymized'),
            'starts_at' => Carbon::createFromFormat('d-m-Y', $startsAt),
            'expires_at' => Carbon::createFromFormat('d-m-Y', $expiresAt),
        ]);

        // Копирование групп (веток опроса) в новый опрос
        $oldNewGroupsIds = [];
        $oldNewQuestionsIds = [];
        foreach ($survey->groups as $group) {
            
            $newGroup = $group->replicate(['survey_id', 'created_at', 'updated_at']);
            $newGroup->slug = $group->slug . '-' . $stamp;
            $newGroup = $newSurvey->groups()->create($newGroup->toArray());

            $oldNewGroupsIds[$group->id] = $newGroup->id;
            if ($group->next_group_id)
                $newGroup->update(['next_group_id' => $oldNewGroupsIds[$group->next_group_id]]);

            if (!$group->questions->count())
                continue;

            // Копирование вопросов в новый опрос
            foreach ($group->questions as $question) {
                $newQuestion = $question->replicate(['group_id', 'created_at', 'updated_at']);
                $newQuestion = $newGroup->questions()->create($newQuestion->toArray());

                $oldNewQuestionsIds[$question->id] = $newQuestion->id;
                if ($question->parent_question_id)
                    $newQuestion->update(['parent_question_id' => $oldNewQuestionsIds[$question->parent_question_id]]);         
            }
        }

        // Копирование ответов в новый вопрос
        foreach ($survey->groups as $group) {
            if (!$group->questions->count())
                continue;

            foreach ($group->questions as $question) {
                $answers = $question->answersVisible();
                if (!$answers->count())
                    continue;

                $newQuestion = \App\Models\Question::findOrFail($oldNewQuestionsIds[$question->id]);

                // Копирование ответов в новый вопрос
                foreach ($answers as $answer) {
                    $newAnswer = $answer->replicate(['question_id', 'created_at', 'updated_at']);
                    $newAnswer = $newQuestion->answers()->create($newAnswer->toArray());

                    if ($answer->next_question)
                        $newAnswer->update(['next_question' => $oldNewQuestionsIds[$answer->next_question]]);
                }
            }
            
        }

        return redirect()->route('surveys.index');
    }

}
