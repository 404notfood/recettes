<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecipeImages
{
    public function store(UploadedFile $file): string
    {
        $source = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new \RuntimeException('Cette image ne peut pas être ouverte.');
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, 1600 / max($width, $height));
        $image = imagecreatetruecolor(max(1, (int) ($width * $ratio)), max(1, (int) ($height * $ratio)));
        imagefill($image, 0, 0, 0xFAF8F2);
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $width, $height);
        ob_start();
        imagewebp($image, null, 85);
        $bytes = ob_get_clean();
        $path = 'recipes/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, (string) $bytes);

        return $path;
    }
}
