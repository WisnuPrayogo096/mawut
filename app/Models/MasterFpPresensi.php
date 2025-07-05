<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\FpPresensi;

class MasterFpPresensi extends Model
{
    use HasFactory;
    protected $connection = "mysql_bosq";
    protected $table = 'fp_finger_mesin';
    protected $primaryKey = 'id';
    protected $fillable = [
        'ipmesin',
        'lokasi_mesin',
        'hapus',
        'tgl_insert',
        'tgl_update',
        'user_update',
    ];

    public function fingers()
    {
        return $this->hasMany(FpPresensi::class, 'id_fp_finger_mesin', 'id');
    }
}
