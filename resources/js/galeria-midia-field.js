document.addEventListener('alpine:init', () => {
    Alpine.data('imageGalleryPicker', (config) => ({
        selecionadas: config.state,
        mediasDisponiveis: config.initialMedias,
        modalAberto: false,
        mediaType: config.mediaType,
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
            console.log('Estado inicial:', JSON.parse(JSON.stringify(this.selecionadas)));

            this.$watch('$wire.get(\'' + config.statePath + '\')', (newState) => {
                this.selecionadas = newState || [];
            });

            Livewire.on('galeria:medias-atualizadas', ({ medias }) => {
                console.log('🔄 Recebendo mídias filtradas:', medias);
                medias.forEach(mediaDaGaleria => {
                    if (mediaDaGaleria.is_video === (this.mediaType === 'video')) {
                        if (!this.mediasDisponiveis.some(local => local.id === mediaDaGaleria.id)) {
                            this.mediasDisponiveis.push(mediaDaGaleria);
                        }
                    }
                });
            });

            Livewire.on('galeria:media-adicionada', ({ media }) => {
                console.log('✨ Nova mídia adicionada:', media);
                if (media.is_video === (this.mediaType === 'video')) {
                    if (!this.mediasDisponiveis.some(local => local.id === media.id)) {
                        this.mediasDisponiveis.push(media);
                    }
                }
            });
        },

        carregarMais() {
            if (this.carregandoMais || !this.temMaisPaginas) return;

            this.carregandoMais = true;
            this.paginaAtual++;

            console.log(`Carregando página ${this.paginaAtual} de ${this.mediaType}...`);

            this.$wire.call('carregarMaisMedias', this.paginaAtual, config.statePath).then(resultado => {
                const mediasFiltradas = resultado.medias.filter(m =>
                    m.is_video === (this.mediaType === 'video')
                );

                this.mediasDisponiveis.push(...mediasFiltradas);
                this.temMaisPaginas = resultado.temMais;
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
                            .title(config.translations.limit_reached.title)
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
            this.$wire.set(config.statePath, this.selecionadas);
        },

        removerMedia(mediaId) {
            const index = this.selecionadas.indexOf(mediaId);
            console.log(`Removendo mídia: ${mediaId}, index: ${index}`);
            if (index > -1) {
                this.selecionadas.splice(index, 1);
            }
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
                    .title(config.translations.limit_reached.title)
                    .warning()
                    .body(config.translations.limit_reached.single)
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
                            console.log('✨ Processamento concluído');
                            this.uploading = false;
                            this.uploadProgress = '';
                            event.target.value = '';
                        })
                        .catch((error) => {
                            console.error('❌ Erro:', error);
                            this.uploading = false;
                            this.uploadProgress = '';
                            event.target.value = '';
                            new FilamentNotification()
                                .title(config.translations.processing_error.title)
                                .danger()
                                .body(config.translations.processing_error.body)
                                .send();
                        });
                },
                (error) => {
                    console.error('❌ Erro no upload:', error);
                    this.uploading = false;
                    this.uploadProgress = '';
                    event.target.value = '';
                    new FilamentNotification()
                        .title(config.translations.upload_error.title)
                        .danger()
                        .body(config.translations.upload_error.body)
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
                                .title(config.translations.image_edited_success.title)
                                .success()
                                .body(config.translations.image_edited_success.body)
                                .send();
                            this.$wire.$refresh();
                        })
                        .catch((error) => {
                            console.error('❌ Erro ao salvar:', error);
                            new FilamentNotification()
                                .title(config.translations.save_error.title)
                                .danger()
                                .body(config.translations.save_error.body)
                                .send();
                        });
                });
            }, 'image/png');
        }
    }));
});