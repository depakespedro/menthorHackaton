<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Menubutton;
use App\Models\Command;

class MenubuttonController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->menubuttons);

        foreach ($request->menubuttons as $mbutton) {
            if (!isset($mbutton['command_id']) || !$mbutton['command_id'] || !$mbutton['order'])
                continue;

            if (isset($mbutton['id']) && $mbutton['id']) {
                $button = Menubutton::findOrFail($mbutton['id']);
                $this->update($request, $button);
            } else {
                $command = Command::findOrFail($mbutton['command_id']);
                $command->buttons()->create([
                    'title' => $mbutton['title'],
                    'order' => $mbutton['order'],
                ]);
            }
        }

        return redirect()->back();
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
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Menubutton $button)
    {
        foreach ($request->menubuttons as $mbutton) {
            if ((int)$mbutton['id'] === (int)$button->id) {
                $button->update([
                    'title' => $mbutton['title'],
                    'command_id' => $mbutton['command_id'],
                    'order' => $mbutton['order'],
                ]);

                return true;
            }
        }

        return true;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $button = Menubutton::findOrFail($id);

        $button->delete();

        return response()->json('ok', 200);
    }
}
