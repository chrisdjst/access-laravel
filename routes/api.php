<?php

declare(strict_types=1);

use ModularizeRbac\Laravel\Http\Controllers\AuditController;
use ModularizeRbac\Laravel\Http\Controllers\LanguageController;
use ModularizeRbac\Laravel\Http\Controllers\ModuleController;
use ModularizeRbac\Laravel\Http\Controllers\RoleController;
use ModularizeRbac\Laravel\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RBAC package routes
|--------------------------------------------------------------------------
|
| Loaded by AccessServiceProvider inside a Route::prefix(...)->middleware(...)
| group reading config('access.route_prefix') and config('access.middleware').
*/

// Routes whose write surface is wide enough to warrant a per-user
// throttle on top of whatever the host applies. The 'access-bulk'
// limiter is registered by AccessServiceProvider with config-driven
// rate (default 10/min).
Route::middleware('throttle:access-bulk')->group(function (): void {
    Route::post('modules/bulk', [ModuleController::class, 'bulkStore']);
    Route::delete('modules/bulk', [ModuleController::class, 'bulkDestroy']);
    Route::post('roles/{role}/clone', [RoleController::class, 'clone']);
    Route::post('roles/{role}/users/bulk', [RoleController::class, 'bulkAssignUsers']);
});

Route::apiResource('modules', ModuleController::class);

Route::get('roles', [RoleController::class, 'index']);
Route::post('roles', [RoleController::class, 'store']);
Route::get('roles/{role}', [RoleController::class, 'show']);
Route::put('roles/{role}', [RoleController::class, 'update']);
Route::delete('roles/{role}', [RoleController::class, 'destroy']);
Route::post('roles/{role}/restore', [RoleController::class, 'restore']);
Route::put('roles/{role}/modules', [RoleController::class, 'syncModules']);
Route::get('roles/{role}/permission-matrix', [RoleController::class, 'permissionMatrix']);

Route::get('users/{user}/accessible-modules', [UserController::class, 'accessibleModules']);

Route::apiResource('languages', LanguageController::class);
Route::put('languages/{language}/default', [LanguageController::class, 'setDefault']);

Route::get('audit', [AuditController::class, 'index']);
