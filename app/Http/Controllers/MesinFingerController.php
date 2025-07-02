<?php

namespace App\Http\Controllers;

use App\Models\MasterFpMasjid;
use App\Models\MasterFpPresensi;
use App\Http\Exceptions\ResponseException;
use App\Http\Resources\BaseResponse;

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
                'ip' => $item->ip_mesin,
                'location' => $item->lokasi_mesin,
                'connection' => $this->isConnected($item->ip_mesin) ? 'Connected' : 'Disconnected',
            ];
        });

        return new BaseResponse($masterFpMasjid->toArray(), 200);
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
                'ip' => $item->ipmesin,
                'location' => $item->lokasi_mesin,
                'connection' => $this->isConnected($item->ipmesin) ? 'Connected' : 'Disconnected',
            ];
        });

        return new BaseResponse($masterFpPresensi->toArray(), 200);
    }
}
