<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_name',
        'page_url',
        'referrer',
        'session_id',
        'first_touch_source',
        'first_touch_medium',
        'first_touch_campaign',
        'first_touch_content',
        'first_touch_term',
        'first_touch_referrer_host',
        'first_touch_landing_path',
        'payload',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];
}
