<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['date', 'name', 'blocks_requests', 'created_by', 'source', 'scope', 'external_id', 'last_synced_at'];

    protected $casts = ['date' => 'date', 'blocks_requests' => 'boolean', 'last_synced_at' => 'datetime'];
}
