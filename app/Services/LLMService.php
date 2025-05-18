<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LLMService
{
    public function analyze(string $message, array $context = []): array
    {
        $systemPrompt = <<<PROMPT
            You are an internal telecom assistant.

            You can handle only these operations:
            1. query_bill → to get a subscriber's basic monthly bill
            2. query_bill_detailed → to get a full breakdown of a subscriber's bill
            3. make_payment → to initiate a payment

            Your job is to:
            - Identify the user's intent based on their message.
            - Extract relevant parameters like subscriber number, month, and year.
            - If any required data is missing, leave the parameter as `null`.

            Valid parameters:
            - subscriber_id (string or number)
            - month (e.g., "March", "04", etc.)
            - year (e.g., "2025")
            - amount (number, only for `make_payment` intent)

            Respond strictly in this JSON format:
            {
            "intent": "query_bill" | "query_bill_detailed" | "make_payment" | "unsupported",
            "parameters": {
                "subscriber_id": "...",
                "month": "...",
                "year": "...",
                "amount": ...
            },
            "response": "..."
            }

            Always convert month to integer, such as January 1, February 2 etc.

            If the message is not related to any of the 3 operations, reply with:
            {
            "intent": "unsupported",
            "parameters": {},
            "response": "I'm sorry, I can only help with billing inquiries."
            }

            Examples:

            Input: "Show me the detailed bill for subscriber 123456, March 2025"
            → intent: "query_bill_detailed", subscriber_id: "123456", month: 3, year: "2025"

            Input: "How much is the bill for April 2025 for 98765?"
            → intent: "query_bill", subscriber_id: "98765", month: 4, year: "2025"

            Input: "Pay 250 for subscriber 321321 for Feb 2024"
            → intent: "make_payment", subscriber_id: "321321", amount: 250, month: 2, year: "2024"

            Input: "What time is my appointment?"
            → intent: "unsupported"
        PROMPT;


        $prompt = "{$systemPrompt}\n\nUser message: \"{$message}\"";

        $response = Http::post(config('services.ollama.base_url') . '/api/generate', [
            'model' => config('services.ollama.model'),
            'prompt' => $prompt,
            'stream' => false,
        ]);

        if ($response->failed()) {
            throw new \Exception("LLM request failed: " . $response->body());
        }

        return json_decode($response->json()['response'], true);
    }

    public function generateReply(string $intent, array $parameters, array $data): string
    {
        $subscriberId = $parameters['subscriber_id'] ?? 'N/A';
        $month = $parameters['month'] ?? 'N/A';
        $year = $parameters['year'] ?? 'N/A';
        $amount = $parameters['amount'] ?? 'N/A';
        $jsonData = $this->prettyPrint($data);

        $prompt = <<<PROMPT
            You're a telecom assistant AI.

            The user intent is: {$intent}

            Here are the relevant parameters:
            - subscriber_id: {$subscriberId}
            - month: {$month}
            - year: {$year}
            - amount: {$amount}

            Here is the response from the internal system (JSON):
            {$jsonData}

            Write a helpful and polite one-paragraph summary in natural language based on this data.
        PROMPT;


        $response = Http::post(config('services.ollama.base_url') . '/api/generate', [
            'model' => config('services.ollama.model'),
            'prompt' => $prompt,
            'stream' => false,
        ]);

        if ($response->failed()) {
            return 'Something went wrong while generating response.';
        }

        return trim($response->json()['response'] ?? '');
    }

    protected function prettyPrint(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function generateReplyFromError(string $type, array $context): string
    {
        $message = $context['message'] ?? 'An error occurred.';
        $data = $context['data'] ?? [];

        $prompt = <<<PROMPT
            You're an AI assistant for a telecom company.

            An error occurred while processing the user's request.
            Error Type: {$type}
            Error Message: {$message}
            Error Data: {$this->prettyPrint($data)}

            Write a clear, short, polite explanation to the user. Company name is XellPay also act like you are live chat assistant. Also use HTML tags to make message more readable such as b, br, etc.
        PROMPT;

        $response = Http::post(config('services.ollama.base_url') . '/api/generate', [
            'model' => config('services.ollama.model'),
            'prompt' => $prompt,
            'stream' => false,
        ]);

        return trim($response->json()['response'] ?? 'Sorry, something went wrong.');
    }
}
