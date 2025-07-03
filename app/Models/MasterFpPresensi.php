<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterFpPresensi extends Model
{
    use HasFactory;
    // protected $connection = "mysql_bosq";
    protected $table = 'fp_finger_mesin';
    protected $primaryKey = 'id';
}
