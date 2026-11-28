<?php
// CLI-only dummy-data fixtures; run through the bounded Docker wrapper.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$checks = $failures = 0;
function check($ok, $label) {
    global $checks, $failures;
    ++$checks;
    if (!$ok) ++$failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
}
function finish() {
    global $checks, $failures;
    echo "CHECKS=$checks FAILURES=$failures\n";
    exit($failures ? 1 : 0);
}
function fixtureAutoload($name) {
    $prefix = 'Joomla\\CMS\\';
    if (str_starts_with($name, $prefix)) {
        $p = '/joomla/libraries/src/' . str_replace('\\', '/', substr($name, strlen($prefix))) . '.php';
        if (is_file($p)) require $p;
    }
}
// Extract a method for a bounded unit test when the surrounding application would
// otherwise bootstrap real services. The method body is unchanged source text.
function sourceMethod($file, $name) {
    $tokens = token_get_all(file_get_contents($file));
    for ($i=0; $i<count($tokens); ++$i) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1;
        while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) ++$j;
        if (!is_array($tokens[$j]) || $tokens[$j][1] !== $name) continue;
        $code = ''; $depth = 0; $started = false;
        for (; $i<count($tokens); ++$i) {
            $token = $tokens[$i]; $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { ++$depth; $started=true; }
            if ($token === '}' && --$depth === 0 && $started) return 'public ' . $code;
        }
    }
    throw new RuntimeException('Method missing: ' . $name);
}

// Application source remains read-only; this installation alias is scratch-local.
if (!is_dir('/scratch/site/components')) mkdir('/scratch/site/components', 0755, true);
if (!is_link('/scratch/site/components/com_flexicontent')) symlink('/src/site', '/scratch/site/components/com_flexicontent');
