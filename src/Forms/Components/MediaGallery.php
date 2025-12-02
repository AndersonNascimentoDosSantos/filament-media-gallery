<?php

namespace Devanderson\FilamentMediaGallery\Forms\Components;

use Devanderson\FilamentMediaGallery\Models\Image;
use Devanderson\FilamentMediaGallery\Models\Video;
use Filament\Forms\Components\Field;

class MediaGallery extends Field
{
    protected string $view = 'filament-media-gallery::forms.components.media-gallery';

    protected string $mediaType = 'image'; // 'image' ou 'video'

    protected string $modelClass = Image::class;

    protected bool $allowUpload = true;

    protected bool $allowMultiple = true;

    protected ?int $maxItems = null;

    protected bool $allowImageEditor = false;

    protected array $imageEditorAspectRatios = ['16:9', '4:3', '1:1'];

    /**
     * Define o tipo de mídia (image ou video)
     */
    public function mediaType(string $type): static
    {
        if (! in_array($type, ['image', 'video'])) {
            throw new \InvalidArgumentException("Tipo de mídia inválido. Use 'image' ou 'video'.");
        }

        $this->mediaType = $type;
        $this->modelClass = ($type === 'video')
            ? config('filament-media-gallery.models.video', Video::class)
            : config('filament-media-gallery.models.image', Image::class);

        return $this;
    }

    /**
     * Permite upload de arquivos
     */
    public function allowUpload(bool $condition = true): static
    {
        $this->allowUpload = $condition;

        return $this;
    }

    /**
     * Permite seleção múltipla
     */
    public function allowMultiple(bool $condition = true): static
    {
        $this->allowMultiple = $condition;

        return $this;
    }

    /**
     * Define máximo de itens selecionáveis
     */
    public function maxItems(?int $max): static
    {
        $this->maxItems = $max;

        return $this;
    }

    /**
     * Habilita editor de imagens (apenas para mediaType = 'image')
     */
    public function imageEditor(bool $condition = true): static
    {
        $this->allowImageEditor = $condition;

        return $this;
    }

    /**
     * Define proporções disponíveis no editor
     */
    public function imageEditorAspectRatios(array $ratios): static
    {
        $this->imageEditorAspectRatios = $ratios;

        return $this;
    }

    /**
     * Obtém mídias disponíveis paginadas
     */
    public function getMediasDisponiveis(): array
    {
        $model = $this->getModelClass();
        $perPage = config('filament-media-gallery.pagination.per_page', 24);

        $mediasPaginadas = $model::with('media')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $medias = collect($mediasPaginadas->items())->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->url,
                'nome_original' => $media->nome_original,
                'is_video' => $this->getMediaType() === 'video',
                'thumbnail_url' => $this->getMediaType() === 'video' && method_exists($media, 'getThumbnailUrlAttribute')
                    ? $media->thumbnail_url
                    : null,
            ];
        });

        return [
            'medias' => $medias->toArray(),
            'temMais' => $mediasPaginadas->hasMorePages(),
        ];
    }

    // Getters
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

    public function getAllowImageEditor(): bool
    {
        return $this->allowImageEditor && $this->mediaType === 'image';
    }

    public function getImageEditorAspectRatios(): array
    {
        return $this->imageEditorAspectRatios;
    }

    /**
     * Método estático para criar instância com tipo image
     */
    public static function images(string $name): static
    {
        return static::make($name)->mediaType('image');
    }

    /**
     * Método estático para criar instância com tipo video
     */
    public static function videos(string $name): static
    {
        return static::make($name)->mediaType('video');
    }
}
