<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FpPresensiController;
use App\Http\Controllers\FpMasjidController;
use App\Http\Controllers\MesinFingerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::get('/api-sholat-v1', [FpMasjidController::class, 'responseApiV1']);
Route::get('/api-sholat-v2', [FpMasjidController::class, 'responseApiV2']);

Route::middleware('auth:sanctum')->group(function () {
    // method get
    Route::get('/fp-absensi', [FpPresensiController::class, 'index']);
    Route::get('/today-presensi', [FpPresensiController::class, 'getTodayFpPresensi']);
    Route::get('/fp-masjid', [FpMasjidController::class, 'index']);
    Route::get('/jadwal-sholat', [FpMasjidController::class, 'jadwalSholat']);
    Route::get('/mesin-masjid', [MesinFingerController::class, 'indexMasjid']);
    Route::get('/mesin-presensi', [MesinFingerController::class, 'indexPresensi']);

    // method post
    Route::post('/fp-masjid/create', [FpMasjidController::class, 'store']);
    Route::post('/fp-presensi/create', [FpPresensiController::class, 'store']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
