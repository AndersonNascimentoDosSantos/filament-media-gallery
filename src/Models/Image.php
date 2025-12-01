<?php



// ============================================
// Image Model
// ============================================

namespace Devanderson\FilamentMediaGallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    protected $fillable = [
        'media_id',
        'width',
        'height',
        'alt_text',
    ];

    protected $appends = ['url'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('filament-media-gallery.table_names.images', 'images');
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
}
