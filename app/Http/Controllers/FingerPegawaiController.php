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

        $validator = Validator::make($request->query(), [
            'bulan' => 'nullable|numeric|min:1|max:12',
            'tahun' => 'nullable|numeric|min:1900',
            'page' => 'nullable|numeric|min:1',
        ]);

        if ($validator->fails()) {
            throw new ResponseException(
                'Format bulan, tahun, atau halaman tidak valid',
                400
            );
        }

        $bulanQuery = $request->query('bulan');
        $tahunQuery = $request->query('tahun');
        $pageQuery = $request->query('page');

        $bulan = $bulanQuery !== null ? (int)$bulanQuery : Carbon::now()->month;
        $tahun = $tahunQuery !== null ? (int)$tahunQuery : Carbon::now()->year;
        $perPage = 10;
        $page = $pageQuery !== null ? (int)$pageQuery : 1;

        if (!$user || empty($user->idf)) {
            throw new ResponseException(
                'ID Finger (IDF) user tidak ditemukan atau kosong. Akses ditolak.',
                403
            );
        }

        $idf = $user->idf;

        $fingerPegawai = FingerPegawai::where('id_finger', $idf)
            ->whereRaw('YEAR(tanggal_absen) = ?', [$tahun])
            ->whereRaw('MONTH(tanggal_absen) = ?', [$bulan])
            ->orderByDesc('tanggal_absen')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($fingerPegawai->isEmpty()) {
            throw new ResponseException(
                'Data Finger Pegawai tidak ditemukan untuk bulan dan tahun yang dipilih.',
                404
            );
        }

        $responseData = [
            'current_page' => $fingerPegawai->currentPage(),
            'items' => $fingerPegawai->items(),
            'per_page' => $fingerPegawai->perPage(),
            'total' => $fingerPegawai->total(),
            'last_page' => $fingerPegawai->lastPage(),
            'from' => $fingerPegawai->firstItem(),
            'to' => $fingerPegawai->lastItem(),
            'links' => [
                'first' => $fingerPegawai->url(1),
                'last' => $fingerPegawai->url($fingerPegawai->lastPage()),
                'prev' => $fingerPegawai->previousPageUrl(),
                'next' => $fingerPegawai->nextPageUrl(),
            ]
        ];

        return new BaseResponse($responseData, 200);
    }
}
