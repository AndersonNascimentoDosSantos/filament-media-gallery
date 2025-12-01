# 🎨 Filament Media Gallery

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devanderson/filament-media-gallery.svg?style=flat-square)](https://packagist.org/packages/devanderson/filament-media-gallery)
[![Total Downloads](https://img.shields.io/packagist/dt/devanderson/filament-media-gallery.svg?style=flat-square)](https://packagist.org/packages/devanderson/filament-media-gallery)
[![License](https://img.shields.io/packagist/l/devanderson/filament-media-gallery.svg?style=flat-square)](https://packagist.org/packages/devanderson/filament-media-gallery)

An advanced media gallery plugin for Filament v3 with full support for images and videos, an integrated image editor, automatic video thumbnail generation, and much more.

![Screenshot](https://via.placeholder.com/800x400?text=Plugin+Screenshot)

## ✨ Features

### 📸 Images
- ✅ Unified gallery with pagination
- ✅ Multiple image uploads
- ✅ Integrated editor with Cropper.js
- ✅ Support for custom aspect ratios
- ✅ Automatic image optimization
- ✅ Formats: JPG, PNG, WebP, GIF

### 🎬 Videos
- ✅ Video uploads (MP4, WebM, MOV)
- ✅ Automatic thumbnail generation via FFmpeg
- ✅ Fallback to placeholders if FFmpeg is not available
- ✅ Video previews in the gallery
- ✅ Support for large videos (up to 256MB by default)

### 🎯 General
- ✅ Modern and responsive interface
- ✅ Dark mode compatible
- ✅ Drag & drop for uploads
- ✅ Upload progress indicator
- ✅ Single or multiple selection
- ✅ Configurable item limit
- ✅ Fully customizable

---

## 📦 Installation

### Requirements
- PHP 8.1 ou superior
- Laravel 10 ou superior
- Filament 3.x

### Via Composer

```bash
composer require devanderson/filament-media-gallery
```

### Publicar Assets e Configuração

```bash
# Publicar tudo
php artisan vendor:publish --tag="filament-media-gallery"

# Ou publicar individualmente
php artisan vendor:publish --tag="filament-media-gallery-config"
php artisan vendor:publish --tag="filament-media-gallery-migrations"
php artisan vendor:publish --tag="filament-media-gallery-views"
```

### Executar Migrations

```bash
php artisan migrate
```

### Link do Storage (se ainda não fez)

```bash
php artisan storage:link
```

---

## 🚀 Uso Básico

### Galeria de Imagens Simples

```php
use DevAnderson\FilamentMediaGallery\Forms\Components\MediaGallery;

MediaGallery::make('imagens_ids')
    ->label('Imagens')
    ->mediaType('image')
    ->allowMultiple(true)
    ->columnSpanFull()
```

### Galeria de Vídeos

```php
MediaGallery::make('videos_ids')
    ->label('Vídeos')
    ->mediaType('video')
    ->allowMultiple(true)
    ->columnSpanFull()
```

### Usando Métodos Estáticos

```php
// Para imagens
MediaGallery::images('imagens_ids')
    ->label('Galeria de Imagens')
    ->allowMultiple(true)
    ->imageEditor(true)
    ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
    ->columnSpanFull()

// Para vídeos
MediaGallery::videos('videos_ids')
    ->label('Galeria de Vídeos')
    ->allowMultiple(false)
    ->columnSpanFull()
```

---

## ⚙️ Configuração Avançada

### Imagem Única com Editor

```php
MediaGallery::images('capa_id')
    ->label('Imagem de Capa')
    ->allowMultiple(false)
    ->imageEditor(true)
    ->imageEditorAspectRatios(['16:9'])
    ->maxItems(1)
```

### Galeria com Limite de Itens

```php
MediaGallery::images('galeria_ids')
    ->label('Galeria (máximo 10 imagens)')
    ->allowMultiple(true)
    ->maxItems(10)
    ->imageEditor(true)
```

### Desabilitar Upload (Apenas Seleção)

```php
MediaGallery::images('imagens_ids')
    ->label('Selecione da Galeria')
    ->allowUpload(false)
    ->allowMultiple(true)
```

---

## 🔧 Uso com Models

### Adicionar Trait ao Model

```php
use DevAnderson\FilamentMediaGallery\Traits\HasMediaGallery;

class Post extends Model
{
    use HasMediaGallery;

    protected $fillable = [
        'title',
        'content',
        'imagens_ids',
        'videos_ids',
    ];

    protected $casts = [
        'imagens_ids' => 'array',
        'videos_ids' => 'array',
    ];
}
```

### Sincronizar Mídias no Resource

```php
use DevAnderson\FilamentMediaGallery\Traits\HasMediaGallery;

class PostResource extends Resource
{
    use HasMediaGallery;

    // Em CreatePost.php
    protected function afterCreate(): void
    {
        $this->syncImagens();
        $this->syncVideos();
    }

    // Em EditPost.php
    protected function afterSave(): void
    {
        $this->syncImagens();
        $this->syncVideos();
    }
}
```

---

## 📝 Configuração

### config/filament-media-gallery.php

```php
return [
    // Disco de armazenamento
    'disk' => env('MEDIA_GALLERY_DISK', 'public'),

    // Paths de armazenamento
    'paths' => [
        'images' => 'gallery/images',
        'videos' => 'gallery/videos',
        'thumbnails' => 'gallery/thumbnails',
    ],

    // Configurações de imagens
    'images' => [
        'max_size' => 10240, // KB
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        
        'editor' => [
            'enabled' => true,
            'aspect_ratios' => ['16:9', '4:3', '1:1'],
        ],
        
        'optimization' => [
            'enabled' => true,
            'quality' => 85,
            'max_width' => 1920,
        ],
    ],

    // Configurações de vídeos
    'videos' => [
        'max_size' => 256000, // KB (256MB)
        'allowed_mimes' => ['video/mp4', 'video/webm'],
        
        'thumbnails' => [
            'enabled' => true,
            'time_offset' => 1.0, // segundos
        ],
        
        'ffmpeg' => [
            'enabled' => true,
            'binary_path' => env('FFMPEG_PATH', 'ffmpeg'),
        ],
    ],
];
```

---

## 🎬 FFmpeg para Thumbnails de Vídeo

### Instalação do FFmpeg

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install ffmpeg
```

#### CentOS/AlmaLinux
```bash
sudo yum install epel-release
sudo yum install ffmpeg
```

#### macOS
```bash
brew install ffmpeg
```

### Verificar Instalação

```bash
ffmpeg -version
```

### Sem FFmpeg?

O plugin funciona sem FFmpeg! Ele automaticamente usa placeholders se FFmpeg não estiver disponível.

---

## 🔌 API / Métodos Públicos

### Componente MediaGallery

| Método | Descrição | Padrão |
|--------|-----------|--------|
| `mediaType(string)` | Define tipo: 'image' ou 'video' | 'image' |
| `allowUpload(bool)` | Permite upload de novos arquivos | true |
| `allowMultiple(bool)` | Permite seleção múltipla | true |
| `maxItems(int)` | Limite máximo de itens | null |
| `imageEditor(bool)` | Habilita editor (só imagens) | false |
| `imageEditorAspectRatios(array)` | Proporções do editor | ['16:9', '4:3', '1:1'] |

### Trait HasMediaGallery

| Método | Descrição |
|--------|-----------|
| `syncImagens()` | Sincroniza relação com imagens |
| `syncVideos()` | Sincroniza relação com vídeos |
| `handleNewMediaUpload()` | Processa upload de nova mídia |
| `handleEditedMediaUpload()` | Processa imagem editada |
| `carregarMaisMedias()` | Carrega mais itens (paginação) |

---

## 🎨 Personalização

### Customizar Views

```bash
php artisan vendor:publish --tag="filament-media-gallery-views"
```

As views estarão em `resources/views/vendor/filament-media-gallery/`

### Customizar Estilos

```bash
php artisan vendor:publish --tag="filament-media-gallery-assets"
```

Edite `resources/css/vendor/filament-media-gallery/media-gallery.css`

### Customizar Models

No config:

```php
'models' => [
    'media' => \App\Models\CustomMedia::class,
    'image' => \App\Models\CustomImage::class,
    'video' => \App\Models\CustomVideo::class,
],
```

---

## 🧪 Testes

```bash
composer test
```

---

## 📝 Changelog

Veja [CHANGELOG](CHANGELOG.md) para mais informações.

---

## 🤝 Contribuindo

Contribuições são bem-vindas! Veja [CONTRIBUTING](CONTRIBUTING.md).

---

## 🔒 Segurança

Se você descobrir alguma vulnerabilidade, envie um email para seu-email@exemplo.com.

---

## 📄 Licença

MIT License. Veja [LICENSE](LICENSE.md) para mais detalhes.

---

## 👨‍💻 Créditos

- [Seu Nome](https://github.com/devanderson)
- [Todos os Contribuidores](../../contributors)

Feito com ❤️ para a comunidade Filament
