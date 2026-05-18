<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->decimal('value', 10, 2);
            $table->string('currency', 3)->default('BRL');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->index(['module_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_prices');
    }
};
