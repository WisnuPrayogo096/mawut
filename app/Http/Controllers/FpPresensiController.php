<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\ResponseException;
use App\Http\Resources\BaseResponse;
use App\Models\FpPresensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

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
        $idf = $user ? $user->idf : null;

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

    public function getTodayFpPresensi()
    {
        $user = Auth::user();

        if (!$user || empty($user->idf)) {
            throw new ResponseException(
                'ID finger user tidak ditemukan atau kosong. Akses ditolak.',
                403
            );
        }

        $today = Carbon::now('Asia/Jakarta');
        $idf = $user->idf;

        $fpPresensi = FpPresensi::where('id_finger', $idf)
            ->whereDate('tanggal_absen', $today)
            ->where('hapus', 0)
            ->get();

        $grouped = $fpPresensi->groupBy('status')->map(function ($items) {
            return $items->first();
        });

        return new BaseResponse($grouped->values()->toArray(), 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'id_fp_finger_mesin' => 'required|integer|exists:fp_finger_mesin,id',
            // 'id_fp_finger_mesin' => 'required|integer|exists:mysql_bosq.fp_finger_mesin,id',
            'status' => 'required|in:0,1',
        ], [
            'id_fp_finger_mesin.required' => 'ID mesin finger wajib diisi.',
            'id_fp_finger_mesin.integer' => 'ID mesin finger harus berupa angka.',
            'id_fp_finger_mesin.exists' => 'ID mesin finger tidak ditemukan.',
            'status.required' => 'Status wajib diisi.',
            'status.in' => 'Status harus berupa 0 atau 1.',
        ]);

        if ($validator->fails()) {
            throw new ResponseException('Data yang dikirim tidak valid.', 400);
        }

        if (empty($user->idf)) {
            throw new ResponseException('ID Finger tidak ditemukan.', 404);
        }

        $idMesin = $request->input('id_fp_finger_mesin');
        $menitMesin = $this->getMenitMesin($idMesin);
        $waktuFinger = Carbon::now('Asia/Jakarta');

        // jika waktu request tepat pada menit dan detik yang ditentukan untuk mesin tersebut
        if ($waktuFinger->minute == $menitMesin && $waktuFinger->second == 1) {
            throw new ResponseException('Silahkan coba lagi di menit selanjutnya.', 409);
        }

        $tglGenerate = $this->generateTimeDb($waktuFinger, $idMesin);

        $data = [
            'id_fp_finger_mesin' => $idMesin,
            'id_finger' => $user->idf,
            'tanggal_absen' => $waktuFinger,
            'status' => $request->input('status'),
            'hapus' => 0,
            'tgl_insert' => $tglGenerate,
            'tgl_update' => $tglGenerate,
            'user_update' => '',
        ];

        $fpPresensi = FpPresensi::create($data);
        return new BaseResponse($fpPresensi->toArray(), 201);
    }

    private function getMenitMesin(int $idMesin): int
    {
        $mapMenit = [
            1 => 1,
            2 => 11,
            3 => 21,
            4 => 31,
        ];

        return $mapMenit[$idMesin] ?? 2;
    }

    private function generateTimeDb(Carbon $waktuSekarang, int $idMesin): Carbon
    {
        $menitMesin = $this->getMenitMesin($idMesin);
        $detikMesin = 1; // only 01

        $jam = $waktuSekarang->hour;
        $menit = $waktuSekarang->minute;
        $detik = $waktuSekarang->second;

        // jika waktu sudah melewati menit dan detik yang ditentukan pada jam saat ini
        if ($menit > $menitMesin || ($menit == $menitMesin && $detik > $detikMesin)) {
            $jam++;
        }

        // jika jam melewati tengah malam (overflow)
        if ($jam >= 24) {
            // set ke hari berikutnya, pada jam 00 dan menit & detik yang sesuai
            return $waktuSekarang->copy()->addDay()->startOfDay()->setTime(0, $menitMesin, $detikMesin);
        }

        // set ke jam yang sudah dihitung, pada menit dan detik yang sesuai
        return $waktuSekarang->copy()->setTime($jam, $menitMesin, $detikMesin);
    }
}
