<?php
// Librería de ejemplo (debes usar la completa desde: https://sourceforge.net/projects/phpqrcode/)
class QRcode {
    public static function png($text, $outfile = false, $level = 'L', $size = 3) {
        file_put_contents($outfile, 'QR ' . $text);
    }
}
?>
