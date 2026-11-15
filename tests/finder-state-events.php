<?php
/** Execute the plugin handler with typed Finder events and legacy arguments. */
namespace Joomla\CMS\Event\Finder {
    class AfterChangeStateEvent {
        public function __construct(private string $context, private array $pks, private int $value) {}
        public function getContext() { return $this->context; }
        public function getPks() { return $this->pks; }
        public function getValue() { return $this->value; }
    }
    class AfterSaveEvent {}
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    $source = file_get_contents(__DIR__ . '/../plugins/finder/flexicontent/flexicontent.php');
    $start = strpos($source, 'public function onFinderChangeState(');
    if ($start === false) { throw new \RuntimeException('Finder state handler missing'); }
    $method = ''; $depth = 0; $opened = false;
    foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
        if (is_array($token) && $token[0] === T_OPEN_TAG) { continue; }
        $method .= is_array($token) ? $token[1] : $token;
        if ($token === '{') { $depth++; $opened = true; }
        if ($token === '}' && --$depth === 0 && $opened) { break; }
    }
    eval('use Joomla\CMS\Event\Finder as FinderEvent;
        class FinderStateFixture {
            public array $calls = [];
            public array $legacy = [];
            private function removeLegacyLinks($pk, $removeTaxonomies = true) { return $this->legacy[$pk] ?? false; }
            public function reindex($pk) { $this->calls[] = ["reindex", $pk]; }
            public function itemStateChange($pks, $value) { $this->calls[] = ["state", $pks, $value]; }
            public function pluginDisable($pks) { $this->calls[] = ["disable", $pks]; }
            ' . $method . '}');
    $event = \Joomla\CMS\Event\Finder\AfterChangeStateEvent::class;
    $cases = [
        [[new $event('com_content.article', [42], 0)], [['state', [42], 0]]],
        [[new $event('com_flexicontent.form', [42], 1)], [['state', [42], 1]]],
        [['com_content.form', [42], -2], [['state', [42], -2]]],
        [[new $event('unrelated.context', [42], 0)], []],
        [[new $event('com_plugins.plugin', [7], 0)], [['disable', [7]]]],
        [[new \Joomla\CMS\Event\Finder\AfterSaveEvent], []],
    ];
    foreach ($cases as [$args, $expected]) {
        $fixture = new FinderStateFixture;
        $fixture->onFinderChangeState(...$args);
        if ($fixture->calls !== $expected) { throw new \RuntimeException('Finder state change was ignored or misrouted'); }
    }
    $fixture = new FinderStateFixture;
    $fixture->legacy[42] = true;
    $fixture->onFinderChangeState(new $event('com_content.article', [42], 0));
    if ($fixture->calls !== [['reindex', 42], ['state', [42], 0]]) {
        throw new \RuntimeException('Legacy index key must be replaced before the state update');
    }
    restore_error_handler();
    echo (count($cases) + 1) . " Finder state event checks passed\n";
}
