@php
    // Registra os assets necessários para o componente
   \Filament\Support\Facades\FilamentAsset::register([
        // Libs externas
        \Filament\Support\Assets\Css::make('cropper-css', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css'),
        \Filament\Support\Assets\Js::make('cropper-js', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js'),
        
        // Assets do Pacote (assumindo que você irá compilá-los para o diretório 'public/vendor/filament-media-gallery')
        \Filament\Support\Assets\Css::make('filament-media-gallery-styles', asset('vendor/filament-media-gallery/css/galeria-midia-field.css')),
        \Filament\Support\Assets\Js::make('filament-media-gallery-scripts', asset('vendor/filament-media-gallery/js/galeria-midia-field.js'))->defer(),
    ]);
    
   $mediaType = $getMediaType(); // 'image' ou 'video'
   $modelClass = $getModelClass();
   $allowMultiple = $getAllowMultiple();
   $maxItems = $getMaxItems();
   $allowUpload = $getAllowUpload();
   $allowImageEditor = $getAllowImageEditor() && $mediaType === 'image'; // Editor só para imagens
    $imageEditorAspectRatios = $getImageEditorAspectRatios();
    $dadosIniciaisGaleria = $getMediasDisponiveis();
 
    // Passa as traduções para o JavaScript de forma segura
    $translations = [
        'limit_reached' => [
            'title' => __('filament-media-gallery::filament-media-gallery.notifications.limit_reached.title'),
            'single' => __('filament-media-gallery::filament-media-gallery.notifications.limit_reached.single'),
        ],
        'processing_error' => [
            'title' => __('filament-media-gallery::filament-media-gallery.notifications.processing_error.title'),
            'body' => __('filament-media-gallery::filament-media-gallery.notifications.processing_error.body'),
        ],
        'upload_error' => [
            'title' => __('filament-media-gallery::filament-media-gallery.notifications.upload_error.title'),
            'body' => __('filament-media-gallery::filament-media-gallery.notifications.upload_error.body'),
        ],
        'image_edited_success' => [
            'title' => __('filament-media-gallery::filament-media-gallery.notifications.image_edited_success.title'),
            'body' => __('filament-media-gallery::filament-media-gallery.notifications.image_edited_success.body'),
        ],
        'save_error' => [
            'title' => __('filament-media-gallery::filament-media-gallery.notifications.save_error.title'),
            'body' => __('filament-media-gallery::filament-media-gallery.notifications.save_error.body'),
        ],
    ];
 
   $fieldId = 'galeria-midia-' . $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="{{ $fieldId }}"
        wire:ignore.self
        x-data="imageGalleryPicker({
            state: $wire.get('{{ $getStatePath() }}') || [],
            statePath: '{{ $getStatePath() }}',
            mediaType: @js($mediaType),
            initialMedias: @js($dadosIniciaisGaleria['medias']),
            temMaisPaginas: @js($dadosIniciaisGaleria['temMais']),
            allowMultiple: @js($allowMultiple),
            maxItems: @js($maxItems),
            aspectRatios: @js($imageEditorAspectRatios ?? []),
            translations: @js($translations)
        })"
        x-init="init()"
    >

        {{-- Mídias Selecionadas --}}
        <div x-show="selecionadas.length > 0" class="g-section">
            <label class="g-label">
                <span x-text="mediaType === 'image' ? '📸 {{ __('filament-media-gallery::filament-media-gallery.labels.selected_images') }}' : '🎬 {{ __('filament-media-gallery::filament-media-gallery.labels.selected_videos') }}'"></span>
            </label>
            <div class="g-grid">
                {{-- Renderiza as mídias disponíveis e o Alpine controla a visibilidade --}}
                <template x-for="media in mediasDisponiveis" :key="media.id">
                    <div x-show="isSelected(media.id)" class="g-thumbnail g-thumbnail-selected group">
                        {{-- Preview de Imagem --}}
                        <template x-if="mediaType === 'image' && !media.is_video">
                            <img :src="media.url" :alt="media.nome_original">
                        </template>
                        {{-- Preview de Vídeo (usa thumbnail se disponível) --}}
                        <template x-if="mediaType === 'video' && media.is_video">
                            <div class="g-video-preview">
                                <template x-if="media.thumbnail_url">
                                    <img :src="media.thumbnail_url" :alt="media.nome_original" class="g-video-thumbnail">
                                </template>
                                <template x-if="!media.thumbnail_url">
                                    <div class="g-video-placeholder">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4.5 4.5a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-9a3 3 0 0 0-3-3h-15Zm10.253 5.747a.75.75 0 0 1 1.246-.547l3.001 2.25a.75.75 0 0 1 0 1.094l-3.001 2.25a.75.75 0 0 1-1.246-.547v-4.5Z"></path></svg>
                                    </div>
                                </template>
                                <div class="g-video-play-overlay">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                </div>
                            </div>
                        </template>

                        <div class="g-thumbnail-actions">
                            @if($allowImageEditor)
                                <button type="button" x-show="mediaType === 'image' && !media.is_video"
                                        @click.stop="abrirEditor(media.id, media.url)"
                                        title="{{ __('filament-media-gallery::filament-media-gallery.buttons.edit_image') }}"
                                        class="g-thumbnail-btn-edit">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                            @endif

                            <button type="button"
                                    @click.stop="removerMedia(media.id)"
                                    title="{{ __('filament-media-gallery::filament-media-gallery.buttons.remove_item') }}"
                                    class="g-thumbnail-btn-remove">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="g-thumbnail-name" x-text="media.nome_original"></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Arquivos Carregados para Upload --}}
        @if($allowUpload)
            <div x-show="uploadedFiles.length > 0" class="g-section">
                <label class="g-label">
                    📁 {{ __('filament-media-gallery::filament-media-gallery.labels.ready_for_upload') }}
                </label>
                <div class="g-grid">
                    <template x-for="(file, index) in uploadedFiles" :key="file.name">
                        <div class="g-thumbnail g-thumbnail-upload group">
                            {{-- Preview condicional baseado no tipo --}}
                            <template x-if="mediaType === 'image'">
                                <img :src="URL.createObjectURL(file)" :alt="file.name">
                            </template>
                            <template x-if="mediaType === 'video'">
                                <div class="g-video-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4.5 4.5a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-9a3 3 0 0 0-3-3h-15Zm10.253 5.747a.75.75 0 0 1 1.246-.547l3.001 2.25a.75.75 0 0 1 0 1.094l-3.001 2.25a.75.75 0 0 1-1.246-.547v-4.5Z"></path></svg>
                                </div>
                            </template>
                            <button type="button"
                                    @click="removeUploadedFile(index)"
                                    class="g-thumbnail-btn-remove">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                            <div class="g-thumbnail-name" x-text="file.name"></div>
                        </div>
                    </template>
                </div>
            </div>
        @endif

        {{-- Área de Botões de Ação - SEPARADA --}}
        <div class="g-actions-container">
            <div class="g-actions-title">
                ⚡ {{ __('filament-media-gallery::filament-media-gallery.labels.available_actions') }}
            </div>

            {{-- INDICADOR DE PROGRESSO DO UPLOAD --}}
            <div x-show="uploading"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95"
                 class="g-upload-progress">
                <div class="g-upload-spinner"></div>
                <span x-text="uploadProgress"></span>
            </div>

            <div class="g-actions">
                <button type="button"
                        @click="modalAberto = true"
                        class="g-btn g-btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span x-text="mediaType === 'image' ? '{{ __('filament-media-gallery::filament-media-gallery.buttons.select_from_gallery') }}' : '{{ __('filament-media-gallery::filament-media-gallery.buttons.select_from_videos') }}'"></span>
                </button>

                @if($allowUpload)
                    <label class="g-btn g-btn-success" :class="{ 'opacity-50 cursor-not-allowed': uploading }">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <span x-text="mediaType === 'image' ? '{{ __('filament-media-gallery::filament-media-gallery.buttons.upload_images') }}' : '{{ __('filament-media-gallery::filament-media-gallery.buttons.upload_videos') }}'"></span>
                        <input type="file"
                               :accept="mediaType === 'image' ? 'image/png,image/jpeg,image/jpg,image/webp' : 'video/mp4,video/webm'"
                               @change="handleMediaUpload($event)"
                               :disabled="uploading"
                               class="g-hidden-input">
                    </label>
                @endif
            </div>
        </div>

        {{-- Modal da Galeria --}}
        <div x-show="modalAberto"
             x-cloak
             class="g-modal-overlay"
             style="display: none;">
            <div class="g-modal-container">
                <div @click.away="modalAberto = false" class="g-modal-content">
                    <div class="g-modal-header">
                        <h3 class="g-modal-title">
                            <span x-text="mediaType === 'image' ? '{{ __('filament-media-gallery::filament-media-gallery.labels.image_gallery') }}' : '{{ __('filament-media-gallery::filament-media-gallery.labels.video_gallery') }}'"></span>
                        </h3>
                        <button type="button" @click="modalAberto = false" class="g-modal-close-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="g-modal-grid">
                        <template x-if="mediasDisponiveis.length > 0">
                            <template x-for="media in mediasDisponiveis" :key="media.id">
                                <div @click="toggleMedia(media.id)"
                                     :class="{ 'g-modal-thumb-selected': isSelected(media.id) }"
                                     class="g-modal-thumb">
                                    {{-- Exibe imagem OU vídeo baseado no mediaType do campo --}}
                                    <template x-if="mediaType === 'image' && !media.is_video">
                                        <img :src="media.url" :alt="media.nome_original" class="g-modal-thumb-img">
                                    </template>
                                    <template x-if="mediaType === 'video' && media.is_video">
                                        <div class="g-video-preview g-modal-video-preview">
                                            <template x-if="media.thumbnail_url">
                                                <img :src="media.thumbnail_url" :alt="media.nome_original" class="g-modal-thumb-img">
                                            </template>
                                            <template x-if="!media.thumbnail_url">
                                                <div class="g-video-placeholder g-video-placeholder-modal">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4.5 4.5a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-9a3 3 0 0 0-3-3h-15Zm10.253 5.747a.75.75 0 0 1 1.246-.547l3.001 2.25a.75.75 0 0 1 0 1.094l-3.001 2.25a.75.75 0 0 1-1.246-.547v-4.5Z"></path></svg>
                                                </div>
                                            </template>
                                            <div class="g-video-play-overlay">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white">
                                                    <path d="M8 5v14l11-7z"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="isSelected(media.id)"
                                         class="g-modal-thumb-check">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <div class="g-modal-thumb-name" x-text="media.nome_original"></div>
                                </div>
                            </template>
                        </template>
                        <template x-if="mediasDisponiveis.length === 0">
                            <div class="g-modal-empty">
                                <span x-text="mediaType === 'image' ? '{{ __('filament-media-gallery::filament-media-gallery.empty.no_images') }}' : '{{ __('filament-media-gallery::filament-media-gallery.empty.no_videos') }}'"></span>
                            </div>
                        </template>
                    </div>

                    <div class="g-modal-footer">
                        <button type="button"
                                @click="modalAberto = false"
                                class="g-btn g-btn-primary">
                            {{ __('filament-media-gallery::filament-media-gallery.buttons.confirm_selection') }}
                        </button>
                        <button type="button"
                                x-show="temMaisPaginas"
                                @click="carregarMais()"
                                :disabled="carregandoMais"
                                class="g-btn g-btn-secondary"
                                x-text="carregandoMais ? '{{ __('filament-media-gallery::filament-media-gallery.buttons.loading') }}' : '{{ __('filament-media-gallery::filament-media-gallery.buttons.load_more') }}'">
                            {{ __('filament-media-gallery::filament-media-gallery.buttons.load_more') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal do Editor de Imagem (APENAS PARA IMAGENS) --}}
        @if($allowImageEditor)
            <div x-show="editorAberto" x-cloak class="g-modal-overlay" style="display: none;">
                <div class="g-modal-container">
                    <div @click.away="fecharEditor()" class="g-modal-content g-modal-editor">

                        <div class="g-modal-header">
                            <h3 class="g-modal-title">{{ __('filament-media-gallery::filament-media-gallery.labels.image_editor') }}</h3>
                            <button type="button" @click="fecharEditor()" class="g-modal-close-btn">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Toolbar do Editor -->
                        <div class="g-editor-toolbar">
                            <div class="g-toolbar-group">
                                <button type="button" @click="resetarImagem()" class="g-toolbar-btn" title="{{ __('filament-media-gallery::filament-media-gallery.editor.reset') }}">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="g-toolbar-group">
                                <button type="button" @click="rotacionar(-90)" class="g-toolbar-btn" title="{{ __('filament-media-gallery::filament-media-gallery.editor.rotate_left') }}">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                    </svg>
                                </button>
                                <button type="button" @click="rotacionar(90)" class="g-toolbar-btn" title="{{ __('filament-media-gallery::filament-media-gallery.editor.rotate_right') }}">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="g-toolbar-group">
                                <button type="button" @click="espelharHorizontal()" class="g-toolbar-btn" title="{{ __('filament-media-gallery::filament-media-gallery.editor.flip_horizontal') }}">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                </button>
                                <button type="button" @click="espelharVertical()" class="g-toolbar-btn" title="{{ __('filament-media-gallery::filament-media-gallery.editor.flip_vertical') }}">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="g-toolbar-group">
                                <button type="button" @click="zoom(0.1)" class="g-toolbar-btn" title="{{ __('filament-media-gallery::filament-media-gallery.editor.zoom_in') }}">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                                    </svg>
                                </button>
                                <button type="button" @click="zoom(-0.1)" class="g-toolbar-btn" title="{{ __('filament-media-gallery::filament-media-gallery.editor.zoom_out') }}">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"></path>
                                    </svg>
                                </button>
                            </div>

                            <template x-if="aspectRatios && aspectRatios.length > 0">
                                <div class="g-toolbar-group">
                                    <label class="g-toolbar-label">{{ __('filament-media-gallery::filament-media-gallery.editor.aspect_ratio') }}:</label>
                                    <select @change="mudarAspectRatio($event.target.value)" class="g-toolbar-select">
                                        <option value="free" :selected="currentAspectRatio === 'free'">{{ __('filament-media-gallery::filament-media-gallery.editor.free') }}</option>
                                        <template x-for="(ratio, index) in aspectRatios" :key="index">
                                            <option :value="ratio" :selected="currentAspectRatio === ratio" x-text="ratio"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>

                        <div class="g-editor-container">
                            <div class="g-editor-wrapper">
                                <img x-ref="imageEditorCanvas" class="g-editor-canvas">
                            </div>
                        </div>

                        <div class="g-modal-footer">
                            <button type="button"
                                    @click="fecharEditor()"
                                    class="g-btn g-btn-secondary">
                                {{ __('filament-media-gallery::filament-media-gallery.buttons.cancel') }}
                            </button>
                            <button type="button"
                                    @click="salvarImagemEditada()"
                                    class="g-btn g-btn-primary">
                                {{ __('filament-media-gallery::filament-media-gallery.buttons.save_changes') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-dynamic-component>
