<?php

namespace App\Http\Controllers\Admin;

use App\Models\Survey;
use App\Models\Bonus;
// use Illuminate\Http\Request;
use App\Http\Requests\BonusForm;
use App\Http\Controllers\Controller;

class BonusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Survey $survey)
    {
        return view('admin.bonuses.index')
            ->with('survey', $survey);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Survey $survey)
    {
        // $this->authorize('resource', $survey);

        $bonus = new Bonus;

        return view('admin.bonuses.create')
            ->with('survey', $survey)
            ->with('bonus', $bonus);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(BonusForm $request, Survey $survey)
    {
        $bonus = $survey->bonuses()->create([
            'title' => $request->title,
            'description' => $request->description,
        ]);

        // if ($image = $request->image)
        //     $bonus->addImage($image);

        return redirect()->route('surveys.bonuses.index', [$survey->id]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Survey $survey, Bonus $bonus)
    {
        // $this->authorize('resource', $survey);

        return view('admin.bonuses.edit')
            ->with('survey', $survey)
            ->with('bonus', $bonus);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(BonusForm $request, Survey $survey, Bonus $bonus)
    {
        // $this->authorize('resource', $survey);

        $bonus->update([
            'title' => $request->title,
            'description' => $request->description,
        ]);

        // if ($image = $request->image)
        //     $bonus->addImage($image);

        return redirect()->route('surveys.bonuses.index', [$survey->id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Survey $survey, Bonus $bonus)
    {
        // $this->authorize('resource', $survey);

        $bonus->delete();

        return redirect()->route('surveys.bonuses.index', [$survey->id]);
    }

    /**
     * Remove image
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deleteImage(Bonus $bonus)
    {
        $bonus->deleteImage();

        return response()->json([
            'success' => true,
        ], 200);
    }
}
