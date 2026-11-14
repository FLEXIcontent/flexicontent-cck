<?php
/** Run the shipped parameter reader with real manifest-default representations. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$source = file_get_contents(__DIR__ . '/../plugins/finder/flexicontent/flexicontent.php');
$start = strpos($source, 'private function getListParameter(');
if ($start === false) { throw new RuntimeException('Finder list parameter reader missing'); }
$method = ''; $depth = 0; $opened = false;
foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) { continue; }
    $method .= is_array($token) ? $token[1] : $token;
    if ($token === '{') { $depth++; $opened = true; }
    if ($token === '}' && --$depth === 0 && $opened) { break; }
}
eval('class FinderParameterFixture {
    public function __construct(private $params) {}
    public function run($name, $default) { return $this->getListParameter($name, $default); }
    ' . $method . '}');
$cases = [
    [[], ['type', 'author'], ['type', 'author']],
    [['value' => 'type,category,language,author'], [], ['type', 'category', 'language', 'author']],
    [['value' => ['type', 'author']], [], ['type', 'author']],
    [['value' => ''], [], []],
    [['value' => null], [], []],
    [['value' => '1, 2'], [], ['1', '2']],
    [['value' => 1], [], ['1']],
];
foreach ($cases as [$data, $default, $expected]) {
    $params = new class ($data) {
        public function __construct(private array $data) {}
        public function get($name, $default) { return array_key_exists($name, $this->data) ? $this->data[$name] : $default; }
    };
    $actual = (new FinderParameterFixture($params))->run('value', $default);
    if ($actual !== $expected) { throw new RuntimeException('Finder parameter normalization lost values'); }
}
restore_error_handler();
echo count($cases) . " Finder parameter checks passed\n";
