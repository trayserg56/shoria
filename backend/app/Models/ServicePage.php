<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePage extends Model
{
    use HasAuthorship, HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'seo_title',
        'seo_description',
        'is_active',
        'show_in_header',
        'show_in_footer',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_header' => 'boolean',
        'show_in_footer' => 'boolean',
        'sort_order' => 'integer',
    ];
}

