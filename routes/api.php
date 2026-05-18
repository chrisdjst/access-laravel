<?php

declare(strict_types=1);

use Casamento\Rbac\Http\Controllers\LanguageController;
use Casamento\Rbac\Http\Controllers\ModuleController;
use Casamento\Rbac\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RBAC package routes
|--------------------------------------------------------------------------
|
| Loaded by RbacServiceProvider inside a Route::prefix(...)->middleware(...)
| group reading config('rbac.route_prefix') and config('rbac.middleware').
*/

Route::apiResource('modules', ModuleController::class);

Route::get('roles', [RoleController::class, 'index']);
Route::get('roles/{role}', [RoleController::class, 'show']);
Route::put('roles/{role}', [RoleController::class, 'update']);
Route::put('roles/{role}/modules', [RoleController::class, 'syncModules']);

Route::apiResource('languages', LanguageController::class);
Route::put('languages/{language}/default', [LanguageController::class, 'setDefault']);
