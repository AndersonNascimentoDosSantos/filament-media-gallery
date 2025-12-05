<?php

namespace Devanderson\FilamentMediaGallery\Forms\Components;

use Devanderson\FilamentMediaGallery\Models\Imagem;
use Devanderson\FilamentMediaGallery\Models\Video;
use Filament\Forms\Components\Field;

class GaleriaMidiaField extends Field
{
    protected string $view = 'filament-media-gallery::components.galeria-midia-field';

    protected string $mediaType = 'image'; // 'image' ou 'video'
    protected string $modelClass = Imagem::class;
    protected bool $allowUpload = true;
    protected bool $allowMultiple = true;
    protected ?int $maxItems = null;
    protected string $directory = 'uploads';
    protected string $disk = 'public';
    protected bool $allowImageEditor = false;
    protected array $imageEditorAspectRatios = ['16:9', '4:3', '1:1'];

    /**
     * Cria uma nova instância do campo com configurações padrão
     */
    public static function make(string $name = null): static
    {
        $static = parent::make($name);

        // Carrega configurações padrão do config
        $config = config('filament-media-gallery.gallery', []);

        $static->allowMultiple($config['allow_multiple'] ?? true);
        $static->maxItems($config['max_items'] ?? null);

        return $static;
    }

    /**
     * Define o tipo de mídia (image ou video)
     */
    public function mediaType(string $type): static
    {
        if (!in_array($type, ['image', 'video'])) {
            throw new \InvalidArgumentException("Tipo de mídia inválido. Use 'image' ou 'video'.");
        }

        // IMPORTANTE: Define o mediaType
        $this->mediaType = $type;

        // Define o model correto baseado no tipo e config
        $this->modelClass = ($type === 'video')
            ? config('filament-media-gallery.video.model', Video::class)
            : config('filament-media-gallery.image.model', Imagem::class);

        // Auto-configura editor de imagem apenas para tipo 'image'
        if ($type === 'image') {
            $editorConfig = config('filament-media-gallery.image.editor', []);
            $this->allowImageEditor = $editorConfig['enabled'] ?? false;
            $this->imageEditorAspectRatios = $editorConfig['aspect_ratios'] ?? ['16:9', '4:3', '1:1'];
        } else {
            // Se for vídeo, desabilita o editor
            $this->allowImageEditor = false;
        }

        return $this;
    }

    public function allowUpload(bool $condition = true): static
    {
        $this->allowUpload = $condition;
        return $this;
    }

    public function allowMultiple(bool $condition = true): static
    {
        $this->allowMultiple = $condition;
        return $this;
    }

    public function maxItems(?int $max): static
    {
        $this->maxItems = $max;
        return $this;
    }

    public function directory(string $directory): static
    {
        $this->directory = $directory;
        return $this;
    }

    public function disk(string $disk): static
    {
        $this->disk = $disk;
        return $this;
    }

    public function imageEditor(bool $condition = true): static
    {
        // Editor só funciona se o tipo for 'image'
        if ($this->mediaType === 'image') {
            $this->allowImageEditor = $condition;
        }
        return $this;
    }

    public function imageEditorAspectRatios(array $ratios): static
    {
        $this->imageEditorAspectRatios = $ratios;
        return $this;
    }

    /**
     * Obtém as mídias disponíveis com paginação
     */
    public function getMediasDisponiveis(): array
    {
        $model = $this->getModelClass();
        $perPage = config('filament-media-gallery.gallery.per_page', 24);

        \Log::info('GaleriaMidiaField: Carregando mídias disponíveis', [
            'mediaType' => $this->getMediaType(),
            'modelClass' => $model,
            'perPage' => $perPage
        ]);

        // FILTRO CORRETO: busca apenas do modelo específico (Imagem OU Video)
        $mediasPaginadas = $model::orderBy('created_at', 'desc')->paginate($perPage);

        $medias = collect($mediasPaginadas->items())->map(function ($media) {
            $data = [
                'id' => $media->id,
                'url' => $media->url,
                'nome_original' => $media->nome_original,
                'is_video' => $this->getMediaType() === 'video',
            ];

            // Adiciona thumbnail_url para vídeos
            if ($this->getMediaType() === 'video' && method_exists($media, 'getThumbnailUrlAttribute')) {
                $data['thumbnail_url'] = $media->thumbnail_url;
            }

            return $data;
        });

        \Log::info('GaleriaMidiaField: Mídias carregadas', [
            'mediaType' => $this->getMediaType(),
            'total' => $medias->count(),
            'temMais' => $mediasPaginadas->hasMorePages()
        ]);

        return [
            'medias' => $medias->toArray(),
            'temMais' => $mediasPaginadas->hasMorePages()
        ];
    }

    // ============ GETTERS ============

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getAllowUpload(): bool
    {
        return $this->allowUpload;
    }

    public function getAllowMultiple(): bool
    {
        return $this->allowMultiple;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function getAllowImageEditor(): bool
    {
        return $this->allowImageEditor && $this->mediaType === 'image';
    }

    public function getImageEditorAspectRatios(): array
    {
        return $this->imageEditorAspectRatios;
    }
}
