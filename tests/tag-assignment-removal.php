<?php
/** Execute the actual sync method with bounded table/event doubles. */
namespace Joomla\CMS\Event {
    class AbstractEvent {
        public static function create($name, $arguments) {
            return new class ($arguments) {
                public function __construct(public array $arguments) {}
                public function setArgument($key, $value) { $this->arguments[$key] = $value; }
            };
        }
    }
}
namespace Joomla\CMS\Language {
    class Text { public static function _($text) { return $text; } }
}
namespace {
    define('FLEXI_J40GE', true);
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    $source = file_get_contents(__DIR__ . '/../admin/models/parentclassitem.php');
    $start = strpos($source, 'protected function syncJTagAssignments(');
    if ($start === false) { throw new \RuntimeException('Tag sync method missing'); }
    $method = ''; $depth = 0; $opened = false;
    foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
        if (is_array($token) && $token[0] === T_OPEN_TAG) { continue; }
        $text = is_array($token) ? $token[1] : $token;
        $method .= $text;
        if ($token === '{') { $depth++; $opened = true; }
        if ($token === '}' && --$depth === 0 && $opened) { break; }
    }
    eval('class TagSyncFixture {
        public $user, $table; public $error; public array $caches = [];
        public function initBatch() {}
        public function createFindJoomlaTags($tags, $create, $column) {
            return $tags ? [1 => (object) ["id" => 501]] : [];
        }
        public function setError($message) { $this->error = $message; }
        public function cleanCache($group, $client) { $this->caches[] = $client; }
        public function run($tags, $replace) {
            return $this->syncJTagAssignments($tags, [42], [42 => "com_content.article.42"], $replace);
        }
        ' . $method . '}');
    $cases = [
        ['replace empty', [], true, true, true, true, 1, 0, false],
        ['append empty', [], false, true, true, true, 0, 1, false],
        ['replace populated', [1 => (object) []], true, true, true, true, 0, 1, false],
        ['append populated', [1 => (object) []], false, true, true, true, 0, 1, false],
        ['denied', [], true, false, true, true, 0, 0, true],
        ['delete failure', [], true, true, true, false, 1, 0, true],
        ['missing helper', [], true, true, false, true, 0, 0, true],
    ];
    foreach ($cases as [$name, $tags, $replace, $allowed, $helperExists, $deleteResult, $deletes, $events, $fails]) {
        $helper = new class ($deleteResult) {
            public int $deletes = 0;
            public function __construct(private bool $result) {}
            public function deleteTagData($table, $pk) {
                if ($pk !== 42) { throw new \RuntimeException('Wrong deletion target'); }
                $this->deletes++; return $this->result;
            }
        };
        $dispatcher = new class {
            public int $events = 0;
            public function dispatch($name, $event) { $this->events++; }
        };
        $fixture = new TagSyncFixture;
        $fixture->user = new class ($allowed) {
            public function __construct(private bool $allowed) {}
            public function authorise($action, $context) { return $this->allowed; }
        };
        $fixture->table = new class ($helperExists ? $helper : null, $dispatcher) {
            public $replaceTags, $newTags;
            public function __construct(private $helper, private $dispatcher) {}
            public function reset() {}
            public function load($id) {}
            public function getTagsHelper() { return $this->helper; }
            public function getDispatcher() { return $this->dispatcher; }
            public function getError() { return 'fixture storage failed'; }
        };
        $result = $fixture->run($tags, $replace);
        if (($result === false) !== $fails || $helper->deletes !== $deletes || $dispatcher->events !== $events) {
            throw new \RuntimeException("Incorrect tag sync: $name");
        }
        if (!$fails && $fixture->caches !== [0, 1]) { throw new \RuntimeException('Cache cleanup skipped'); }
    }
    restore_error_handler();
    echo count($cases) . " tag assignment removal checks passed\n";
}
