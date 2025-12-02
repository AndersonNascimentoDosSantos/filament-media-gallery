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

        // Try to access the form if it exists
        if (property_exists($this, 'form') && method_exists($this, 'form')) {
            try {
                $form = $this->form($this->makeForm());
                $components = $form->getComponents(true);

                foreach ($components as $component) {
                    if ($component->getName() === $key &&
                        method_exists($component, 'getMediaType')) {

                        $config = [
                            'mediaType' => $component->getMediaType(),
                            'modelClass' => $component->getModelClass(),
                            'allowMultiple' => $component->getAllowMultiple(),
                            'allowUpload' => $component->getAllowUpload(),
                            'maxItems' => $component->getMaxItems(),
                        ];

                        $this->fieldConfigCache[$key] = $config;
                        \Log::info('MediaGalleryUpload: Configuration obtained from component', $config);

                        return $config;
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('MediaGalleryUpload: Error accessing form', [
                    'error' => $e->getMessage(),
                ]);
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

            $media = $modelClass::create([
                'path' => $newPath,
                'original_name' => $tempFile->getClientOriginalName(),
                'mime_type' => $tempFile->getMimeType(),
                'size' => $tempFile->getSize(),
            ]);

            if ($mediaType === 'video') {
                $thumbnail = $this->generateVideoThumbnail($newPath);
                // dd($thumbnail);
                if ($thumbnail) {
                    $media->update(['thumbnail_path' => $thumbnail]);
                }
            }

            \Log::info('MediaGalleryUpload: Media created', [
                'media_id' => $media->id,
                'model_class' => $modelClass,
            ]);

            $currentState = $this->data[$dataKey] ?? [];
            if (is_string($currentState)) {
                $currentState = json_decode($currentState, true) ?? []; // @phpstan-ignore-line
            }
            $currentState[] = $media->id;
            $this->data[$dataKey] = $currentState;

            $this->data[$uploadKey] = null;

            Notification::make()
                ->success()
                ->title('Upload Complete')
                ->body('The new media has been added.')
                ->send();

            //            dd($media->thumbnail_url);
            $this->dispatch('gallery:media-added', media: [
                'id' => $media->id,
                'url' => $media->url,
                'original_name' => $media->original_name,
                'is_video' => $mediaType === 'video',
                'thumbnail_url' => ($mediaType === 'video' &&
                    method_exists($media, 'getThumbnailUrlAttribute'))
                    ? $media->thumbnail_url
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
}
