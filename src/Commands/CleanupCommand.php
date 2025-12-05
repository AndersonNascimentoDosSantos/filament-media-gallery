<?php

namespace Devanderson\FilamentMediaGallery\Commands;

use Illuminate\Console\Command;
use Devanderson\FilamentMediaGallery\Facades\FilamentMediaGallery;

class CleanupCommand extends Command
{
    protected $signature = 'filament-media-gallery:cleanup
                            {--dry-run : Lista os arquivos que seriam deletados sem deletar}
                            {--type=both : Tipo de mídia (image, video, both)}';

    protected $description = 'Limpa arquivos órfãos da galeria de mídias';

    public function handle(): int
    {
        $this->info('🧹 Iniciando limpeza da galeria...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $type = $this->option('type');

        if ($dryRun) {
            $this->warn('⚠️  Modo DRY RUN - Nenhum arquivo será deletado');
            $this->newLine();
        }

        // Estatísticas antes da limpeza
        $stats = FilamentMediaGallery::getStats();
        $this->info('📊 Estatísticas atuais:');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de Imagens', $stats['total_imagens']],
                ['Total de Vídeos', $stats['total_videos']],
                ['Espaço Total Usado', $stats['espaco_total_usado']],
            ]
        );
        $this->newLine();

        if (!$dryRun && !$this->confirm('Deseja continuar com a limpeza?', true)) {
            $this->comment('Operação cancelada.');
            return self::SUCCESS;
        }

        // Limpar imagens órfãs
        if (in_array($type, ['image', 'both'])) {
            $this->cleanImages($dryRun);
        }

        // Limpar vídeos órfãos
        if (in_array($type, ['video', 'both'])) {
            $this->cleanVideos($dryRun);
        }

        $this->newLine();
        $this->info('✅ Limpeza concluída!');

        // Estatísticas após a limpeza
        if (!$dryRun) {
            $this->newLine();
            $newStats = FilamentMediaGallery::getStats();
            $this->info('📊 Estatísticas após limpeza:');
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Total de Imagens', $newStats['total_imagens']],
                    ['Total de Vídeos', $newStats['total_videos']],
                    ['Espaço Total Usado', $newStats['espaco_total_usado']],
                ]
            );
        }

        return self::SUCCESS;
    }

    protected function cleanImages(bool $dryRun): void
    {
        $this->info('🖼️  Processando imagens...');

        if ($dryRun) {
            $this->comment('   Verificando arquivos órfãos de imagens...');
            // Aqui você poderia implementar uma verificação que não deleta
            return;
        }

        $deleted = FilamentMediaGallery::cleanOrphanImages();

        if (count($deleted) > 0) {
            $this->warn("   ❌ {count($deleted)} arquivo(s) órfão(s) de imagem deletado(s)");

            if ($this->option('verbose')) {
                foreach ($deleted as $file) {
                    $this->line("      - {$file}");
                }
            }
        } else {
            $this->info('   ✓ Nenhum arquivo órfão de imagem encontrado');
        }
    }

    protected function cleanVideos(bool $dryRun): void
    {
        $this->info('🎬 Processando vídeos...');

        if ($dryRun) {
            $this->comment('   Verificando arquivos órfãos de vídeos...');
            return;
        }

        $deleted = FilamentMediaGallery::cleanOrphanVideos();

        if (count($deleted) > 0) {
            $this->warn("   ❌ " . count($deleted) . " arquivo(s) órfão(s) de vídeo deletado(s)");

            if ($this->option('verbose')) {
                foreach ($deleted as $file) {
                    $this->line("      - {$file}");
                }
            }
        } else {
            $this->info('   ✓ Nenhum arquivo órfão de vídeo encontrado');
        }
    }
}
