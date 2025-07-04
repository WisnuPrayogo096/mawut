<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\MasterFpMasjid;

class FpMasjid extends Model
{
    use HasFactory;
    // protected $connection = "mysql_bosq";
    public $timestamps = false;
    protected $table = 'binroh_finger_pegawai';
    protected $primaryKey = 'id';
    protected $fillable = [
        'id_finger',
        'id_binroh_mesin_finger',
        'waktu_finger',
        'status',
        'hapus',
        'tgl_insert',
        'tgl_update',
        'user_update',
    ];

    public function mesin()
    {
        return $this->belongsTo(MasterFpMasjid::class, 'id_binroh_mesin_finger', 'id');
    }
}
