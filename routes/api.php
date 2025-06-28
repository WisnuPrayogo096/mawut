<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FingerPegawaiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/get-fp-absensi', [FingerPegawaiController::class, 'getFingerPegawai'])->middleware('auth:sanctum');
