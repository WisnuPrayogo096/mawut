<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FpPresensi extends Model
{
    use HasFactory;
    // protected $connection = "mysql_bosq";
    public $timestamps = false;
    protected $table = 'fp_finger_pegawai';
    protected $primaryKey = 'id';
    protected $fillable = [
        'id_fp_finger_mesin',
        'id_finger',
        'tanggal_absen',
        'status',
        'hapus',
        'tgl_insert',
        'tgl_update',
        'user_update',
    ];
}
