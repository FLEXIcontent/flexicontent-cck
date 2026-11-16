<?php
/**
 * Execute the shipped Basic autocomplete query against MySQL or MariaDB.
 * Requires a disposable database named by FC_AUTOCOMPLETE_TEST_DATABASE.
 * Optional connection variables: HOST, PORT, SOCKET, USER, PASSWORD with the
 * same FC_AUTOCOMPLETE_TEST_ prefix. Tables use a random prefix and are removed.
 */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$database = getenv('FC_AUTOCOMPLETE_TEST_DATABASE');
if (!$database) { throw new RuntimeException('Set FC_AUTOCOMPLETE_TEST_DATABASE to a disposable test database'); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$link = new mysqli(
    getenv('FC_AUTOCOMPLETE_TEST_HOST') ?: '127.0.0.1',
    getenv('FC_AUTOCOMPLETE_TEST_USER') ?: 'root',
    getenv('FC_AUTOCOMPLETE_TEST_PASSWORD') ?: '',
    $database,
    (int) (getenv('FC_AUTOCOMPLETE_TEST_PORT') ?: 3306),
    getenv('FC_AUTOCOMPLETE_TEST_SOCKET') ?: null
);
$link->set_charset('utf8mb4');
$link->query("SET SESSION sql_mode=''"); // Joomla's mysqli connection uses the legacy date columns.
$source = file_get_contents(__DIR__ . '/../site/tasks/core.php');
$start = strpos($source, 'protected static function basicAutocompleteQuery(');
if ($start === false) { throw new RuntimeException('Basic autocomplete predicate missing'); }
$method = ''; $depth = 0; $opened = false;
foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) { continue; }
    $method .= is_array($token) ? $token[1] : $token;
    if ($token === '{') { $depth++; $opened = true; }
    if ($token === '}' && --$depth === 0 && $opened) { break; }
}
$queryStart = strpos($source, "\t\t\$access_where = '';");
$queryEnd = strpos($source, "\t\t\$data = !in_array('words'", $queryStart);
if ($queryStart === false || $queryEnd === false) { throw new RuntimeException('Autocomplete query missing'); }
$queryBody = substr($source, $queryStart, $queryEnd - $queryStart);
eval('class BasicAutocompleteFixture {' . $method . '
    public static function query($db, $_words, $next_word, $levels) {
        $app = new class ($levels) {
            public function __construct(private $levels) {}
            public function getIdentity() {
                return new class ($this->levels) {
                    public function __construct(private $levels) {}
                    public function getAuthorisedViewLevels() { return explode(",", $this->levels); }
                };
            }
        };
        $type = "basic_index"; $use_tmp = true; $filtercat = 0;
        $cid_list = $type_join = $lang_where = "";
        $limitstart = 0; $limit = 300;
        $_text_match = "MATCH(si.search_index) AGAINST (" . $db->Quote("+" . implode(" +", $_words) . ($next_word ? "" : "*")) . " IN BOOLEAN MODE)";
        ' . $queryBody . '
        return $query;
    }
}');
$db = new class ($link) {
    public function __construct(private mysqli $link) {}
    public function escape($value, $extra = false) {
        $value = $this->link->real_escape_string($value);
        return $extra ? addcslashes($value, '%_') : $value;
    }
    public function Quote($value, $escape = true) { return "'" . ($escape ? $this->escape($value) : $value) . "'"; }
    public function getNullDate() { return '0000-00-00 00:00:00'; }
};
$prefix = 'fcac_' . bin2hex(random_bytes(6)) . '_';
$tables = [];
$checks = 0;
function expectSame($expected, $actual, $name) {
    global $checks;
    if ($expected !== $actual) { throw new RuntimeException($name . ': ' . json_encode(['expected' => $expected, 'actual' => $actual])); }
    $checks++;
}
try {
    foreach ([
        'flexicontent_items_tmp' => '(id INT PRIMARY KEY, type_id INT NOT NULL, catid INT DEFAULT 1, access INT DEFAULT 1, state INT DEFAULT 1, publish_up DATETIME DEFAULT NULL, publish_down DATETIME DEFAULT NULL) ENGINE=MyISAM',
        'categories' => '(id INT PRIMARY KEY, published INT NOT NULL, access INT NOT NULL) ENGINE=MyISAM',
        'flexicontent_types' => '(id INT PRIMARY KEY, access INT NOT NULL) ENGINE=MyISAM',
        'flexicontent_fields' => '(id INT PRIMARY KEY, published INT NOT NULL, issearch INT NOT NULL, access INT NOT NULL) ENGINE=MyISAM',
        'flexicontent_fields_type_relations' => '(field_id INT NOT NULL, type_id INT NOT NULL, PRIMARY KEY(field_id,type_id), KEY type_id(type_id)) ENGINE=MyISAM',
        'flexicontent_basicsearch_index' => '(field_id INT NOT NULL, item_id INT NOT NULL, search_index MEDIUMTEXT NOT NULL, PRIMARY KEY(field_id,item_id), KEY item_id(item_id), FULLTEXT KEY(search_index)) ENGINE=MyISAM',
    ] as $table => $schema) {
        $link->query('CREATE TABLE ' . $prefix . $table . ' ' . $schema);
        $tables[] = $prefix . $table;
    }
    $link->query("INSERT INTO {$prefix}flexicontent_items_tmp (id,type_id) VALUES (1,1),(2,1),(3,1)");
    $link->query("INSERT INTO {$prefix}categories VALUES (1,1,1)");
    $link->query("INSERT INTO {$prefix}flexicontent_types VALUES (1,1)");
    $link->query("INSERT INTO {$prefix}flexicontent_fields VALUES (1,1,1,1),(2,1,1,1),(3,1,1,2),(4,1,1,0)");
    $link->query("INSERT INTO {$prefix}flexicontent_fields_type_relations VALUES (1,1),(2,1),(3,1),(4,1)");
    $link->query("INSERT INTO {$prefix}flexicontent_basicsearch_index VALUES
        (1,1,'quasaralpha'),(2,1,'nebulabeta continuation the afterword'),(3,1,'restrictedword confidentialcompletion'),(4,1,'legacypublic'),
        (1,2,'orionalpha'),(2,2,'andromedabeta nextword'),(1,3,'elsewhereonly')");
    $run = static function ($text, $levels = '1', $searchPrefix = '') use ($db, $link, $prefix) {
        $next = (bool) preg_match('/\s$/u', $text);
        $words = preg_split('/\s+/u', trim($text));
        $words = array_map(static fn($word) => $searchPrefix . $word, $words);
        $query = str_replace('#__', $prefix, BasicAutocompleteFixture::query($db, $words, $next, $levels));
        return $link->query($query)->fetch_all(MYSQLI_ASSOC);
    };
    $ids = static fn($rows) => array_map(static fn($row) => (int) $row['item_id'], $rows);
    $texts = static fn($rows) => array_column($rows, 'search_index');
    expectSame([1], $ids($run('quasaralpha neb')), 'Title and body may satisfy different terms');
    expectSame([1], $ids($run('nebulabeta quas')), 'Field order is irrelevant');
    expectSame([1], $ids($run('quasaralpha nebulabeta cont')), 'Three terms may span fields');
    expectSame([1], $ids($run('nebulabeta cont')), 'Same-field matching');
    expectSame([1], $ids($run('QUASARALPHA NEB')), 'Case-insensitive matching');
    expectSame([1], $ids($run('quasaralpha quasaralpha neb')), 'Repeated terms');
    expectSame([], $ids($run('elsewhereonly neb')), 'Terms cannot come from different items');
    expectSame([], $ids($run('missingword neb')), 'Missing required term');
    expectSame([], $ids($run(str_repeat('quasaralpha ', 16) . 'neb')), 'Long input bounds the number of full-text lookups');
    expectSame([], $ids($run('restrictedword neb')), 'Restricted earlier term cannot qualify an item');
    expectSame([], $ids($run('quasaralpha confid')), 'Restricted completion cannot be exposed');
    expectSame([1], $ids($run('restrictedword neb', '1,2')), 'Authorized earlier term');
    expectSame([1], $ids($run('quasaralpha confid', '1,2')), 'Authorized completion');
    expectSame([1], $ids($run('legacypublic neb')), 'Legacy access zero is public');
    expectSame(['nebulabeta continuation the afterword'], $texts($run('quasaralpha nebulabeta ')), 'Next-word candidates retain the last word');
    expectSame(['nebulabeta continuation the afterword'], $texts($run('quasaralpha the ')), 'Next-word stopword can occur in another field');
    expectSame([1], $ids($run('quasaralpha the neb')), 'Ignored stopword preserves Boolean semantics');
    expectSame([1], $ids($run('quasaralpha ab neb')), 'Ignored short word preserves Boolean semantics');
    foreach (['access=2', 'published=0', 'issearch=0'] as $restriction) {
        foreach ([1,2] as $field) {
            $link->query("UPDATE {$prefix}flexicontent_fields SET $restriction WHERE id=$field");
            expectSame([], $ids($run('quasaralpha neb')), 'Both participating fields enforce ' . $restriction);
            $link->query("UPDATE {$prefix}flexicontent_fields SET access=1,published=1,issearch=1 WHERE id=$field");
        }
    }
    foreach ([1,2] as $field) {
        $link->query("DELETE FROM {$prefix}flexicontent_fields_type_relations WHERE field_id=$field");
        expectSame([], $ids($run('quasaralpha neb')), 'Both participating fields require a type relation');
        $link->query("INSERT INTO {$prefix}flexicontent_fields_type_relations VALUES ($field,1)");
    }
    $link->query("UPDATE {$prefix}flexicontent_fields_type_relations SET type_id=2 WHERE field_id=1");
    expectSame([], $ids($run('quasaralpha neb')), 'A relation to another type cannot qualify the item');
    $link->query("UPDATE {$prefix}flexicontent_fields_type_relations SET type_id=1 WHERE field_id=1");
    foreach ([['flexicontent_items_tmp','access',2],['flexicontent_items_tmp','state',0],['categories','access',2],['categories','published',0],['flexicontent_types','access',2]] as [$table,$column,$value]) {
        $link->query("UPDATE {$prefix}{$table} SET $column=$value WHERE id=1");
        expectSame([], $ids($run('quasaralpha neb')), "The production query retains $table $column checks");
        $link->query("UPDATE {$prefix}{$table} SET $column=1 WHERE id=1");
    }
    expectSame([1], $ids($run("quasaralpha' neb")), 'Full-text tokenization of an apostrophe remains valid SQL');
    foreach (['quasaralpha %', 'quasaralpha _', 'quasaralpha \\', 'quasaralpha +', 'quasaralpha @', 'quasaralpha "'] as $text) {
        expectSame([], $run($text), 'Literal punctuation cannot bypass matching: ' . $text);
    }
    $link->query("UPDATE {$prefix}flexicontent_basicsearch_index SET search_index='vvvquasaralpha' WHERE item_id=1 AND field_id=1");
    $link->query("UPDATE {$prefix}flexicontent_basicsearch_index SET search_index='vvvnebulabeta vvvcontinuation vvvthe vvvafterword' WHERE item_id=1 AND field_id=2");
    expectSame([1], $ids($run('quasaralpha neb', '1', 'vvv')), 'Search-prefix mode across fields');
    expectSame(['vvvnebulabeta vvvcontinuation vvvthe vvvafterword'], $texts($run('quasaralpha nebulabeta ', '1', 'vvv')), 'Search-prefix next word');
    expectSame([], $ids($run('quasaralpha ab neb', '1', 'vvv')), 'Prefix makes short required terms searchable');
    for ($offset = 0; $offset < 10000; $offset += 500) {
        $items = $rows = [];
        for ($n = $offset; $n < $offset + 500; $n++) {
            $id = 1000000 + $n;
            $items[] = "($id,1)";
            $rows[] = "(1,$id,'commoncontext titleword'),(2,$id,'nebulabeta continuation the afterword'),(4,$id,'publicnoise')";
        }
        $link->query("INSERT INTO {$prefix}flexicontent_items_tmp (id,type_id) VALUES " . implode(',', $items));
        $link->query("INSERT INTO {$prefix}flexicontent_basicsearch_index VALUES " . implode(',', $rows));
    }
    // Keep a bad full-text join plan from stalling CI. Both engines enforce a
    // server-side limit; this is a bounded regression fixture, not a benchmark.
    $link->query(stripos($link->server_info, 'MariaDB') !== false ? 'SET SESSION max_statement_time=10' : 'SET SESSION max_execution_time=10000');
    expectSame(300, count($run('commoncontext neb')), 'Common terms on 10000 items obey the scan limit');
    echo "$checks Basic autocomplete SQL checks passed on " . $link->server_info . "\n";
} finally {
    foreach (array_reverse($tables) as $table) { $link->query('DROP TABLE ' . $table); }
    $link->close();
    restore_error_handler();
}
