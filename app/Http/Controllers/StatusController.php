<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
          'status' => 'OK',
          'app'    => config('app.name'),
          'env'    => config('app.env'),
          'time'   => now()->toIso8601String(),
        ]);
    }
}
