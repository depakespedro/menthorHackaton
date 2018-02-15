<?php

namespace App\Console\Commands;

use App\Models\IntersectionRespondentSegment;
use App\Models\IntersectionSegment;
use App\Models\Respondent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LinkedRespondentsSegments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linkedrespondentssegments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'linked respondents segments';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        telegramLog('Started cron = linkedrespondentssegments' . print_r('1', true));

        //достаем пересечения
        $intersectionsSegments = IntersectionSegment::all();

        //бежим по пересечениям
        foreach ($intersectionsSegments as $intersectionSegment) {

            $projectIntersectionSegment = $intersectionSegment->project;

            //достаем всех респондентов подходящих под проект данного пересечения
            $respondents = Respondent::whereHas('surveys', function($query) use ($projectIntersectionSegment) {
                $query->whereHas('project', function ($query) use ($projectIntersectionSegment) {
                    $query->where('id', '=', $projectIntersectionSegment->id);
                });
            });

            //достаем сегменты
            $segmentOne = $intersectionSegment->segmentOne;
            $segmentTwo = $intersectionSegment->segmentTwo;

            //достаем респондентов данных сегментов
            $respondentsSegmentOne = $segmentOne->filterRespondents($respondents)->get();
            $respondentsSegmentTwo = $segmentTwo->filterRespondents($respondents)->get();

            //бежим по респондентам первого сегмента
            foreach ($respondentsSegmentOne as $respondentSegmentOne) {
                //бежим по респонлентам воторго сегмента
                foreach ($respondentsSegmentTwo as $respondentSegmentTwo) {

                    try {
                        //линкуем двух респондентов, с проверкой что два респондента не являются одним и проверяем наличие связи
                        $status = false;
                        $link = IntersectionRespondentSegment::checkIntersection($respondentSegmentOne, $respondentSegmentTwo, $projectIntersectionSegment);
                        if(($respondentSegmentOne->id != $respondentSegmentTwo->id) and !$link) {
                            $status = notifySimilarRespondents($respondentSegmentOne, $respondentSegmentTwo);
                        }

                        //если линкование рпошло успешно, делаем пометку что эти два респондента линковались в области данного проекта
                        if($status) {
                            IntersectionRespondentSegment::createIntersection($respondentSegmentOne, $respondentSegmentTwo, $projectIntersectionSegment);
                        }
                    } catch (\Exception $exception) {
                        Log::error('LinkedRespondentsSegments erro : resp1=' . $respondentSegmentOne->id . ' resp2=' . $respondentSegmentTwo->id . ' proj=' . $projectIntersectionSegment->id);
                        continue;
                    }

                }
            }
        }
    }
}
