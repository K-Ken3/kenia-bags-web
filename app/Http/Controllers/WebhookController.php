<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        return response()->json([
            'message' => 'No signed live payment webhook is configured.',
        ], 503);
    }
}
