<?php
/**
 * The FLEXIcontent link browser of the JCE editor, for JCE 2.9 and JCE 3.
 *
 * 1. The plugin package: both JCE versions find it by the same rules (a plugin of the "jce" group that is named
 *    links_<name>), JCE 2.9 creates WFLinkBrowser_<Name>, JCE 3 creates Wfe\Plugins\Links\<Name> with two arguments.
 * 2. The link browser: its profile options under both versions, the stylesheet, search engine friendly URLs.
 * 3. The step of the dashboard that installs the plugin: a JCE 2.x site, a JCE 3 site, a site without JCE,
 *    an update, a plugin that was unpublished, a failed installation, and the removal.
 *
 * Runs the real files with doubles of Joomla and of JCE in temporary folders; no Joomla, no database, no network.
 */
namespace Joomla\CMS {
    final class Factory {
        public static $db, $messages = array(), $languages = array(), $cleaned = array();
        public static function getApplication() {
            return new class {
                public function enqueueMessage($text, $type = 'message') { Factory::$messages[] = $type . ': ' . strip_tags($text); }
                public function getLanguage() { return new class { public function load($extension, $base = null) { Factory::$languages[] = $extension . '@' . $base; return true; } }; }
            };
        }
        public static function getContainer() {
            return new class {
                public function get($service) {
                    if ($service === \Joomla\Database\DatabaseInterface::class) return Factory::$db;
                    return new class { public function createCacheController($type, $options) { return new class { public function clean($group) { Factory::$cleaned[] = $group; } }; } };
                }
            };
        }
    }
}
namespace Joomla\CMS\Cache {
    interface CacheControllerFactoryInterface {}
}
namespace Joomla\CMS\Language {
    final class Text { public static function _($text) { return $text; } }
}
namespace Joomla\CMS\Object {
    class CMSObject {}
}
namespace Joomla\CMS\Plugin {
    final class PluginHelper {
        public static $jceEditor = true;
        public static function isEnabled($group, $name) { return $group === 'editors' && $name === 'jce' && self::$jceEditor; }
    }
}
namespace Joomla\Database {
    interface DatabaseInterface {}
}
namespace Joomla\CMS\Installer {
    // Copies the package into plugins/jce/<element> of the site and writes the row, like the plugin installer of Joomla.
    // JCE has an "extension" plugin that publishes every plugin of the "jce" group after its installation.
    final class Installer {
        public static $site, $calls = array(), $fail = false, $jcePublishes = true;
        private $hasDatabase = false;
        public function setDatabase($db) { $this->hasDatabase = $db instanceof \Joomla\Database\DatabaseInterface; }
        public function setOverwrite($state) {}
        public function setUpgrade($state) {}
        public function install($source) {
            if (!$this->hasDatabase) throw new \RuntimeException('The installer has no database');
            self::$calls[] = 'install ' . $source;
            if (self::$fail) return false;
            $manifest = simplexml_load_file($source . '/links_flexicontentlinks.xml');
            $target = self::$site . '/plugins/' . $manifest['group'] . '/links_flexicontentlinks';
            if (!is_dir($target)) mkdir($target, 0700, true);
            foreach (glob($source . '/*') as $file) copy($file, $target . '/' . basename($file));
            $db = \Joomla\CMS\Factory::$db;
            if (!$db->row) $db->row = (object) array('extension_id' => 9001, 'enabled' => 0);
            if (self::$jcePublishes) $db->row->enabled = 1;
            return true;
        }
        public function uninstall($type, $id) {
            if (!$this->hasDatabase) throw new \RuntimeException('The installer has no database');
            self::$calls[] = 'uninstall ' . $type . ' ' . $id;
            \Joomla\CMS\Factory::$db->row = null;
            return true;
        }
    }
}
namespace {
    $root = str_replace('\\', '/', dirname(__DIR__));
    $checks = 0;
    $failures = array();
    $check = function ($condition, $message) use (&$checks, &$failures) {
        $checks++;
        if (!$condition) $failures[] = $message;
    };
    function source($path) { global $root; return str_replace("\r\n", "\n", file_get_contents($root . '/' . $path)); }
    function copyTree($from, $to) {
        if (!is_dir($to)) mkdir($to, 0700, true);
        foreach (scandir($from) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            is_dir($from . '/' . $entry) ? copyTree($from . '/' . $entry, $to . '/' . $entry) : copy($from . '/' . $entry, $to . '/' . $entry);
        }
    }
    function removeTree($dir) {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            is_dir($dir . '/' . $entry) ? removeTree($dir . '/' . $entry) : unlink($dir . '/' . $entry);
        }
        rmdir($dir);
    }

    // Only the two statements of the installer are answered
    final class ExtensionsDb implements \Joomla\Database\DatabaseInterface {
        public $row = null, $log = array();
        private $sql;
        const WHERE = " WHERE type = 'plugin' AND folder = 'jce' AND element = 'links_flexicontentlinks'";
        public function quote($value) { return "'" . $value . "'"; }
        public function setQuery($sql) { $this->sql = (string) $sql; $this->log[] = $this->sql; return $this; }
        public function loadObject() {
            if ($this->sql !== 'SELECT extension_id, enabled FROM #__extensions' . self::WHERE) throw new \RuntimeException('Unexpected query: ' . $this->sql);
            return $this->row ? clone $this->row : null;
        }
        public function execute() {
            if (!preg_match('/^UPDATE #__extensions SET enabled = ([01])' . preg_quote(self::WHERE, '/') . '$/', $this->sql, $m)) throw new \RuntimeException('Unexpected query: ' . $this->sql);
            if ($this->row) $this->row->enabled = (int) $m[1];
            return true;
        }
    }

    // The editor plugin of JCE: checkAccess() is a boolean getParam() in both versions
    final class WFEditorPlugin {
        public static $params = array();
        public static function getInstance($config = array()) { return new self; }
        public function getName() { return 'link'; }
        public function getParam($key, $fallback = '', $default = '', $type = 'string') { return array_key_exists($key, self::$params) ? self::$params[$key] : $fallback; }
        public function checkAccess($option, $default = 0) { return (bool) $this->getParam($option, $default); }
    }
    // The document of JCE 2.9 is a singleton; the link dialog of JCE 3 hands out its own document
    final class WFDocument {
        public static $styles = array();
        public static function getInstance() { return new self; }
        public function addStyleSheet($files, $root = 'media') { foreach ((array) $files as $file) self::$styles[] = $root . ':' . $file; }
    }
    final class LinkDialog3 {
        public $styles = array();
        public function getDocument() { return $this; }
        public function addStyleSheet($files, $root = 'media') { foreach ((array) $files as $file) $this->styles[] = $root . ':' . $file; }
    }

    define('_JEXEC', 1);
    $work = str_replace('\\', '/', sys_get_temp_dir()) . '/flexi-jce-links-' . bin2hex(random_bytes(8));
    mkdir($work, 0700, true);
    register_shutdown_function('removeTree', $work);

    // A file that stops the script (e.g. a guard that asks for a constant of JCE) would end the test with exit code 0
    $finished = false;
    register_shutdown_function(function () use (&$finished) {
        if (!$finished) { fwrite(STDERR, "The test stopped before its end\n"); exit(1); }
    });

    // A site with FLEXIcontent: the JCE folder of the component is at its installed path
    $makeSite = function ($name) use ($root, $work) {
        $site = $work . '/' . $name;
        copyTree($root . '/site/librairies/JCE', $site . '/components/com_flexicontent/librairies/JCE');
        return $site;
    };
    $element = 'links_flexicontentlinks';
    $package = $root . '/site/librairies/JCE/' . $element;


    /**
     * 1. The plugin package, as both JCE versions look for it
     */

    $manifest = simplexml_load_file($package . '/' . $element . '.xml');
    $check($manifest->getName() === 'extension' && (string) $manifest['type'] === 'plugin' && (string) $manifest['group'] === 'jce' && (string) $manifest['method'] === 'upgrade',
        'package: the manifest is an upgradable plugin of the "jce" group');
    $named = array();
    foreach ($manifest->files->children() as $entry) $named[(string) $entry] = (string) $entry['plugin'];
    $check(isset($named[$element . '.php']) && $named[$element . '.php'] === $element, 'package: the entry file gives the plugin its element name');
    $shipped = array_values(array_diff(scandir($package), array('.', '..', $element . '.xml')));
    $check($shipped === array_values(array_intersect($shipped, array_keys($named))) && count($shipped) === count($named), 'package: the manifest names every file of the folder, and nothing else');

    // Both versions: PluginHelper::getPlugin('jce'), a name with "-" or "_" that does not start with "editor", split once
    $parts = preg_split('/[-_]/', $element, 2);
    $check(preg_match('/[-_]/', $element) === 1 && preg_match('/^editor[-_]/', $element) === 0 && $parts === array('links', 'flexicontentlinks'),
        'package: the element is read as a "links" extension that is named "flexicontentlinks"');
    // JCE 3 imports the plugin as <type>_<name>, and both read the manifest <type>_<name>.xml: "_" is the separator that fits both
    $check($parts[0] . '_' . $parts[1] === $element && is_file($package . '/' . $parts[0] . '_' . $parts[1] . '.xml'), 'package: element and manifest use the "_" separator');

    $site = $makeSite('loaders');
    copyTree($package, $site . '/plugins/jce/' . $element);
    $path = $site . '/plugins/jce/' . $element;

    // JCE 2.9 (WFExtension::loadExtensions): <path>/<folder name>.php, or src/<type>.php when the plugin has a src folder
    $entry29 = is_dir($path . '/src') ? $path . '/src/' . $parts[0] . '.php' : $path . '/' . basename($path) . '.php';
    $check($entry29 === $path . '/' . $element . '.php' && is_file($entry29), 'JCE 2.9: the entry file is ' . $element . '.php');

    // JCE 3 (AdapterHelper::loadPlugins): <path>/<Name>.php, or src/<type>.php, and <path>/<folder name>.php when that does not exist.
    // file_exists() is also true for a folder, and for another letter case on Windows and macOS
    $entry3 = $path . '/' . ucfirst($parts[1]) . '.php';
    if (is_dir($path . '/src')) $entry3 = $path . '/src/' . $parts[0] . '.php';
    $check(!file_exists($entry3) && !file_exists($path . '/' . $parts[1]) && !file_exists($path . '/' . $parts[1] . '.php'), 'JCE 3: nothing in the plugin folder is taken for its first choice ' . ucfirst($parts[1]) . '.php');
    if (!file_exists($entry3)) $entry3 = $path . '/' . basename($path) . '.php';
    $check($entry3 === $entry29, 'JCE 3: falls back to the same entry file as JCE 2.9');

    // Without the component the plugin must still give JCE 2.9 its class: checked in a second process
    $probe = $work . '/probe.php';
    file_put_contents($probe, '<?php error_reporting(-1); define("_JEXEC", 1); define("JPATH_SITE", ' . var_export($work . '/no-component', true) . ');'
        . ' require ' . var_export($package . '/' . $element . '.php', true) . '; $b = new WFLinkBrowser_Flexicontentlinks(); $b->display();'
        . ' echo json_encode(array($b->isEnabled(), $b->getOption(), $b->getList(), $b->getLinks((object) array("option" => "x")), class_exists("Wfe\\\\Plugins\\\\Links\\\\Flexicontentlinks", false)));');
    $output = array();
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1', $output, $status);
    $check($status === 0 && implode('', $output) === '[false,[],"",[],true]', 'package: without the component both class names exist and list nothing (' . implode(' ', $output) . ')');

    // Joomla includes the entry file too, outside of JCE (PluginHelper::importPlugin): it must load without the constant of JCE
    define('JPATH_SITE', $site);
    define('JPATH_ADMINISTRATOR', $site . '/administrator');
    require_once $entry29;
    $check(!defined('_WF_EXT') && class_exists('WFLinkBrowser_Flexicontentlinks', false), 'JCE 2.9: the entry file defines WFLinkBrowser_Flexicontentlinks');
    $check(class_exists('Wfe\\Plugins\\Links\\Flexicontentlinks', false), 'JCE 3: the entry file defines Wfe\\Plugins\\Links\\Flexicontentlinks');
    $check((new ReflectionClass('WFLinkBrowser_Flexicontentlinks'))->getFileName() !== false
        && str_replace('\\', '/', (new ReflectionClass('WFLinkBrowser_Flexicontentlinks'))->getFileName()) === $site . '/components/com_flexicontent/librairies/JCE/links/flexicontentlinks.php',
        'package: the link browser is the one of the component, the plugin has no copy of it');
    $check(!class_exists('PlgJceLinks_flexicontentlinks', false), 'package: Joomla finds no event plugin class in the entry file');
    define('_WF_EXT', 1);


    /**
     * 2. The link browser under both versions
     */

    $options = array('com_flexicontent_authored_items', 'com_flexicontent_items', 'com_flexicontent_tagged_items', 'com_flexicontent_tags');
    $listed = function ($browser) use ($options) {
        $found = array();
        foreach ($options as $option) if (strpos($browser->getList(), 'id="index.php?option=' . $option . '&') !== false) $found[] = $option;
        return $found;
    };
    $stylesheet = 'joomla:components/com_flexicontent/librairies/JCE/links/flexicontentlinks/css/flexicontentlinks';

    // JCE 2.9: new WFLinkBrowser_<Name>(), then isEnabled(), display(), getList(), getOption() and getLinks()
    WFEditorPlugin::$params = array();
    $class29 = 'WFLinkBrowser_' . ucfirst($parts[1]);
    $browser = new $class29();
    $check($browser->isEnabled() === true, 'JCE 2.9: the link browser is enabled by default');
    $check($browser->getOption() === $options && $browser->getOption() === $options, 'JCE 2.9: the four lists are offered, and asking twice does not repeat them');
    $check($listed($browser) === $options, 'JCE 2.9: the four lists are in the tree');
    $check(in_array('plg_system_flexisystem@' . JPATH_ADMINISTRATOR, \Joomla\CMS\Factory::$languages, true), 'the titles of the lists are loaded into the language of the dialog');
    $browser->display();
    $check(WFDocument::$styles === array($stylesheet), 'JCE 2.9: the stylesheet is added to the document, from the site root');
    $check(is_file($root . '/site/librairies/JCE/links/flexicontentlinks/css/flexicontentlinks.css'), 'the stylesheet is in the component');
    $check($browser->getLinks((object) array('option' => 'com_content')) === array(), 'a request for a list of another extension returns an empty array');

    // JCE 3: new Wfe\Plugins\Links\<Name>($config, $container); isEnabled() is not asked
    $class3 = 'Wfe\\Plugins\\' . ucfirst($parts[0]) . '\\' . ucfirst($parts[1]);
    $dialog = new LinkDialog3;
    WFDocument::$styles = array();
    $browser = new $class3(array('name' => $parts[1], 'title' => 'plg_jce_links_flexicontentlinks', 'path' => $entry3), $dialog);
    $check($browser instanceof WFLinkBrowser_Flexicontentlinks && $browser->getOption() === $options && $listed($browser) === $options, 'JCE 3: the same link browser, with the four lists');
    $browser->display();
    $check($dialog->styles === array($stylesheet) && WFDocument::$styles === array(), 'JCE 3: the stylesheet is added to the document of the link dialog');
    foreach (array('display', 'getOption', 'getList', 'getLinks') as $method) {
        $check(method_exists($browser, $method), 'JCE 3: the link adapter can call ' . $method . '()');
    }

    // The profile option of the link browser, which JCE 3 does not ask by itself
    WFEditorPlugin::$params = array('link.links.flexicontentlinks.enable' => 0);
    $browser = new $class3(array(), $dialog);
    $check($browser->isEnabled() === false && $browser->getOption() === array() && $browser->getList() === ''
        && $browser->getLinks((object) array('option' => 'com_flexicontent_items')) === array(), 'a profile that turns the link browser off lists nothing, under JCE 3 too');

    // Content links of the profile: a yes / no option in JCE 2.9, a list of providers in JCE 3
    $withoutContent = array('com_flexicontent_tags');
    WFEditorPlugin::$params = array('links.joomlalinks.content' => 0);
    $check($listed(new $class29()) === $withoutContent && WFLinkBrowser_Flexicontentlinks::contentAllowed() === false, 'JCE 2.9: content links turned off: only the tags are listed');
    WFEditorPlugin::$params = array('links.joomlalinks.content' => 1);
    $check($listed(new $class29()) === $options, 'JCE 2.9: content links turned on: all lists');
    WFEditorPlugin::$params = array('links.joomla.list.providers' => array('contacts', 'tags'));
    $check($listed(new $class3(array(), $dialog)) === $withoutContent && WFLinkBrowser_Flexicontentlinks::contentAllowed() === false, 'JCE 3: "content" is not among the providers: only the tags are listed');
    WFEditorPlugin::$params = array('links.joomla.list.providers' => array('contacts', 'content'), 'links.joomlalinks.content' => 0);
    $check($listed(new $class3(array(), $dialog)) === $options, 'JCE 3: "content" is among the providers: all lists');
    WFEditorPlugin::$params = array('links.joomla.list.providers' => 'contacts, content');
    $check(WFLinkBrowser_Flexicontentlinks::contentAllowed() === true, 'JCE 3: a comma separated list of providers is read too');
    WFEditorPlugin::$params = array('links.joomla.list.providers' => array(), 'links.joomlalinks.content' => 0);
    $check(WFLinkBrowser_Flexicontentlinks::contentAllowed() === false, 'JCE 3: a profile that still has the JCE 2.9 option keeps it');
    WFEditorPlugin::$params = array('links.flexicontentlinks.tags' => 0);
    $check($listed(new $class29()) === array_values(array_diff($options, $withoutContent)), 'the tags option of the profile is unchanged');

    // The adapters ask the link browser, they no longer call a class that neither version has
    foreach (array('items', 'tags', 'tagged_items', 'authored_items') as $adapter) {
        $code = source('site/librairies/JCE/links/flexicontentlinks/' . $adapter . '.php');
        $check(strpos($code, 'WFLinkExtension') === false && strpos($code, 'return WFLinkBrowser_Flexicontentlinks::route($url);') !== false, $adapter . ': URLs are routed by the link browser');
        $check(strpos($code, "defined('_WF_EXT') or die") !== false, $adapter . ': can only be loaded inside JCE');
    }
    foreach (array('items' => 2, 'authored_items' => 2, 'tagged_items' => 1) as $adapter => $times) {
        $code = source('site/librairies/JCE/links/flexicontentlinks/' . $adapter . '.php');
        $check(substr_count($code, 'WFLinkBrowser_Flexicontentlinks::contentAllowed()') === $times && strpos($code, "'links.joomlalinks.content'") === false, $adapter . ': every check of the content links goes through the link browser');
    }

    // Search engine friendly URLs: the helper of the running JCE version is used
    $url = 'index.php?option=com_flexicontent&view=item&id=1';
    WFEditorPlugin::$params = array('links.joomlalinks.sef_url' => 1);
    $check(WFLinkBrowser_Flexicontentlinks::route($url) === $url, 'route: without a helper of JCE the URL is returned as it is');
    eval('final class WFLinkHelper { public static function route($url) { return "jce2:" . $url; } }');
    WFEditorPlugin::$params = array();
    $check(WFLinkBrowser_Flexicontentlinks::route($url) === $url, 'route: the URL is not changed unless the profile asks for it');
    WFEditorPlugin::$params = array('links.joomlalinks.sef_url' => 1);
    $check(WFLinkBrowser_Flexicontentlinks::route($url) === 'jce2:' . $url, 'JCE 2.9: route uses WFLinkHelper');
    eval('namespace Wfe\Helper; final class LinkHelper { public static function route($url) { return "jce3:" . $url; } }');
    WFEditorPlugin::$params = array('links.joomla.sef_url' => 1);
    $check(WFLinkBrowser_Flexicontentlinks::route($url) === 'jce3:' . $url, 'JCE 3: route uses Wfe\\Helper\\LinkHelper');
    WFEditorPlugin::$params = array('links.joomla.sef_url' => 0, 'links.joomlalinks.sef_url' => 1);
    $check(WFLinkBrowser_Flexicontentlinks::route($url) === $url, 'JCE 3: its own option wins over the one of JCE 2.9');
    WFEditorPlugin::$params = array();


    /**
     * 3. The step of the dashboard that installs the plugin
     */

    require $root . '/site/librairies/JCE/installer.php';

    $db = \Joomla\CMS\Factory::$db = new ExtensionsDb;
    $reset = function ($site) use ($db) {
        \Joomla\CMS\Installer\Installer::$site = $site;
        \Joomla\CMS\Installer\Installer::$calls = array();
        \Joomla\CMS\Installer\Installer::$fail = false;
        \Joomla\CMS\Installer\Installer::$jcePublishes = true;
        \Joomla\CMS\Plugin\PluginHelper::$jceEditor = true;
        \Joomla\CMS\Factory::$messages = array();
        \Joomla\CMS\Factory::$cleaned = array();
        $db->row = null;
        $db->log = array();
    };
    $calls = function () { return \Joomla\CMS\Installer\Installer::$calls; };
    $sourceOf = function ($site) use ($element) { return $site . '/components/com_flexicontent/librairies/JCE/' . $element; };
    $pluginOf = function ($site) use ($element) { return $site . '/plugins/jce/' . $element . '/' . $element . '.php'; };

    // No JCE
    $none = $makeSite('no-jce');
    $reset($none);
    $check(FlexicontentJceLinks::detectLayout($none) === '', 'no JCE: nothing is detected');
    $check(FlexicontentJceLinks::install($none) === 'none' && $calls() === array() && $db->log === array() && \Joomla\CMS\Factory::$messages === array() && !is_dir($none . '/plugins'),
        'no JCE: nothing is installed, asked or reported');

    // JCE 2.x: the editor is in the site component; earlier versions of FLEXIcontent have copied their files into it
    $jce2 = $makeSite('jce-2');
    $links2 = $jce2 . '/components/com_jce/editor/extensions/links';
    mkdir($links2 . '/joomlalinks', 0700, true);
    mkdir($links2 . '/flexicontentlinks/css', 0700, true);
    mkdir($jce2 . '/plugins/editors/jce', 0700, true);
    foreach (array('joomlalinks.php', 'joomlalinks.xml', 'joomlalinks/content.php', 'flexicontentlinks.php', 'flexicontentlinks.xml', 'flexicontentlinks/items.php', 'flexicontentlinks/css/flexicontentlinks.css') as $file) {
        file_put_contents($links2 . '/' . $file, 'x');
    }
    $reset($jce2);
    $check(FlexicontentJceLinks::detectLayout($jce2) === '2', 'JCE 2.x: detected by the link extensions folder of the site component');
    $check(count(FlexicontentJceLinks::getLegacyCopy($jce2)) === 3, 'JCE 2.x: the copy of an earlier version is found');
    $check(FlexicontentJceLinks::install($jce2) === 'installed', 'JCE 2.x: the plugin is installed');
    $check($calls() === array('install ' . $sourceOf($jce2)), 'JCE 2.x: the installer of Joomla gets the package of the component');
    $check(is_file($pluginOf($jce2)) && $db->row && $db->row->enabled === 1, 'JCE 2.x: the plugin is in plugins/jce and published');
    $check(!file_exists($links2 . '/flexicontentlinks.php') && !file_exists($links2 . '/flexicontentlinks.xml') && !file_exists($links2 . '/flexicontentlinks'),
        'JCE 2.x: nothing is copied into the editor folder of JCE, and the copy of an earlier version is removed');
    $check(is_file($links2 . '/joomlalinks.php') && is_file($links2 . '/joomlalinks.xml') && is_file($links2 . '/joomlalinks/content.php'), 'JCE 2.x: the link extension of JCE itself is left alone');
    $check(\Joomla\CMS\Factory::$messages === array('message: FLEXI_INSTALLED JCE Links plugin for FLEXIcontent') && in_array('com_plugins', \Joomla\CMS\Factory::$cleaned, true),
        'JCE 2.x: the installation is reported and the cached plugin list is cleared');

    // JCE 3: the editor is in the editor plugin, the site component has no editor folder
    $jce3 = $makeSite('jce-3');
    mkdir($jce3 . '/plugins/editors/jce/Wfe/Plugins/Links', 0700, true);
    mkdir($jce3 . '/components/com_jce/src', 0700, true);
    $reset($jce3);
    \Joomla\CMS\Installer\Installer::$jcePublishes = false;
    $check(FlexicontentJceLinks::detectLayout($jce3) === '3', 'JCE 3: detected by the editor folder of the editor plugin');
    $check(FlexicontentJceLinks::install($jce3) === 'installed' && $calls() === array('install ' . $sourceOf($jce3)), 'JCE 3: the plugin is installed from the package of the component');
    $check(is_file($pluginOf($jce3)) && !is_dir($jce3 . '/components/com_jce/editor'), 'JCE 3: the plugin is in plugins/jce, no JCE 2 folder is created');
    $check($db->row && $db->row->enabled === 1, 'JCE 3: the new plugin is published, also when JCE does not do it');

    // A second visit of the dashboard
    $check(FlexicontentJceLinks::install($jce3) === 'current' && count($calls()) === 1 && count(\Joomla\CMS\Factory::$messages) === 1, 'up to date: nothing is installed or reported again');
    $db->row->enabled = 0;
    $db->log = array();
    $check(FlexicontentJceLinks::install($jce3) === 'current' && $db->row->enabled === 0 && count($db->log) === 1, 'up to date: a plugin that was unpublished is not published again');

    // An update of FLEXIcontent changes the package
    file_put_contents($sourceOf($jce3) . '/' . $element . '.php', "\n// changed", FILE_APPEND);
    \Joomla\CMS\Installer\Installer::$jcePublishes = true;
    $check(FlexicontentJceLinks::install($jce3) === 'updated' && count($calls()) === 2, 'update: a changed package is installed again');
    $check($db->row->enabled === 0, 'update: a plugin that was unpublished stays unpublished, although JCE publishes what is installed');
    $check(md5_file($pluginOf($jce3)) === md5_file($sourceOf($jce3) . '/' . $element . '.php') && FlexicontentJceLinks::install($jce3) === 'current', 'update: the installed files are the new ones');
    $check(end(\Joomla\CMS\Factory::$messages) === 'message: FLEXI_UPDATED JCE Links plugin for FLEXIcontent', 'update: it is reported as an update');

    // Files without a row (e.g. the row was removed): installed again
    $db->row = null;
    $check(FlexicontentJceLinks::install($jce3) === 'installed' && count($calls()) === 3 && $db->row->enabled === 1, 'files without a row in the extensions table are installed again');

    // A site that was upgraded from JCE 2.x and still has the old folder
    mkdir($jce3 . '/components/com_jce/editor/extensions/links', 0700, true);
    file_put_contents($jce3 . '/components/com_jce/editor/extensions/links/flexicontentlinks.php', 'x');
    $check(FlexicontentJceLinks::detectLayout($jce3) === '3', 'JCE 3 with a left over JCE 2 folder: JCE 3 is detected');
    $check(FlexicontentJceLinks::install($jce3) === 'current' && FlexicontentJceLinks::getLegacyCopy($jce3) === array(), 'JCE 3 with a left over JCE 2 folder: the old copy is removed');

    // JCE is installed, its editor plugin is disabled: as before, nothing is done
    $off = $makeSite('jce-off');
    mkdir($off . '/plugins/editors/jce/Wfe', 0700, true);
    $reset($off);
    \Joomla\CMS\Plugin\PluginHelper::$jceEditor = false;
    $check(FlexicontentJceLinks::install($off) === 'none' && $calls() === array() && $db->log === array(), 'JCE editor plugin disabled: nothing is installed');

    // The installer of Joomla fails
    $reset($off);
    \Joomla\CMS\Installer\Installer::$fail = true;
    $check(FlexicontentJceLinks::install($off) === 'failed' && $db->row === null && !is_file($pluginOf($off)), 'failed installation: no row is written');
    $check(\Joomla\CMS\Factory::$messages === array('warning: FLEXI_FAILED_TO JLIB_INSTALLER_INSTALL JCE Links plugin for FLEXIcontent'), 'failed installation: a warning is shown');

    // Removal with FLEXIcontent
    $reset($jce2);
    $check(FlexicontentJceLinks::uninstall($jce2) === null && $calls() === array(), 'uninstall: nothing to do when the plugin was never installed');
    $db->row = (object) array('extension_id' => 9001, 'enabled' => 1);
    file_put_contents($links2 . '/flexicontentlinks.php', 'x');
    $check(FlexicontentJceLinks::uninstall($jce2) === true && $calls() === array('uninstall plugin 9001') && $db->row === null, 'uninstall: the plugin is removed by the installer of Joomla');
    $check(FlexicontentJceLinks::getLegacyCopy($jce2) === array() && is_file($links2 . '/joomlalinks.php'), 'uninstall: the copy of an earlier version is removed too');
    // Also when JCE itself was removed before: the row of the plugin would stay behind
    $reset($none);
    $db->row = (object) array('extension_id' => 9002, 'enabled' => 0);
    $check(FlexicontentJceLinks::uninstall($none) === true && $calls() === array('uninstall plugin 9002'), 'uninstall: the plugin is removed on a site that no longer has JCE');

    // The callers
    $model = source('admin/models/flexicontent.php');
    $step = substr($model, strpos($model, 'function install_3rdParty_plugins()'), strpos($model, 'function checkCollations()') - strpos($model, 'function install_3rdParty_plugins()'));
    $check(strpos($step, "require_once JPATH_ROOT . '/components/com_flexicontent/librairies/JCE/installer.php';") !== false && strpos($step, 'FlexicontentJceLinks::install();') !== false,
        'dashboard: the step installs the plugin');
    $check(strpos($step, 'com_jce') === false && strpos($step, 'flexicontentlinks') === false, 'dashboard: the step no longer copies files into JCE');
    $script = source('script.php');
    $uninstall = substr($script, strpos($script, 'function uninstall( $parent )'));
    $check(strpos($uninstall, "JPATH_SITE . '/components/com_flexicontent/librairies/JCE/installer.php'") !== false && strpos($uninstall, 'FlexicontentJceLinks::uninstall()') !== false,
        'uninstall of FLEXIcontent: the plugin is removed');
    $check(strpos(source('flexicontent.xml'), 'links_flexicontentlinks') === false, 'the plugin is not installed on sites without JCE: the manifest of the component does not name it');
    $check(!is_file($root . '/site/librairies/JCE/links/flexicontentlinks.xml'), 'the manifest for the editor folder of JCE 2.6 is gone');

    $finished = true;
    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
