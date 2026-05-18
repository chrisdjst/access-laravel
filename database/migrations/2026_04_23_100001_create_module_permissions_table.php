<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->nullable(); // ex.: "Leitor", "Editor", "Admin"
            $table->boolean('is_reading_allowed')->default(false);
            $table->boolean('is_writing_allowed')->default(false);
            $table->boolean('is_editing_allowed')->default(false);
            $table->boolean('is_delete_allowed')->default(false);
            $table->boolean('is_listing_allowed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_permissions');
    }
};
