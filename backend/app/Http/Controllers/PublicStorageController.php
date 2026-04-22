<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicStorageController extends Controller
{
    public function show(string $path): BinaryFileResponse
    {
        $storageRoot = realpath(storage_path('app/public'));

        if ($storageRoot === false) {
            abort(404);
        }

        $absolutePath = realpath($storageRoot . DIRECTORY_SEPARATOR . $path);

        if (
            $absolutePath === false ||
            ! str_starts_with($absolutePath, $storageRoot . DIRECTORY_SEPARATOR) ||
            ! is_file($absolutePath)
        ) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
