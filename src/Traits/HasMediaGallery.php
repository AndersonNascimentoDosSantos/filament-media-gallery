<?php

namespace Devanderson\FilamentMediaGallery\Traits;

use Devanderson\FilamentMediaGallery\Models\Image as Imagem;
use Devanderson\FilamentMediaGallery\Models\Video;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait HasMediaGallery
{
    /**
     * Cache for field configurations to avoid repeated lookups.
     */
    protected array $fieldConfigCache = [];

    /**
     * Gets the configuration for a media field.
     */
    protected function getFieldConfig(string $statePath): ?array
    {
        // Remove the 'data.' prefix if it exists
        $key = str_starts_with($statePath, 'data.') ? substr($statePath, 5) : $statePath;

        // Check if it's already in cache
        if (isset($this->fieldConfigCache[$key])) {
            return $this->fieldConfigCache[$key];
        }

        \Log::info('MediaGalleryUpload: Fetching field configuration', [
            'statePath' => $statePath,
            'key' => $key,
        ]);

        // List of potential form-providing methods to check, in order of priority.
        $formProviders = [
            'getMountedTableActionForm', // For table actions (e.g., in Relation Managers)
            'getMountedActionForm',      // For page actions
            'form',                      // For main forms (Create/Edit pages)
        ];

        foreach ($formProviders as $provider) {
            if (method_exists($this, $provider)) {
                try {
                    $form = $this->{$provider}();

                    // The main 'form' method might need the makeForm() helper.
                    if ($provider === 'form' && property_exists($this, 'form')) {
                        $form = $this->form($this->makeForm());
                    }

                    if ($form) {
                        foreach ($form->getComponents(true) as $component) {
                            if ($component->getStatePath() === $key && method_exists($component, 'getMediaType')) {
                                $config = [
                                    'mediaType' => $component->getMediaType(),
                                    'modelClass' => $component->getModelClass(),
                                    'allowMultiple' => $component->getAllowMultiple(),
                                    'allowUpload' => $component->getAllowUpload(),
                                    'maxItems' => $component->getMaxItems(),
                                ];
                                $this->fieldConfigCache[$key] = $config;
                                \Log::info("MediaGalleryUpload: Configuration found via '{$provider}'", $config);

                                return $config;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning("MediaGalleryUpload: Error accessing form via '{$provider}'", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Fallback: infer from the field name
        $config = $this->inferFieldConfig($key);

        if ($config) {
            $this->fieldConfigCache[$key] = $config;

            return $config;
        }

        return null;
    }

    /**
     * Infers the field configuration based on its name.
     */
    protected function inferFieldConfig(string $fieldName): ?array
    {
        \Log::info('MediaGalleryUpload: Inferring configuration', [
            'fieldName' => $fieldName,
        ]);

        // Detects if it's a video or image field by name
        $isVideoField = str_contains(strtolower($fieldName), 'video');

        $config = [
            'mediaType' => $isVideoField ? 'video' : 'image',
            'modelClass' => $isVideoField ? Video::class : Imagem::class,
            'allowMultiple' => true,
            'allowUpload' => true,
            'maxItems' => null,
        ];

        \Log::info('MediaGalleryUpload: Inferred configuration', $config);

        return $config;
    }

    /**
     * Processes the upload of a new media (image or video).
     */
    public function handleNewMediaUpload(string $uploadedFilename, string $statePath): void
    {
        try {
            \Log::info('MediaGalleryUpload: Starting handleNewMediaUpload', [
                'uploadedFilename' => $uploadedFilename,
                'statePath' => $statePath,
            ]);

            $config = $this->getFieldConfig($statePath);

            if (! $config) {
                throw new \Exception("Could not get configuration for field '$statePath'.");
            }

            $allowMultiple = $config['allowMultiple'];
            $mediaType = $config['mediaType'];
            $modelClass = $config['modelClass'];

            \Log::info('MediaGalleryUpload: Field settings', [
                'mediaType' => $mediaType,
                'modelClass' => $modelClass,
                'allowMultiple' => $allowMultiple,
            ]);

            // Remove the 'data.' prefix to access the $this->data array
            $dataKey = str_starts_with($statePath, 'data.') ? substr($statePath, 5) : $statePath;

            if (! $allowMultiple) {
                $currentState = $this->data[$dataKey] ?? [];

                if (is_string($currentState)) {
                    $currentState = json_decode($currentState, true) ?? [];
                }

                if (! empty($currentState)) {
                    Notification::make()
                        ->warning()
                        ->title('Limit Reached')
                        ->body('Only one media item is allowed.')
                        ->send();

                    return;
                }
            }

            $uploadKey = $dataKey . '_new_media';
            $tempFile = $this->data[$uploadKey] ?? null;

            \Log::info('MediaGalleryUpload: Checking temporary file', [
                'uploadKey' => $uploadKey,
                'tempFile_exists' => $tempFile !== null,
                'tempFile_class' => $tempFile ? get_class($tempFile) : 'null',
            ]);

            if (! $tempFile instanceof TemporaryUploadedFile) {
                throw new \Exception('Temporary file not found or invalid.');
            }

            $newPath = $tempFile->store('gallery', 'public');

            \Log::info('MediaGalleryUpload: File stored', [
                'newPath' => $newPath,
                'original_name' => $tempFile->getClientOriginalName(),
            ]);

            // 1. Cria o registro principal na tabela 'media'
            $media = \Devanderson\FilamentMediaGallery\Models\Media::create([
                'type' => $mediaType,
                'path' => $newPath,
                'original_name' => $tempFile->getClientOriginalName(),
                'mime_type' => $tempFile->getMimeType(),
                'size' => $tempFile->getSize(),
            ]);

            // 2. Cria o registro específico (Image ou Video) e o associa
            $specificMedia = new $modelClass;
            $specificMedia->media_id = $media->id;
            $specificMedia->save();

            if ($mediaType === 'video') {
                $thumbnail = $this->generateVideoThumbnail($newPath);
                // dd($thumbnail);
                if ($thumbnail) {
                    $specificMedia->update(['thumbnail_path' => $thumbnail]);
                }
            }

            \Log::info('MediaGalleryUpload: Media created', [
                'media_id' => $media->id,
                'specific_media_id' => $specificMedia->id,
                'model_class' => get_class($specificMedia),
            ]);

            $currentState = $this->data[$dataKey] ?? [];
            if (is_string($currentState)) {
                $currentState = json_decode($currentState, true) ?? []; // @phpstan-ignore-line
            }
            $currentState[] = $specificMedia->id;
            $this->data[$dataKey] = $currentState;

            Notification::make()
                ->success()
                ->title('Upload Complete')
                ->body('The new media has been added.')
                ->send();

            //            dd($media->thumbnail_url);
            $this->dispatch('gallery:media-added', media: [
                'id' => $specificMedia->id,
                'url' => $specificMedia->url,
                'original_name' => $specificMedia->original_name,
                'is_video' => $mediaType === 'video',
                'thumbnail_url' => ($mediaType === 'video' && method_exists($specificMedia, 'getThumbnailUrlAttribute'))
                    ? $specificMedia->thumbnail_url
                    : null,
            ]);

            \Log::info('MediaGalleryUpload: Upload completed successfully', [
                'media_id' => $media->id,
            ]);

        } catch (\Exception $e) {
            \Log::error('MediaGalleryUpload: Error in handleNewMediaUpload', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->danger()
                ->title('Upload Error')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }

    /**
     * Processes the upload of an edited image.
     */
    public function handleEditedMediaUpload($mediaId, $fileName, $statePath): void
    {
        try {
            \Log::info('MediaGalleryUpload: Starting handleEditedMediaUpload', [
                'mediaId' => $mediaId,
                'fileName' => $fileName,
                'statePath' => $statePath,
            ]);

            // Remove the 'data.' prefix if it exists
            $dataKey = str_starts_with($statePath, 'data.') ? substr($statePath, 5) : $statePath;
            $uploadKey = $dataKey . '_edited_media';
            $tempFile = $this->data[$uploadKey] ?? null;

            if (! $tempFile instanceof TemporaryUploadedFile) {
                throw new \Exception('Edited file not found.');
            }

            $image = Imagem::find($mediaId);
            if (! $image) {
                throw new \Exception('The original image was not found.');
            }

            if (Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }

            $newPath = $tempFile->store('gallery', 'public');

            $image->update([
                'path' => $newPath,
                'original_name' => $fileName,
                'size' => $tempFile->getSize(),
                'mime_type' => $tempFile->getMimeType(),
            ]);

            $this->data[$uploadKey] = null;

            Notification::make()
                ->success()
                ->title('Image Updated')
                ->body('The image has been edited and saved.')
                ->send();

            \Log::info('MediaGalleryUpload: Image edited successfully', [
                'image_id' => $image->id,
            ]);

        } catch (\Exception $e) {
            \Log::error('MediaGalleryUpload: Error in handleEditedMediaUpload', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->danger()
                ->title('Error Saving')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }

    /**
     * Loads more media for the gallery with pagination, filtered by media type.
     */
    public function loadMoreMedia(int $page, string $statePath): array
    {
        try {
            \Log::info('MediaGalleryUpload: Loading more media', [
                'page' => $page,
                'statePath' => $statePath,
            ]);

            $config = $this->getFieldConfig($statePath);

            if (! $config) {
                throw new \Exception("Could not get configuration for field '$statePath'.");
            }

            $mediaType = $config['mediaType'];
            $modelClass = $config['modelClass'];
            $perPage = 24;

            \Log::info('MediaGalleryUpload: Fetching media', [
                'mediaType' => $mediaType,
                'modelClass' => $modelClass,
                'page' => $page,
            ]);

            // Fetches only from the correct model (Image OR Video)
            $mediaItems = $modelClass::orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $mappedMedia = collect($mediaItems->items())->map(function ($media) use ($mediaType) {
                $data = [
                    'id' => $media->id,
                    'url' => $media->url,
                    'original_name' => $media->original_name,
                    'is_video' => $mediaType === 'video',
                ];

                // Add thumbnail_url for videos
                if ($mediaType === 'video' && method_exists($media, 'getThumbnailUrlAttribute')) {
                    $data['thumbnail_url'] = $media->thumbnail_url;
                }

                return $data;
            })->toArray();

            \Log::info('MediaGalleryUpload: Media loaded', [
                'mediaType' => $mediaType,
                'total' => count($mappedMedia),
                'hasMorePages' => $mediaItems->hasMorePages(),
            ]);

            return [
                'media' => $mappedMedia,
                'hasMore' => $mediaItems->hasMorePages(),
            ];
        } catch (\Exception $e) {
            \Log::error('MediaGalleryUpload: Error in loadMoreMedia', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['media' => [], 'hasMore' => false];
        }
    }

    /**
     * Syncs the images relationship based on the data from the form.
     */
    protected function syncImages(): void
    {
        $this->syncMedia('image');
    }

    /**
     * Syncs the videos relationship based on the data from the form.
     */
    protected function syncVideos(): void
    {
        $this->syncMedia('video');
    }

    /**
     * Generic method to sync media relationships (many-to-many).
     *
     * @param  string  $type  'image' or 'video'
     */
    private function syncMedia(string $type): void
    {
        // Determine the data key and relationship name dynamically.
        $dataKey = ($type === 'image') ? 'image_ids' : 'video_ids';
        $relationshipName = ($type === 'image') ? 'images' : 'videos';

        \Log::info("MediaGallerySync: Starting sync for {$type}s.", [
            'record_id' => $this->record->id,
            'data_key' => $dataKey,
            'raw_data' => $this->data[$dataKey] ?? 'not set',
        ]);

        if (! method_exists($this->record, $relationshipName)) {
            \Log::error("MediaGallerySync: Relationship '{$relationshipName}' does not exist on the model.", [
                'model' => get_class($this->record),
            ]);

            return;
        }

        $mediaIds = [];
        $rawIds = $this->data[$dataKey] ?? [];

        // Handle data coming as a JSON string or an array.
        if (is_string($rawIds)) {
            $decoded = json_decode($rawIds, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $mediaIds = $decoded;
            } else {
                \Log::warning("MediaGallerySync: Failed to decode JSON string for {$type} IDs.", [
                    'raw' => $rawIds,
                    'error' => json_last_error_msg(),
                ]);
            }
        } elseif (is_array($rawIds)) {
            $mediaIds = $rawIds;
        } elseif (is_numeric($rawIds)) {
            $mediaIds = [$rawIds];
        }

        // Clean and validate the IDs.
        $sanitizedIds = collect($mediaIds)
            ->filter(fn ($id) => ! empty($id) && is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        \Log::info("MediaGallerySync: Processed {$type} IDs for syncing.", [
            'sanitized_ids' => $sanitizedIds,
            'count' => count($sanitizedIds),
        ]);

        try {
            // Use sync() to manage the many-to-many relationship.
            $this->record->{$relationshipName}()->sync($sanitizedIds);

            // Dispara um evento para notificar o frontend que a sincronização ocorreu.
            $this->dispatch('gallery:media-synced', type: $type, ids: $sanitizedIds);
            \Log::info("MediaGallerySync: {$type}s synced successfully.", [
                'total' => count($sanitizedIds),
            ]);
        } catch (\Exception $e) {
            \Log::error("MediaGallerySync: Error while syncing {$type}s.", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    //    protected function afterCreate(): void
    //    {
    //        $this->syncImages(); // Sincroniza o relacionamento 'images'
    //    }
}
