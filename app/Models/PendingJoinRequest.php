<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingJoinRequest extends Model
{
    protected $fillable = [
        'user_id',
        'chat_id',
        'message_id',
        'expires_at',
        'processed',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'processed' => 'boolean',
    ];
}
