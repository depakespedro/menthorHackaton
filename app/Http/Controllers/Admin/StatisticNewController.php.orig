<?php

namespace App\Http\Controllers\Admin;

use App\Services\Contracts\StatisticsContract;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\Respondent;
use App\Export\SurveyStatisticExport;

use Illuminate\Foundation\Application;
use Maatwebsite\Excel\Excel;
use Carbon\Carbon;

class StatisticNewController extends Controller
{
    protected $statisticsManager;

    public function __construct(StatisticsContract $statistics)
    {
        $this->statisticsManager = $statistics;
    }

    public function export(Survey $survey)
	{
        $this->statisticsManager->exportExcel($survey);
        return response('ok', 200);
	}
}
