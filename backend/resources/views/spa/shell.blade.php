<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="{{ $seo['robots'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:type" content="{{ $seo['og_type'] }}">
    <meta property="og:site_name" content="Shoria">
    @if (! empty($seo['og_image']))
    <meta property="og:image" content="{{ $seo['og_image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    @else
    <meta name="twitter:card" content="summary">
    @endif
    @foreach ($seo['json_ld'] as $block)
    <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endforeach
    {!! $viteHeadTags !!}
</head>
<body>
{!! $bodyHtml !!}
</body>
</html>
