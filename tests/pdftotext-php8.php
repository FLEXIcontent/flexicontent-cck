<?php
/**
 * The bundled PdfToText library (site/librairies/PdfToText, used to index the text of PDF files) must load and
 * extract text without any PHP notice, warning or deprecation, and its replacement of utf8_encode() must give
 * the same bytes.
 */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    // Calls silenced with @ in the library stay silent
    if (!(error_reporting() & $severity)) {
        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!extension_loaded('gd')) {
    // The library uses GD constants in its class definitions
    fwrite(STDERR, "pdftotext-php8: skipped, the GD extension is not loaded\n");
    exit(0);
}

require __DIR__ . '/../site/librairies/PdfToText/PdfToText.php';

// 1. ISO-8859-1 to UTF-8, the conversion utf8_encode() made
$latin1 = '';
$utf8   = '';
for ($i = 0; $i < 256; $i++) {
    $latin1 .= chr($i);
    $utf8   .= $i < 0x80 ? chr($i) : chr(0xC0 | ($i >> 6)) . chr(0x80 | ($i & 0x3F));
}
if (PdfObjectBase::Latin1ToUtf8($latin1) !== $utf8) {
    throw new RuntimeException('Latin1ToUtf8() does not convert ISO-8859-1 to UTF-8');
}
if (PdfObjectBase::Latin1ToUtf8("caf\xE9") !== "caf\xC3\xA9") {
    throw new RuntimeException('Latin1ToUtf8() does not convert an accented letter');
}

// 2. Text of a small two-page PDF
$stream = static function ($text) {
    $data = 'BT /F1 24 Tf 72 720 Td (' . $text . ') Tj ET';
    return '<< /Length ' . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
};
$objects = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R 6 0 R] /Count 2 >>',
    3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
    4 => $stream('Hello FLEXIcontent'),
    5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    6 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 7 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
    7 => $stream('Second page'),
];
$pdf     = "%PDF-1.4\n";
$offsets = [];
foreach ($objects as $number => $body) {
    $offsets[$number] = strlen($pdf);
    $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
}
$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
foreach ($offsets as $offset) {
    $pdf .= sprintf("%010d 00000 n \n", $offset);
}
$pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";

$file = tempnam(sys_get_temp_dir(), 'fcpdf');
file_put_contents($file, $pdf);

try {
    $parser = new PdfToText($file);
    $text   = $parser->Text;
} finally {
    unlink($file);
}

if (strpos($text, 'Hello FLEXIcontent') === false || strpos($text, 'Second page') === false) {
    throw new RuntimeException('Unexpected text: ' . var_export($text, true));
}
if (count($parser->Pages) !== 2) {
    throw new RuntimeException('Expected 2 pages, got ' . count($parser->Pages));
}

// ASCII85 emits bytes from a 32-bit accumulator, including partial final groups.
$decoder = new ReflectionMethod(PdfToText::class, '__decode_ascii_85');
$emptyParser = (new ReflectionClass(PdfToText::class))->newInstanceWithoutConstructor();
foreach (['5sdq,~>' => 'ABCD', '5sdp~>' => 'ABC', 'z~>' => "\0\0\0\0"] as $encoded => $expected) {
    if ($decoder->invoke($emptyParser, $encoded) !== $expected) {
        throw new RuntimeException('ASCII85 byte decoding changed');
    }
}
// A PDF octal escape keeps the low eight bits, including values above 0377.
foreach (['\\101\\102' => 'AB', '\\377' => "\xFF", '\\777' => "\xFF"] as $encoded => $expected) {
    foreach (['Unescape', 'ProcessEscapedString'] as $method) {
        if ((new ReflectionMethod(PdfObjectBase::class, $method))->invoke($emptyParser, $encoded, true) !== $expected) {
            throw new RuntimeException('PDF octal byte decoding changed');
        }
    }
}

echo "pdftotext-php8: OK\n";
