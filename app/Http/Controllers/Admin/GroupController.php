<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GroupForm;
use App\Models\Group;
use App\Models\Survey;

class GroupController extends Controller
{
    public function __construct()
    {
        // dd("est");
        // to middleware or not to middleware?
        // $this->middleware('group');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Survey $survey)
    {
        $groups = $survey->groups;
        
        return view('admin.groups.index')
            ->with('survey', $survey)
            ->with('groups', $groups);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Survey $survey)
    {
        $this->authorize('resource', $survey);

        $group = new Group;
        $group->order = $survey->groups->max('order') + 1;

        return view('admin.groups.create')
            ->with('survey', $survey)
            ->with('group', $group);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(GroupForm $request, Survey $survey)
    {
        $this->authorize('resource', $survey);

        $survey->groups()->create([
            // 'answer_id' => $request->answer_id,
            'next_group_id' => $request->next_group_id,
            'slug' => $request->slug,
            'title' => $request->title,
            'description' => $request->description,
            'order' => $request->order,
        ]);

        return redirect()->route('surveys.groups.index', [$survey->id]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Survey $survey, Group $group)
    {
        $this->authorize('resource', $survey);

        $group = $survey->groups()->where('groups.id', $group->id)->firstOrFail();
        
        return view('admin.groups.show')
            ->with('survey', $survey)
            ->with('group', $group);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Survey $survey, Group $group)
    {
        $this->authorize('resource', $survey);

        return view('admin.groups.edit')
            ->with('survey', $survey)
            ->with('group', $group);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(GroupForm $request, Survey $survey, Group $group)
    {
        $this->authorize('resource', $survey);

        $group->update([
            // 'answer_id' => $request->answer_id,
            'next_group_id' => $request->next_group_id,
            'slug' => $request->slug,
            'title' => $request->title,
            'description' => $request->description,
            'order' => $request->order,
        ]);

        return redirect()->route('surveys.groups.index', [$survey->id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Survey $survey, Group $group)
    {
        $this->authorize('resource', $survey);

        $group->forceDelete();

        return redirect()->route('surveys.groups.index', [$survey->id]);
    }

    public function reorder(Group $group)
    {
        $order = 0;
        foreach ($group->questions as $question) {
            $order++;
            $question->update(['order' => $order]);
        }

        return redirect()->route('groups.questions.index', [$group->id]);
    }
}
