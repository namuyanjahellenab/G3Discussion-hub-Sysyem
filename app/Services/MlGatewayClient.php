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
        ], (float) config('services.ml_gateway.classify_timeout', 1.5));

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
            // connectTimeout is deliberately the short shared budget, not
            // pdf_timeout - reaching the gateway at all is either fast or
            // instantly refused (same as every other call), so there's no
            // reason to wait longer just to find out it's unreachable.
            // pdf_timeout only extends the budget for actual PDF rendering
            // once a connection is established - see ->timeout() below,
            // and the class-level note on why ->timeout() alone wasn't
            // enough to bound a refused connection on this environment.
            // Attachment images live on this server's disk, not the gateway's -
            // in production these are two separate machines/containers, so the
            // gateway can't just read the file locally. Passing Laravel's own
            // public URL lets it fetch each image over HTTP instead (see
            // pdf.py), the same way a browser tab would.
            $response = Http::withToken($token)
                ->connectTimeout((float) config('services.ml_gateway.timeout', 0.5))
                ->timeout((float) config('services.ml_gateway.pdf_timeout', 8))
                ->post(rtrim($baseUrl, '/') . '/export-topic-pdf', [
                    'TopicID' => $topicId,
                    'BaseUrl' => config('app.url'),
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

    private function post(string $path, array $payload, ?float $timeoutOverride = null): ?array
    {
        $baseUrl = config('services.ml_gateway.url');
        $token = config('services.ml_gateway.token');

        if (!$baseUrl || !$token) {
            return null;
        }

        try {
            // ->timeout() alone bounds total transfer time, not the TCP
            // connect phase - on this environment, an unreachable gateway's
            // connection attempt itself was the ~2s+ cost (confirmed by
            // timing a raw curl call: only CURLOPT_CONNECTTIMEOUT_MS
            // actually cut it off; the overall timeout option didn't).
            // ->connectTimeout() is what maps to that curl option, so it's
            // required here, not just ->timeout(), to actually fail fast.
            $budget = $timeoutOverride ?? (float) config('services.ml_gateway.timeout', 0.5);
            $response = Http::withToken($token)
                ->connectTimeout($budget)
                ->timeout($budget)
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
