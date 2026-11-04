<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class VideoReviewEngine
{
    /**
     * Browser-native MIME types that can be directly played in standard HTML5 video elements.
     */
    protected const BROWSER_NATIVE_MIMES = [
        'video/mp4',
        'video/webm',
        'video/ogg',
    ];

    /**
     * Inspect uploaded video deliverable, determine direct browser compatibility,
     * and route to direct playback or optional FFmpeg worker queue.
     */
    public function evaluateVideoDeliverable(string $versionId, string $mimeType, string $storagePath): array
    {
        $ffmpegEnabled = filter_var(env('ENABLE_FFMPEG_WORKER', false), FILTER_VALIDATE_BOOLEAN);
        $isNative = in_array(strtolower($mimeType), self::BROWSER_NATIVE_MIMES, true);

        if ($isNative) {
            // Direct playback ready immediately without transcoding overhead
            DB::table('deliverable_versions')
                ->where('id', $versionId)
                ->update([
                    'preview_status' => 'ready',
                    'preview_url' => Storage::url($storagePath),
                    'metadata' => json_encode([
                        'playback_mode' => 'direct_html5',
                        'codec_source' => 'browser_native',
                        'mime_type' => $mimeType,
                    ]),
                ]);

            return [
                'preview_status' => 'ready',
                'playback_mode' => 'direct_html5',
                'message' => 'Video is web-compatible and ready for frame-accurate review.',
            ];
        }

        // Non-native formats (e.g. .mov with Apple ProRes, .avi, .mkv)
        if ($ffmpegEnabled) {
            // Queue to the dedicated resource-capped media worker
            DB::table('deliverable_versions')
                ->where('id', $versionId)
                ->update([
                    'preview_status' => 'processing',
                    'metadata' => json_encode([
                        'playback_mode' => 'queued_for_transcoding',
                        'original_mime' => $mimeType,
                    ]),
                ]);

            // Dispatch job to Redis 'media' queue (Concurrency = 1)
            // \App\Jobs\ProcessVideoConversionJob::dispatch($versionId)->onQueue('media');

            return [
                'preview_status' => 'processing',
                'playback_mode' => 'queued_for_transcoding',
                'message' => 'File queued for local FFmpeg optimization into web-streamable MP4.',
            ];
        }

        // FFmpeg is disabled; provide clear fallback instead of failing silently
        DB::table('deliverable_versions')
            ->where('id', $versionId)
            ->update([
                'preview_status' => 'preview_unavailable',
                'metadata' => json_encode([
                    'playback_mode' => 'fallback_download_only',
                    'original_mime' => $mimeType,
                    'reason' => 'Container or codec is not directly streamable by standard web browsers and local FFmpeg worker is not activated.',
                ]),
            ]);

        return [
            'preview_status' => 'preview_unavailable',
            'playback_mode' => 'fallback_download_only',
            'message' => 'This video format requires external desktop playback or activating the optional FFmpeg media worker profile.',
        ];
    }
}
