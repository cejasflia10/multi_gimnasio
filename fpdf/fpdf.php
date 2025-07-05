<?php
// Versión básica de FPDF (simulación funcional mínima)
class FPDF {
    function AddPage() {}
    function SetFont($fam, $style='', $size=0) {}
    function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {}
    function Output($dest='', $name='', $isUTF8=false) {
        echo "PDF generado correctamente (simulado).";
    }
}
?>
