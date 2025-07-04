<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\ResponseException;
use App\Http\Resources\BaseResponse;
use App\Models\FpPresensi;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FpPresensiController extends Controller
{
    public function index(Request $request)
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
        $page = $pageQuery !== null ? (int)$pageQuery : 1;
        $perPage = 10;
        $idf = $user->idf;

        if (!$user || empty($idf)) {
            throw new ResponseException(
                'ID finger user tidak ditemukan atau kosong. Akses ditolak.',
                403
            );
        }

        $fpPresensi = FpPresensi::where('id_finger', $idf)
            ->whereRaw('YEAR(tanggal_absen) = ?', [$tahun])
            ->whereRaw('MONTH(tanggal_absen) = ?', [$bulan])
            ->where('hapus', 0)
            ->orderByDesc('tanggal_absen')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($fpPresensi->isEmpty()) {
            throw new ResponseException(
                'Data finger pegawai tidak ditemukan untuk bulan dan tahun yang dipilih.',
                404
            );
        }

        $groupedItems = [];
        foreach ($fpPresensi->items() as $item) {
            $dateKey = Carbon::parse($item->tanggal_absen)->format('Y-m-d');

            if (!isset($groupedItems[$dateKey])) {
                $groupedItems[$dateKey] = [
                    'date' => $dateKey,
                    'records' => []
                ];
            }

            $groupedItems[$dateKey]['records'][] = $item;
        }

        $groupedItemsArray = array_values($groupedItems);

        $responseData = [
            'current_page' => $fpPresensi->currentPage(),
            'items' => $groupedItemsArray,
            'per_page' => $fpPresensi->perPage(),
            'total' => $fpPresensi->total(),
            'last_page' => $fpPresensi->lastPage(),
            'from' => $fpPresensi->firstItem(),
            'to' => $fpPresensi->lastItem(),
            'links' => [
                'first' => $fpPresensi->url(1),
                'last' => $fpPresensi->url($fpPresensi->lastPage()),
                'prev' => $fpPresensi->previousPageUrl(),
                'next' => $fpPresensi->nextPageUrl(),
            ]
        ];

        return new BaseResponse($responseData, 200);
    }
}
