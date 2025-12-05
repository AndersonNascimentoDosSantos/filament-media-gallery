<?php

namespace Devanderson\FilamentMediaGallery;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Devanderson\FilamentMediaGallery\Commands\InstallCommand;
use Devanderson\FilamentMediaGallery\Commands\CleanupCommand;
use Devanderson\FilamentMediaGallery\Commands\StatsCommand;
use Devanderson\FilamentMediaGallery\Forms\Components\GaleriaMidiaField;

class FilamentMediaGalleryServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-media-gallery';

    public static string $viewNamespace = 'filament-media-gallery';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasMigrations([
                'create_imagens_table',
                'create_videos_table',
            ])
            ->hasCommands([
                InstallCommand::class,
                CleanupCommand::class,
                StatsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Registra a classe principal do plugin como singleton
        $this->app->singleton(FilamentMediaGallery::class, function ($app) {
            return new FilamentMediaGallery();
        });

        // Registra o alias da facade
        $this->app->alias(FilamentMediaGallery::class, 'filament-media-gallery');
    }

    public function packageBooted(): void
    {
        // Registra os assets do Cropper.js
        FilamentAsset::register([
            Css::make('cropper-css', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css'),
            Js::make('cropper-js', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js'),
        ], package: 'filament-media-gallery');

        // Registra o componente de formulário
        if (class_exists(\Filament\Forms\Components\Field::class)) {
            \Filament\Forms\Components\Field::macro('galeriaMidia', function () {
                return new GaleriaMidiaField($this->getName());
            });
        }
    }

    protected function getAssetPackageName(): ?string
    {
        return 'devanderson/filament-media-gallery';
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_imagens_table',
            'create_videos_table',
        ];
    }
}
