<?php

namespace App\Jobs;

use App\Models\Topic;
use App\Models\TopicClassification;
use App\Services\MlGatewayClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Was a foreach loop in DiscussionHubPageController::backfillClassifications()
 * calling the ML gateway synchronously, once per unclassified topic (up to 15
 * sequential HTTP calls), on every forum/recommend page load. Classification
 * is pure enrichment — the page renders fine without a category badge — so
 * it belongs in the background, not blocking the request.
 *
 * ShouldBeUnique so re-visiting the forum page before the queue worker gets
 * to an already-dispatched topic doesn't pile up duplicate jobs.
 */
class ClassifyTopicJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $topicId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->topicId;
    }

    public function handle(MlGatewayClient $gateway): void
    {
        $topic = Topic::find($this->topicId);
        if (!$topic) {
            return;
        }

        $result = $gateway->classify($topic->Title, $topic->TopicID);

        if ($result && !empty($result['PredictedCategory'])) {
            TopicClassification::updateOrCreate(
                ['TopicID' => $topic->TopicID],
                [
                    'PredictedCategory' => $result['PredictedCategory'],
                    'ConfidenceScore' => $result['ConfidenceScore'] ?? 0,
                ]
            );
        }
    }
}
