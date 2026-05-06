<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_cards', function (Blueprint $table) {
            $table->string('link_url', 2048)->nullable()->after('image_url');
            $table->json('lines')->nullable()->after('link_url');
        });

        foreach (DB::table('marketing_cards')->orderBy('id')->cursor() as $row) {
            $raw = $row->buttons ?? null;
            $buttons = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $buttons = is_array($decoded) ? $decoded : [];
            }

            $linkUrl = null;
            $lines = [];

            foreach ($buttons as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if ($linkUrl === null && isset($item['url']) && is_string($item['url']) && trim($item['url']) !== '') {
                    $linkUrl = trim($item['url']);
                }
                if (isset($item['label']) && is_string($item['label']) && trim($item['label']) !== '') {
                    $lines[] = trim($item['label']);
                }
            }

            DB::table('marketing_cards')->where('id', $row->id)->update([
                'link_url' => $linkUrl,
                'lines' => $lines === [] ? null : json_encode($lines),
            ]);
        }

        Schema::table('marketing_cards', function (Blueprint $table) {
            $table->dropColumn('buttons');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_cards', function (Blueprint $table) {
            $table->json('buttons')->nullable()->after('image_url');
        });

        foreach (DB::table('marketing_cards')->orderBy('id')->cursor() as $row) {
            $url = is_string($row->link_url ?? null) ? trim($row->link_url) : '';
            $linesRaw = $row->lines ?? null;
            $lines = [];
            if (is_string($linesRaw) && $linesRaw !== '') {
                $decoded = json_decode($linesRaw, true);
                $lines = is_array($decoded) ? $decoded : [];
            }

            $buttons = [];
            foreach ($lines as $i => $label) {
                if (! is_string($label) || trim($label) === '') {
                    continue;
                }
                $buttons[] = [
                    'label' => trim($label),
                    'url' => $url !== '' ? $url : '/catalog',
                ];
            }

            if ($buttons === [] && $url !== '') {
                $buttons[] = ['label' => 'Перейти', 'url' => $url];
            }

            DB::table('marketing_cards')->where('id', $row->id)->update([
                'buttons' => $buttons === [] ? null : json_encode($buttons),
            ]);
        }

        Schema::table('marketing_cards', function (Blueprint $table) {
            $table->dropColumn(['link_url', 'lines']);
        });
    }
};
