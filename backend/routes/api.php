<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TagController;
use App\Http\Controllers\OfficeController;

//Tags...
Route::get('/tags', TagController::class);

//Offices...
Route::get('/offices', [OfficeController::class, 'index']);
Route::get('/offices/{office}', [OfficeController::class, 'show']);
Route::post('/offices', [OfficeController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::put('/offices/{office}', [OfficeController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}', [OfficeController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);
