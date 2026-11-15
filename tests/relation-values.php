<?php
/** Legacy relation values can contain only an item ID, with no category suffix. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$source = file_get_contents(__DIR__ . '/../plugins/flexicontent_fields/relation/relation.php');
$start = strpos($source, 'protected function parseRelatedItems(');
if ($start === false) { throw new RuntimeException('Relation parser missing'); }
$method = ''; $depth = 0; $opened = false;
foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) { continue; }
    $method .= is_array($token) ? $token[1] : $token;
    if ($token === '{') { $depth++; $opened = true; }
    if ($token === '}' && --$depth === 0 && $opened) { break; }
}
eval('class RelationValueFixture {
    public function run($values, $limit = 0) { $ids = null; return [$this->parseRelatedItems($values, $limit, $ids), $ids]; }
    ' . $method . '}');
$fixture = new RelationValueFixture;
foreach ([['42', 42, 0], ['43:7', 43, 7], [44, 44, 0], ['45:', 45, 0]] as [$value, $id, $category]) {
    [$sets, $ids] = $fixture->run([[$value]]);
    if ($sets[0][$id]->itemid !== $id || $sets[0][$id]->catid !== $category || $ids !== [[$id]]) {
        throw new RuntimeException('Relation ID/category changed');
    }
}
[$sets, $ids] = $fixture->run([['42', '43:7']], 1);
if (array_keys($sets[0]) !== [42] || $ids !== [[42]]) { throw new RuntimeException('Relation limit changed'); }
[$sets, $ids] = $fixture->run([[], ['', null]]);
if ($sets !== [[], []] || $ids !== [[], []]) { throw new RuntimeException('Empty relation values changed'); }
restore_error_handler();
echo "6 relation value checks passed\n";
