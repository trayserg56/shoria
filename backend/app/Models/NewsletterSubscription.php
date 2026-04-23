<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'source',
        'status',
        'first_touch_source',
        'first_touch_medium',
        'first_touch_campaign',
        'first_touch_content',
        'first_touch_term',
        'first_touch_referrer_host',
        'first_touch_landing_path',
        'subscribed_at',
        'unsubscribed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];
}
