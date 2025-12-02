<?php

namespace Devanderson\FilamentMediaGallery\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Devanderson\FilamentMediaGallery\FilamentMediaGallery
 */
class FilamentMediaGallery extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Devanderson\FilamentMediaGallery\FilamentMediaGallery::class;
    }
}
