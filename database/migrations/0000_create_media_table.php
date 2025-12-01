<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-media-gallery.table_names.media', 'media');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'image' ou 'video'
            $table->string('path');
            $table->string('nome_original');
            $table->string('mime_type');
            $table->unsignedBigInteger('tamanho');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-media-gallery.table_names.media', 'media'));
    }
};
