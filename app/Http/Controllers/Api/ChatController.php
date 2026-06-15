<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatAssistantService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatAssistantService $service) {}

    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array',
        ]);

        $result = $this->service->chat(
            $request->input('message'),
            $request->input('history', [])
        );

        return response()->json($result);
    }
}
