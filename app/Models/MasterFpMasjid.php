<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterFpMasjid extends Model
{
    use HasFactory;
    protected $connection = "mysql_bosq";
    protected $table = 'binroh_mesin_finger';
    protected $primaryKey = 'id';
}
