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
            'page'  => 'nullable|numeric|min:1',
        ], [
            'bulan.numeric' => 'Bulan harus berupa angka.',
            'bulan.min'     => 'Bulan minimal 1.',
            'bulan.max'     => 'Bulan maksimal 12.',
            'tahun.numeric' => 'Tahun harus berupa angka.',
            'tahun.min'     => 'Tahun minimal 1900.',
            'page.numeric'  => 'Halaman harus berupa angka.',
            'page.min'      => 'Halaman minimal 1.',
        ]);

        if ($validator->fails()) {
            throw new ResponseException(
                'Format bulan, tahun, atau halaman tidak valid',
                400
            );
        }

        $bulan = $request->query('bulan', Carbon::now()->month);
        $tahun = $request->query('tahun', Carbon::now()->year);
        $page = (int)$request->query('page', 1);
        $perPage = 10;
        $idf = $user->idf;

        if (!$user || empty($idf)) {
            throw new ResponseException(
                'ID finger user tidak ditemukan atau kosong. Akses ditolak.',
                403
            );
        }

        // Subquery untuk mendapatkan ID record pertama dan terakhir setiap hari
        $dailyRecordIds = FpPresensi::query()
            ->select(
                DB::raw('MIN(id) as first_id'),
                DB::raw('MAX(id) as last_id')
            )
            ->where('id_finger', $idf)
            ->whereYear('tanggal_absen', $tahun)
            ->whereMonth('tanggal_absen', $bulan)
            ->where('hapus', 0)
            ->groupBy(DB::raw('DATE(tanggal_absen)'));

        // Mengambil ID unik dari subquery
        $recordIds = collect($dailyRecordIds->get()->makeHidden(['first_id', 'last_id']))
            ->flatMap(function ($item) {
                // Menggabungkan first_id dan last_id, dan memastikan tidak ada duplikat jika hanya ada satu record
                return array_unique([$item->first_id, $item->last_id]);
            });

        // Query utama untuk mengambil data absensi berdasarkan ID yang sudah didapatkan
        $fpPresensiQuery = FpPresensi::whereIn('id', $recordIds)
            ->orderBy('tanggal_absen', 'desc');

        // Paginasi Manual
        $total = $fpPresensiQuery->count();
        $items = $fpPresensiQuery->offset(($page - 1) * $perPage)->limit($perPage)->get();

        $fpPresensiPaginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        if ($fpPresensiPaginator->isEmpty()) {
            throw new ResponseException(
                'Data finger pegawai tidak ditemukan untuk bulan dan tahun yang dipilih.',
                404
            );
        }

        // Mengelompokkan data berdasarkan tanggal
        $groupedItems = [];
        foreach ($fpPresensiPaginator->items() as $item) {
            $dateKey = Carbon::parse($item->tanggal_absen)->format('Y-m-d');

            if (!isset($groupedItems[$dateKey])) {
                $groupedItems[$dateKey] = [
                    'date'    => $dateKey,
                    'records' => []
                ];
            }
            $groupedItems[$dateKey]['records'][] = $item;
        }

        // Memastikan urutan record di dalam grup benar (pagi ke malam)
        foreach ($groupedItems as &$group) {
            usort($group['records'], function ($a, $b) {
                return strtotime($a->tanggal_absen) <=> strtotime($b->tanggal_absen);
            });
        }

        $groupedItemsArray = array_values($groupedItems);

        $responseData = [
            'current_page' => $fpPresensiPaginator->currentPage(),
            'items'        => $groupedItemsArray,
            'per_page'     => $fpPresensiPaginator->perPage(),
            'total'        => $fpPresensiPaginator->total(),
            'last_page'    => $fpPresensiPaginator->lastPage(),
            'from'         => $fpPresensiPaginator->firstItem(),
            'to'           => $fpPresensiPaginator->lastItem(),
            'links'        => [
                'first' => $fpPresensiPaginator->url(1),
                'last'  => $fpPresensiPaginator->url($fpPresensiPaginator->lastPage()),
                'prev'  => $fpPresensiPaginator->previousPageUrl(),
                'next'  => $fpPresensiPaginator->nextPageUrl(),
            ]
        ];

        return new BaseResponse($responseData, 200);
    }
}
