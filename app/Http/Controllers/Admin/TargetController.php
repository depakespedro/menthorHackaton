<?php

namespace App\Http\Controllers\Admin;

use App\Models\Survey;
use App\Models\Target;
// use Illuminate\Http\Request;
use App\Http\Requests\TargetForm;
use App\Http\Controllers\Controller;

class TargetController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Survey $survey)
    {
        return view('admin.targets.index')
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

        $target = new Target;

        return view('admin.targets.create')
            ->with('survey', $survey)
            ->with('target', $target);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(TargetForm $request, Survey $survey)
    {
        $target = $survey->targets()->create([
            'title' => $request->title,
            'description' => $request->description,
            'default' => $request->default,
        ]);

        if ($image = $request->image)
            $target->addImage($image);

        if ($request->qa && is_array($request->qa) && count($request->qa))
            $target->synchronizeQuestions($request);

        return redirect()->route('surveys.targets.index', [$survey->id]);
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
    public function edit(Survey $survey, Target $target)
    {
        // $this->authorize('resource', $survey);

        return view('admin.targets.edit')
            ->with('survey', $survey)
            ->with('target', $target);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(TargetForm $request, Survey $survey, Target $target)
    {
        // $this->authorize('resource', $survey);

        $target->update([
            'title' => $request->title,
            'description' => $request->description,
            'default' => $request->default,
        ]);

        if ($image = $request->image)
            $target->addImage($image);

        if ($request->qa && is_array($request->qa) && count($request->qa))
            $target->synchronizeQuestions($request);

        return redirect()->route('surveys.targets.index', [$survey->id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Survey $survey, Target $target)
    {
        // $this->authorize('resource', $survey);

        $target->delete();

        return redirect()->route('surveys.targets.index', [$survey->id]);
    }

    /**
     * Remove image
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deleteImage(Target $target)
    {
        $target->deleteImage();

        return response()->json([
            'success' => true,
        ], 200);
    }
}
