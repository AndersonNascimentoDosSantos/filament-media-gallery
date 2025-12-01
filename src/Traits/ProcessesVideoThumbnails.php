<?php

namespace Devanderson\FilamentMediaGallery\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;

trait ProcessesVideoThumbnails
{
    /**
     * Generates a thumbnail from a video using FFmpeg.
     *
     * @param string $videoPath Path to the video in storage.
     * @param float $timeInSeconds Time in seconds to capture the frame (default: 1s).
     * @return string|null Path of the generated thumbnail or null on failure.
     */
    protected function generateVideoThumbnail(string $videoPath, float $timeInSeconds = 1.0): ?string
    {
        try {
            $fullVideoPath = Storage::disk('public')->path($videoPath);

            if (!file_exists($fullVideoPath)) {
                Log::error('VideoThumbnailProcess: Video not found', [
                    'path' => $fullVideoPath
                ]);
                return null;
            }

            // Check if FFmpeg is available
            if (!$this->isFfmpegAvailable()) {
                Log::warning('VideoThumbnailProcess: FFmpeg not available, trying alternative method');
                return $this->generateAlternativeThumbnail($videoPath);
            }

            // Define the thumbnail path
            $thumbnailPath = 'thumbnails/video_' . uniqid() . '.jpg';
            $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);

            // Create directory if it doesn't exist
            $thumbnailDir = dirname($fullThumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // FFmpeg command to extract a frame
            $command = sprintf(
                'ffmpeg -i %s -ss %s -vframes 1 -q:v 2 %s 2>&1',
                escapeshellarg($fullVideoPath),
                $timeInSeconds,
                escapeshellarg($fullThumbnailPath)
            );

            Log::info('VideoThumbnailProcess: Executing FFmpeg', [
                'command' => $command
            ]);

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullThumbnailPath)) {
                Log::info('VideoThumbnailProcess: Thumbnail generated successfully', [
                    'thumbnail_path' => $thumbnailPath
                ]);

                // Optimize the image (resize if it's too large)
                $this->optimizeThumbnail($fullThumbnailPath);

                return $thumbnailPath;
            }

            Log::error('VideoThumbnailProcess: Error generating thumbnail', [
                'return_code' => $returnCode,
                'output' => $output
            ]);

            return $this->generateAlternativeThumbnail($videoPath);

        } catch (\Exception $e) {
            Log::error('VideoThumbnailProcess: Exception while generating thumbnail', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Checks if FFmpeg is installed and available.
     */
    protected function isFfmpegAvailable(): bool
    {
        exec('ffmpeg -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Alternative method to generate a thumbnail when FFmpeg is not available.
     * Returns a placeholder image.
     */
    protected function generateAlternativeThumbnail(string $videoPath): ?string
    {
        try {
            Log::info('VideoThumbnailProcess: Generating alternative thumbnail');

            // Create a placeholder image
            $image = Image::canvas(640, 360, '#667eea');

            // Adiciona ícone de play
            $image->text('▶', 320, 180, function($font) {
                $font->file(public_path('fonts/Arial.ttf') ?: null);
                $font->size(120);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });

            // Add text
            $videoName = basename($videoPath);
            $image->text($videoName, 320, 300, function($font) {
                $font->file(public_path('fonts/Arial.ttf') ?: null);
                $font->size(16);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });

            $thumbnailPath = 'thumbnails/placeholder_' . uniqid() . '.jpg';
            $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);

            // Create directory if it doesn't exist
            $thumbnailDir = dirname($fullThumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            $image->save($fullThumbnailPath, 85);

            Log::info('VideoThumbnailProcess: Alternative thumbnail generated', [
                'thumbnail_path' => $thumbnailPath
            ]);

            return $thumbnailPath;

        } catch (\Exception $e) {
            Log::error('VideoThumbnailProcess: Error generating alternative thumbnail', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Optimizes the thumbnail by resizing if necessary.
     */
    protected function optimizeThumbnail(string $fullPath, int $maxWidth = 640): void
    {
        try {
            if (!file_exists($fullPath)) {
                return;
            }

            $image = Image::make($fullPath);

            // Resize maintaining aspect ratio if it's larger than the max width
            if ($image->width() > $maxWidth) {
                $image->resize($maxWidth, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            // Save with 85% quality
            $image->save($fullPath, 85);

            Log::info('VideoThumbnailProcess: Thumbnail optimized', [
                'path' => $fullPath,
                'width' => $image->width(),
                'height' => $image->height()
            ]);

        } catch (\Exception $e) {
            Log::warning('VideoThumbnailProcess: Error optimizing thumbnail', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generates multiple thumbnails at different times in the video.
     *
     * @param string $videoPath Path to the video.
     * @param int $quantity Number of thumbnails to generate.
     * @return array Array with the paths of the generated thumbnails.
     */
    protected function generateMultipleThumbnails(string $videoPath, int $quantity = 5): array
    {
        $thumbnails = [];

        try {
            $duration = $this->getVideoDuration($videoPath);

            if (!$duration) {
                Log::warning('VideoThumbnailProcess: Could not get video duration');
                return [$this->generateVideoThumbnail($videoPath)];
            }

            $interval = $duration / ($quantity + 1);

            for ($i = 1; $i <= $quantity; $i++) {
                $time = $interval * $i;
                $thumbnail = $this->generateVideoThumbnail($videoPath, $time);

                if ($thumbnail) {
                    $thumbnails[] = $thumbnail;
                }
            }

            Log::info('VideoThumbnailProcess: Multiple thumbnails generated', [
                'quantity' => count($thumbnails)
            ]);

        } catch (\Exception $e) {
            Log::error('VideoThumbnailProcess: Error generating multiple thumbnails', [
                'message' => $e->getMessage()
            ]);
        }

        return $thumbnails;
    }

    /**
     * Gets the video duration in seconds using FFmpeg.
     */
    protected function getVideoDuration(string $videoPath): ?float
    {
        try {
            if (!$this->isFfmpegAvailable()) {
                return null;
            }

            $fullVideoPath = Storage::disk('public')->path($videoPath);

            $command = sprintf(
                'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
                escapeshellarg($fullVideoPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && isset($output[0])) {
                return (float)$output[0];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('VideoThumbnailProcess: Error getting duration', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Deletes the associated thumbnail when a video is deleted.
     */
    protected function deleteThumbnail(?string $thumbnailPath): void
    {
        if (!$thumbnailPath) {
            return;
        }

        try {
            if (Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
                Log::info('VideoThumbnailProcess: Thumbnail deleted', [
                    'path' => $thumbnailPath
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('VideoThumbnailProcess: Error deleting thumbnail', [
                'message' => $e->getMessage()
            ]);
        }
    }
}
