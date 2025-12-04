<?php

namespace Devanderson\FilamentMediaGallery\Forms\Components;

use Devanderson\FilamentMediaGallery\Models\Image;
use Devanderson\FilamentMediaGallery\Models\Video;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

//    protected function setUp(): void
//    {
//        parent::setUp();
//
//        // Carrega os IDs do relacionamento quando o formulário é preenchido.
//        $this->loadStateFromRelationshipsUsing(static function (MediaGallery $component, ?Model $record): void {
//            if (! $record) {
//                return;
//            }
//
//            $relationship = $component->getRelationship($record);
//
//            if (! $relationship instanceof BelongsToMany) {
//                return;
//            }
//
//            $component->state($relationship->get()->pluck('id')->all());
//        });
//    }

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
        $perPage = 24;

        \Log::info('getMediasDisponiveis', [
            'modelClass' => $model,
            'mediaType' => $this->mediaType,
        ]);

        $mediasPaginadas = $model::orderBy('created_at', 'desc')->paginate($perPage);

        $medias = collect($mediasPaginadas->items())->map(function ($media) {
            $data = [
                'id' => $media->id,
                'url' => $media->url ?? '',
                'nome_original' => $media->nome_original ?? 'Sem nome',
                'is_video' => $this->getMediaType() === 'video',
            ];

            if ($this->getMediaType() === 'video' && method_exists($media, 'getThumbnailUrlAttribute')) {
                $data['thumbnail_url'] = $media->thumbnail_url;
            }

            \Log::info('Media mapeada', $data);

            return $data;
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
