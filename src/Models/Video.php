<?php

// ============================================
// Video Model
// ============================================

namespace Devanderson\FilamentMediaGallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    protected $fillable = [
        'media_id',
        'thumbnail_path',
        'duration',
        'codec',
        'width',
        'height',
    ];

    protected $appends = ['url', 'thumbnail_url'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('filament-media-gallery.table_names.videos', 'videos');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function getUrlAttribute(): string
    {
        return $this->media->url ?? '';
    }

    public function getOriginalNameAttribute(): string
    {
        return $this->media->original_name ?? '';
    }

    public function getThumbnailUrlAttribute(): string
    {
        $disk = config('filament-media-gallery.disk', 'public');

        if ($this->thumbnail_path && Storage::disk($disk)->exists($this->thumbnail_path)) {
            return url('storage/' . $this->thumbnail_path);
        }

        // Returns a placeholder
        return asset('vendor/filament-media-gallery/images/video-placeholder.png');
    }

    public function hasThumbnail(): bool
    {
        $disk = config('filament-media-gallery.disk', 'public');

        return ! empty($this->thumbnail_path) &&
            Storage::disk($disk)->exists($this->thumbnail_path);
    }

    /**
     * Deletes the thumbnail when the model is removed.
     */
    protected static function booted(): void
    {
        static::deleting(function (Video $video) {
            $disk = config('filament-media-gallery.disk', 'public');

            if ($video->thumbnail_path && Storage::disk($disk)->exists($video->thumbnail_path)) {
                Storage::disk($disk)->delete($video->thumbnail_path);
            }
        });
    }
}
