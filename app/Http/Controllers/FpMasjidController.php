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
            $dateKey = Carbon::parse($item->waktu_finger)->format('Y-m-d');

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

        // Panggil API dan lakukan validasi dasar
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

        $dateNow = Carbon::parse($jadwalHariIni['date_for'])->format('Y-m-d');
        // Siapkan data awal untuk respons JSON
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

        // Logika utama untuk menentukan jadwal sholat saat ini
        $sekarang = Carbon::now('Asia/Jakarta');

        foreach ($waktuSholatPenting as $nama => $waktu) {
            // Konversi string waktu dari API menjadi objek Carbon
            $waktuMulai = Carbon::createFromFormat('g:i a', $waktu, 'Asia/Jakarta');

            // Tambahkan 50 menit untuk mendapatkan waktu akhir rentang
            $waktuSelesai = $waktuMulai->copy()->addMinutes(50);

            // Cek apakah waktu saat ini berada di dalam rentang
            if ($sekarang->between($waktuMulai, $waktuSelesai, true)) {
                // Jika ya, ubah data respons dengan informasi yang relevan
                $responseData = [
                    'date' => $dateNow,
                    'prayer' => $nama,
                    'start_time' => $waktuMulai->format('H:i'),
                    'end_time' => $waktuSelesai->format('H:i'),
                    'active' => true,
                ];
                break; // Hentikan loop karena jadwal sudah ditemukan
            }
        }

        return new BaseResponse($responseData, 200);
    }
}
