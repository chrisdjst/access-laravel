<?php

declare(strict_types=1);

use ModularizeRbac\Laravel\Http\Controllers\AuditController;
use ModularizeRbac\Laravel\Http\Controllers\LanguageController;
use ModularizeRbac\Laravel\Http\Controllers\ModuleController;
use ModularizeRbac\Laravel\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RBAC package routes
|--------------------------------------------------------------------------
|
| Loaded by AccessServiceProvider inside a Route::prefix(...)->middleware(...)
| group reading config('access.route_prefix') and config('access.middleware').
*/

Route::apiResource('modules', ModuleController::class);

Route::get('roles', [RoleController::class, 'index']);
Route::get('roles/{role}', [RoleController::class, 'show']);
Route::put('roles/{role}', [RoleController::class, 'update']);
Route::put('roles/{role}/modules', [RoleController::class, 'syncModules']);

Route::apiResource('languages', LanguageController::class);
Route::put('languages/{language}/default', [LanguageController::class, 'setDefault']);

Route::get('audit', [AuditController::class, 'index']);
