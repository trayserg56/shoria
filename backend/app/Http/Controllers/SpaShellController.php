<?php

namespace App\Http\Controllers;

use App\Services\Seo\SpaServerMetaResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SpaShellController extends Controller
{
    public function __construct(private SpaServerMetaResolver $spaServerMetaResolver) {}

    public function showRoot(Request $request): Response
    {
        return $this->serveSpa($request);
    }

    public function showPath(Request $request, string $path): Response|BinaryFileResponse
    {
        $candidate = public_path($path);
        if (is_file($candidate)) {
            return response()->file($candidate);
        }

        return $this->serveSpa($request);
    }

    private function serveSpa(Request $request): Response
    {
        $indexPath = public_path('spa-index.html');

        if (! is_file($indexPath)) {
            if (app()->environment('local') && is_file(public_path('index.html'))) {
                $indexPath = public_path('index.html');
            } elseif (app()->environment('local') && $request->path() === '') {
                return response(view('welcome'));
            }
        }

        if (! is_file($indexPath)) {
            abort(404);
        }

        $raw = file_get_contents($indexPath);

        if ($raw === false || $raw === '') {
            abort(503, 'SPA index unreadable');
        }

        $seo = $this->spaServerMetaResolver->resolve($request);

        $metaDescription = $seo['description'] !== '' ? $seo['description'] : $seo['title'];

        return response()
            ->view('spa.shell', [
                'seo' => $seo,
                'metaDescription' => $metaDescription,
                'viteHeadTags' => $this->extractHeadAssetTags($raw),
                'bodyHtml' => $this->extractBodyInnerHtml($raw),
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function extractHeadAssetTags(string $html): string
    {
        if (! preg_match('/<head[^>]*>([\s\S]*?)<\/head>/i', $html, $headMatch)) {
            return '';
        }

        $head = $headMatch[1];
        $tags = [];

        if (preg_match_all('#<script\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*</script>#i', $head, $scripts, PREG_SET_ORDER)) {
            foreach ($scripts as $match) {
                if (str_contains($match[1], 'assets/')) {
                    $tags[] = $match[0];
                }
            }
        }

        if (preg_match_all('#<link\b[^>]+>#i', $head, $links, PREG_SET_ORDER)) {
            foreach ($links as $match) {
                $tag = $match[0];
                if (preg_match('/href=["\']([^"\']+)["\']/', $tag, $hrefMatch) === 1 && str_contains($hrefMatch[1], 'assets/')) {
                    $tags[] = $tag;
                }
            }
        }

        return implode("\n    ", $tags);
    }

    private function extractBodyInnerHtml(string $html): string
    {
        if (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $html, $m)) {
            return $m[1];
        }

        return '';
    }
}
