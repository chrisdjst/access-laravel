<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('redirect', 255)->nullable();
            $table->string('icon', 50)->nullable();
            $table->uuid('root_module_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['root_module_id', 'sort_order']);
            $table->index('is_active');
        });

        // Add the self-referencing FK after the table exists to avoid
        // "no unique constraint matching given keys" on Postgres when the PK
        // hasn't been flushed yet within the same CREATE statement.
        Schema::table('modules', function (Blueprint $table) {
            $table->foreign('root_module_id')
                ->references('id')->on('modules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
