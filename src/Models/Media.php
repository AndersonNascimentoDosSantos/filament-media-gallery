<?php
namespace Devanderson\FilamentMediaGallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'path',
        'nome_original',
        'mime_type',
        'tamanho',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $appends = ['url'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('filament-media-gallery.table_names.media', 'media');
    }

    /**
     * Retorna a URL completa do arquivo
     */
    public function getUrlAttribute(): string
    {
        if (!$this->path) {
            return '';
        }

        $disk = config('filament-media-gallery.disk', 'public');
        return url(Storage::disk($disk)->url($this->path));
    }

    /**
     * Relação polimórfica com Image
     */
    public function image(): HasOne
    {
        return $this->hasOne(Image::class);
    }

    /**
     * Relação polimórfica com Video
     */
    public function video(): HasOne
    {
        return $this->hasOne(Video::class);
    }

    /**
     * Formata o tamanho do arquivo
     */
    public function getTamanhoFormatadoAttribute(): string
    {
        $bytes = $this->tamanho;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Deleta o arquivo ao remover o modelo
     */
    protected static function booted(): void
    {
        static::deleting(function (Media $media) {
            $disk = config('filament-media-gallery.disk', 'public');

            if ($media->path && Storage::disk($disk)->exists($media->path)) {
                Storage::disk($disk)->delete($media->path);
            }
        });
    }
}
