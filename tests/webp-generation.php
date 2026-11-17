<?php
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message) {
    if (error_reporting() & $severity) throw new RuntimeException($message);
    return true;
});
if (!function_exists('imagewebp')) { fwrite(STDERR, "WebP generation skipped: GD WebP unavailable\n"); exit(0); }
$source = file_get_contents($argv[1] ?? __DIR__ . '/../plugins/flexicontent_fields/image/image.php');
$start = strpos($source, 'function generateWebpVariant(');
$method = ''; $depth = 0; $opened = false;
foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
    $method .= is_array($token) ? $token[1] : $token;
    if ($token === '{') { ++$depth; $opened = true; }
    if ($token === '}' && --$depth === 0 && $opened) break;
}
eval('class WebpGenerationFixture { public ' . $method . '}');
$dir = sys_get_temp_dir() . '/fc-webp-test-' . getmypid();
mkdir($dir); $generator = new WebpGenerationFixture; $checks = 0;
try {
    foreach (['jpeg', 'png', 'gif', 'indexed-png'] as $format) {
        $indexed = in_array($format, ['gif', 'indexed-png'], true);
        $image = $indexed ? imagecreate(73, 41) : imagecreatetruecolor(73, 41);
        imagealphablending($image, false); imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 80, 100, 140, 127);
        imagefill($image, 0, 0, $transparent);
        if ($indexed) imagecolortransparent($image, $transparent);
        imagefilledrectangle($image, 10, 10, 20, 20, imagecolorallocate($image, 200, 90, 50));
        $extension = $format === 'indexed-png' ? 'png' : $format;
        $src = "$dir/source-$format.$extension"; $dest = "$dir/out-$format";
        ('image' . $extension)($image, $src);
        // A prior failed encoder may have left a fresh empty file.
        file_put_contents($dest . '.webp', '');
        if (!$generator->generateWebpVariant($src, $dest)) throw new RuntimeException("Generation failed: $format");
        $dimensions = @getimagesize($dest . '.webp');
        if (!$dimensions || $dimensions[0] !== 73 || $dimensions[1] !== 41 || $dimensions[2] !== IMAGETYPE_WEBP) {
            throw new RuntimeException("Unreadable or incorrect WebP: $format");
        }
        $decoded = imagecreatefromwebp($dest . '.webp');
        if ($format !== 'jpeg' && (imagecolorat($decoded, 0, 0) >> 24) !== 127) {
            throw new RuntimeException("Transparency lost: $format");
        }
        $hash = hash_file('sha256', $dest . '.webp');
        if (!$generator->generateWebpVariant($src, $dest) || hash_file('sha256', $dest . '.webp') !== $hash) {
            throw new RuntimeException("Fresh variant changed: $format");
        }
        $checks += 3;
    }
} finally {
    foreach (glob($dir . '/*') as $file) unlink($file);
    rmdir($dir);
}
echo "$checks WebP generation checks passed\n";
