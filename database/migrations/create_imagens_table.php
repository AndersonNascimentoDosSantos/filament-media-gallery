<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagens', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('nome_original');
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('tamanho')->comment('Tamanho em bytes');
            $table->timestamps();
            $table->softDeletes();

            // Índices para melhor performance
            $table->index('created_at');
            $table->index('mime_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagens');
    }
};
