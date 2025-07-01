<?php

namespace App\Http\Controllers;

use App\Models\MasterFpMasjid;
use App\Models\MasterFpPresensi;
use App\Http\Exceptions\ResponseException;

class MesinFingerController extends Controller
{
    private function isConnected($ip): bool
    {
        $output = [];
        if (stripos(PHP_OS, 'WIN') === 0) {
            exec("ping -n 1 -w 1 $ip", $output);
        } else {
            exec("ping -c 1 -W 1 $ip", $output);
        }

        // cek setiap baris output
        foreach ($output as $line) {
            if (stripos($line, 'ttl') !== false) {
                return true;
            }
        }
        return false;
    }

    public function indexMasjid()
    {
        $masterFpMasjid = MasterFpMasjid::where([
            ['hapus', 0],
            ['status', 1],
        ])->get();

        if ($masterFpMasjid->isEmpty()) {
            throw new ResponseException('Data mesin finger masjid tidak ditemukan atau kosong.', 404);
        }

        $masterFpMasjid = $masterFpMasjid->map(function ($item) {
            return [
                'id' => $item->id,
                'ip_mesin' => $item->ip_mesin,
                'lokasi_mesin' => $item->lokasi_mesin,
                'status_koneksi' => $this->isConnected($item->ip_mesin) ? 'terhubung' : 'terputus',
            ];
        });

        return response()->json($masterFpMasjid);
    }

    public function indexPresensi()
    {
        $masterFpPresensi = MasterFpPresensi::where('hapus', 0)->get();

        if ($masterFpPresensi->isEmpty()) {
            throw new ResponseException('Data mesin finger presensi tidak ditemukan atau kosong.', 404);
        }

        $masterFpPresensi = $masterFpPresensi->map(function ($item) {
            return [
                'id' => $item->id,
                'ip_mesin' => $item->ipmesin,
                'lokasi_mesin' => $item->lokasi_mesin,
                'status_koneksi' => $this->isConnected($item->ipmesin) ? 'terhubung' : 'terputus',
            ];
        });

        return response()->json($masterFpPresensi);
    }
}
