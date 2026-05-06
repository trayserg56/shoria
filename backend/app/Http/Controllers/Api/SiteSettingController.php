<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;

class SiteSettingController extends Controller
{
    public function __invoke()
    {
        $s = SiteSetting::current();

        return response()->json([
            'logo_text' => $s->logo_text,
            'logo_image_url' => $s->logo_image_url,
            'phone_display' => $s->phone_display,
            'phone_tel' => $s->phone_tel,
            'work_hours_short' => $s->work_hours_short,
        ]);
    }
}
