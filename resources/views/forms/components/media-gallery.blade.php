@php
    // Carrega os assets do Cropper.js
   \Filament\Support\Facades\FilamentAsset::register([
       \Filament\Support\Assets\Css::make('cropper-css', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css'),
       \Filament\Support\Assets\Js::make('cropper-js', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js'),
   ]);

   $mediaType = $getMediaType(); // 'image' ou 'video'
   $modelClass = $getModelClass();
   $allowMultiple = $getAllowMultiple();
   $maxItems = $getMaxItems();
   $allowUpload = $getAllowUpload();
   $allowImageEditor = $getAllowImageEditor() && $mediaType === 'image'; // Editor só para imagems
    $imageEditorAspectRatios = $getImageEditorAspectRatios();
    $dadosIniciaisGaleria = $getMediasDisponiveis();

   // IMPORTANTE: Busca apenas as mídias DO TIPO CORRETO que já estão selecionadas
   $initialSelectedMediaObjects = $modelClass::find($getState() ?? [])->map(fn ($media) => [
       'id' => $media->id,
       'url' => $media->url,
       'original_name' => $media->original_name,
       'is_video' => $mediaType === 'video',
       'thumbnail_url' => $mediaType === 'video' ? $media->thumbnail_url : null,
   ]);

   $fieldId = 'galeria-midia-' . $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="{{ $fieldId }}"
        wire:ignore.self
        x-data="imageGalleryPicker({
             state: @js($getState() ?? []),
             statePath: '{{ $getStatePath() }}',
             mediaType: @js($mediaType),
             initialSelectedMedia: @js($initialSelectedMediaObjects),
             initialMedias: @js($dadosIniciaisGaleria['medias']),
             temMaisPaginas: @js($dadosIniciaisGaleria['temMais']),
             allowMultiple: @js($allowMultiple),
             maxItems: @js($maxItems),
             aspectRatios: @js($imageEditorAspectRatios ?? [])
         })"
        x-init="init()"
    >
        {{-- Mídias Selecionadas --}}
        <div x-show="selectedMediaObjects.length > 0" class="g-section">
            <label class="g-label">
                <span x-text="mediaType === 'image' ? '📸 Imagens Selecionadas' : '🎬 Vídeos Selecionados'"></span>
            </label>
            <div class="g-grid">
                {{-- Renderiza diretamente os objetos de mídia selecionados --}}
                <template x-for="media in selectedMediaObjects" :key="media.id">
                    <div class="g-thumbnail g-thumbnail-selected group">
                        {{-- Preview de Imagem --}}
                        <template x-if="!media.is_video">
                            <img :src="media.url" :alt="media.original_name">
                        </template>
                        {{-- Preview de Vídeo (usa thumbnail se disponível) --}}
                        <template x-if="media.is_video">
                            <div class="g-video-preview">
                                <template x-if="media.thumbnail_url">
                                    <img :src="media.thumbnail_url" :alt="media.original_name" class="g-video-thumbnail">
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
                                <button type="button" x-show="!media.is_video"
                                        @click.stop="abrirEditor(media.id, media.url)"
                                        title="Editar Imagem"
                                        class="g-thumbnail-btn-edit">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                            @endif

                            <button type="button"
                                    @click.stop="removerMedia(media.id)"
                                    title="Remover Item"
                                    class="g-thumbnail-btn-remove">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="g-thumbnail-name" x-text="media.original_name"></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Arquivos Carregados para Upload --}}
        @if($allowUpload)
            <div x-show="uploadedFiles.length > 0" class="g-section">
                <label class="g-label">
                    📁 Arquivos Prontos para Upload
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
                ⚡ Ações Disponíveis
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
                    <span x-text="mediaType === 'image' ? 'Selecionar da Galeria' : 'Selecionar da Videoteca'"></span>
                </button>

                @if($allowUpload)
                    <label class="g-btn g-btn-success" :class="{ 'opacity-50 cursor-not-allowed': uploading }">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <span x-text="mediaType === 'image' ? 'Upload de Imagens' : 'Upload de Vídeos'"></span>
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
                            <span x-text="mediaType === 'image' ? 'Galeria de Imagens' : 'Videoteca'"></span>
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
                                    <template x-if="!media.is_video">
                                        <img :src="media.url" :alt="media.original_name" class="g-modal-thumb-img">
                                    </template>
                                    <template x-if="media.is_video">
                                        <div class="g-video-preview g-modal-video-preview">
                                            <template x-if="media.thumbnail_url">
                                                <img :src="media.thumbnail_url" :alt="media.original_name" class="g-modal-thumb-img">
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
                                    <div class="g-modal-thumb-name" x-text="media.original_name"></div>
                                </div>
                            </template>
                        </template>
                        <template x-if="mediasDisponiveis.length === 0">
                            <div class="g-modal-empty">
                                <span x-text="mediaType === 'image' ? 'Nenhuma imagem disponível' : 'Nenhum vídeo disponível'"></span>
                            </div>
                        </template>
                    </div>

                    <div class="g-modal-footer">
                        <button type="button"
                                @click="modalAberto = false"
                                class="g-btn g-btn-primary">
                            Confirmar Seleção
                        </button>
                        <button type="button"
                                x-show="temMaisPaginas"
                                @click="carregarMais()"
                                :disabled="carregandoMais"
                                class="g-btn g-btn-secondary"
                                x-text="carregandoMais ? 'Carregando...' : 'Carregar Mais'">
                            Carregar Mais
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
                            <h3 class="g-modal-title">Editor de Imagem</h3>
                            <button type="button" @click="fecharEditor()" class="g-modal-close-btn">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Toolbar do Editor -->
                        <div class="g-editor-toolbar">
                            <div class="g-toolbar-group">
                                <button type="button" @click="resetarImagem()" class="g-toolbar-btn" title="Resetar">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="g-toolbar-group">
                                <button type="button" @click="rotacionar(-90)" class="g-toolbar-btn" title="Rotacionar Esquerda">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                    </svg>
                                </button>
                                <button type="button" @click="rotacionar(90)" class="g-toolbar-btn" title="Rotacionar Direita">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="g-toolbar-group">
                                <button type="button" @click="espelharHorizontal()" class="g-toolbar-btn" title="Espelhar Horizontal">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                </button>
                                <button type="button" @click="espelharVertical()" class="g-toolbar-btn" title="Espelhar Vertical">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="g-toolbar-group">
                                <button type="button" @click="zoom(0.1)" class="g-toolbar-btn" title="Zoom In">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                                    </svg>
                                </button>
                                <button type="button" @click="zoom(-0.1)" class="g-toolbar-btn" title="Zoom Out">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"></path>
                                    </svg>
                                </button>
                            </div>

                            <template x-if="aspectRatios && aspectRatios.length > 0">
                                <div class="g-toolbar-group">
                                    <label class="g-toolbar-label">Proporção:</label>
                                    <select @change="mudarAspectRatio($event.target.value)" class="g-toolbar-select">
                                        <option value="free" :selected="currentAspectRatio === 'free'">Livre</option>
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
                                Cancelar
                            </button>
                            <button type="button"
                                    @click="salvarImagemEditada()"
                                    class="g-btn g-btn-primary">
                                Salvar Alterações
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-dynamic-component>

