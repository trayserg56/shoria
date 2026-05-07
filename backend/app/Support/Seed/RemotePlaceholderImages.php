<?php

namespace App\Support\Seed;

/**
 * Внешние демо-картинки без picsum.photos (у витрин часто 403 от Cloudflare).
 * Тот же пул, что и в ShopDemoSeeder (Unsplash).
 */
final class RemotePlaceholderImages
{
    /**
     * @var list<string>
     */
    public const UNSPLASH_PHOTO_IDS = [
        '1542291026-7eec264c27ff',
        '1460353581641-37baddab0fa2',
        '1518002171953-a080ee817e1f',
        '1525966222134-fcfa99b8ae77',
        '1549298916-b41d501d3772',
        '1600185365483-26d7a4cc7519',
        '1543508282-6319a3e2621f',
        '1515955656352-a1fa3ffcd111',
        '1607522370275-f14206abe5d3',
        '1605348532760-6753d2c43329',
        '1523275335684-37898b6baf30',
        '1478131143081-80f7f994ca69',
        '1491553895911-0055eca6402d',
        '1511556532299-8f662fc26c06',
        '1514989940723-e8e51635b782',
        '1546435770-a3e426bf472b',
    ];

    public static function productImageUrl(int $seed): string
    {
        return self::unsplashUrl($seed, 1200, null);
    }

    public static function categoryImageUrl(int $seed): string
    {
        return self::unsplashUrl($seed, 800, 520);
    }

    private static function unsplashUrl(int $seed, int $w, ?int $h): string
    {
        $ids = self::UNSPLASH_PHOTO_IDS;
        $id = $ids[$seed % count($ids)];
        $hPart = $h !== null ? "&h={$h}" : '';

        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w={$w}{$hPart}&q=80";
    }
}
