<?php
/** Run the real preflight without a Joomla database; one version per process. */
namespace Joomla\CMS {
    class Version {
        public static string $version;
        public function getShortVersion() { return self::$version; }
    }
    class Factory {
        public static array $messages = [];
        public static function getApplication() {
            return new class {
                public function enqueueMessage($message, $type) {
                    Factory::$messages[] = [$message, $type];
                }
            };
        }
    }
}
namespace Joomla\CMS\Language {
    class Text {
        public static function _($text) { return $text; }
    }
}
namespace {
    define('_JEXEC', 1);
    define('FLEXI_J16GE', true);
    define('FLEXI_J30GE', true);
    define('FLEXI_J40GE', true);
    require __DIR__ . '/../script.php';

    class FixtureInstaller extends \com_flexicontentInstallerScript {
        public function getExistingManifest($name = 'com_flexicontent', $type = 'component') {
            return ['version' => '5.1'];
        }
    }
    $manifest = simplexml_load_file(__DIR__ . '/../flexicontent.xml');
    $parent = new class ($manifest) {
        public function __construct(private \SimpleXMLElement $manifest) {}
        public function getManifest() { return $this->manifest; }
    };
    $version = $argv[1] ?? '6.2.0';
    \Joomla\CMS\Version::$version = $version;
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    ob_start();
    try {
        $result = (new FixtureInstaller)->preflight('install', $parent);
    } finally {
        ob_end_clean();
        restore_error_handler();
    }
    if (error_reporting() !== E_ALL || ini_get('display_errors') !== '0') {
        throw new \RuntimeException('Preflight changed Joomla error-reporting settings');
    }
    if ($version === '4.4.0') {
        if ($result !== false || !str_contains(\Joomla\CMS\Factory::$messages[0][0] ?? '', '5.0.0')) {
            throw new \RuntimeException('Unsupported Joomla must be rejected with the required version');
        }
    } elseif ($result === false || \Joomla\CMS\Factory::$messages) {
        throw new \RuntimeException('Supported Joomla preflight was rejected');
    }
    echo "Installer preflight $version passed; error settings preserved\n";
}
