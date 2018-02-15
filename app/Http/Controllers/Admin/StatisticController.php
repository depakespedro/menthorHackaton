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

class StatisticController extends Controller
{
	protected $app;
	protected $excel;

	public function __construct(Application $app, Excel $excel)
	{
		$this->app = $app;
		$this->excel = $excel;
	}

	public function index()
	{
		$surveys = Survey::orderBy('created_at', 'desc')->get();

		return view('admin.statistics.index', compact('surveys'));
	}

	public function export(Survey $survey)
	{
	    $statisticsManager = app(StatisticsContract::class);
		return $statisticsManager->exportExcelOldVersion($survey);
	}

	public function destroy(Survey $survey)
	{
		foreach ($survey->respondents as $respondent) {
			$respondent->answers()->wherePivot('survey_id', $survey->id)->detach();
		}

		$survey->respondents()->detach();

		return redirect()->back();
	}
}
