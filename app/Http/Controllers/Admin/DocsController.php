<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests;

class DocsController extends Controller
{
    public function index()
    {
    	return view('admin.docs.index');
    }
}

