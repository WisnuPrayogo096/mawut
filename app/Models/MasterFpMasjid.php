<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\FpMasjid;

class MasterFpMasjid extends Model
{
    use HasFactory;
    // protected $connection = "mysql_bosq";
    protected $table = 'binroh_mesin_finger';
    protected $primaryKey = 'id';
    protected $fillable = [
        'ip_mesin',
        'lokasi_mesin',
        'status',
        'hapus',
        'tgl_insert',
        'tgl_update',
        'user_update',
    ];

    public function fingers()
    {
        return $this->hasMany(FpMasjid::class, 'id_binroh_mesin_finger', 'id');
    }
}
