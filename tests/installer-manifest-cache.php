<?php
/** Exercise the real manifest-cache reader, including a fresh install's absent row. */
namespace Joomla\CMS {
    class Factory {
        public static $cache;
        public static function getDbo() {
            return new class {
                public function setQuery($query) { return $this; }
                public function quote($value) { return "'" . $value . "'"; }
                public function loadResult() { return Factory::$cache; }
            };
        }
    }
}
namespace {
    define('_JEXEC', 1);
    define('FLEXI_J16GE', true);
    define('FLEXI_J30GE', true);
    define('FLEXI_J40GE', true);
    require __DIR__ . '/../script.php';
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    $cases = [
        'missing' => [null, '0.0.0'],
        'invalid' => ['{broken', '0.0.0'],
        'empty' => ['', '0.0.0'],
        'no-version' => ['{}', '0.0.0'],
        'null-version' => ['{"version":null}', '0.0.0'],
        'existing' => ['{"version":"5.1","name":"FLEXIcontent"}', '5.1'],
    ];
    $case = $argv[1] ?? 'missing';
    [\Joomla\CMS\Factory::$cache, $expected] = $cases[$case];
    $installer = new \com_flexicontentInstallerScript;
    $manifest = $installer->getExistingManifest();
    if ($manifest['version'] !== $expected) { throw new \RuntimeException('Incorrect existing version'); }
    if ($case === 'existing' && $manifest['name'] !== 'FLEXIcontent') {
        throw new \RuntimeException('Existing metadata was discarded');
    }
    if ($installer->getExistingManifest() !== $manifest) { throw new \RuntimeException('Cache changed'); }
    restore_error_handler();
    echo "Installer manifest cache $case passed\n";
}
