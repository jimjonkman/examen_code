<?php
declare(strict_types=1);

$directory = dirname(__DIR__) . '/storage/uploads/samples';
if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
}
foreach (['cv' => 'Voorbeeld CV - fictieve testgegevens', 'letter' => 'Voorbeeld motivatiebrief - fictieve testgegevens'] as $name => $text) {
    $stream = "BT /F1 18 Tf 50 770 Td ({$text}) Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    file_put_contents($directory . '/' . $name . '.pdf', $pdf);
}
echo 'Voorbeelddocumenten aangemaakt.' . PHP_EOL;