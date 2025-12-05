<?php

// config for Devanderson/FilamentMediaGallery
return [
    // Disco de armazenamento
    'disk' => env('MEDIA_GALLERY_DISK', 'public'),

    // Caminho de armazenamento
    'path' => env('MEDIA_GALLERY_PATH', 'galeria'),

    // Configurações de imagens
    'image' => [
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'max_size' => 10240, // KB
        'editor' => [
            'enabled' => true,
            'aspect_ratios' => ['16:9', '4:3', '1:1', '9:16'],
        ],
    ],

    // Configurações de vídeos
    'video' => [
        'allowed_extensions' => ['mp4', 'webm', 'ogg'],
        'max_size' => 102400, // KB
        'thumbnail' => [
            'enabled' => true,
            'time' => 1.0,
            'width' => 640,
        ],
    ],

    // Paginação da galeria
    'gallery' => [
        'per_page' => 24,
        'allow_multiple' => true,
        'max_items' => null,
    ],
];
