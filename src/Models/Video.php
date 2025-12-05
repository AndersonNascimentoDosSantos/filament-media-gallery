<?php

namespace Devanderson\FilamentMediaGallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    use HasFactory;

    protected $table = 'videos';

    protected $fillable = [
        'path',
        'thumbnail_path',
        'nome_original',
        'mime_type',
        'tamanho',
        'duracao',
    ];

    protected $casts = [
        'tamanho' => 'integer',
        'duracao' => 'float',
    ];

    protected $appends = ['url', 'thumbnail_url'];

    /**
     * Retorna a URL completa do vídeo
     */
    public function getUrlAttribute(): string
    {
        $disk = config('filament-media-gallery.disk', 'public');
        return Storage::disk($disk)->url($this->path);
    }

    /**
     * Retorna a URL completa da thumbnail
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        $disk = config('filament-media-gallery.disk', 'public');
        return Storage::disk($disk)->url($this->thumbnail_path);
    }

    /**
     * Retorna o tamanho formatado
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
     * Retorna a duração formatada
     */
    public function getDuracaoFormatadaAttribute(): ?string
    {
        if (!$this->duracao) {
            return null;
        }

        $seconds = (int) $this->duracao;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Deleta o vídeo e thumbnail do storage ao deletar o registro
     */
    protected static function booted(): void
    {
        static::deleting(function (Video $video) {
            $disk = config('filament-media-gallery.disk', 'public');

            if (Storage::disk($disk)->exists($video->path)) {
                Storage::disk($disk)->delete($video->path);
            }

            if ($video->thumbnail_path && Storage::disk($disk)->exists($video->thumbnail_path)) {
                Storage::disk($disk)->delete($video->thumbnail_path);
            }
        });
    }

    /**
     * Scope para filtrar por tipo MIME
     */
    public function scopeByMimeType($query, string $mimeType)
    {
        return $query->where('mime_type', 'like', $mimeType . '%');
    }

    /**
     * Scope para ordenar por mais recentes
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Verifica se o vídeo tem thumbnail
     */
    public function hasThumbnail(): bool
    {
        return !empty($this->thumbnail_path);
    }
}
