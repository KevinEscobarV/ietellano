<?php

namespace App\Support;

trait ResizesInstitutionLogo
{
    /**
     * Return the institution logo as a small cached data URI, suitable for
     * embedding in dompdf (the source PNG is too large to embed as-is).
     */
    protected function institutionLogo(): ?string
    {
        $source = public_path('images/logo.png');

        if (! is_file($source)) {
            return null;
        }

        $cache = storage_path('app/institution-logo.png');

        if (! is_file($cache) || filemtime($cache) < filemtime($source)) {
            $this->resizeLogo($source, $cache, 220);
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($cache));
    }

    private function resizeLogo(string $source, string $target, int $max): void
    {
        [$width, $height] = getimagesize($source);
        $scale = min($max / $width, $max / $height, 1);
        $newW = (int) round($width * $scale);
        $newH = (int) round($height * $scale);

        $src = imagecreatefrompng($source);
        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagepng($dst, $target);
        imagedestroy($src);
        imagedestroy($dst);
    }
}
