<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use JeroenNoten\LaravelAdminLte\AdminLte;
use Illuminate\Http\Request;
use Lang;

class AuditoriesController extends Controller
{
    protected $adminlte;

    public function __construct(AdminLte $adminlte)
    {
        $this->middleware(['auth', 'locale']);

        $this->adminlte = $adminlte;
    }

    public function index()
    {
        return view('user.auditories.router')
            ->with('translations', Lang::get('client'))
            ->with('adminlte', $this->adminlte);
    }
}
