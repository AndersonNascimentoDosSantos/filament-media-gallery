<?php

namespace Devanderson\FilamentMediaGallery\Commands;

use Illuminate\Console\Command;
use Devanderson\FilamentMediaGallery\Facades\FilamentMediaGallery;

class StatsCommand extends Command
{
    protected $signature = 'filament-media-gallery:stats
                            {--detailed : Exibe informações detalhadas}
                            {--json : Exibe resultado em formato JSON}';

    protected $description = 'Exibe estatísticas da galeria de mídias';

    public function handle(): int
    {
        $detailed = $this->option('detailed');
        $json = $this->option('json');

        if ($json) {
            return $this->handleJsonOutput($detailed);
        }

        $this->displayStats($detailed);

        return self::SUCCESS;
    }

    protected function displayStats(bool $detailed): void
    {
        $this->info('📊 Estatísticas da Galeria de Mídias');
        $this->newLine();

        // Estatísticas gerais
        $stats = FilamentMediaGallery::getStats();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de Imagens', $stats['total_imagens']],
                ['Total de Vídeos', $stats['total_videos']],
                ['Tamanho Total (Imagens)', $stats['tamanho_total_imagens']],
                ['Tamanho Total (Vídeos)', $stats['tamanho_total_videos']],
                ['Espaço Total Usado', $stats['espaco_total_usado']],
            ]
        );

        // Informações de storage
        if ($detailed) {
            $this->newLine();
            $this->info('💾 Informações de Armazenamento');
            $this->newLine();

            $storageInfo = FilamentMediaGallery::getStorageInfo();

            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Total de Arquivos', $storageInfo['total_files']],
                    ['Tamanho Total', $storageInfo['total_size_formatted']],
                    ['Tamanho Médio', $storageInfo['average_size_formatted']],
                ]
            );

            // Maiores arquivos
            $this->newLine();
            $this->info('📈 Top 5 Maiores Imagens');
            $this->newLine();

            $largest = FilamentMediaGallery::getLargest(5, 'image');

            if (!empty($largest['images']) && $largest['images']->count() > 0) {
                $imageData = $largest['images']->map(fn($img) => [
                    $img->nome_original,
                    $img->tamanho_formatado,
                    $img->created_at->format('d/m/Y H:i'),
                ]);

                $this->table(
                    ['Nome', 'Tamanho', 'Data'],
                    $imageData
                );
            } else {
                $this->comment('   Nenhuma imagem encontrada');
            }

            $this->newLine();
            $this->info('📈 Top 5 Maiores Vídeos');
            $this->newLine();

            if (!empty($largest['videos']) && $largest['videos']->count() > 0) {
                $videoData = $largest['videos']->map(fn($video) => [
                    $video->nome_original,
                    $video->tamanho_formatado,
                    $video->duracao_formatada ?? 'N/A',
                    $video->created_at->format('d/m/Y H:i'),
                ]);

                $this->table(
                    ['Nome', 'Tamanho', 'Duração', 'Data'],
                    $videoData
                );
            } else {
                $this->comment('   Nenhum vídeo encontrado');
            }

            // Mídias recentes
            $this->newLine();
            $this->info('🕐 Últimas 5 Mídias Adicionadas');
            $this->newLine();

            $recent = FilamentMediaGallery::getRecent(5);

            if (!empty($recent['images']) && $recent['images']->count() > 0) {
                $this->line('📸 <fg=cyan>Imagens:</>');
                foreach ($recent['images'] as $img) {
                    $this->line("   • {$img->nome_original} - {$img->created_at->diffForHumans()}");
                }
            }

            $this->newLine();

            if (!empty($recent['videos']) && $recent['videos']->count() > 0) {
                $this->line('🎬 <fg=cyan>Vídeos:</>');
                foreach ($recent['videos'] as $video) {
                    $this->line("   • {$video->nome_original} - {$video->created_at->diffForHumans()}");
                }
            }

            // Informações do FFmpeg
            $this->newLine();
            $this->info('🎥 Informações do FFmpeg');
            $this->newLine();

            $ffmpegInfo = FilamentMediaGallery::getFFmpegInfo();

            if ($ffmpegInfo) {
                $this->table(
                    ['Propriedade', 'Valor'],
                    [
                        ['Status', '✅ Disponível'],
                        ['Versão', $ffmpegInfo['version']],
                        ['Caminho', $ffmpegInfo['path']],
                    ]
                );
            } else {
                $this->warn('   ⚠️  FFmpeg não está disponível');
                $this->comment('   Thumbnails de vídeos não serão gerados automaticamente');
            }
        }
    }

    protected function handleJsonOutput(bool $detailed): int
    {
        $data = [
            'stats' => FilamentMediaGallery::getStats(),
        ];

        if ($detailed) {
            $data['storage'] = FilamentMediaGallery::getStorageInfo();
            $data['largest'] = FilamentMediaGallery::getLargest(5);
            $data['recent'] = FilamentMediaGallery::getRecent(5);
            $data['ffmpeg'] = FilamentMediaGallery::getFFmpegInfo();
        }

        $this->line(json_encode($data, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
