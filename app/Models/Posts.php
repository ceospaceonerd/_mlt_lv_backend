<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Posts extends Model
{
    use HasFactory;

    public $fillable = [
        'title', 'photo', 'description','category_id','short_description','slug'
    ];

}
