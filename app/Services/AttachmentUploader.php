<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Shared upload path for Post/Reply/Topic attachments — was three copies of
 * the same "store the raw file, guess AttachmentType from extension" logic
 * (storeMessage/storeTopic/storeReply), none of which resized images before
 * storing them, so a 12MP phone photo used as a small inline thumbnail was
 * served at full resolution every time it rendered.
 */
class AttachmentUploader
{
    private const MAX_DIMENSION = 1600;
    private const JPEG_QUALITY = 78;

    /**
     * @return array{path: string, type: string}
     */
    public static function store(UploadedFile $file, string $directory = 'discussions', string $disk = 'public'): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $isImage = in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true);
        $safeOriginalName = self::sanitizeName($file->getClientOriginalName());

        if ($isImage && function_exists('imagecreatefromstring')) {
            $resized = self::resize($file->getRealPath(), $extension);
            if ($resized !== null) {
                // Re-encoded as JPEG regardless of the original format, so the
                // stored filename's extension is forced to .jpg to match the
                // actual bytes - only the basename (what the user sees/downloads
                // as) comes from their original filename.
                $baseName = pathinfo($safeOriginalName, PATHINFO_FILENAME) . '.jpg';
                $path = $directory . '/' . uniqid('', true) . '--' . $baseName;
                Storage::disk($disk)->put($path, $resized);

                return ['path' => $path, 'type' => 'image'];
            }
        }

        // storeAs (not store()) so the filename on disk keeps the user's
        // original name after the uniqid prefix - store() alone discards it
        // in favor of a random hash, which is all downloads/attachment links
        // showed until now instead of a recognizable name. The uniqid prefix
        // still guarantees no collision between two uploads named the same.
        $path = $file->storeAs($directory, uniqid('', true) . '--' . $safeOriginalName, $disk);

        return ['path' => $path, 'type' => $isImage ? 'image' : 'file'];
    }

    /**
     * The human-readable filename to show/download an attachment as -
     * strips the "{uniqid}--" collision-avoidance prefix store() adds, so
     * the user sees the name they originally uploaded instead of the prefix
     * or a random storage hash.
     */
    public static function displayName(string $path): string
    {
        $base = basename($path);
        $separatorPosition = strpos($base, '--');

        return $separatorPosition === false ? $base : substr($base, $separatorPosition + 2);
    }

    /**
     * Strip anything but a conservative safe character set so the stored
     * filename can't traverse directories, break the uniqid separator, or
     * carry characters some filesystems/URLs choke on - while still reading
     * as the original name.
     */
    private static function sanitizeName(string $originalName): string
    {
        $originalName = basename($originalName);
        $sanitized = preg_replace('/[^A-Za-z0-9._ -]/', '_', $originalName);
        $sanitized = trim($sanitized, '.');

        return $sanitized !== '' ? $sanitized : 'file';
    }

    /**
     * Downscale to MAX_DIMENSION on the long edge and re-encode as JPEG.
     * Returns null (caller falls back to storing the original) if GD can't
     * decode the file — an unsupported/corrupt image shouldn't block the
     * whole upload.
     */
    private static function resize(string $sourcePath, string $extension): ?string
    {
        try {
            $data = file_get_contents($sourcePath);
            $image = @imagecreatefromstring($data);
            if ($image === false) {
                return null;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $longEdge = max($width, $height);

            if ($longEdge > self::MAX_DIMENSION) {
                $scale = self::MAX_DIMENSION / $longEdge;
                $newWidth = (int) round($width * $scale);
                $newHeight = (int) round($height * $scale);

                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            ob_start();
            imagejpeg($image, null, self::JPEG_QUALITY);
            $bytes = ob_get_clean();
            imagedestroy($image);

            return $bytes;
        } catch (\Throwable $e) {
            Log::warning("AttachmentUploader: image resize failed, storing original — {$e->getMessage()}");
            return null;
        }
    }
}
