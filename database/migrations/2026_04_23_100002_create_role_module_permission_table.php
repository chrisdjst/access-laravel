<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_module_permission', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // roles (Spatie) usa chave primária integer — model customizado do projeto usa uuid.
            // A coluna id da tabela 'roles' é uuid (traço HasUuid no Role model).
            $table->uuid('role_id');
            $table->uuid('module_id');
            $table->uuid('module_permission_id');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('module_permission_id')->references('id')->on('module_permissions')->cascadeOnDelete();

            $table->unique(['role_id', 'module_id'], 'rmp_role_module_unique');
            $table->index('module_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_module_permission');
    }
};
