<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Events\SendMessage;
use App\Services\LLMService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessage
{
    /**
     * Create the event listener.
     */
    public function __construct(protected LLMService $llm) {}


    public function handle(SendMessage $event): void
    {
        $userId = $event->userId;
        $message = $event->message;

        $user = \App\Models\User::find($userId);
        if (!$user) return;

        $analysis = $this->llm->analyze($message);
        $intent = $analysis['intent'];
        $parameters = $analysis['parameters'];

        $token = $user->currentAccessToken()?->plainTextToken ?? null;
        if (!$token) {
            broadcast(new MessageReceived("Please log in first.", $userId));
            return;
        }

        // App A'ya istek
        $response = match ($intent) {
            'query_bill' => $this->callGsmApp('/api/v1/bill/query', [
                'subscriber_no' => $user->id,
                'month' => $parameters['month'],
                'year' => $parameters['year']
            ], $token),

            'query_bill_detailed' => $this->callGsmApp('/api/v1/bill/query-detailed', [
                'subscriber_no' => $user->id,
                'month' => $parameters['month'],
                'year' => $parameters['year'],
                'page' => 1
            ], $token),

            'make_payment' => $this->callGsmApp('/api/v1/bill/pay', [
                'month' => $parameters['month'],
                'year' => $parameters['year'],
                'amount' => $parameters['amount']
            ], $token),

            default => null
        };

        $data = $response?->json() ?? [];
        $statusCode = $response?->status() ?? 500;

        $final = $statusCode === 200
            ? $this->llm->generateReply($intent, $parameters, $data)
            : $this->llm->generateReplyFromError('api_error', ['message' => $data['message'] ?? 'Error']);

        broadcast(new MessageReceived($final, $userId));
    }

    private function callGsmApp(string $path, array $data, string $token)
    {
        $url = config('services.app_a.base_url') . $path;
        return Http::withToken($token)->acceptJson()->post($url, $data);
    }

}
