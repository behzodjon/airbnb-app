<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TagController;
use App\Http\Controllers\OfficeController;

Route::get('/tags', TagController::class);
Route::get('/offices', [OfficeController::class,'index']);
