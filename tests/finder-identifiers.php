<?php
/** A Finder identifier must be identical during indexing, state changes and removal. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$source = file_get_contents(__DIR__ . '/../plugins/finder/flexicontent/flexicontent.php');
$start = strpos($source, 'protected function getUrl(');
if ($start === false) { throw new RuntimeException('Finder identifier builder missing'); }
$method = ''; $depth = 0; $opened = false;
foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) { continue; }
    $method .= is_array($token) ? $token[1] : $token;
    if ($token === '{') { $depth++; $opened = true; }
    if ($token === '}' && --$depth === 0 && $opened) { break; }
}
eval('class FinderIdentifierFixture {
    public function run($id, $category = 0) { return $this->getUrl($id, "com_flexicontent", "item", $category); }
    ' . $method . '}');
$fixture = new FinderIdentifierFixture;
$expected = 'index.php?option=com_flexicontent&view=item&id=42';
foreach ([0, 2, 99] as $category) {
    if ($fixture->run(42, $category) !== $expected) { throw new RuntimeException('Category changed the Finder identity'); }
}
if ($fixture->run(43, 2) === $expected) { throw new RuntimeException('Different items share a Finder identity'); }
restore_error_handler();
echo "4 Finder identifier checks passed\n";
