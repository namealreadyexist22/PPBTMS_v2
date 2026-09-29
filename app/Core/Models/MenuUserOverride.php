<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuUserOverride extends Model
{
    protected $fillable = ['user_id', 'menu_id', 'access'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(\App\Core\Models\Menu::class);
    }
}
