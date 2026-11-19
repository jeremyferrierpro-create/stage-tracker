<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HelpController extends Controller
{
    /**
     * Guide d'utilisation et méthodologie DWWM.
     */
    public function index(): View
    {
        return view('help.index');
    }
}
