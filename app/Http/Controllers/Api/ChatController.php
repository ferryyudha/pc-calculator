<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(private ChatAssistantService $service) {}

    public function send(Request $request)
    {
        $request->validate([
            'message'           => 'required|string|max:500',
            'history'           => 'nullable|array',
            'internal_messages' => 'nullable|array',
        ]);

        $result = $this->service->chat(
            $request->input('message'),
            $request->input('history', []),
            $request->input('internal_messages', [])
        );

        return response()->json($result);
    }

    /**
     * Menerima laporan error dari frontend (React) dan mencatatnya ke chat_errors.log.
     */
    public function logFrontendError(Request $request)
    {
        $request->validate([
            'type'    => 'required|string|max:100',
            'message' => 'required|string|max:1000',
            'detail'  => 'nullable|string|max:2000',
            'url'     => 'nullable|string|max:500',
        ]);

        Log::channel('chat_errors')->error('[Frontend] ' . $request->input('type'), [
            'message' => $request->input('message'),
            'detail'  => $request->input('detail'),
            'url'     => $request->input('url'),
            'ip'      => $request->ip(),
        ]);

        return response()->json(['logged' => true]);
    }
}
