<?php

namespace App\Traits;

use App\Models\Video;
use App\Models\Imagem;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait ProcessaUploadGaleria
{
    /**
     * Cache de configurações dos campos para evitar buscas repetidas
     */
    protected array $fieldConfigCache = [];

    /**
     * Obtém as configurações de um campo de mídia.
     */
    protected function getFieldConfig(string $statePath): ?array
    {
        // Remove o prefixo 'data.' se existir
        $key = str_starts_with($statePath, 'data.') ? substr($statePath, 5) : $statePath;

        // Verifica se já está em cache
        if (isset($this->fieldConfigCache[$key])) {
            return $this->fieldConfigCache[$key];
        }

        \Log::info('ProcessaUploadGaleria: Buscando configuração do campo', [
            'statePath' => $statePath,
            'key' => $key
        ]);

        // Tenta acessar o form se existir
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
                        \Log::info('ProcessaUploadGaleria: Configuração obtida do componente', $config);
                        return $config;
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('ProcessaUploadGaleria: Erro ao acessar form', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Fallback: inferir pelo nome do campo
        $config = $this->inferFieldConfig($key);

        if ($config) {
            $this->fieldConfigCache[$key] = $config;
            return $config;
        }

        return null;
    }

    /**
     * Infere a configuração do campo baseado no nome
     */
    protected function inferFieldConfig(string $fieldName): ?array
    {
        \Log::info('ProcessaUploadGaleria: Inferindo configuração', [
            'fieldName' => $fieldName
        ]);

        // Detecta se é campo de vídeo ou imagem pelo nome
        $isVideoField = str_contains(strtolower($fieldName), 'video');

        $config = [
            'mediaType' => $isVideoField ? 'video' : 'image',
            'modelClass' => $isVideoField ? Video::class : Imagem::class,
            'allowMultiple' => true,
            'allowUpload' => true,
            'maxItems' => null,
        ];

        \Log::info('ProcessaUploadGaleria: Configuração inferida', $config);

        return $config;
    }

    /**
     * Processa o upload de uma nova mídia (imagem ou vídeo).
     */
    public function handleNewMediaUpload(string $uploadedFilename, string $statePath): void
    {
        try {
            \Log::info('ProcessaUploadGaleria: Iniciando handleNewMediaUpload', [
                'uploadedFilename' => $uploadedFilename,
                'statePath' => $statePath
            ]);

            $config = $this->getFieldConfig($statePath);

            if (!$config) {
                throw new \Exception("Não foi possível obter configuração do campo '$statePath'.");
            }

            $allowMultiple = $config['allowMultiple'];
            $mediaType = $config['mediaType'];
            $modelClass = $config['modelClass'];

            \Log::info('ProcessaUploadGaleria: Configurações do campo', [
                'mediaType' => $mediaType,
                'modelClass' => $modelClass,
                'allowMultiple' => $allowMultiple
            ]);

            // Remove o prefixo 'data.' para acessar o array $this->data
            $dataKey = str_starts_with($statePath, 'data.') ? substr($statePath, 5) : $statePath;

            if (!$allowMultiple) {
                $currentState = $this->data[$dataKey] ?? [];

                if (is_string($currentState)) {
                    $currentState = json_decode($currentState, true) ?? [];
                }

                if (!empty($currentState)) {
                    Notification::make()
                        ->warning()
                        ->title('Limite Atingido')
                        ->body('Apenas uma mídia é permitida.')
                        ->send();
                    return;
                }
            }

            $uploadKey = $dataKey . '_new_media';
            $tempFile = $this->data[$uploadKey] ?? null;

            \Log::info('ProcessaUploadGaleria: Verificando arquivo temporário', [
                'uploadKey' => $uploadKey,
                'tempFile_exists' => $tempFile !== null,
                'tempFile_class' => $tempFile ? get_class($tempFile) : 'null'
            ]);

            if (!$tempFile instanceof TemporaryUploadedFile) {
                throw new \Exception('Arquivo temporário não encontrado ou inválido.');
            }

            $newPath = $tempFile->store('galeria', 'public');

            \Log::info('ProcessaUploadGaleria: Arquivo armazenado', [
                'newPath' => $newPath,
                'original_name' => $tempFile->getClientOriginalName()
            ]);

            $media = $modelClass::create([
                'path' => $newPath,
                'nome_original' => $tempFile->getClientOriginalName(),
                'mime_type' => $tempFile->getMimeType(),
                'tamanho' => $tempFile->getSize(),
            ]);

            if ($mediaType === 'video') {
                $thumbnail = $this->gerarThumbnailVideo($newPath);
//dd($thumbnail);
                if ($thumbnail) {
                    $media->update(['thumbnail_path' => $thumbnail]);
                }
            }

            \Log::info('ProcessaUploadGaleria: Mídia criada', [
                'media_id' => $media->id,
                'model_class' => $modelClass
            ]);

            $currentState = $this->data[$dataKey] ?? [];
            if (is_string($currentState)) {
                $currentState = json_decode($currentState, true) ?? [];
            }
            $currentState[] = $media->id;
            $this->data[$dataKey] = $currentState;

            $this->data[$uploadKey] = null;

            Notification::make()
                ->success()
                ->title('Upload Concluído')
                ->body('A nova mídia foi adicionada.')
                ->send();

//            dd($media->thumbnail_url);
            $this->dispatch('galeria:media-adicionada', media: [
                'id' => $media->id,
                'url' => $media->url,
                'nome_original' => $media->nome_original,
                'is_video' => $mediaType === 'video',
                'thumbnail_url' => ($mediaType === 'video' &&
                    method_exists($media, 'getThumbnailUrlAttribute'))
                    ? $media->thumbnail_url
                    : null,
            ]);

            \Log::info('ProcessaUploadGaleria: Upload concluído com sucesso', [
                'media_id' => $media->id
            ]);

        } catch (\Exception $e) {
            \Log::error('ProcessaUploadGaleria: Erro em handleNewMediaUpload', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->danger()
                ->title('Erro no Upload')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }

    /**
     * Processa o upload de uma imagem editada.
     */
    public function handleEditedMediaUpload($mediaId, $fileName, $statePath): void
    {
        try {
            \Log::info('ProcessaUploadGaleria: Iniciando handleEditedMediaUpload', [
                'mediaId' => $mediaId,
                'fileName' => $fileName,
                'statePath' => $statePath
            ]);

            // Remove o prefixo 'data.' se existir
            $dataKey = str_starts_with($statePath, 'data.') ? substr($statePath, 5) : $statePath;
            $uploadKey = $dataKey . '_edited_media';
            $tempFile = $this->data[$uploadKey] ?? null;

            if (!$tempFile instanceof TemporaryUploadedFile) {
                throw new \Exception('Arquivo editado não encontrado.');
            }

            $imagem = Imagem::find($mediaId);
            if (!$imagem) {
                throw new \Exception('A imagem original não foi encontrada.');
            }

            if (Storage::disk('public')->exists($imagem->path)) {
                Storage::disk('public')->delete($imagem->path);
            }

            $newPath = $tempFile->store('galeria', 'public');

            $imagem->update([
                'path' => $newPath,
                'nome_original' => $fileName,
                'tamanho' => $tempFile->getSize(),
                'mime_type' => $tempFile->getMimeType(),
            ]);

            $this->data[$uploadKey] = null;

            Notification::make()
                ->success()
                ->title('Imagem Atualizada')
                ->body('A imagem foi editada e salva.')
                ->send();

            \Log::info('ProcessaUploadGaleria: Imagem editada com sucesso', [
                'imagem_id' => $imagem->id
            ]);

        } catch (\Exception $e) {
            \Log::error('ProcessaUploadGaleria: Erro em handleEditedMediaUpload', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->danger()
                ->title('Erro ao Salvar')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }

    /**
     * Carrega mais mídias para a galeria com paginação.
     * AGORA FILTRA POR TIPO DE MÍDIA!
     */
    public function carregarMaisMedias(int $pagina = 1, string $statePath): array
    {
        try {
            \Log::info('ProcessaUploadGaleria: Carregando mais mídias', [
                'pagina' => $pagina,
                'statePath' => $statePath
            ]);

            $config = $this->getFieldConfig($statePath);

            if (!$config) {
                throw new \Exception("Não foi possível obter configuração do campo '$statePath'.");
            }

            $mediaType = $config['mediaType'];
            $modelClass = $config['modelClass'];
            $perPage = 24;

            \Log::info('ProcessaUploadGaleria: Buscando mídias', [
                'mediaType' => $mediaType,
                'modelClass' => $modelClass,
                'pagina' => $pagina
            ]);

            // AQUI É A CHAVE: busca apenas do modelo correto (Imagem OU Video)
            $medias = $modelClass::orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $pagina);

            $mappedMedias = collect($medias->items())->map(function ($media) use ($mediaType) {
                $data = [
                    'id' => $media->id,
                    'url' => $media->url,
                    'nome_original' => $media->nome_original,
                    'is_video' => $mediaType === 'video',
                ];

                // Adiciona thumbnail_url para vídeos
                if ($mediaType === 'video' && method_exists($media, 'getThumbnailUrlAttribute')) {
                    $data['thumbnail_url'] = $media->thumbnail_url;
                }

                return $data;
            })->toArray();

            \Log::info('ProcessaUploadGaleria: Mídias carregadas', [
                'mediaType' => $mediaType,
                'total' => count($mappedMedias),
                'hasMorePages' => $medias->hasMorePages()
            ]);

            return [
                'medias' => $mappedMedias,
                'temMais' => $medias->hasMorePages(),
            ];
        } catch (\Exception $e) {
            \Log::error('ProcessaUploadGaleria: Erro em carregarMaisMedias', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['medias' => [], 'temMais' => false];
        }
    }
}
