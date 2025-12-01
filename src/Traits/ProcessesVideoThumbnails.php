<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;

trait ProcessaVideoThumbnail
{
    /**
     * Gera thumbnail de um vídeo usando FFmpeg
     *
     * @param string $videoPath Caminho do vídeo no storage
     * @param float $timeInSeconds Tempo em segundos para capturar o frame (padrão: 1s)
     * @return string|null Caminho do thumbnail gerado ou null se falhar
     */
    protected function gerarThumbnailVideo(string $videoPath, float $timeInSeconds = 1.0): ?string
    {
        try {
            $fullVideoPath = Storage::disk('public')->path($videoPath);

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

            // Define o caminho do thumbnail
            $thumbnailPath = 'thumbnails/video_' . uniqid() . '.jpg';
            $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);

            // Cria diretório se não existir
            $thumbnailDir = dirname($fullThumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // Comando FFmpeg para extrair frame
            $command = sprintf(
                'ffmpeg -i %s -ss %s -vframes 1 -q:v 2 %s 2>&1',
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

                // Otimiza a imagem (redimensiona se for muito grande)
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
        exec('ffmpeg -version 2>&1', $output, $returnCode);
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

            // Cria uma imagem placeholder
            $image = Image::canvas(640, 360, '#667eea');

            // Adiciona ícone de play
            $image->text('▶', 320, 180, function($font) {
                $font->file(public_path('fonts/Arial.ttf') ?: null);
                $font->size(120);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });

            // Adiciona texto
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

            // Cria diretório se não existir
            $thumbnailDir = dirname($fullThumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            $image->save($fullThumbnailPath, 85);

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
    protected function otimizarThumbnail(string $fullPath, int $maxWidth = 640): void
    {
        try {
            if (!file_exists($fullPath)) {
                return;
            }

            $image = Image::make($fullPath);

            // Redimensiona mantendo proporção se for maior que o máximo
            if ($image->width() > $maxWidth) {
                $image->resize($maxWidth, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            // Salva com qualidade 85%
            $image->save($fullPath, 85);

            Log::info('ProcessaVideoThumbnail: Thumbnail otimizado', [
                'path' => $fullPath,
                'width' => $image->width(),
                'height' => $image->height()
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
                return [$this->gerarThumbnailVideo($videoPath)];
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
            if (Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
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
