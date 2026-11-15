<?php

namespace App\Support;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Sinh mã QR dạng SVG (không cần extension gd/imagick) - BN-09. */
class QrCode
{
    public static function svg(string $text, int $size = 200): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()));

        // Bỏ khai báo XML để nhúng trực tiếp vào HTML
        return preg_replace('/^<\?xml.*?\?>\s*/', '', $writer->writeString($text));
    }

    public static function dataUri(string $text, int $size = 200): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $size));
    }
}
