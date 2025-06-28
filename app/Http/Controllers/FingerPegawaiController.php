<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\ResponseException;
use App\Http\Resources\BaseResponse;
use App\Models\FingerPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FingerPegawaiController extends Controller
{
    public function getFingerPegawai(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'bulan' => 'nullable|integer|min:1|max:12',
            'tahun' => 'nullable|integer|min:1900',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            throw new ResponseException(
                'Format bulan, tahun, atau halaman tidak valid',
                400
            );
        }

        $bulan = $request->input('bulan', Carbon::now()->month);
        $tahun = $request->input('tahun', Carbon::now()->year);
        $perPage = 10;
        $page = $request->input('page', 1);

        if (!$user || empty($user->idf)) {
            throw new ResponseException(
                'ID Finger (IDF) user tidak ditemukan atau kosong. Akses ditolak.',
                403
            );
        }

        $idf = $user->idf;

        $fingerPegawai = FingerPegawai::where('id_finger', $idf)
            ->whereYear('tanggal_absen', $tahun)
            ->whereMonth('tanggal_absen', $bulan)
            ->orderByDesc('tanggal_absen')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($fingerPegawai->isEmpty()) {
            throw new ResponseException(
                'Data Finger Pegawai tidak ditemukan untuk bulan dan tahun yang dipilih.',
                404
            );
        }

        return new BaseResponse($fingerPegawai->toArray(), 200);
    }
}
