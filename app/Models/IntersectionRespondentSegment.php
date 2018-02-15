<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntersectionRespondentSegment extends Model
{
    protected $table = 'intersections_respondents_segments';

    public $timestamps = false;

    public function respondentOne()
    {
        return $this->hasOne(Respondent::class, 'id', 'respondent_one_id');
    }

    public function respondentTwo()
    {
        return $this->hasOne(Respondent::class, 'id', 'respondent_two_id');
    }

    public function project()
    {
        return $this->hasOne(Project::class);
    }

    // чекает наличие связи между респондентами
    static function checkIntersection(Respondent $respondentOne, Respondent $respondentTwo, Project $project)
    {
        $intersections = self::where('respondent_one_id', '=', $respondentOne->id)
            ->where('respondent_two_id', '=', $respondentTwo->id)
            ->where('project_id', '=', $project->id)
            ->get();

        if(!$intersections->isEmpty()){
            return true;
        }

        return false;
    }

    static function createIntersection(Respondent $respondentOne, Respondent $respondentTwo, Project $project)
    {
        $intersectionRespondentSegment = new IntersectionRespondentSegment();
        $intersectionRespondentSegment->respondent_one_id = $respondentOne->id;
        $intersectionRespondentSegment->respondent_two_id = $respondentTwo->id;
        $intersectionRespondentSegment->project_id = $project->id;
        $intersectionRespondentSegment->save();

        //добавляем обратную запись для того чтобы избежать отрицания при проверке связи
        $intersectionRespondentSegment = new IntersectionRespondentSegment();
        $intersectionRespondentSegment->respondent_one_id = $respondentTwo->id;
        $intersectionRespondentSegment->respondent_two_id = $respondentOne->id;
        $intersectionRespondentSegment->project_id = $project->id;
        $intersectionRespondentSegment->save();
    }
}
