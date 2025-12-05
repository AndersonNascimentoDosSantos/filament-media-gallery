<?php

namespace Devanderson\FilamentMediaGallery;
use Illuminate\Support\Facades\Storage;
use Devanderson\FilamentMediaGallery\Models\Imagem;
use Devanderson\FilamentMediaGallery\Models\Video;

class FilamentMediaGallery
{
    /**
     * Obtém as estatísticas da galeria
     */
    public function getStats(): array
    {
        return [
            'total_imagens' => Imagem::count(),
            'total_videos' => Video::count(),
            'tamanho_total_imagens' => $this->formatBytes(Imagem::sum('tamanho')),
            'tamanho_total_videos' => $this->formatBytes(Video::sum('tamanho')),
            'espaco_total_usado' => $this->formatBytes(
                Imagem::sum('tamanho') + Video::sum('tamanho')
            ),
        ];
    }

    /**
     * Obtém todas as imagens
     */
    public function getImages(int $perPage = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = $perPage ?? config('filament-media-gallery.gallery.per_page', 24);
        return Imagem::orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Obtém todos os vídeos
     */
    public function getVideos(int $perPage = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = $perPage ?? config('filament-media-gallery.gallery.per_page', 24);
        return Video::orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Obtém uma imagem por ID
     */
    public function getImage(int $id): ?Imagem
    {
        return Imagem::find($id);
    }

    /**
     * Obtém um vídeo por ID
     */
    public function getVideo(int $id): ?Video
    {
        return Video::find($id);
    }

    /**
     * Deleta uma imagem
     */
    public function deleteImage(int $id): bool
    {
        $imagem = Imagem::find($id);

        if (!$imagem) {
            return false;
        }

        return $imagem->delete();
    }

    /**
     * Deleta um vídeo
     */
    public function deleteVideo(int $id): bool
    {
        $video = Video::find($id);

        if (!$video) {
            return false;
        }

        return $video->delete();
    }

    /**
     * Limpa imagens órfãs (arquivos sem registro no banco)
     */
    public function cleanOrphanImages(): array
    {
        $disk = config('filament-media-gallery.disk', 'public');
        $path = config('filament-media-gallery.path', 'galeria');

        $files = Storage::disk($disk)->files($path);
        $registeredPaths = Imagem::pluck('path')->toArray();

        $orphans = array_diff($files, $registeredPaths);
        $deleted = [];

        foreach ($orphans as $orphan) {
            if (Storage::disk($disk)->delete($orphan)) {
                $deleted[] = $orphan;
            }
        }

        return $deleted;
    }

    /**
     * Limpa vídeos órfãos (arquivos sem registro no banco)
     */
    public function cleanOrphanVideos(): array
    {
        $disk = config('filament-media-gallery.disk', 'public');
        $path = config('filament-media-gallery.path', 'galeria');

        $files = Storage::disk($disk)->files($path);
        $registeredPaths = Video::pluck('path')->toArray();
        $registeredThumbnails = Video::whereNotNull('thumbnail_path')
            ->pluck('thumbnail_path')
            ->toArray();

        $registered = array_merge($registeredPaths, $registeredThumbnails);
        $orphans = array_diff($files, $registered);
        $deleted = [];

        foreach ($orphans as $orphan) {
            if (Storage::disk($disk)->delete($orphan)) {
                $deleted[] = $orphan;
            }
        }

        return $deleted;
    }

    /**
     * Limpa todos os arquivos órfãos
     */
    public function cleanOrphans(): array
    {
        return [
            'images' => $this->cleanOrphanImages(),
            'videos' => $this->cleanOrphanVideos(),
        ];
    }

    /**
     * Busca mídias por nome
     */
    public function search(string $query, string $type = 'both'): array
    {
        $results = [];

        if (in_array($type, ['image', 'both'])) {
            $results['images'] = Imagem::where('nome_original', 'like', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->get();
        }

        if (in_array($type, ['video', 'both'])) {
            $results['videos'] = Video::where('nome_original', 'like', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return $results;
    }

    /**
     * Obtém mídias recentes
     */
    public function getRecent(int $limit = 10, string $type = 'both'): array
    {
        $results = [];

        if (in_array($type, ['image', 'both'])) {
            $results['images'] = Imagem::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        }

        if (in_array($type, ['video', 'both'])) {
            $results['videos'] = Video::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        }

        return $results;
    }

    /**
     * Obtém as maiores mídias por tamanho
     */
    public function getLargest(int $limit = 10, string $type = 'both'): array
    {
        $results = [];

        if (in_array($type, ['image', 'both'])) {
            $results['images'] = Imagem::orderBy('tamanho', 'desc')
                ->limit($limit)
                ->get();
        }

        if (in_array($type, ['video', 'both'])) {
            $results['videos'] = Video::orderBy('tamanho', 'desc')
                ->limit($limit)
                ->get();
        }

        return $results;
    }

    /**
     * Verifica o uso de espaço
     */
    public function getStorageInfo(): array
    {
        $disk = config('filament-media-gallery.disk', 'public');
        $path = config('filament-media-gallery.path', 'galeria');

        $totalSize = 0;
        $fileCount = 0;

        $files = Storage::disk($disk)->allFiles($path);

        foreach ($files as $file) {
            $totalSize += Storage::disk($disk)->size($file);
            $fileCount++;
        }

        return [
            'total_files' => $fileCount,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'average_size' => $fileCount > 0 ? $totalSize / $fileCount : 0,
            'average_size_formatted' => $fileCount > 0
                ? $this->formatBytes($totalSize / $fileCount)
                : '0 bytes',
        ];
    }

    /**
     * Formata bytes em formato legível
     */
    public function formatBytes(int|float $bytes, int $precision = 2): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, $precision) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, $precision) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, $precision) . ' KB';
        }

        return number_format($bytes, 0) . ' bytes';
    }

    /**
     * Valida se um arquivo é uma imagem válida
     */
    public function isValidImage(string $mimeType): bool
    {
        $allowedTypes = config('filament-media-gallery.image.allowed_extensions', [
            'jpg', 'jpeg', 'png', 'gif', 'webp'
        ]);

        $extension = explode('/', $mimeType)[1] ?? '';

        return in_array($extension, $allowedTypes) ||
            in_array(str_replace('image/', '', $mimeType), $allowedTypes);
    }

    /**
     * Valida se um arquivo é um vídeo válido
     */
    public function isValidVideo(string $mimeType): bool
    {
        $allowedTypes = config('filament-media-gallery.video.allowed_extensions', [
            'mp4', 'webm', 'ogg'
        ]);

        $extension = explode('/', $mimeType)[1] ?? '';

        return in_array($extension, $allowedTypes) ||
            in_array(str_replace('video/', '', $mimeType), $allowedTypes);
    }

    /**
     * Obtém configuração do plugin
     */
    public function config(string $key = null, $default = null)
    {
        if ($key === null) {
            return config('filament-media-gallery');
        }

        return config("filament-media-gallery.{$key}", $default);
    }

    /**
     * Verifica se FFmpeg está disponível
     */
    public function hasFFmpeg(): bool
    {
        exec('ffmpeg -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Obtém informações sobre FFmpeg
     */
    public function getFFmpegInfo(): ?array
    {
        if (!$this->hasFFmpeg()) {
            return null;
        }

        exec('ffmpeg -version 2>&1', $output);

        return [
            'available' => true,
            'version' => $output[0] ?? 'Unknown',
            'path' => config('filament-media-gallery.video.ffmpeg.path', 'ffmpeg'),
        ];
    }
}
