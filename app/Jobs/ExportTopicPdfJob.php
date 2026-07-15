<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Post;
use App\Models\TopicExport;
use App\Services\MlGatewayClient;
use Dompdf\Dompdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Moved off the request cycle: PDF export was a synchronous ML-gateway call
 * (falling back to local Dompdf) blocking the HTTP response. Now the
 * controller just creates a pending TopicExport row and dispatches this job;
 * the user gets a Notification with a download link once it's ready.
 */
class ExportTopicPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $topicExportId)
    {
    }

    public function handle(MlGatewayClient $gateway): void
    {
        $export = TopicExport::find($this->topicExportId);
        if (!$export) {
            return;
        }

        $topic = $export->topic;
        if (!$topic) {
            $export->update(['Status' => 'failed']);
            return;
        }

        try {
            $pdfBytes = $gateway->exportTopicPdf($topic->TopicID);

            if ($pdfBytes === null) {
                $posts = Post::with(['author', 'parent.author', 'replies.author'])
                    ->where('TopicID', $topic->TopicID)
                    ->orderBy('CreatedAt')
                    ->get();

                $html = view('messages.export_pdf', compact('topic', 'posts'))->render();
                $dompdf = new Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfBytes = $dompdf->output();
            }

            $path = 'exports/' . Str::slug($topic->Title ?: 'discussion') . '-' . $export->TopicExportID . '.pdf';
            Storage::disk('public')->put($path, $pdfBytes);

            $export->update(['Status' => 'ready', 'FilePath' => $path]);

            // The notification dropdown renders Message as HTML (see
            // sidebar-notifications-script.blade.php), so this is the only
            // way to deliver a clickable download link without a schema
            // change — but that also means untrusted input must be escaped
            // here, not left to the renderer.
            $safeTitle = e($topic->Title);
            $downloadUrl = route('topic-exports.download', $export->TopicExportID);

            Notification::create([
                'UserID' => $export->UserID,
                'Message' => "Your PDF export of \"{$safeTitle}\" is ready. <a href=\"{$downloadUrl}\" target=\"_blank\" rel=\"noopener\">Download it here</a>.",
                'Status' => false,
                'Type' => 'Export',
            ]);
        } catch (\Throwable $e) {
            Log::error("ExportTopicPdfJob failed for TopicExportID={$this->topicExportId}: {$e->getMessage()}");
            $export->update(['Status' => 'failed']);
        }
    }
}
