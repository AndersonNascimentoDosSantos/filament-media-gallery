<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-media-gallery.table_names.videos', 'videos');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained(
                config('filament-media-gallery.table_names.media', 'media')
            )->cascadeOnDelete();
            $table->string('thumbnail_path')->nullable();
            $table->float('duration')->nullable(); // em segundos
            $table->string('codec')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-media-gallery.table_names.videos', 'videos'));
    }
};