<script>
    function imageGalleryPicker(config) {
        return {
            selecionadas: config.state,
            selectedMediaObjects: config.initialSelectedMedia,
            mediasDisponiveis: config.initialMedias,
            modalAberto: false,
            mediaType: config.mediaType, // 'image' ou 'video'
            uploadedFiles: [],
            editorAberto: false,
            cropper: null,
            imagemParaEditarId: null,
            imagemParaEditarUrl: null,
            arquivoParaEditar: null,
            aspectRatios: config.aspectRatios,
            currentAspectRatio: config.aspectRatios.length > 0 ? config.aspectRatios[0] : 'free',
            uploading: false,
            uploadProgress: '',
            paginaAtual: 1,
            temMaisPaginas: config.temMaisPaginas,
            carregandoMais: false,

            init() {
                console.log('🖼️ Galeria Iniciada - Tipo:', this.mediaType, 'Mídias:', this.mediasDisponiveis.length);
                // Força a sincronização inicial para garantir que os objetos sejam carregados
                this.syncSelectedObjects();
                console.log('Estado inicial (IDs):', JSON.parse(JSON.stringify(this.selecionadas)));
                console.log('Objetos Iniciais:', JSON.parse(JSON.stringify(this.selectedMediaObjects)));


                this.$watch('$wire.get(\'' + config.statePath + '\')', (newState) => {
                    if (newState && JSON.stringify(this.selecionadas) !== JSON.stringify(newState)) {
                        this.selecionadas = newState || [];
                        this.syncSelectedObjects();
                    }
                });

                // Ouve o evento de sincronização para atualizar a lista de mídias, se necessário.
                Livewire.on('gallery:media-synced', ({ type, ids }) => {
                    console.log('🔄 Recebendo mídias filtradas:', medias);
                    medias.forEach(media => {
                        // Só adiciona se for do tipo correto
                        if (media.is_video === (this.mediaType === 'video')) {
                            if (!this.mediasDisponiveis.some(local => local.id === media.id)) {
                                this.mediasDisponiveis.push(media);
                            }
                        }
                    });
                });

                Livewire.on('gallery:media-added', ({ media }) => {
                    console.log('✨ Nova mídia adicionada:', media);
                    // Verifica se é do tipo correto antes de adicionar

                    if (media.is_video !== (this.mediaType === 'video')) {
                        return;
                    }


                    // Adiciona a nova mídia à lista de mídias disponíveis se ainda não existir.
                    if (!this.mediasDisponiveis.some(local => local.id === media.id)) {
                        this.mediasDisponiveis.unshift(media);
                    }


                // Força a atualização do estado e a sincronização dos objetos.
                this.selecionadas = this.$wire.get(config.statePath);
                this.syncSelectedObjects();
                });
                // 1. Ouvir o evento emitido pelo trait Livewire
                $wire.on('gallery:media-added', (event) => {
                    // A nova mídia é event.media
                    const newMedia = event.media;

                    // 2. Adicionar o ID ao estado do Filament (que é sincronizado com o Livewire)
                    // Isso garante que o estado do campo seja persistido.
                    this.state.push(newMedia.id);

                    // 3. Adicionar o objeto da mídia à lista de 'selecionados' do Alpine
                    // Isso é o que faz a imagem aparecer imediatamente na view.
                    this.selectedMediaObjects.push(newMedia);

                    // Opcional: Notificar que o estado foi alterado para garantir que o Filament o capture
                    this.state = [...this.state];
                    $wire.set(this.statePath, this.state);
                });
            },

            carregarMais() {
                if (this.carregandoMais || !this.temMaisPaginas) return;

                this.carregandoMais = true;
                this.paginaAtual++;

                console.log(`Carregando página ${this.paginaAtual} de ${this.mediaType}...`);

                this.$wire.call('carregarMaisMedias', this.paginaAtual, config.statePath).then(resultado => {
                    // Filtra apenas o tipo correto (proteção extra)
                    const mediasFiltradas = resultado.medias.filter(m =>
                        m.is_video === (this.mediaType === 'video')
                    );

                    this.mediasDisponiveis.push(...mediasFiltradas);
                    this.temMaisPaginas = resultado.hasMore;
                    this.carregandoMais = false;
                    console.log(`Página ${this.paginaAtual} carregada. Total: ${this.mediasDisponiveis.length}`);
                }).catch(error => {
                    console.error('Erro ao carregar mais mídias:', error);
                    this.carregandoMais = false;
                });
            },

            toggleMedia(mediaId) {
                console.log(`Toggling mídia: ${mediaId}`);
                if (config.allowMultiple) {
                    const index = this.selecionadas.indexOf(mediaId);
                    if (index > -1) {
                        this.selecionadas.splice(index, 1);
                    } else {
                        if (config.maxItems && this.selecionadas.length >= config.maxItems) {
                            console.warn('Máximo de itens atingido:', config.maxItems);
                            new FilamentNotification()
                                .title('Limite Atingido')
                                .warning()
                                .body('Máximo de ' + config.maxItems + (this.mediaType === 'image' ? ' imagens' : ' vídeos') + ' permitido')
                                .send();
                            return;
                        }
                        this.selecionadas.push(mediaId);
                    }
                } else {
                    this.selecionadas = this.isSelected(mediaId) ? [] : [mediaId];
                }
                console.log('Estado após toggle:', JSON.parse(JSON.stringify(this.selecionadas)));
                this.syncSelectedObjects();
                this.$wire.set(config.statePath, this.selecionadas);
            },

            syncSelectedObjects() {
                // Combina as mídias da galeria e as mídias já selecionadas para garantir que todos os IDs possam ser encontrados.
                const allAvailableMedia = [...this.selectedMediaObjects, ...this.mediasDisponiveis];
                const uniqueMedia = allAvailableMedia.filter((media, index, self) =>
                    index === self.findIndex((m) => m.id === media.id)
                );

                this.selectedMediaObjects = this.selecionadas.map(id => uniqueMedia.find(media => media.id == id)).filter(Boolean);
                console.log('Syncing selected objects:', this.selectedMediaObjects);
            },

            removerMedia(mediaId) {
                const index = this.selecionadas.indexOf(mediaId);
                console.log(`Removendo mídia: ${mediaId}, index: ${index}`);
                if (index > -1) {
                    this.selecionadas.splice(index, 1);
                }
                this.syncSelectedObjects();
                this.$wire.set(config.statePath, this.selecionadas);
            },

            isSelected(mediaId) {
                const numericId = parseInt(mediaId, 10);
                return this.selecionadas.map(id => parseInt(id, 10)).includes(numericId);
            },

            handleMediaUpload(event) {
                const file = event.target.files[0];
                console.log('📤 Upload iniciado:', file);
                if (!file) return;

                if (!config.allowMultiple && this.selecionadas.length > 0) {
                    new FilamentNotification()
                        .title('Limite Atingido')
                        .warning()
                        .body('Apenas um item é permitido.')
                        .send();
                    event.target.value = '';
                    return;
                }

                this.uploading = true;
                this.uploadProgress = `Enviando ${file.name}...`;

                this.$wire.upload(
                    config.statePath + '_new_media',
                    file,
                    (uploadedFilename) => {
                        console.log('✅ Upload concluído:', uploadedFilename);
                        this.$wire.call('handleNewMediaUpload', uploadedFilename, config.statePath)
                            .then(() => {
                                console.log('✨ Processamento do backend concluído.');
                                this.uploading = false;
                                this.uploadProgress = '';
                                event.target.value = '';
                                                        });
                    },
                    (error) => {
                        console.error('❌ Erro no upload:', error);
                        this.uploading = false;
                        this.uploadProgress = '';
                        event.target.value = '';
                        new FilamentNotification()
                            .title('Erro no Upload')
                            .danger()
                            .body('Erro ao enviar a mídia.')
                            .send();
                    },
                    (event) => {
                        const progress = Math.round(event.detail.progress);
                        this.uploadProgress = `Enviando: ${progress}%`;
                    }
                );
            },

            removeUploadedFile(index) {
                console.log(`Removendo arquivo do index: ${index}`);
                this.uploadedFiles.splice(index, 1);
            },

            async abrirEditor(imagemId, imagemUrl) {
                // Editor só funciona para imagens
                if (this.mediaType !== 'image') {
                    console.warn('Editor disponível apenas para imagens');
                    return;
                }

                console.log(`🖌️ Abrindo editor - ID: ${imagemId}`);
                this.imagemParaEditarId = imagemId;
                this.imagemParaEditarUrl = imagemUrl;

                try {
                    const response = await fetch(imagemUrl);
                    const blob = await response.blob();
                    const file = new File([blob], imagemUrl.split('/').pop(), { type: blob.type });
                    this.arquivoParaEditar = file;

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.$refs.imageEditorCanvas.src = e.target.result;
                        this.editorAberto = true;
                        this.$nextTick(() => this.initCropper());
                    };
                    reader.readAsDataURL(file);
                } catch (error) {
                    console.error('❌ Erro ao carregar imagem:', error);
                    new FilamentNotification()
                        .title('Erro ao Carregar')
                        .danger()
                        .body('Não foi possível carregar a imagem.')
                        .send();
                }
            },

            fecharEditor() {
                console.log('Fechando editor.');
                this.editorAberto = false;
                if (this.cropper) {
                    this.cropper.destroy();
                    this.cropper = null;
                }
                this.$refs.imageEditorCanvas.src = '';
                this.imagemParaEditarId = null;
                this.imagemParaEditarUrl = null;
                this.arquivoParaEditar = null;
            },

            initCropper() {
                console.log('Inicializando Cropper.js');
                if (this.cropper) {
                    this.cropper.destroy();
                }
                this.cropper = new Cropper(this.$refs.imageEditorCanvas, {
                    aspectRatio: this.getAspectRatioValue(this.currentAspectRatio),
                    viewMode: 2,
                    dragMode: 'move',
                    autoCropArea: 0.9,
                    responsive: true,
                    restore: false,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                    minContainerWidth: 300,
                    minContainerHeight: 200,
                });
            },

            getAspectRatioValue(ratioString) {
                if (!ratioString || ratioString === 'free') return NaN;
                const parts = ratioString.split(':');
                return parseFloat(parts[0]) / parseFloat(parts[1]);
            },

            resetarImagem() { if (this.cropper) this.cropper.reset(); },
            rotacionar(degree) { if (this.cropper) this.cropper.rotate(degree); },
            espelharHorizontal() { if (this.cropper) this.cropper.scaleX(-this.cropper.getData().scaleX || -1); },
            espelharVertical() { if (this.cropper) this.cropper.scaleY(-this.cropper.getData().scaleY || -1); },
            zoom(factor) { if (this.cropper) this.cropper.zoom(factor); },
            mudarAspectRatio(ratioString) {
                this.currentAspectRatio = ratioString;
                if (this.cropper) this.cropper.setAspectRatio(this.getAspectRatioValue(ratioString));
            },

            salvarImagemEditada() {
                console.log('💾 Salvando imagem editada...');
                if (!this.cropper) return;

                this.cropper.getCroppedCanvas().toBlob((blob) => {
                    console.log('🖼️ Canvas convertido para Blob');
                    const fileName = `${this.arquivoParaEditar.name.split('.').slice(0, -1).join('.')}_edited.png`;

                    this.$wire.upload(config.statePath + '_edited_media', blob, () => {
                        console.log('✅ Upload da imagem editada concluído');
                        this.$wire.call('handleEditedMediaUpload', this.imagemParaEditarId, fileName, config.statePath)
                            .then(() => {
                                console.log('✨ Imagem atualizada com sucesso');
                                this.fecharEditor();
                                new FilamentNotification()
                                    .title('Sucesso')
                                    .success()
                                    .body('Imagem editada com sucesso!')
                                    .send();
                                this.$wire.$refresh();
                            })
                            .catch((error) => {
                                console.error('❌ Erro ao salvar:', error);
                                new FilamentNotification()
                                    .title('Erro ao Salvar')
                                    .danger()
                                    .body('Erro ao salvar imagem.')
                                    .send();
                            });
                    });
                }, 'image/png');
            }
        }
    }
