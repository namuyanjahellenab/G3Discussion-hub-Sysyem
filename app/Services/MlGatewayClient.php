<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MlGatewayClient
{
    /**
     * Classify a piece of text (e.g. a topic title) into a category.
     * Returns null if the gateway is unreachable or misconfigured — callers
     * must fall back gracefully since this is a separate Python process.
     */
    public function classify(string $text, ?int $referenceId = null): ?array
    {
        $response = $this->post('/classify', [
            'MessageText' => $text,
            'MessageID' => $referenceId,
        ]);

        return $response;
    }

    /**
     * Rank topic categories by relevance for a user's interests/recent activity.
     * Returns null if the gateway is unreachable or misconfigured.
     */
    public function recommend(int $userId, array $interests = [], array $recentMessages = []): ?array
    {
        $response = $this->post('/recommend', [
            'UserID' => $userId,
            'Interests' => array_values($interests),
            'RecentMessages' => array_values($recentMessages),
        ]);

        return $response;
    }

    private function post(string $path, array $payload): ?array
    {
        $baseUrl = config('services.ml_gateway.url');
        $token = config('services.ml_gateway.token');

        if (!$baseUrl || !$token) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('services.ml_gateway.timeout', 3))
                ->post(rtrim($baseUrl, '/') . $path, $payload);

            if ($response->failed()) {
                Log::warning("ML gateway {$path} request failed", ['status' => $response->status()]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning("ML gateway {$path} request errored: {$e->getMessage()}");
            return null;
        }
    }
}
