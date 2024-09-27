<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class IcoreController extends Controller
{
    public function icore(Request $request)
    {
        return view('icore'); // Icore
    }
}
