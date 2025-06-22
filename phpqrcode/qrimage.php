private static function image($frame, $pixelPerPoint = 4, $outerFrame = 4, $back_color = 0xFFFFFF, $fore_color = 0x000000)
{
    // Conversión segura a int
    $pixelPerPoint = intval($pixelPerPoint);
    $outerFrame = intval($outerFrame);
    $back_color = intval($back_color);
    $fore_color = intval($fore_color);

    $h = count($frame);
    $w = strlen($frame[0]);

    $imgW = $w + 2 * $outerFrame;
    $imgH = $h + 2 * $outerFrame;

    $base_image = ImageCreate($imgW, $imgH);

    // Foreground color
    $r1 = ($fore_color & 0xFF0000) >> 16;
    $g1 = ($fore_color & 0x00FF00) >> 8;
    $b1 = ($fore_color & 0x0000FF);

    // Background color
    $r2 = ($back_color & 0xFF0000) >> 16;
    $g2 = ($back_color & 0x00FF00) >> 8;
    $b2 = ($back_color & 0x0000FF);

    $col[0] = ImageColorAllocate($base_image, $r2, $g2, $b2);
    $col[1] = ImageColorAllocate($base_image, $r1, $g1, $b1);

    imagefill($base_image, 0, 0, $col[0]);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if ($frame[$y][$x] == '1') {
                ImageSetPixel($base_image, $x + $outerFrame, $y + $outerFrame, $col[1]);
            }
        }
    }

    $target_image = ImageCreate($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
    ImageCopyResized($target_image, $base_image, 0, 0, 0, 0, $imgW * $pixelPerPoint, $imgH * $pixelPerPoint, $imgW, $imgH);
    ImageDestroy($base_image);

    return $target_image;
}
