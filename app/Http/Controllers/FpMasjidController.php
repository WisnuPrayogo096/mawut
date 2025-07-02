<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\ResponseException;
use App\Http\Resources\BaseResponse;
use App\Models\FpMasjid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class FpMasjidController extends Controller
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

        $bulan = $bulanQuery !== null ? (int)$bulanQuery : Carbon::now('Asia/Jakarta')->month;
        $tahun = $tahunQuery !== null ? (int)$tahunQuery : Carbon::now('Asia/Jakarta')->year;
        $page = $pageQuery !== null ? (int)$pageQuery : 1;
        $perPage = 10;
        $idf = $user->idf;

        if (!$user || empty($idf)) {
            throw new ResponseException(
                'ID finger user tidak ditemukan atau kosong. Akses ditolak.',
                403
            );
        }

        $fpMasjid = FpMasjid::where('id_finger', $idf)
            ->whereRaw('YEAR(waktu_finger) = ?', [$tahun])
            ->whereRaw('MONTH(waktu_finger) = ?', [$bulan])
            ->where('hapus', 0)
            ->orderByDesc('waktu_finger')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($fpMasjid->isEmpty()) {
            throw new ResponseException(
                'Data finger masjid tidak ditemukan untuk bulan dan tahun yang dipilih.',
                404
            );
        }

        $groupedItems = [];
        foreach ($fpMasjid->items() as $item) {
            $dateKey = Carbon::parse($item->waktu_finger, 'Asia/Jakarta')->format('Y-m-d');

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
            'current_page' => $fpMasjid->currentPage(),
            'items' => $groupedItemsArray,
            'per_page' => $fpMasjid->perPage(),
            'total' => $fpMasjid->total(),
            'last_page' => $fpMasjid->lastPage(),
            'from' => $fpMasjid->firstItem(),
            'to' => $fpMasjid->lastItem(),
            'links' => [
                'first' => $fpMasjid->url(1),
                'last' => $fpMasjid->url($fpMasjid->lastPage()),
                'prev' => $fpMasjid->previousPageUrl(),
                'next' => $fpMasjid->nextPageUrl(),
            ]
        ];

        return new BaseResponse($responseData, 200);
    }

    public function jadwalSholat()
    {
        $apiSholat = 'https://muslimsalat.com/malang.json?key=bc2f2bba711f74e1e342eb7cfba0d459';

        try {
            $response = Http::get($apiSholat);

            if ($response->failed() || !isset($response->json()['items'][0])) {
                return response()->json(['error' => 'Gagal mengambil data jadwal sholat dari API.'], 502);
            }

            $data = $response->json();
            $jadwalHariIni = $data['items'][0];
        } catch (\Exception $e) {
            return response()->json(['error' => 'Tidak dapat terhubung ke server jadwal sholat.'], 500);
        }

        $dateNow = Carbon::parse($jadwalHariIni['date_for'], 'Asia/Jakarta')->format('Y-m-d');
        $responseData = [
            'date' => $dateNow,
            'message' => 'Tidak ada waktu sholat saat ini',
            'active' => false,
        ];

        $waktuSholatPenting = [
            'Subuh'   => $jadwalHariIni['fajr'],
            'Dzuhur'  => $jadwalHariIni['dhuhr'],
            'Ashar'   => $jadwalHariIni['asr'],
            'Maghrib' => $jadwalHariIni['maghrib'],
            'Isya'    => $jadwalHariIni['isha'],
        ];

        $sekarang = Carbon::now('Asia/Jakarta');

        foreach ($waktuSholatPenting as $prayer => $waktu) {
            $waktuMulai = Carbon::createFromFormat('g:i a', $waktu, 'Asia/Jakarta');

            // tambahkan range 50 menit
            $waktuSelesai = $waktuMulai->copy()->addMinutes(50);

            if ($sekarang->between($waktuMulai, $waktuSelesai, true)) {
                $responseData = [
                    'date' => $dateNow,
                    'prayer' => $prayer,
                    'start_time' => $waktuMulai->format('H:i'),
                    'end_time' => $waktuSelesai->format('H:i'),
                    'active' => true,
                ];
                break;
            }
        }

        return new BaseResponse($responseData, 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            // 'id_binroh_mesin_finger' => 'required|integer|exists:binroh_mesin_finger,id',
            'id_binroh_mesin_finger' => 'required|integer',
        ], [
            'id_binroh_mesin_finger.required' => 'ID mesin finger wajib diisi.',
            'id_binroh_mesin_finger.integer' => 'ID mesin finger harus berupa angka.',
            // 'id_binroh_mesin_finger.exists' => 'ID mesin finger tidak ditemukan.',
        ]);

        if ($validator->fails()) {
            throw new ResponseException('Data yang dikirim tidak valid.', 400);
        }

        if (empty($user->idf)) {
            throw new ResponseException('ID Finger tidak ditemukan.', 404);
        }

        $waktuFinger = Carbon::now('Asia/Jakarta');

        // jika waktu request tepat pada menit ke-2 dan detik ke-1, buak.
        if ($waktuFinger->minute == 2 && $waktuFinger->second == 1) {
            throw new ResponseException('Silahkan coba lagi di menit selanjutnya.', 409);
        }

        $tglGenerate = $this->generateTimeDb($waktuFinger);

        $data = [
            'id_finger' => $user->idf,
            'id_binroh_mesin_finger' => $request->input('id_binroh_mesin_finger'),
            'waktu_finger' => $waktuFinger,
            'status' => 0,
            'hapus' => 0,
            'tgl_insert' => $tglGenerate,
            'tgl_update' => $tglGenerate,
            'user_update' => '',
        ];

        $fpMasjid = FpMasjid::create($data);
        return new BaseResponse($fpMasjid->toArray(), 201);
    }

    private function generateTimeDb(Carbon $waktuSekarang): Carbon
    {
        $jam = $waktuSekarang->hour;
        $menit = $waktuSekarang->minute;
        $detik = $waktuSekarang->second;

        // jika waktu sudah melewati menit ke-2, detik ke-1 pada jam saat ini
        if ($menit > 2 || ($menit == 2 && $detik > 1)) {
            $jam++;
        }

        // jika jam melewati tengah malam (overflow)
        if ($jam >= 24) {
            // set ke hari berikutnya, jam 00:02:01
            return $waktuSekarang->copy()->addDay()->startOfDay()->setTime(0, 2, 1);
        }

        // set ke jam yang sudah dihitung, pada menit ke-2 dan detik ke-1
        return $waktuSekarang->copy()->setTime($jam, 2, 1);
    }
}
