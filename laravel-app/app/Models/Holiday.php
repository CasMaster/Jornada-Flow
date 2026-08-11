<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['date', 'name', 'blocks_requests', 'created_by'];

    protected $casts = ['date' => 'date', 'blocks_requests' => 'boolean'];
}
