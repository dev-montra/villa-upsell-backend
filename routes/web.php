<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Must be protected by owner/admin authentication middleware
Route::middleware('auth:sanctum')->group(function () {
    // Property Settings CRUD for the Owner Dashboard
    Route::apiResource('properties', Api\PropertyController::class);
});