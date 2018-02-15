<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionForm;
use App\Models\Group;
use App\Models\Question;

class QuestionController extends Controller
{
    public function index(Group $group)
    {
        $questions = $group->questions;

        return view('admin.questions.index')
            ->with('survey', $group->survey()->firstOrFail())
            ->with('group', $group)
            ->with('questions', $questions);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Group $group)
    {
        $question = new Question;
        $prevQuestion = $group->questions()->where('order', $group->questions->max('order'))->first();

        $question->order = $group->questions->max('order') + 1;

        return view('admin.questions.create')
            ->with('survey', $group->survey()->firstOrFail())
            ->with('group', $group)
            ->with('question', $question)
            ->with('prevQuestion', $prevQuestion);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Group $group, QuestionForm $request)
    {
        $question = $group->questions()->create([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'order' => $request->order,
            'mandatory' => $request->has('mandatory'),
            'delay' => $request->delay,
        ]);

        if ($image = $request->image)
            $question->addImage($image);

        if ($request->prev_question)
            $group->reorderQuestions($question, $request->prev_question);

        return redirect()->route('groups.questions.index', [$group->id]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Group $group, Question $question)
    {
        $question = $group->questions()->where('questions.id', $question->id)->firstOrFail();

        return view('admin.questions.show')
            ->with('survey', $group->survey()->firstOrFail())
            ->with('group', $group)
            ->with('question', $question);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Group $group, Question $question)
    {
        $prevQuestion = $group->questions()->where('order', $question->order - 1)->first();

        return view('admin.questions.edit')
            ->with('survey', $group->survey()->firstOrFail())
            ->with('group', $group)
            ->with('question', $question)
            ->with('prevQuestion', $prevQuestion);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Group $group, Question $question, QuestionForm $request)
    {
        $question->update([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'order' => $request->order,
            'mandatory' => $request->has('mandatory'),
            'delay' => $request->delay,
            'url_social_network' => $request->url_social_network,
        ]);

        if ($image = $request->image)
            $question->addImage($image);

        if ($request->prev_question)
            $group->reorderQuestions($question, $request->prev_question);

        return redirect()->route('groups.questions.index', $group->id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Group $group, Question $question)
    {
        $question->forceDelete();

        $order = 0;
        foreach ($group->questions as $question) {
            $order++;
            $question->update(['order' => $order]);
        }

        return redirect()->route('groups.questions.index', [$group->id]);
    }

    /**
     * Remove image
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deleteImage(Question $question)
    {
        $question->deleteImage();

        return response()->json([
            'success' => true,
        ], 200);
    }
}
