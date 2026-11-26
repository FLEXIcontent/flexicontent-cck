<?php
/** Standalone regression for the item form's inherited comments setting. */
$source = file_get_contents(__DIR__ . '/../admin/views/item/view.html.php');
if (!preg_match('/\$isdisabled\s*=\s*[^;]+;/', $source, $match)) {
    throw new RuntimeException('Comments selector expression not found');
}

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$cases = [
    [null, false], ['', false], [0, true], ['0', true], [false, false],
    [1, false], ['1', false], [2, false], ['2', false], [true, false],
];
foreach ($cases as [$value, $expected]) {
    $page_params = new class ($value) {
        public function __construct(private mixed $value) {}
        public function get($name) { return $this->value; }
    };
    eval($match[0]);
    if ((bool) $isdisabled !== $expected) {
        throw new RuntimeException('Incorrect selector for ' . var_export($value, true));
    }
}
restore_error_handler();
echo count($cases) . " comments selector checks passed\n";
