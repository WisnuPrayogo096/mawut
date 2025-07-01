<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FingerPegawaiController;
use App\Http\Controllers\MasterMesinFpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/get-fp-absensi', [FingerPegawaiController::class, 'getFingerPegawai']);
    Route::get('/get-mesin-masjid', [MasterMesinFpController::class, 'getMasterFpMasjid']);
    Route::get('/get-mesin-presensi', [MasterMesinFpController::class, 'getMasterFpPresensi']);
});
