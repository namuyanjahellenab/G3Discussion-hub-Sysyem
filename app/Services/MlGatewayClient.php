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
    public function classify(string $text, ?int $referenceId = null, ?string $context = null): ?array
    {
        $response = $this->post('/classify', [
            'MessageText' => $text,
            'MessageID' => $referenceId,
            'Context' => $context,
        ]);

        return $response;
    }

    /**
     * Rank specific candidate topics for a user by relevance. Each topic's
     * own title is scored individually against interests/recent activity,
     * instead of every topic in a category collapsing onto one identical
     * score. Returns null if the gateway is unreachable.
     *
     * @param array<int, array{TopicID: int, Title: ?string, Category: ?string}> $topics
     */
    public function recommendTopics(int $userId, array $interests, array $recentMessages, array $topics): ?array
    {
        return $this->post('/recommend-topics', [
            'UserID' => $userId,
            'Interests' => array_values($interests),
            'RecentMessages' => array_values($recentMessages),
            'Topics' => array_values($topics),
        ]);
    }

    /**
     * Every group ranked by member count + weighted recent activity, joined
     * or not - an objective "what's popular right now" signal, not a
     * personalized "you haven't joined this" suggestion.
     * Returns null if the gateway is unreachable or misconfigured.
     */
    public function trendingGroups(): ?array
    {
        return $this->post('/trending-groups', []);
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
     * Classify a piece of text for both spam and educational relevance in a
     * single gateway round trip (avoids calling /classify twice per post).
     * Pass $context (e.g. the topic + original question) to judge a reply's
     * relevance against that specific thread rather than generic "is this
     * educational" — otherwise the general course-relevance check is used.
     * Fails open: unreachable/misconfigured gateway means "not spam" and
     * "educational" — content is never blocked or flagged on an absent signal.
     *
     * @return array{isSpam: bool, isEducational: bool}
     */
    public function moderateContent(string $text, ?string $context = null): array
    {
        if (trim($text) === '') {
            return ['isSpam' => false, 'isEducational' => true];
        }

        $result = $this->classify($text, null, $context);

        return [
            'isSpam' => (bool) ($result['IsFiltered'] ?? false),
            'isEducational' => (bool) ($result['IsEducational'] ?? true),
        ];
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
