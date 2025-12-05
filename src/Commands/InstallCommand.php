<?php

namespace Devanderson\FilamentMediaGallery\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'filament-media-gallery:install';

    protected $description = 'Install Filament Media Gallery plugin';

    public function handle(): int
    {
        $this->info('🚀 Installing Filament Media Gallery...');
        $this->newLine();

        // Publicar configurações
        $this->call('vendor:publish', [
            '--tag' => 'filament-media-gallery-config',
            '--force' => $this->option('verbose'),
        ]);

        // Publicar migrations
        $this->call('vendor:publish', [
            '--tag' => 'filament-media-gallery-migrations',
            '--force' => $this->option('verbose'),
        ]);

        // Perguntar se quer executar migrations
        if ($this->confirm('📦 Deseja executar as migrations agora?', true)) {
            $this->call('migrate');
        }

        // Perguntar se quer publicar views
        if ($this->confirm('🎨 Deseja publicar as views para customização?', false)) {
            $this->call('vendor:publish', [
                '--tag' => 'filament-media-gallery-views',
                '--force' => true,
            ]);
        }

        // Verificar FFmpeg
        $this->newLine();
        $this->checkFFmpeg();

        // Instruções finais
        $this->newLine(2);
        $this->showCompletionMessage();

        return self::SUCCESS;
    }

    protected function checkFFmpeg(): void
    {
        exec('ffmpeg -version 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            $this->info('✅ FFmpeg está instalado - Thumbnails de vídeos habilitados!');
        } else {
            $this->warn('⚠️  FFmpeg não encontrado - Thumbnails de vídeos não serão gerados.');
            $this->line('   Para instalar FFmpeg:');
            $this->line('   - Ubuntu/Debian: sudo apt-get install ffmpeg');
            $this->line('   - macOS: brew install ffmpeg');
            $this->line('   - Windows: https://ffmpeg.org/download.html');
        }
    }

    protected function showCompletionMessage(): void
    {
        $this->components->info('✅ Filament Media Gallery instalado com sucesso!');
        $this->newLine();

        $this->line('📚 Próximos passos:');
        $this->newLine();

        $this->line('1️⃣  Configure o custom theme (IMPORTANTE!)');
        $this->line('   Adicione ao seu arquivo CSS:');
        $this->line('   <fg=yellow>@import \'../../../../vendor/vendor-name/filament-media-gallery/resources/**/*.blade.php\';</>');
        $this->newLine();

        $this->line('2️⃣  Use o campo no seu Resource:');
        $this->line('   <fg=cyan>use VendorName\FilamentMediaGallery\Forms\Components\GaleriaMidiaField;</>');
        $this->newLine();
        $this->line('   <fg=cyan>GaleriaMidiaField::make(\'imagens\')</>');
        $this->line('   <fg=cyan>    ->mediaType(\'image\')</>');
        $this->line('   <fg=cyan>    ->allowMultiple()</>');
        $this->line('   <fg=cyan>    ->imageEditor()</>');
        $this->newLine();

        $this->line('3️⃣  Use o trait nas suas páginas:');
        $this->line('   <fg=cyan>use VendorName\FilamentMediaGallery\Traits\ProcessaUploadGaleria;</>');
        $this->newLine();

        $this->line('📖 Documentação completa: https://github.com/vendor-name/filament-media-gallery');
        $this->newLine();
    }
}
