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

    public function responseApiV1()
    {
        $apiUrl = 'https://muslimsalat.com/malang.json?key=bc2f2bba711f74e1e342eb7cfba0d459';

        try {
            $response = Http::get($apiUrl);

            if ($response->failed() || !data_get($response->json(), 'items.0')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data jadwal sholat dari API.',
                    'data'    => null
                ], 502);
            }

            $item = data_get($response->json(), 'items.0');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat terhubung ke server jadwal sholat.',
                'error'   => $e->getMessage(),
                'data'    => null
            ], 500);
        }

        $jadwalSholat = [
            'subuh'    => $item['fajr'],
            'dzuhur'   => $item['dhuhr'],
            'ashar'    => $item['asr'],
            'maghrib'  => $item['maghrib'],
            'isya'     => $item['isha'],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil jadwal sholat.',
            'kota'    => data_get($response->json(), 'query', 'Malang'),
            'tanggal' => data_get($item, 'date_for'),
            'sumber'  => 'MuslimSalat API',
            'data'    => $jadwalSholat
        ], 200);
    }

    public function responseApiV2()
    {
        $dateNow = Carbon::now('Asia/Jakarta')->format('d-m-Y');
        $apiUrl = "https://api.aladhan.com/v1/timingsByCity/{$dateNow}?city=Malang&country=Indonesia&method=1";

        try {
            $response = Http::get($apiUrl);

            if ($response->failed() || !data_get($response->json(), 'data.timings')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data jadwal sholat dari API.',
                    'data'    => null
                ], 502);
            }

            $timings = data_get($response->json(), 'data.timings');
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat terhubung ke server jadwal sholat.',
                'error'   => $e->getMessage(),
                'data'    => null
            ], 500);
        }

        $jadwalSholat = [
            'subuh'    => $timings['Fajr'],
            'dzuhur'   => $timings['Dhuhr'],
            'ashar'    => $timings['Asr'],
            'maghrib'  => $timings['Maghrib'],
            'isya'     => $timings['Isha'],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil jadwal sholat.',
            'tanggal' => $dateNow,
            'kota'    => 'Malang',
            'metode'  => 'Kemenag (Method 1)',
            'data'    => $jadwalSholat
        ], 200);
    }

    public function jadwalSholatV1()
    {
        $apiSholat = 'https://muslimsalat.com/malang.json?key=bc2f2bba711f74e1e342eb7cfba0d459';

        try {
            $response = Http::get($apiSholat);

            if ($response->failed()) {
                return new BaseResponse([
                    'error' => 'Gagal mengambil data jadwal sholat dari API.',
                ], 502);
            }

            $jadwalHariIni = $response->json('items.0');

            if (!$jadwalHariIni) {
                return new BaseResponse([
                    'error' => 'Data jadwal sholat tidak ditemukan.',
                ], 502);
            }

            $user = Auth::user();
            $idf = $user ? $user->idf : null;
        } catch (\Throwable $e) {
            // Log::error('Jadwal sholat error: '.$e->getMessage());

            return new BaseResponse([
                'error' => 'Tidak dapat terhubung ke server jadwal sholat.',
            ], 500);
        }

        $dateNow = Carbon::parse($jadwalHariIni['date_for'], 'Asia/Jakarta')->toDateString();

        $responseData = [
            'date' => $dateNow,
            'prayer' => null,
            'start_time' => null,
            'end_time' => null,
            'active' => false,
            'status' => false,
            'message' => 'Tidak ada waktu sholat saat ini',
        ];

        $waktuSholatPenting = [
            'Subuh'   => $jadwalHariIni['fajr'],
            'Dzuhur'  => $jadwalHariIni['dhuhr'],
            'Ashar'   => $jadwalHariIni['asr'],
            'Maghrib' => $jadwalHariIni['maghrib'],
            'Isya'    => $jadwalHariIni['isha'],
        ];

        $sekarang = Carbon::now('Asia/Jakarta');
        // $sekarang = Carbon::parse('2025-11-20 19:00:00', 'Asia/Jakarta');

        foreach ($waktuSholatPenting as $prayer => $waktu) {
            $waktuMulai = Carbon::parse($dateNow . ' ' . $waktu, 'Asia/Jakarta');
            $waktuSelesai = $waktuMulai->copy()->addMinutes(50);

            if ($sekarang->between($waktuMulai, $waktuSelesai, true)) {
                $statusPegawai = false;

                if ($idf) {
                    $statusPegawai = FpMasjid::where('id_finger', $idf)
                        ->where('hapus', 0)
                        ->whereBetween('waktu_finger', [$waktuMulai, $waktuSelesai])
                        ->exists();
                }

                $responseData = [
                    'date'       => $dateNow,
                    'prayer'     => $prayer,
                    'start_time' => $waktuMulai->format('H:i'),
                    'end_time'   => $waktuSelesai->format('H:i'),
                    'active'     => true,
                    'status'     => $statusPegawai,
                    'message'    => "Sedang waktu sholat $prayer",
                ];
                break;
            }
        }

        return new BaseResponse($responseData, 200);
    }

    public function jadwalSholatV2()
    {
        $dateNowApi = Carbon::now('Asia/Jakarta')->format('d-m-Y');
        $apiUrl = "https://api.aladhan.com/v1/timingsByCity/{$dateNowApi}?city=Malang&country=Indonesia&method=1";

        try {
            $response = Http::get($apiUrl);

            if ($response->failed()) {
                return new BaseResponse([
                    'error' => 'Gagal mengambil data jadwal sholat dari API.',
                ], 502);
            }

            $data = $response->json('data');

            if (!$data || !isset($data['timings'])) {
                return new BaseResponse([
                    'error' => 'Data jadwal sholat tidak ditemukan.',
                ], 502);
            }

            $timings = $data['timings'];

            $gregorianDate = $data['date']['gregorian']['date'] ?? null;

            if ($gregorianDate) {
                $dateNow = Carbon::createFromFormat('d-m-Y', $gregorianDate, 'Asia/Jakarta')
                    ->toDateString();
            } else {
                $dateNow = Carbon::now('Asia/Jakarta')->toDateString();
            }

            $user = Auth::user();
            $idf = $user ? $user->idf : null;
        } catch (\Throwable $e) {
            // Log::error('Jadwal sholat error: '.$e->getMessage());
            return new BaseResponse([
                'error' => 'Tidak dapat terhubung ke server jadwal sholat.',
            ], 500);
        }

        $responseData = [
            'date'       => $dateNow,
            'prayer'     => null,
            'start_time' => null,
            'end_time'   => null,
            'active'     => false,
            'status'     => false,
            'message'    => 'Tidak ada waktu sholat saat ini',
        ];

        $waktuSholatPenting = [
            'Subuh'   => $timings['Fajr']    ?? null,
            'Dzuhur'  => $timings['Dhuhr']   ?? null,
            'Ashar'   => $timings['Asr']     ?? null,
            'Maghrib' => $timings['Maghrib'] ?? null,
            'Isya'    => $timings['Isha']    ?? null,
        ];

        $sekarang = Carbon::now('Asia/Jakarta');
        // $sekarang = Carbon::parse('2025-11-20 17:35:00', 'Asia/Jakarta'); // untuk testing

        foreach ($waktuSholatPenting as $prayer => $waktu) {
            if (!$waktu) {
                continue;
            }

            $waktuSholat = Carbon::parse($dateNow . ' ' . $waktu, 'Asia/Jakarta');
            $waktuMulai = $waktuSholat->copy()->addMinutes(10);
            $waktuSelesai = $waktuMulai->copy()->addMinutes(50);

            if ($sekarang->between($waktuMulai, $waktuSelesai, true)) {
                $statusPegawai = false;

                if ($idf) {
                    $statusPegawai = FpMasjid::where('id_finger', $idf)
                        ->where('hapus', 0)
                        ->whereBetween('waktu_finger', [$waktuMulai, $waktuSelesai])
                        ->exists();
                }

                $responseData = [
                    'date'       => $dateNow,
                    'prayer'     => $prayer,
                    'start_time' => $waktuMulai->format('H:i'),
                    'end_time'   => $waktuSelesai->format('H:i'),
                    'active'     => true,
                    'status'     => $statusPegawai,
                    'message'    => "Sedang waktu sholat $prayer",
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
            'id_binroh_mesin_finger' => 'required|integer|exists:binroh_mesin_finger,id',
            // 'id_binroh_mesin_finger' => 'required|integer|exists:mysql_bosq.binroh_mesin_finger,id',
        ], [
            'id_binroh_mesin_finger.required' => 'ID mesin finger wajib diisi.',
            'id_binroh_mesin_finger.integer' => 'ID mesin finger harus berupa angka.',
            'id_binroh_mesin_finger.exists' => 'ID mesin finger tidak ditemukan.',
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
