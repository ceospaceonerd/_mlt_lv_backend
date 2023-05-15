<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Casts\EncryptCast;
class Plan extends Model
{
    use HasFactory;
    protected $fillable = [
        'name','price','duration','max_users','durationtype','tenant_id',
    ];
    protected $encryptable = [
        'id',
    ];

}
