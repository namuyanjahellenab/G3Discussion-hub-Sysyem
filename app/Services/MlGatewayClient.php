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
    public function recommend(int $userId, array $interests = [], array $recentMessages = [], array $groupIds = []): ?array
    {
        $response = $this->post('/recommend', [
            'UserID' => $userId,
            'Interests' => array_values($interests),
            'RecentMessages' => array_values($recentMessages),
            'GroupIDs' => array_values($groupIds),
        ]);

        return $response;
    }

    /**
     * Rank groups the user hasn't joined by member count and recent activity,
     * for cold-start members who don't have enough signal for topic matching.
     * Returns null if the gateway is unreachable or misconfigured.
     */
    public function recommendGroups(int $userId, array $excludeGroupIds = []): ?array
    {
        $response = $this->post('/recommend-groups', [
            'UserID' => $userId,
            'GroupIDs' => array_values($excludeGroupIds),
        ]);

        return $response;
    }

    /**
     * Whether a piece of text trips the gateway's spam-keyword detection.
     * Fails open: unreachable/misconfigured gateway means "not spam" rather
     * than blocking or flagging content based on an absent signal.
     */
    public function isSpam(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        $result = $this->classify($text);

        return (bool) ($result['IsFiltered'] ?? false);
    }

    /**
     * Render a topic + its posts/replies to a PDF, generated entirely by the
     * Python gateway (xhtml2pdf) - there is no PHP/Dompdf fallback for this by
     * design. Returns the raw PDF bytes, or null if the gateway is unreachable
     * or the topic couldn't be exported.
     */
    public function exportTopicPdf(int $topicId): ?string
    {
        $baseUrl = config('services.ml_gateway.url');
        $token = config('services.ml_gateway.token');

        if (!$baseUrl || !$token) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('services.ml_gateway.timeout', 3))
                ->post(rtrim($baseUrl, '/') . '/export-topic-pdf', [
                    'TopicID' => $topicId,
                ]);

            if ($response->failed()) {
                Log::warning('ML gateway /export-topic-pdf request failed', ['status' => $response->status()]);
                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning("ML gateway /export-topic-pdf request errored: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Build a canonical share URL, pre-filled text, and per-platform links for
     * a topic. Returns null if the gateway is unreachable.
     */
    public function topicShareLinks(int $topicId, string $baseUrl): ?array
    {
        return $this->post('/topic-share-links', [
            'TopicID' => $topicId,
            'BaseUrl' => $baseUrl,
        ]);
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
