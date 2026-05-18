<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('translatable_type', 255);
            $table->string('translatable_id', 36);
            $table->uuid('language_id');
            $table->string('field', 50);
            $table->text('value');
            $table->timestamps();

            $table->foreign('language_id')->references('id')->on('languages')->cascadeOnDelete();

            $table->unique(
                ['translatable_type', 'translatable_id', 'language_id', 'field'],
                'translations_unique'
            );
            $table->index(['translatable_type', 'translatable_id'], 'translations_morph_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
