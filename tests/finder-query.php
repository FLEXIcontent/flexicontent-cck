<?php
/** Run the shipped Finder query builder against the supported database API. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$source = file_get_contents(__DIR__ . '/../plugins/finder/flexicontent/flexicontent.php');
$start = strpos($source, 'protected function getListQuery(');
if ($start === false) { throw new RuntimeException('Finder query builder missing'); }
$method = ''; $depth = 0; $opened = false;
foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) { continue; }
    $method .= is_array($token) ? $token[1] : $token;
    if ($token === '{') { $depth++; $opened = true; }
    if ($token === '}' && --$depth === 0 && $opened) { break; }
}
class FinderQueryDouble {
    public array $casts = [];
    public array $selects = [];
    public function select($value) { $this->selects[] = $value; return $this; }
    public function from($value) { return $this; }
    public function join($type, $value) { return $this; }
    public function charLength($field, $operator, $value) { return "CHAR_LENGTH($field) $operator $value"; }
    public function concatenate($fields, $separator) { return "CONCAT_WS('$separator', " . implode(', ', $fields) . ')'; }
    // castAsChar deliberately does not exist, matching Joomla 6's database query.
    public function castAs($type, $value) {
        if ($type !== 'CHAR') { throw new RuntimeException('Wrong cast type'); }
        $this->casts[] = [$type, $value];
        return "CAST($value AS CHAR)";
    }
}
eval('class FinderQueryFixture {
    protected static bool $isJ4GE = false;
    public function __construct(private $query) {}
    public function getDatabase() {
        return new class ($this->query) {
            public function __construct(private $query) {}
            public function getQuery($new) { return $this->query; }
        };
    }
    public function run() { return $this->getListQuery(); }
    ' . $method . '}');
$query = new FinderQueryDouble;
$result = (new FinderQueryFixture($query))->run();
if ($result !== $query || $query->casts !== [['CHAR', 'a.id'], ['CHAR', 'c.id']]) {
    throw new RuntimeException('Item and category IDs must use the supported cast API');
}
$sql = implode('\n', $query->selects);
foreach (['ELSE CAST(a.id AS CHAR) END as slug', 'ELSE CAST(c.id AS CHAR) END as catslug', 'e.type_id AS fc_type_id'] as $clause) {
    if (!str_contains($sql, $clause)) { throw new RuntimeException('Finder SQL lost ' . $clause); }
}
restore_error_handler();
echo "5 Finder query checks passed\n";
