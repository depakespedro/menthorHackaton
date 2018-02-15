<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\SegmentRequest;
use App\Model\Condition;
use App\Models\Filter;
use App\Models\IntersectionSegment;
use App\Models\Project;
use App\Models\Respondent;
use App\Models\Segment;
use App\Models\User;
use App\Transformers\FilterTransformer;
use App\Transformers\RespondentTransformer;
use App\Transformers\SegmentTransformer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Throwable;

class SegmentsController extends Controller
{
    protected $user = null;

    public function __construct()
    {
        $this->middleware(['auth:api']);

        $this->user = \Auth::guard('api')->user();
    }

    public function index(Project $project)
    {
        $user = $this->user;

        //todo  сделать проверку что проект авторизованного пользователя
//        if ($user->id != $project->user_id){
//            return ['success' => false, 'error' => 'access denied'];
//        }

        $segments = $project->segments;

        return [
            'success' => true,
            'segments' => fractal()
                ->collection($segments)
                ->transformWith(new SegmentTransformer())
                ->toArray()
        ];
    }

    public function store(Project $project, Request $request)
    {


        DB::beginTransaction();

        try {
            $user = $this->user;

            $titleSegment = $request->get('title', null);

            if (is_null($titleSegment)) {
                return [
                    'success' => false,
                    'error' => 'empty title'
                ];
            }

            $filters = $request->get('filters', []);

            $segment = new Segment();
            $segment->title = $titleSegment;
            $segment->description = $titleSegment;
            $segment->project_id = $project->id;
            $segment->user_id = $user->id;
            $segment->save();

            foreach ($filters as $filter) {

                $filterModel = Filter::find($filter['filter']['id']);

                $condition = Condition::find($filter['condition']['id']);

                if (in_array($filter['filter']['scope'], ['choiceQuestion', 'multipleQuestion', 'freeTextQuestion', 'guessQuestion'])) {
                    if (in_array($filter['filter']['scope'], ['choiceQuestion', 'multipleQuestion'])) {
                        $rawArguments = json_decode($filter['argument']);

                        $arrayAnswers = [];
                        foreach ($rawArguments->answers as $answer) {
                            $arrayAnswers[] = $answer->id;
                        }

                        $argument = [
                            'answersId' => $arrayAnswers,
                            'questionId' => $rawArguments->question->id,
                            'surveyId' => $rawArguments->survey->id,
                        ];
                    } elseif (in_array($filter['filter']['scope'], ['freeTextQuestion', 'guessQuestion'])) {
                        $rawArguments = json_decode($filter['argument']);

                        $arguments = trim($rawArguments->answers);
                        $arguments = explode(',', $arguments);

                        $arrayAnswers = [];
                        foreach ($arguments as $argument) {
                            $arrayAnswers[] = trim($argument);
                        }

                        $argument = [
                            'answersText' => $arrayAnswers,
                            'questionId' => $rawArguments->question->id,
                            'surveyId' => $rawArguments->survey->id,
                        ];
                    }

                    $argument = serialize($argument);

                } else {
                    $argument = $filter['argument'];
                }

                $segment->attachFilter($filterModel, $argument, $condition);
            }

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();

            return [
                'success' => false,
                'comment' => 'segment error',
            ];
        } catch (Throwable $exception) {
            DB::rollBack();

            return [
                'success' => false,
                'comment' => 'segment error',
            ];
        }

        return [
            'success' => true,
            'comment' => 'segment created',
        ];
    }

    public function delete(Segment $segment)
    {
        $user = $this->user;

        if (is_null($segment)) {
            return [
                'success' => false,
                'comment' => 'segment no find',
            ];
        }

        if ($user->id != $segment->user_id) {
            return [
                'success' => false,
                'comment' => 'access denied',
            ];
        }

        $segment->detachAllFilters();

        $segment->delete();

        return [
            'success' => true,
            'comment' => 'segment deleted',
        ];
    }

    public function respondents(Project $project, Segment $segment)
    {
        $user = auth()->user();

        //todo  сделать проверку что проект авторизованного пользователя
//        if ($user->id != $project->user_id){
//            return ['success' => false, 'error' => 'access denied'];
//        }

        if (is_null($project)) {
            return ['success' => false, 'error' => 'no project'];
        }

        if (is_null($segment)) {
            return ['success' => false, 'error' => 'no segment'];
        }

        //достаем опросы авториованного юзера
        $surveys = $project->surveys->pluck('id');

        //достаем респондентов данных опросов
        $respondents = Respondent::whereHas('surveys', function ($query) use ($surveys) {
            $query->whereIn('survey_id', $surveys);
        });

        //фильтруем респондентов по указанному сегменту
        $respondents = $segment->filterRespondents($respondents);
        $respondents = $respondents->get();

        return [
            'success' => true,
            'respondents' => fractal()
                ->collection($respondents)
                ->transformWith(new RespondentTransformer())
                ->toArray()
        ];
    }

    public function fullSurveys(Project $project, $scope)
    {
        if ($scope == 'multipleQuestion') {
            $type = 'multiple_choice';
        } elseif ($scope == 'choiceQuestion') {
            $type = 'choice';
        } elseif ($scope == 'freeTextQuestion') {
            $type = 'short_free_text';
        } elseif ($scope == 'guessQuestion') {
            $type = 'guess';
        }

        $surveys = $project->surveys;

        $arraySurveys = [];
        foreach ($surveys as $survey) {
            $questions = $survey->questions()->where('type', '=', $type)->get();

            $arrayQuestions = [];
            foreach ($questions as $question) {
                $answers = $question->answers()->isVisible(true)->get();

                $arrayAnswers = [];
                foreach ($answers as $answer) {
                    $arrayAnswers[] = [
                        'id' => $answer->id,
                        'answer_text' => $answer->answer_text,
                    ];
                }

                $arrayQuestions[] = [
                    'id' => $question->id,
                    'title' => $question->title,
                    'answers' => $arrayAnswers,
                ];
            }

            $arraySurveys[] = [
                'id' => $survey->id,
                'title' => $survey->title,
                'questions' => $arrayQuestions,
            ];
        }

//        dd($arraySurveys);

        return [
            'success' => true,
            'surveys' => json_encode($arraySurveys),
        ];
    }

    public function intersectionsSegments(Project $project)
    {

        $intersectionsSegments = $project->intersectionsSegments->load('segmentOne', 'segmentTwo');

        return [
            'success' => true,
            'intersectionsSegments' => $intersectionsSegments,
        ];
    }

    public function createIntersectionsSegments(Project $project, Request $request)
    {
        $intersectionSegment = new IntersectionSegment();
        $intersectionSegment->title = $request->get('intersectionSegmentTitle');
        $intersectionSegment->segment_one_id = $request->get('segmentOne');
        $intersectionSegment->segment_two_id = $request->get('segmentTwo');
        $intersectionSegment->project_id = $project->id;
        $intersectionSegment->save();

        return [
            'success' => true,
        ];
    }

    public function deleteIntersectionSegment(IntersectionSegment $intersectionSegment)
    {
        $intersectionSegment->delete();
        return [
            'success' => true,
        ];
    }
}
