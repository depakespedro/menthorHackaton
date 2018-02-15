<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Http\Request;
use Request as RequestFacade;
use App\Http\Requests\AnswerForm;

class AnswerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Question $question)
    {
        $answers = $question->answersVisible();

        if (RequestFacade::ajax()) {
            return response()->json([
                'data' => $answers
            ], 200);
        }

        return view('admin.answers.index')
            ->with('question', $question)
            ->with('answers', $answers);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Question $question, AnswerForm $request)
    {
        $answer = $question->answers()->create([
            'next_question'  => $request->next_question,
            'value'  => $request->value,
            'code'  => $request->code,
            'width'  => $request->width,
            'answer_text' => $request->answer_text,
            'order'  => $request->order,
            'visible' => $request->visible,
        ]);

        return response()->json(['id' => $answer->id], 200);
    }

    /**
     * Update existing resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Question $question, Answer $answer, AnswerForm $request)
    {
        $answer->update([
            'next_question'  => $request->next_question,
            'value'  => $request->value,
            'code'  => $request->code,
            'width'  => $request->width,
            'answer_text' => $request->answer_text,
            'order'  => $request->order,
            'visible' => $request->visible,
        ]);

        return response()->json($answer->toArray(), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Question $question, Answer $answer)
    {
        // Answer::onlyTrashed()->forceDelete();
        $answer->forceDelete();

        return response()->json(true, 200);
    }
}
