<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\MessageReceived;
use App\Http\Controllers\Controller;
use App\Services\LLMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiMessageController extends Controller
{

    public function __construct(public readonly LLMService $llm) {}

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();
        $text = $request->input('message');
        $token = $request->bearerToken();

        $analysis = $this->llm->analyze($text);
        $intent = $analysis['intent'];
        $parameters = $analysis['parameters'];

        $response = match ($intent) {
            'query_bill' => $this->callGsmApp('/api/v1/bill/query', [
                'subscriber_no' => $user->id,
                'month' => $parameters['month'],
                'year' => $parameters['year'],
            ], $token),

            'query_bill_detailed' => $this->callGsmApp('/api/v1/bill/query-detailed', [
                'subscriber_no' => $user->id,
                'month' => $parameters['month'],
                'year' => $parameters['year'],
                'page' => 1,
            ], $token),

            'make_payment' => $this->callGsmApp('/api/v1/bill/pay', [
                'month' => $parameters['month'],
                'year' => $parameters['year'],
                'amount' => $parameters['amount'],
            ], $token),

            default => null,
        };
        if ($response == null) {
            $reply = $this->llm->generateReply($analysis['intent'], $parameters, $analysis);
        } else {
            $data = $response?->json() ?? [];
            $statusCode = $response?->status();

            $reply = $statusCode === 200
                ? $this->llm->generateReply($intent, $parameters, $data)
                : $this->llm->generateReplyFromError('api_error', [
                    'message' => $data['message'] ?? 'Unexpected error',
                ]);
        }

        broadcast(new MessageReceived($reply, $user->id))->toOthers();
        return response()->json(['sent' => true]);
    }


    protected function callGsmApp(string $path, array $data, string $token)
    {
        $url = rtrim(config('services.gsm_app.base_url'), '/') . '/' . ltrim($path, '/');

        return Http::withToken($token)
            ->acceptJson()
            ->post($url, $data);
    }
}