</script>

<style>
    /* [TODO O CSS ANTERIOR PERMANECE IGUAL] */
    /* Container Principal */
    .g-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding: 1rem;
    }

    /* Seções de Conteúdo */
    .g-section {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        background-color: rgb(249, 250, 251);
        padding: 1.5rem;
        border-radius: 0.5rem;
    }

    .dark .g-section {
        background-color: rgb(31, 41, 55);
    }

    .g-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(55, 65, 81);
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid rgb(229, 231, 235);
        padding-bottom: 0.5rem;
    }

    .dark .g-label {
        color: rgb(209, 213, 219);
        border-bottom-color: rgb(75, 85, 99);
    }

    /* Grid de Imagens - CENTRALIZADO */
    .g-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: center;
        align-items: flex-start;
        padding: 1rem 0;
    }

    /* Thumbnails */
    .g-thumbnail {
        position: relative;
        border-radius: 0.5rem;
        overflow: hidden;
        width: 150px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        transition: all 0.3s;
    }

    .g-thumbnail:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .g-thumbnail img {
        width: 100%;
        height: 10rem;
        object-fit: cover;
        display: block;
    }

    .g-video-placeholder {
        width: 100%;
        height: 10rem;
        background-color: #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
    }
    .dark .g-video-placeholder {
        background-color: #374151;
        color: #6b7280;
    }
    .g-video-placeholder svg {
        width: 4rem;
        height: 4rem;
    }

    /* Preview de Vídeo com Thumbnail */
    .g-video-preview {
        position: relative;
        width: 100%;
        height: 10rem;
        overflow: hidden;
    }

    .g-video-thumbnail {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .g-video-play-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 4rem;
        height: 4rem;
        background-color: rgba(0, 0, 0, 0.7);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        pointer-events: none;
    }

    .g-video-play-overlay svg {
        width: 2rem;
        height: 2rem;
        margin-left: 0.25rem;
    }

    .g-thumbnail:hover .g-video-play-overlay,
    .g-modal-thumb:hover .g-video-play-overlay {
        background-color: rgba(37, 99, 235, 0.9);
        transform: translate(-50%, -50%) scale(1.1);
    }

    .g-modal-video-preview {
        height: 8rem;
    }

    .g-thumbnail-selected {
        border: 3px solid rgb(59, 130, 246);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }

    .g-thumbnail-upload {
        border: 3px solid rgb(22, 163, 74);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);
    }

    /* Ações dos Thumbnails */
    .g-thumbnail-actions {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        display: flex;
        gap: 0.25rem;
        opacity: 0;
        transition: opacity 0.3s;
        z-index: 10;
    }

    .g-thumbnail.group:hover .g-thumbnail-actions {
        opacity: 1;
    }

    .g-thumbnail-btn-edit,
    .g-thumbnail-btn-remove {
        background-color: rgba(17, 24, 39, 0.8);
        color: white;
        border-radius: 0.375rem;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        backdrop-filter: blur(4px);
    }

    .g-thumbnail-btn-edit:hover {
        background-color: rgb(37, 99, 235);
        transform: scale(1.1);
    }

    .g-thumbnail-btn-remove:hover {
        background-color: rgb(220, 38, 38);
        transform: scale(1.1);
    }

    .g-thumbnail-btn-edit svg,
    .g-thumbnail-btn-remove svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    /* Nome do Thumbnail */
    .g-thumbnail-name {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
        color: white;
        font-size: 0.75rem;
        padding: 1rem 0.5rem 0.5rem 0.5rem;
        text-overflow: ellipsis;
        white-space: nowrap;
        overflow: hidden;
    }

    /* Área de Botões de Ação */
    .g-actions-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        padding: 1.5rem;
        background-color: white;
        border-radius: 0.5rem;
        border: 2px dashed rgb(229, 231, 235);
    }

    .dark .g-actions-container {
        background-color: rgb(31, 41, 55);
        border-color: rgb(75, 85, 99);
    }

    .g-actions-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(107, 114, 128);
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .dark .g-actions-title {
        color: rgb(156, 163, 175);
    }

    .g-actions {
        display: flex;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    /* Botões */
    .g-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 1.5rem;
        border: 1px solid transparent;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.875rem;
        color: white;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        min-width: 200px;
    }

    .g-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .g-btn:active {
        transform: translateY(0);
    }

    .g-btn svg {
        width: 1.25rem;
        height: 1.25rem;
        margin-right: 0.5rem;
        flex-shrink: 0;
    }

    .g-btn-primary {
        background-color: rgb(37, 99, 235);
    }

    .g-btn-primary:hover {
        background-color: rgb(29, 78, 216);
    }

    .g-btn-secondary {
        background-color: rgb(107, 114, 128);
        color: white;
    }

    .g-btn-secondary:hover {
        background-color: rgb(75, 85, 99);
    }

    .g-btn-success {
        background-color: rgb(21, 128, 61);
    }

    .g-btn-success:hover {
        background-color: rgb(22, 101, 52);
    }

    .g-hidden-input {
        display: none;
    }

    /* Modal */
    .g-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 50;
        overflow-y: auto;
        background-color: rgba(17, 24, 39, 0.75);
        backdrop-filter: blur(4px);
    }

    .g-modal-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 1rem;
    }

    .g-modal-content {
        position: relative;
        background-color: white;
        border-radius: 0.75rem;
        max-width: 72rem;
        width: 100%;
        padding: 1.5rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .dark .g-modal-content {
        background-color: rgb(31, 41, 55);
    }

    .g-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid rgb(229, 231, 235);
    }

    .dark .g-modal-header {
        border-bottom-color: rgb(75, 85, 99);
    }

    .g-modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: rgb(17, 24, 39);
    }

    .dark .g-modal-title {
        color: white;
    }

    .g-modal-close-btn {
        color: rgb(156, 163, 175);
        background: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        padding: 0.25rem;
        border-radius: 0.375rem;
    }

    .g-modal-close-btn:hover {
        color: rgb(107, 114, 128);
        background-color: rgb(243, 244, 246);
    }

    .dark .g-modal-close-btn:hover {
        background-color: rgb(55, 65, 81);
    }

    .g-modal-close-btn svg {
        width: 1.5rem;
        height: 1.5rem;
    }

    .g-modal-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
        max-height: 60vh;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .g-modal-thumb {
        position: relative;
        cursor: pointer;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 2px solid rgb(229, 231, 235);
        transition: all 0.2s;
    }

    .g-modal-thumb:hover {
        border-color: rgb(147, 197, 253);
        transform: scale(1.02);
    }

    .g-modal-thumb-selected {
        border: 4px solid rgb(59, 130, 246);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }

    .g-modal-thumb-img {
        width: 100%;
        height: 8rem;
        object-fit: cover;
    }

    .g-video-placeholder-modal {
        height: 8rem;
    }

    .g-modal-thumb-check {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background-color: rgb(37, 99, 235);
        color: white;
        border-radius: 9999px;
        width: 1.75rem;
        height: 1.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .g-modal-thumb-check svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .g-modal-thumb-name {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
        color: white;
        font-size: 0.75rem;
        padding: 1rem 0.5rem 0.5rem 0.5rem;
        text-overflow: ellipsis;
        white-space: nowrap;
        overflow: hidden;
    }

    .g-modal-empty {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem 1rem;
        color: rgb(107, 114, 128);
    }

    .g-modal-footer {
        margin-top: 1.5rem;
        padding-top: 1rem;
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        border-top: 2px solid rgb(229, 231, 235);
    }

    .dark .g-modal-footer {
        border-top-color: rgb(75, 85, 99);
    }

    /* Editor */
    .g-modal-editor {
        max-width: 90vw;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    .g-editor-container {
        flex-grow: 1;
        height: 60vh;
        max-height: 60vh;
        background-color: rgb(249, 250, 251);
        border-radius: 0.5rem;
        padding: 1rem;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
        position: relative;
    }

    .dark .g-editor-container {
        background-color: rgb(17, 24, 39);
    }

    .g-editor-wrapper {
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
        position: relative;
    }

    .g-editor-canvas {
        max-width: 100%;
        max-height: 100%;
        display: block;
        margin: 0 auto;
    }

    .g-editor-wrapper .cropper-container {
        max-width: 100% !important;
        max-height: 100% !important;
    }

    .g-editor-toolbar {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        background-color: rgb(243, 244, 246);
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
    }

    .dark .g-editor-toolbar {
        background-color: rgb(55, 65, 81);
    }

    .g-toolbar-group {
        display: flex;
        gap: 0.25rem;
        align-items: center;
    }

    .g-toolbar-btn {
        padding: 0.5rem;
        background-color: white;
        border: 1px solid rgb(209, 213, 219);
        border-radius: 0.375rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .g-toolbar-btn:hover {
        background-color: rgb(243, 244, 246);
        border-color: rgb(156, 163, 175);
        transform: scale(1.05);
    }

    .dark .g-toolbar-btn {
        background-color: rgb(31, 41, 55);
        border-color: rgb(75, 85, 99);
    }

    .dark .g-toolbar-btn:hover {
        background-color: rgb(55, 65, 81);
    }

    .g-toolbar-btn svg {
        width: 1.25rem;
        height: 1.25rem;
        color: rgb(55, 65, 81);
    }

    .dark .g-toolbar-btn svg {
        color: rgb(209, 213, 219);
    }

    .g-toolbar-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: rgb(55, 65, 81);
        margin-right: 0.5rem;
    }

    .dark .g-toolbar-label {
        color: rgb(209, 213, 219);
    }

    .g-toolbar-select {
        padding: 0.5rem;
        background-color: white;
        border: 1px solid rgb(209, 213, 219);
        border-radius: 0.375rem;
        font-size: 0.875rem;
        cursor: pointer;
    }

    .dark .g-toolbar-select {
        background-color: rgb(31, 41, 55);
        border-color: rgb(75, 85, 99);
        color: white;
    }

    /* Upload Progress */
    .g-upload-progress {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 1rem;
        background: linear-gradient(135deg, rgb(219, 234, 254) 0%, rgb(191, 219, 254) 100%);
        border-radius: 0.5rem;
        color: rgb(37, 99, 235);
        font-size: 0.875rem;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
        border: 1px solid rgb(191, 219, 254);
    }

    .dark .g-upload-progress {
        background: linear-gradient(135deg, rgb(30, 58, 138) 0%, rgb(23, 37, 84) 100%);
        color: rgb(147, 197, 253);
        border-color: rgb(30, 64, 175);
    }

    .g-upload-spinner {
        border: 3px solid rgb(191, 219, 254);
        border-top-color: rgb(37, 99, 235);
        border-radius: 50%;
        width: 1.5rem;
        height: 1.5rem;
        animation: spin 0.8s linear infinite;
    }

    .dark .g-upload-spinner {
        border-color: rgb(30, 64, 175);
        border-top-color: rgb(147, 197, 253);
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .opacity-50 {
        opacity: 0.5;
    }

    .cursor-not-allowed {
        cursor: not-allowed;
    }

    .cursor-not-allowed input {
        cursor: not-allowed;
    }
</style>
