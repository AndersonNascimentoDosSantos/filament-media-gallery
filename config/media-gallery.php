<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    */
    'disk' => env('MEDIA_GALLERY_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Storage Paths
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'images' => 'gallery/images',
        'videos' => 'gallery/videos',
        'thumbnails' => 'gallery/thumbnails',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Settings
    |--------------------------------------------------------------------------
    */
    'images' => [
        'max_size' => 10240, // KB (10MB)
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],

        'editor' => [
            'enabled' => true,
            'aspect_ratios' => ['16:9', '4:3', '1:1', '3:2'],
        ],

        'optimization' => [
            'enabled' => true,
            'quality' => 85,
            'max_width' => 1920,
            'max_height' => 1080,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Settings
    |--------------------------------------------------------------------------
    */
    'videos' => [
        'max_size' => 256000, // KB (256MB)
        'allowed_mimes' => ['video/mp4', 'video/webm', 'video/quicktime'],
        'allowed_extensions' => ['mp4', 'webm', 'mov'],

        'thumbnails' => [
            'enabled' => true,
            'time_offset' => 1.0, // segundos
            'fallback_placeholder' => true,
        ],

        'ffmpeg' => [
            'enabled' => true,
            'binary_path' => env('FFMPEG_PATH', 'ffmpeg'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    */
    'models' => [
        'media' => \Devanderson\FilamentMediaGallery\Models\Media::class,
        'image' => \Devanderson\FilamentMediaGallery\Models\Image::class,
        'video' => \Devanderson\FilamentMediaGallery\Models\Video::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'table_names' => [
        'media' => 'media',
        'images' => 'images',
        'videos' => 'videos',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'per_page' => 24,
    ],
];
