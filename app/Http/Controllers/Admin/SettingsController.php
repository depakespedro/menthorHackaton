<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Menubutton;
use App\Models\Command;

class SettingsController extends Controller
{
    public function index()
    {
    	return view('admin.settings.index')
    		->with('menubuttons', Menubutton::orderBy('order')->get())
    		->with('commands', Command::all());
    }
}
