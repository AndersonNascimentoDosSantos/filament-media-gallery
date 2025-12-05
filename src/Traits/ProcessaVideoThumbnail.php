<?php

namespace Devanderson\FilamentMediaGallery\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

trait ProcessaVideoThumbnail
{
    /**
     * Gera thumbnail de um vídeo usando FFmpeg
     *
     * @param string $videoPath Caminho do vídeo no storage
     * @param float|null $timeInSeconds Tempo em segundos para capturar o frame (null usa config)
     * @return string|null Caminho do thumbnail gerado ou null se falhar
     */
    protected function gerarThumbnailVideo(string $videoPath, ?float $timeInSeconds = null): ?string
    {
        try {
            // Usa configurações do plugin
            $disk = config('filament-media-gallery.disk', 'public');
            $thumbnailEnabled = config('filament-media-gallery.video.thumbnail.enabled', true);

            if (!$thumbnailEnabled) {
                Log::info('ProcessaVideoThumbnail: Geração de thumbnail desabilitada no config');
                return null;
            }

            $fullVideoPath = Storage::disk($disk)->path($videoPath);

            if (!file_exists($fullVideoPath)) {
                Log::error('ProcessaVideoThumbnail: Vídeo não encontrado', [
                    'path' => $fullVideoPath
                ]);
                return null;
            }

            // Verifica se FFmpeg está disponível
            if (!$this->ffmpegDisponivel()) {
                Log::warning('ProcessaVideoThumbnail: FFmpeg não disponível, tentando método alternativo');
                return $this->gerarThumbnailAlternativo($videoPath);
            }

            // Usa tempo do config se não especificado
            if ($timeInSeconds === null) {
                $timeInSeconds = config('filament-media-gallery.video.thumbnail.time', 1.0);
            }

            // Define o caminho do thumbnail
            $thumbnailPath = 'thumbnails/video_' . uniqid() . '.jpg';
            $fullThumbnailPath = Storage::disk($disk)->path($thumbnailPath);

            // Cria diretório se não existir
            $thumbnailDir = dirname($fullThumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // Usa caminho do FFmpeg do config
            $ffmpegPath = config('filament-media-gallery.video.ffmpeg.path', 'ffmpeg');

            // Comando FFmpeg para extrair frame
            $command = sprintf(
                '%s -i %s -ss %s -vframes 1 -q:v 2 %s 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($fullVideoPath),
                $timeInSeconds,
                escapeshellarg($fullThumbnailPath)
            );

            Log::info('ProcessaVideoThumbnail: Executando FFmpeg', [
                'command' => $command
            ]);

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullThumbnailPath)) {
                Log::info('ProcessaVideoThumbnail: Thumbnail gerado com sucesso', [
                    'thumbnail_path' => $thumbnailPath
                ]);

                // Otimiza a imagem usando config
                $this->otimizarThumbnail($fullThumbnailPath);

                return $thumbnailPath;
            }

            Log::error('ProcessaVideoThumbnail: Erro ao gerar thumbnail', [
                'return_code' => $returnCode,
                'output' => $output
            ]);

            return $this->gerarThumbnailAlternativo($videoPath);

        } catch (\Exception $e) {
            Log::error('ProcessaVideoThumbnail: Exceção ao gerar thumbnail', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Verifica se FFmpeg está instalado e disponível
     */
    protected function ffmpegDisponivel(): bool
    {
        $ffmpegPath = config('filament-media-gallery.video.ffmpeg.path', 'ffmpeg');
        exec($ffmpegPath . ' -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Método alternativo para gerar thumbnail quando FFmpeg não está disponível
     * Retorna uma imagem placeholder
     */
    protected function gerarThumbnailAlternativo(string $videoPath): ?string
    {
        try {
            Log::info('ProcessaVideoThumbnail: Gerando thumbnail alternativo');

            // Verifica se Intervention Image está disponível
            if (!class_exists(\Intervention\Image\Facades\Image::class)) {
                Log::warning('ProcessaVideoThumbnail: Intervention Image não disponível');
                return null;
            }

            $disk = config('filament-media-gallery.disk', 'public');
            $width = config('filament-media-gallery.video.thumbnail.width', 640);
            $height = (int) ($width * 9 / 16); // Proporção 16:9

            // Cria uma imagem placeholder
            $image = \Intervention\Image\Facades\Image::canvas($width, $height, '#667eea');

            // Adiciona ícone de play
            $image->text('▶', $width / 2, $height / 2, function($font) {
                $font->size(120);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });

            // Adiciona texto
            $videoName = basename($videoPath);
            $image->text($videoName, $width / 2, $height - 40, function($font) {
                $font->size(16);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });

            $thumbnailPath = 'thumbnails/placeholder_' . uniqid() . '.jpg';
            $fullThumbnailPath = Storage::disk($disk)->path($thumbnailPath);

            // Cria diretório se não existir
            $thumbnailDir = dirname($fullThumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            $quality = config('filament-media-gallery.video.thumbnail.quality', 85);
            $image->save($fullThumbnailPath, $quality);

            Log::info('ProcessaVideoThumbnail: Thumbnail alternativo gerado', [
                'thumbnail_path' => $thumbnailPath
            ]);

            return $thumbnailPath;

        } catch (\Exception $e) {
            Log::error('ProcessaVideoThumbnail: Erro ao gerar thumbnail alternativo', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Otimiza o thumbnail redimensionando se necessário
     */
    protected function otimizarThumbnail(string $fullPath, ?int $maxWidth = null): void
    {
        try {
            if (!file_exists($fullPath)) {
                return;
            }

            // Verifica se Intervention Image está disponível
            if (!class_exists(\Intervention\Image\Facades\Image::class)) {
                Log::info('ProcessaVideoThumbnail: Intervention Image não disponível para otimização');
                return;
            }

            // Usa config se não especificado
            if ($maxWidth === null) {
                $maxWidth = config('filament-media-gallery.video.thumbnail.width', 640);
            }

            $image = \Intervention\Image\Facades\Image::make($fullPath);

            // Redimensiona mantendo proporção se for maior que o máximo
            if ($image->width() > $maxWidth) {
                $image->resize($maxWidth, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            // Salva com qualidade do config
            $quality = config('filament-media-gallery.video.thumbnail.quality', 85);
            $image->save($fullPath, $quality);

            Log::info('ProcessaVideoThumbnail: Thumbnail otimizado', [
                'path' => $fullPath,
                'width' => $image->width(),
                'height' => $image->height(),
                'quality' => $quality
            ]);

        } catch (\Exception $e) {
            Log::warning('ProcessaVideoThumbnail: Erro ao otimizar thumbnail', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gera múltiplos thumbnails em diferentes momentos do vídeo
     *
     * @param string $videoPath Caminho do vídeo
     * @param int $quantidade Quantidade de thumbnails a gerar
     * @return array Array com os caminhos dos thumbnails gerados
     */
    protected function gerarMultiplosThumbnails(string $videoPath, int $quantidade = 5): array
    {
        $thumbnails = [];

        try {
            $duracao = $this->obterDuracaoVideo($videoPath);

            if (!$duracao) {
                Log::warning('ProcessaVideoThumbnail: Não foi possível obter duração do vídeo');
                $thumbnail = $this->gerarThumbnailVideo($videoPath);
                return $thumbnail ? [$thumbnail] : [];
            }

            $intervalo = $duracao / ($quantidade + 1);

            for ($i = 1; $i <= $quantidade; $i++) {
                $tempo = $intervalo * $i;
                $thumbnail = $this->gerarThumbnailVideo($videoPath, $tempo);

                if ($thumbnail) {
                    $thumbnails[] = $thumbnail;
                }
            }

            Log::info('ProcessaVideoThumbnail: Múltiplos thumbnails gerados', [
                'quantidade' => count($thumbnails)
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessaVideoThumbnail: Erro ao gerar múltiplos thumbnails', [
                'message' => $e->getMessage()
            ]);
        }

        return $thumbnails;
    }

    /**
     * Obtém a duração do vídeo em segundos usando FFmpeg
     */
    protected function obterDuracaoVideo(string $videoPath): ?float
    {
        try {
            if (!$this->ffmpegDisponivel()) {
                return null;
            }

            $disk = config('filament-media-gallery.disk', 'public');
            $fullVideoPath = Storage::disk($disk)->path($videoPath);

            $ffprobePath = config('filament-media-gallery.video.ffmpeg.ffprobe_path', 'ffprobe');

            $command = sprintf(
                '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
                escapeshellarg($ffprobePath),
                escapeshellarg($fullVideoPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && isset($output[0])) {
                return (float)$output[0];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('ProcessaVideoThumbnail: Erro ao obter duração', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Deleta thumbnail associado quando vídeo é excluído
     */
    protected function deletarThumbnail(?string $thumbnailPath): void
    {
        if (!$thumbnailPath) {
            return;
        }

        try {
            $disk = config('filament-media-gallery.disk', 'public');

            if (Storage::disk($disk)->exists($thumbnailPath)) {
                Storage::disk($disk)->delete($thumbnailPath);
                Log::info('ProcessaVideoThumbnail: Thumbnail deletado', [
                    'path' => $thumbnailPath
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('ProcessaVideoThumbnail: Erro ao deletar thumbnail', [
                'message' => $e->getMessage()
            ]);
        }
    }
}
