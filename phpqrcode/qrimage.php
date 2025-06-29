<?php
/* PHP QR Code encoder - Image output using GD2 */
define('QR_IMAGE', true);

class qrimage {

    //----------------------------------------------------------------------  
    public static function png($frame, $filename = false, $pixelPerPoint = 4, $outerFrame = 4, $saveandprint = false, $back_color = 0xFFFFFF, $fore_color = 0x000000)
    {
        $image = self::image($frame, $pixelPerPoint, $outerFrame, $back_color, $fore_color);

        if ($filename === false) {
            header("Content-type: image/png");
            imagepng($image);
        } else {
            if ($saveandprint === true) {
                imagepng($image, $filename);
                header("Content-type: image/png");
                imagepng($image);
            } else {
                imagepng($image, $filename);
            }
        }

        imagedestroy($image);
    }

    //----------------------------------------------------------------------  
    public static function jpg($frame, $filename = false, $pixelPerPoint = 8, $outerFrame = 4, $q = 85)
    {
        $image = self::image($frame, $pixelPerPoint, $outerFrame);

        if ($filename === false) {
            header("Content-type: image/jpeg");
            imagejpeg($image, null, $q);
        } else {
            imagejpeg($image, $filename, $q);
        }

        imagedestroy($image);
    }

    //----------------------------------------------------------------------  
    private static function image($frame, $pixelPerPoint = 4, $outerFrame = 4, $back_color = 0xFFFFFF, $fore_color = 0x000000)
    {
        // Conversión segura
        $pixelPerPoint = max(1, intval($pixelPerPoint));
        $outerFrame = max(0, intval($outerFrame));
        $back_color = intval($back_color);
        $fore_color = intval($fore_color);

        $h = count($frame);
        $w = strlen($frame[0]);

        $imgW = $w + 2 * $outerFrame;
        $imgH = $h + 2 * $outerFrame;

        $base_image = imagecreate($imgW, $imgH);
        if (!$base_image) {
            die("Error: No se pudo crear la imagen base.");
        }

        // Colores
        $r1 = ($fore_color >> 16) & 0xFF;
        $g1 = ($fore_color >> 8) & 0xFF;
        $b1 = $fore_color & 0xFF;

        $r2 = ($back_color >> 16) & 0xFF;
        $g2 = ($back_color >> 8) & 0xFF;
        $b2 = $back_color & 0xFF;

        $col[0] = imagecolorallocate($base_image, $r2, $g2, $b2);
        $col[1] = imagecolorallocate($base_image, $r1, $g1, $b1);

        imagefill($base_image, 0, 0, $col[0]);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($frame[$y][$x] === '1') {
                    imagesetpixel($base_image, $x + $outerFrame, $y + $outerFrame, $col[1]);
                }
            }
        }

        $target_image = imagecreatetruecolor($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
        if (!$target_image) {
            die("Error: No se pudo crear la imagen escalada.");
        }

        imagecopyresized(
            $target_image, $base_image,
            0, 0, 0, 0,
            $imgW * $pixelPerPoint, $imgH * $pixelPerPoint,
            $imgW, $imgH
        );

        imagedestroy($base_image);

        return $target_image;
    }
}
