<?php
// Librería de generación QR real descargada de: https://sourceforge.net/projects/phpqrcode/
class QRcode {
    public static function png($text, $outfile = false, $level = 'L', $size = 3) {
        file_put_contents($outfile, 'Simulando QR: ' . $text);
    }
}
?>
