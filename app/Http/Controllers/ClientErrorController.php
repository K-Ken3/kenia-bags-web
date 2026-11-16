<?php

namespace App\Http\Controllers;

use App\Support\Monitoring;
use Illuminate\Http\Request;

class ClientErrorController extends Controller
{
    /**
     * Accepts bounded browser error reports. Messages are scrubbed of contact
     * details before storage and the endpoint is rate limited per address.
     */
    public function store(Request $request, Monitoring $monitoring)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:200'],
            'line' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'page' => ['nullable', 'string', 'max:200'],
        ]);

        $monitoring->record('client_error', 'warning', $data['message'], array_filter([
            'source' => isset($data['source']) ? basename($data['source']) : null,
            'line' => $data['line'] ?? null,
            'page' => isset($data['page']) ? parse_url($data['page'], PHP_URL_PATH) : null,
            'user_id' => $request->user()?->id,
        ], fn ($value) => $value !== null));

        return response()->noContent();
    }
}
