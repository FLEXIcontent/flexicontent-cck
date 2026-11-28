<?php
/**
 * Stored titles, names, descriptions, sizes, coordinates and links in the value layouts of the fields,
 * in the site templates and in the module templates.
 * Renders the real layout files (or runs the real template lines) with dummy values; no Joomla bootstrap, no database, no browser.
 */
namespace Joomla\CMS\Language {
    final class Text {
        public static $strings = array();
        public static function _($key, $jsSafe = false) { $s = isset(self::$strings[$key]) ? self::$strings[$key] : $key; return $jsSafe ? addslashes($s) : $s; }
        public static function sprintf($key, ...$args) { return vsprintf(self::_($key), $args); }
        public static function script($key, $jsSafe = false) {}
    }
}
namespace Joomla\CMS\HTML {
    final class HTMLHelper {
        public static function _($name, ...$args) { return ''; }
        public static function tooltipText($title = '', $content = '', $translate = true, $escape = true) { return htmlspecialchars($title . '::' . $content, ENT_QUOTES, 'UTF-8'); }
    }
}
namespace Joomla\String {
    final class StringHelper {
        public static function strlen($s) { return mb_strlen((string) $s, 'UTF-8'); }
        public static function substr($s, $start, $length = null) { return mb_substr((string) $s, $start, $length, 'UTF-8'); }
        public static function strrpos($s, $needle) { return mb_strrpos((string) $s, $needle, 0, 'UTF-8'); }
        public static function strtolower($s) { return mb_strtolower((string) $s, 'UTF-8'); }
    }
}
namespace Joomla\CMS\String {
    final class PunycodeHelper { public static function emailToUTF8($email) { return $email; } }
}
namespace Joomla\CMS\Uri {
    final class Uri { public static function root($path = false) { return $path ? '' : 'https://fixture.example/'; } }
}
namespace Joomla\CMS\Router {
    // Same as the Joomla method with its default arguments: the URL is encoded for HTML
    final class Route { public static function _($url, $xhtml = true) { return $xhtml ? htmlspecialchars($url, ENT_COMPAT, 'UTF-8') : $url; } }
}
namespace Joomla\Filesystem {
    final class Path { public static function clean($path, $ds = DIRECTORY_SEPARATOR) { return preg_replace('#[/\\\\]+#', $ds, $path); } }
}
namespace Joomla\Registry {
    final class Registry {
        private $data = array();
        public function set($key, $value) { $this->data[$key] = $value; }
        public function get($key, $default = null) { return array_key_exists($key, $this->data) ? $this->data[$key] : $default; }
    }
}
namespace Joomla\CMS {
    final class Factory { public static $app; public static function getApplication() { return self::$app; } }
}
namespace {
    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('JPATH_ROOT', dirname(__DIR__));
    define('JPATH_SITE', JPATH_ROOT);
    define('_FC_CONTINUE_', 1);
    define('_FC_BREAK_', 2);
    define('_FC_RETURN_', 3);

    function methodSource($source, $name) {
        $start = strpos($source, 'function ' . $name . '(');
        $method = ''; $depth = 0; $opened = false;
        foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
            $method .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { ++$depth; $opened = true; }
            if ($token === '}' && --$depth === 0 && $opened) break;
        }
        return $method;
    }
    function linesWith($source, $needle) {
        return array_values(array_filter(preg_split('/\R/', $source), static function ($line) use ($needle) { return strpos($line, $needle) !== false; }));
    }
    function source($file) { return file_get_contents(JPATH_ROOT . $file); }
    // Runs one template line as PHP, with $this and the given variables
    function renderLine($line, array $vars, $self = null) {
        $run = function () use ($line, $vars) { extract($vars); ob_start(); eval('use Joomla\String\StringHelper; ?>' . $line); return ob_get_clean(); };
        return $run->call($self ?: new ScopeStub);
    }
    // Runs PHP statements taken from a layout and returns the named variable
    function runCode($code, array $vars, $return, $self = null) {
        $run = function () use ($code, $vars, $return) { extract($vars); eval('use Joomla\CMS\Language\Text; ' . $code); return $$return; };
        return $run->call($self ?: new ScopeStub);
    }
    // Includes a real layout file and returns what it printed and the variables it left
    function renderFile($file, array $vars, $self = null) {
        $run = function () use ($file, $vars) { extract($vars); ob_start(); include JPATH_ROOT . $file; $__out = ob_get_clean(); return array($__out, get_defined_vars()); };
        return $run->call($self ?: new ScopeStub);
    }
    // What a browser makes of the markup: scripts, event handlers and script URLs that it would run
    function activeContent($html) {
        $found = array();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8"><div id="fixture-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($dom->getElementsByTagName('*') as $el) {
            if (strtolower($el->nodeName) === 'script' && $el->getAttribute('type') !== 'text/x-jquery-tmpl') $found[] = 'script';
            foreach ($el->attributes as $attr) {
                if (stripos($attr->name, 'on') === 0) $found[] = $attr->name . '=' . $attr->value;
                if (in_array(strtolower($attr->name), array('href', 'src', 'action', 'formaction'), true) && preg_match('/^\s*(javascript|data|vbscript)\s*:/i', $attr->value) && $attr->value !== 'javascript:;') $found[] = $attr->name . '=' . $attr->value;
            }
        }
        return $found;
    }
    function tagNames($html) {
        $names = array();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8"><div id="fixture-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($dom->getElementsByTagName('*') as $el) $names[strtolower($el->nodeName)] = true;
        return array_keys($names);
    }
    function textOf($html) {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8"><div id="fixture-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        return trim(preg_replace('/\s+/u', ' ', $dom->getElementById('fixture-root')->textContent));
    }

    // The object that template lines see as $this
    final class ScopeStub { public $params; public function __construct($params = null) { $this->params = $params; } }
    final class ParamsStub {
        private $data;
        public function __construct(array $data = array()) { $this->data = $data; }
        public function get($key, $default = null) { return array_key_exists($key, $this->data) ? $this->data[$key] : $default; }
    }
    final class DocumentStub {
        public $tags = array(); public $scripts = array();
        public function addCustomTag($html) { $this->tags[] = trim($html); return $this; }
        public function addScript($url) { return $this; }
        public function getWebAssetManager() { return $this; }
        public function addInlineScript($js) { $this->scripts[] = $js; return $this; }
        public function addInlineStyle($css) { return $this; }
        public function registerAndUseStyle($name, $url, $options = array()) { return $this; }
    }
    final class ApplicationStub {
        public $document;
        public function __construct() { $this->document = new DocumentStub; }
        public function getDocument() { return $this->document; }
        public function getIdentity() { return (object) array('id' => 0); }
    }
    final class flexicontent_db { public static function unserialize_array($v, $force_array = false, $force_value = false) { return is_array($v) ? $v : false; } }
    final class FlexicontentHelperRoute {
        public static function getItemRoute($slug, $catslug = 0, $itemid = 0, $item = null) { return 'index.php?option=com_flexicontent&view=item&cid=' . $catslug . '&id=' . $slug; }
        public static function getCategoryRoute($slug, $itemid = 0, $vars = array()) { return 'index.php?option=com_flexicontent&view=category&cid=' . $slug; }
        public static function getTagRoute($slug, $itemid = 0) { return 'index.php?option=com_flexicontent&view=tags&id=' . $slug; }
    }
    final class FlexicontentFields {
        public static function getFieldsPerGroup() { return null; }
        public static function getFields(&$items) {}
        public static function createItemsListHTML($params, $items) { return '<h4 class="marker-info-title">Custom</h4><p>{__address__}</p>'; }
    }
    final class modFlexigooglemapHelper {
        public static function getField($id) { return (object) array('id' => $id, 'parameters' => new ParamsStub(array('custom_marker_path' => 'modules/mod_flexigooglemap/assets/marker'))); }
    }
    final class FlexicontentModelItem {}

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };

    // PHP notices and warnings of the layouts are failures too
    $php_errors = array();
    set_error_handler(static function ($severity, $message, $file, $line) use (&$php_errors) {
        if (error_reporting() & $severity) $php_errors[] = $message . ' in ' . basename($file) . ':' . $line;
        return true;
    });

    require JPATH_ROOT . '/site/classes/helpers/security.php';

    eval('use Joomla\String\StringHelper;
        final class flexicontent_html {
            public static $frameworks = array();
            public static function getToolTip($title = "", $content = "", $translate = 1, $escape = 1) { return \Joomla\CMS\HTML\HTMLHelper::tooltipText($title, $content, $translate, $escape); }
            public static function loadFramework($name, $mode = "", $params = null) { self::$frameworks[] = $name; }
            public static ' . methodSource(source('/site/classes/helpers/html.php'), 'striptagsandcut') . '
        }');

    \Joomla\CMS\Factory::$app = new ApplicationStub;
    $document = \Joomla\CMS\Factory::$app->document;
    $json_string = '("(?:[^"\\\\]|\\\\.)*")';
    $enc = static function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };

    // Stored text: quotes, markup, a script end tag, character references, a trailing backslash and a line break, and ordinary text
    $texts = array(
        'Tom & Jerry\'s "Best" <b>bold</b>',
        '"><script>alert(1)</script>',
        "' onmouseover='alert(1)",
        '" onmouseover="alert(1)',
        '</script><script>alert(1)</script>',
        '<img src=x onerror=alert(1)>',
        "back\\slash, new\nline, end\\",
        'Plain title',
    );


    // 1. Image field galleries: sizes of a stored value are integers, URLs and texts are encoded, the Pannellum configuration is JSON
    eval('final class plgFlexicontent_fieldsImage {
        static $default_widths  = array("l" => 800, "m" => 400, "s" => 120, "b" => 40);
        static $default_heights = array("l" => 600, "m" => 300, "s" => 90, "b" => 30);
        static $display_to_thumb_size = array();
        static $index_to_thumb_size = array(-1 => "b", 1 => "s", 2 => "m", 3 => "l", 4 => "o");
        static $thumb_only_displays = array();
        static $value_only_displays = array();
        static $js_added = array();
        static $isItemsManager = false;
        static $isHtmlViewFE = false;
        static $isTablet = false;
        static $isMobile = false;
        public function unserialize_array($v) { return is_array($v) ? $v : false; }
        public static ' . methodSource(source('/plugins/flexicontent_fields/image/image.php'), 'safeLinkUrl') . '
        public function render($layout, array $values, $inline = 0) {
            static $field_id = 0;
            $field = (object) array("id" => ++$field_id, "parameters" => new ParamsStub);
            $item = (object) array("id" => 5);
            $prop = "display"; $field->{$prop} = array(); $view = "item"; $is_ingroup = $use_ingroup = false; $isSingle = false;
            $orig_urlpath = "images/original"; $thumb_urlpath = "images/thumbs"; $extra_prefix = "";
            $usetitle = $usealt = $usedesc = 1; $usecust1 = $usecust2 = 0; $cust1_label = $cust2_label = "";
            $alt_image_prefix = "Image "; $uselegend = 0; $tooltip_class = ""; $grouptype = -1; $useogp = 0; $ogplimit = 0;
            $linkto_item = 0; $showtitle = $showdesc = $inline; $pretext = $posttext = $separatorf = $opentag = $closetag = "";
            $thumb_size = 3; $field_name_js = "img"; $PPFX_ = "pfx_";
            include JPATH_ROOT . "/plugins/flexicontent_fields/image/tmpl/" . $layout . ".php";
            return is_array($field->{$prop}) ? implode("", $field->{$prop}) : (string) $field->{$prop};
        }
    }');
    $image = new plgFlexicontent_fieldsImage;

    $bad_sizes = array(
        'size_w_l' => '800" onload="alert(1)', 'size_h_l' => '600" onerror="alert(2)',
        'size_w_m' => '400, https://attacker.example/x.png 9999w', 'size_h_m' => '300"><script>alert(3)</script>',
        'size_w_s' => '120px) 1px" onclick="alert(4)', 'size_h_s' => array('90'),
    );
    $good_sizes = array('size_w_l' => '800', 'size_h_l' => '600', 'size_w_m' => '400', 'size_h_m' => '300', 'size_w_s' => '120', 'size_h_s' => '90');

    foreach (array('bad sizes' => $bad_sizes, 'good sizes' => $good_sizes, 'no sizes' => array()) as $label => $sizes) {
        $html = $image->render('value_gallery_elastislide', array(array('originalname' => 'a&b.png', 'title' => 'T', 'desc' => 'D') + $sizes));
        $check(activeContent($html) === array('ontouchstart=e.preventDefault();') || activeContent($html) === array(), 'elastislide (' . $label . '): no script, handler or script URL from a value');
        $check(preg_match('~ srcset="/images/thumbs/l_a&amp;b\.png 800w, /images/thumbs/m_a&amp;b\.png 400w, /images/thumbs/s_a&amp;b\.png 120w" sizes="\(min-width: 800px\) 800px, \(min-width: 400px\) 400px, 120px"~', $html) === 1, 'elastislide (' . $label . '): srcset and sizes hold integers and encoded URLs');
        $check(strpos($html, 'data-medium="/images/thumbs/m_a&amp;b.png"') !== false && strpos($html, 'data-large="/images/thumbs/l_a&amp;b.png"') !== false && strpos($html, 'src="/images/thumbs/l_a&amp;b.png"') !== false, 'elastislide (' . $label . '): image URLs are attribute-encoded');
        $check(strpos($html, ' width="800" height="600" ') !== false, 'elastislide (' . $label . '): width and height are integers');

        $html = $image->render('value_gallery_galleriffic', array(array('originalname' => 'photo.png', 'title' => 'T', 'desc' => 'D') + $sizes));
        $expected = $label === 'no sizes' ? ' data-width="0" data-height="0" ' : ' data-width="800" data-height="600" ';
        $check(strpos($html, $expected) !== false, 'galleriffic (' . $label . '): data-width and data-height are integers');
        $handlers = array_unique(activeContent($html));
        $check(count($handlers) === 1 && strpos(reset($handlers), 'onclick=if (gf_gallery_') === 0, 'galleriffic (' . $label . '): only its own click handler is printed');

        $html = $image->render('value_gallery_pannellum', array(array('originalname' => 'pano.png', 'title' => 'T') + $sizes));
        $check(strpos($html, ' style="width: 800px; height: 800px; max-width: 100%; max-height: 50vh;"') !== false, 'pannellum (' . $label . '): the viewer size is integers');
        $check(activeContent($html) === array('script'), 'pannellum (' . $label . '): only the viewer script is printed');
    }

    foreach ($texts as $text) {
        $label = json_encode($text);

        // Pannellum: every configuration value is one JSON string literal, the title stays HTML-encoded because the viewer inserts it as HTML
        $html = $image->render('value_gallery_pannellum', array(array('originalname' => "pano's & co.png", 'title' => $text)));
        $ok = preg_match('~<script>\s*pannellum\.viewer\(\'panorama_5_\d+_0\', \{\s*"type": "equirectangular",\s*"panorama": ' . $json_string . ',\s*"preview": ' . $json_string . ',\s*"title": ' . $json_string . ',\s*\}\);\s*</script>~', $html, $m) === 1;
        $check($ok, 'pannellum: the configuration is JSON string literals: ' . $label);
        if ($ok) {
            $check(json_decode($m[1]) === "/images/original/pano's & co.png" && json_decode($m[2]) === "/images/thumbs/l_pano's & co.png", 'pannellum: panorama and preview URLs arrive unchanged: ' . $label);
            $check(json_decode($m[3]) === htmlspecialchars($text, ENT_COMPAT, 'UTF-8'), 'pannellum: the title arrives HTML-encoded: ' . $label);
            $check(strpbrk($m[1] . $m[2] . $m[3], "<>&'") === false, 'pannellum: no literal holds a character that HTML or the script parser could act on: ' . $label);
        }
        $check(substr_count($html, '<script') === 1 && substr_count($html, '</script') === 1, 'pannellum: the title adds no script element: ' . $label);

        // Inline title / description box, widgetkit overlay and multibox description: text only
        foreach (array('value_gallery_widgetkit', 'value_gallery_multibox', 'value_gallery_pannellum') as $layout) {
            $html = $image->render($layout, array(array('originalname' => 'photo.png', 'title' => $text, 'desc' => $text)), 1);
            $html = preg_replace('~<script>.*?</script>~s', '', $html);
            $check(activeContent($html) === array(), $layout . ': title and description add no script, handler or script URL: ' . $label);
            $check(strpos($html, '<div class="fc_img_tooltip_title" style="line-height:1em; font-weight:bold;">' . $enc($text) . '</div>') !== false, $layout . ': inline title is encoded text: ' . $label);
            $check(strpos($html, '<div class="fc_img_tooltip_desc" style="line-height:1em;">' . $enc($text) . '</div>') !== false, $layout . ': inline description is encoded text: ' . $label);
            $check(strpos($html, '<b>bold') === false && strpos($html, '<script>alert') === false && strpos($html, '<img src=x') === false, $layout . ': no markup from the title: ' . $label);
        }
        $html = $image->render('value_gallery_widgetkit', array(array('originalname' => 'photo.png', 'title' => $text, 'desc' => $text)));
        $check(strpos($html, '<b>' . $enc($text) . '</b>: ' . $enc($text)) !== false, 'widgetkit: the overlay is encoded text: ' . $label);
        $html = $image->render('value_gallery_multibox', array(array('originalname' => 'photo.png', 'title' => $text, 'desc' => $text)));
        $check(strpos($html, '<b>' . $enc($text) . '</b><br> ' . $enc($text)) !== false, 'multibox: the description box is encoded text: ' . $label);
        $html = $image->render('value_gallery_multibox', array(array('originalname' => 'photo.png', 'title' => $text)));
        $check(preg_match('~<div class="multiBoxDesc mb5_\d+_0">\s*' . preg_quote($enc($text), '~') . '\s*</div>~', $html) === 1, 'multibox: a title without description is encoded text: ' . $label);
    }

    // Edit form of the image field: the stored name is text in the preview message and one attribute value in the preview image
    $form = source('/plugins/flexicontent_fields/image/tmpl/field_InlineBoxes.php');
    $found = preg_match('/\$fc_preview_msg_text = .*?: \'\';/s', $form, $m) === 1;
    $check($found, 'image form: preview message code found');
    $msg_code = $found ? $m[0] : '';
    $link_lines = linesWith($form, '$img_link = htmlspecialchars(');
    $img_lines  = linesWith($form, 'src="\'.$img_link.\'"');
    $check(count($link_lines) === 1 && count($img_lines) === 1, 'image form: preview image code found');
    foreach (array('folder/<img src=x onerror=alert(1)>.png', 'a&b "c".png', "it's.png", 'plain.png') as $name) {
        $html = runCode($msg_code, array('value' => array('isURL' => 0), 'image_subpath' => $name, 'elementid_n' => 'custom_img_0'), 'fc_preview_msg');
        $check(activeContent($html) === array() && tagNames($html) === array('html', 'body', 'div', 'span') && textOf($html) === $name, 'image form: the stored name is shown as text: ' . $name);
    }
    foreach (array('https://img.example/a.png" onerror="alert(1)', "https://img.example/a.png' onerror='alert(1)", 'https://img.example/a.png?x=1&y=2') as $url) {
        $html = runCode(implode(' ', $link_lines) . ' ' . implode(' ', $img_lines), array('image_subpath' => $url, 'elementid_n' => 'custom_img_0'), 'imgpreview');
        $check(activeContent($html) === array() && strpos($html, ' src="' . $enc($url) . '" ') !== false, 'image form: a URL value stays inside the src attribute: ' . $url);
    }


    // 2. Map module: one location entry is JSON literals, its HTML holds encoded text and a checked directions link
    $map_location = static function (array $param_data, $title, array $coords) {
        static $module_id = 100;
        $params = new ParamsStub($param_data + array('fieldaddressid' => 5, 'uselink' => 1, 'usedirection' => 1));
        $module = (object) array('id' => ++$module_id);
        $locationItem = (object) array('id' => 12, 'alias' => 'item', 'catid' => 7, 'title' => $title);
        $locationCoords = $coords; $mapLocations = array(); $mapItems = array(); $valueOrder = 0;
        include JPATH_ROOT . '/modules/mod_flexigooglemap/tmpl/default_mapLocation.php';
        return $mapLocations;
    };

    foreach ($texts as $text) {
        $label = json_encode($text);
        $entries = $map_location(array('linkmode' => $text, 'readmore' => $text, 'directionname' => $text), $text, array(
            'lat' => '48.8566667', 'lon' => '2.3509871', 'addr_display' => $text, 'url' => 'https://maps.example/dir?a=1&b=2',
        ));
        $entry = count($entries) === 1 ? json_decode($entries[0]) : null;
        $check(is_array($entry) && count($entry) === 4, 'map location: the entry is one JSON array: ' . $label);
        if (!is_array($entry)) continue;
        $check(strpbrk($entries[0], "<>&'") === false, 'map location: the entry holds no character that HTML or the script parser could act on: ' . $label);
        $check($entry[1] === 48.8566667 && $entry[2] === 2.3509871 && $entry[3] === '__default__', 'map location: coordinates are numbers, the marker is the default one: ' . $label);
        $check(preg_match('/,48\.8566667, 2\.3509871, "__default__"/', $entries[0]) === 1, 'map location: coordinates are printed as they were stored: ' . $label);

        $html = $entry[0];
        $check(activeContent($html) === array(), 'map location: the popup HTML has no script, handler or script URL: ' . $label);
        $check(tagNames($html) === array('html', 'body', 'div', 'h4', 'p', 'a', 'span'), 'map location: the popup HTML has only its own elements: ' . $label);
        $expected_text = trim(preg_replace('/\s+/u', ' ', str_replace(array("\r", "\n"), ' ', $text)));
        $check(strpos($html, '<h4 class="marker-info-title">' . $enc($text) . '</h4>') !== false, 'map location: the title is encoded text: ' . $label);
        $check(strpos($html, '<p>' . str_replace("\n", ' ', $enc($text)) . '</p>') !== false, 'map location: the address is encoded text: ' . $label);
        $check(strpos($html, ' target="' . str_replace("\n", ' ', $enc($text)) . '" class="fc-map-link btn btn-secondary">') !== false, 'map location: the link target is one attribute value: ' . $label);
        $check(strpos($html, '<a href="https://maps.example/dir?a=1&amp;b=2" target="_blank" class="fc-map-link btn btn-secondary">') !== false, 'map location: the stored directions link is kept and encoded: ' . $label);
        $check(strpos($html, '<a href="index.php?option=com_flexicontent&amp;view=item&amp;cid=7&amp;id=12:item" target=') !== false, 'map location: the item link is not encoded twice: ' . $label);
    }

    // Button texts are labels: markup is encoded, a character reference of a translation is kept
    $entries = $map_location(array('readmore' => 'Lire la suite&hellip; <b>x</b>', 'directionname' => 'Itin&eacute;raire'), 'T', array('lat' => '1', 'lon' => '2'));
    $entry = json_decode($entries[0]);
    $check(strpos($entry[0], '<span class="fc-map-link-text">Lire la suite&hellip; &lt;b&gt;x&lt;/b&gt;</span>') !== false && strpos($entry[0], 'Itin&eacute;raire</span>') !== false, 'map location: button labels keep character references and lose markup');

    // Directions link: only http(s) or site-relative links are used, anything else falls back to the generated maps link
    foreach (array('javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'javascript&colon;alert(1)', 'data:text/html,<script>alert(1)</script>', 'https://maps.example/" onclick="alert(1)', "https://maps.example/' onclick='alert(1)") as $url) {
        $entries = $map_location(array(), 'T', array('lat' => '48.5', 'lon' => '-2.25', 'url' => $url));
        $entry = json_decode($entries[0]);
        $check(is_array($entry) && activeContent($entry[0]) === array() && strpos($entry[0], '<a href="http://maps.google.com/maps?q=48.5%2C-2.25" target="_blank"') !== false, 'map location: an unsafe directions link is replaced: ' . $url);
    }

    // Coordinates: numbers only, whatever is stored
    $coordinates = array(
        array('48.85); alert(1); //', '2.35', '48.85, 2.35'),
        array('48.85', "2.35]; alert(1); [", '48.85, 2.35'),
        array('1e400', '-1e-12', '0, -0'),
        array('-0', '00012.500', '0, 12.5'),
        array('abc', '<script>', '0, 0'),
        array('-33.8688', '151.2093', '-33.8688, 151.2093'),
        array('0', '0', '0, 0'),
        array('12', '7.5', '12, 7.5'),
    );
    foreach ($coordinates as list($lat, $lon, $expected)) {
        $entries = $map_location(array(), 'T', array('lat' => $lat, 'lon' => $lon));
        $check(count($entries) === 1 && is_array(json_decode($entries[0])) && preg_match('/,' . preg_quote($expected, '/') . ', "__default__"/', $entries[0]) === 1, 'map location: coordinates ' . json_encode(array($lat, $lon)) . ' are printed as ' . $expected);
    }
    $check($map_location(array(), 'T', array('lat' => array('1'), 'lon' => '2')) === array(), 'map location: a coordinate that is no number or text is skipped');

    // Custom marker: a name that could leave the image tag or the marker folder is not used, a plain name is
    foreach (array('x" onerror="alert(1).png', "x' onerror='alert(1).png", '../../../images/x.png', 'sub/../../x.png', 'x<y>.png') as $marker) {
        $entries = $map_location(array(), 'T', array('lat' => '1', 'lon' => '2', 'custom_marker' => $marker));
        $entry = json_decode($entries[0]);
        $check(is_array($entry) && count($entry) === 4 && $entry[3] === '__default__', 'map location: marker name is refused: ' . $marker);
    }
    $entries = $map_location(array(), 'T', array('lat' => '1', 'lon' => '2', 'custom_marker' => 'airplane.png', 'marker_anchor' => 'TopL'));
    $entry = json_decode($entries[0]);
    $check(is_array($entry) && count($entry) === 8 && $entry[3] === 'https://fixture.example/modules/mod_flexigooglemap/assets/marker/airplane.png' && (int) $entry[4] > 0 && (int) $entry[5] > 0 && $entry[6] === '0' && $entry[7] === '0', 'map location: a plain marker name is used with its size and anchor');

    // Custom HTML mode: the template output is kept as HTML, the address placed into it is encoded
    $entries = $map_location(array('infotextmode' => 1), 'T', array('lat' => '1', 'lon' => '2', 'addr_display' => '<img src=x onerror=alert(1)> 5 Main St'));
    $entry = json_decode($entries[0]);
    $check(is_array($entry) && activeContent($entry[0]) === array() && strpos($entry[0], '<h4 class="marker-info-title">Custom</h4><p>&lt;img src=x onerror=alert(1)&gt; 5 Main St</p>') !== false, 'map location: the address is encoded inside a custom HTML template');


    // 3. Map module layout: parameters cannot leave the style and script elements, ordinary values print as before
    $map_layout = static function (array $param_data, array $mapItemData, $defaultMarkerURL = null) {
        $params = new ParamsStub($param_data);
        $module = (object) array('id' => 7); $moduleclass_sfx = '';
        $renderedMapLocations = array('["<div>x</div>",1, 2, "__default__"]');
        $wS_dMU = 20; $hS_dMU = 30; $wA_dMU = 10; $hA_dMU = 30;
        ob_start();
        include JPATH_ROOT . '/modules/mod_flexigooglemap/tmpl/default.php';
        return ob_get_clean();
    };
    $items = array((object) array('title' => '</script><script>alert(6)</script>', 'link' => 'index.php?a=1&amp;b=2'));
    // Script text without its double-quoted string literals: what is left is code
    $code_of = static function ($html) {
        preg_match_all('~<script\b[^>]*>(.*?)</script>~s', $html, $m);
        return preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '""', implode("\n", $m[1]));
    };

    foreach (array('googlemap', 'openstreetmap') as $api) {
        $plain = $map_layout(array('mapapi' => $api, 'width' => '100%', 'height' => '400', 'maptype' => 'HYBRID', 'usescrollmouse' => 'false', 'maxzoom' => '15', 'gridsize' => '60',
            'mapstyle' => '[{"featureType":"water","stylers":[{"color":"#aabbcc"}]}]', 'os_tile_server_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'clustermode' => 1, 'marker_opacity' => '0.5', 'use_dynamic_marker_list' => 1, 'map_list_width' => 'calc(100% - 20px)'), $items);
        $check(strpos($plain, "width: 100%;\n") !== false || strpos($plain, "width: 100%;\r\n") !== false, $api . ': a percent width is unchanged');
        $check(preg_match('/height: 400px;\R/', $plain) === 1 && strpos($plain, 'opacity: 0.5 !important;') !== false && strpos($plain, 'width: calc(100% - 20px);') !== false, $api . ': numeric height, opacity and a calc() width are unchanged');
        if ($api === 'googlemap') {
            $check(strpos($plain, 'mapTypeId: google.maps.MapTypeId.HYBRID,') !== false && strpos($plain, 'scrollwheel: false,') !== false, 'googlemap: map type and scroll wheel option are unchanged');
            $check(strpos($plain, 'styles: [{"featureType":"water","stylers":[{"color":"#aabbcc"}]}],') !== false, 'googlemap: a valid map style is printed as it was');
            $check(strpos($plain, 'gridSize:60,') !== false && strpos($plain, 'maxZoom:15,') !== false && strpos($plain, 'width: 53,') !== false, 'googlemap: cluster options are unchanged');
        } else {
            $check(strpos($plain, 'L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {') !== false && strpos($plain, 'scrollWheelZoom: false') !== false && preg_match('/maxZoom: 15\s*\}\)/', $plain) === 1, 'openstreetmap: tile server, scroll wheel option and zoom are unchanged');
        }
        $check(strpos($plain, 'let locations = [["<div>x</div>",1, 2, "__default__"]];') !== false, $api . ': the location entries are printed as given');

        $hostile = $map_layout(array('mapapi' => $api,
            'width' => '100%;}</style><img src=x onerror=console.log(1)>', 'height' => '60vh;} body{display:none} </style><svg onload=console.log(2)>',
            'maptype' => 'ROADMAP}); alert(1); ({', 'usescrollmouse' => 'true}); alert(2); ({', 'maxzoom' => '17}); alert(3); ({', 'gridsize' => '50}); alert(4); ({',
            'mapstyle' => '[]}); alert(5); ({', 'os_tile_server_url' => "https://tile.example/{z}/{x}/{y}.png'); alert(7); ('",
            'clustermode' => 1, 'marker_opacity' => '1;} </style><script>alert(8)</script>', 'use_dynamic_marker_list' => 1,
            'map_list_width' => '320px;}</style><script>alert(9)</script>', 'map_height_wrapped' => '45vh;}</style><script>alert(10)</script>', 'marker_list_height_wrapped' => '45vh</style><script>alert(11)</script>',
            'info_popup' => 2, 'marker_clicktarget' => "_blank'); alert(12); ('"), $items, "https://fixture.example/m.png'}; alert(13); ({a:'");
        $check(substr_count($hostile, '<script') === substr_count($plain, '<script') && substr_count($hostile, '</script') === substr_count($plain, '</script'), $api . ': parameters and item titles add no script element');
        $check(substr_count($hostile, '</style') === substr_count($plain, '</style') && strpos($hostile, '<img src=x') === false && strpos($hostile, '<svg') === false, $api . ': CSS sizes cannot end the style element');
        $check(strpos($code_of($hostile), 'alert(') === false, $api . ': no parameter value becomes script code');
        $check(preg_match('~let mapItems = (\[.*\]);~', $hostile, $m) === 1 && is_array(json_decode($m[1])) && json_decode($m[1])[0]->title === $items[0]->title && strpbrk($m[1], "<>&'") === false, $api . ': the item data is JSON without characters the HTML parser could act on');
        if ($api === 'googlemap') {
            $check(strpos($hostile, 'mapTypeId: google.maps.MapTypeId.ROADMAPalert,') !== false && strpos($hostile, 'scrollwheel: true,') !== false && strpos($hostile, 'styles: [],') !== false, 'googlemap: map type is letters, the scroll wheel option is a boolean, an invalid map style is not printed');
            $check(strpos($hostile, 'gridSize:50,') !== false && strpos($hostile, 'maxZoom:17,') !== false, 'googlemap: cluster options are integers');
        } else {
            $check(preg_match('~L\.tileLayer\(' . $json_string . ', \{~', $hostile, $m) === 1 && json_decode($m[1]) === "https://tile.example/{z}/{x}/{y}.png'); alert(7); ('", 'openstreetmap: the tile server is one string literal');
            $check(preg_match('/maxZoom: 17\s*\}\)/', $hostile) === 1 && strpos($hostile, 'maxClusterRadius :50 }') !== false, 'openstreetmap: zoom and cluster radius are integers');
        }
    }
    $styled = $map_layout(array('mapapi' => 'googlemap', 'mapstyle' => '[{"featureType":"</script><script>alert(1)</script>"}]'), $items);
    $unstyled = $map_layout(array('mapapi' => 'googlemap'), $items);
    $check(substr_count($styled, '</script') === substr_count($unstyled, '</script') && strpos($styled, '<script>alert(1)') === false, 'googlemap: a map style value cannot end the script element');
    $check(preg_match('~styles: (\[.*\]),~', $styled, $m) === 1 && json_decode($m[1])[0]->featureType === '</script><script>alert(1)</script>', 'googlemap: the map style value arrives unchanged');
    $layout_source = source('/modules/mod_flexigooglemap/tmpl/default.php');
    $check(strpos($layout_source, "src=\"' + marker._icon_url + '\"") === false && substr_count($layout_source, "li.querySelector('img.fc_module_marker_list_icon').src = marker._icon_url;") === 2, 'map layout: the marker icon URL is set as a property, not concatenated into HTML');


    // 4. Address field: coordinates and zoom are numbers, address text is encoded, the map link is checked and encoded
    final class AddressFixture {
        static $isItemsManager = false;
        public $values = array();
        public function render(array $values, array $param_data, $view = 'item') {
            static $field_id = 0;
            $this->values = $values;
            $field = (object) array('id' => ++$field_id, 'name' => 'addr', 'parameters' => new ParamsStub($param_data + array('show_map' => 'both')));
            $item = (object) array('id' => 5); $prop = 'display'; $field->{$prop} = array(); $is_ingroup = false;
            ob_start();
            include JPATH_ROOT . '/plugins/flexicontent_fields/addressint/tmpl/value.php';
            return implode('', $field->{$prop}) . ob_get_clean();
        }
    }
    $address = new AddressFixture;
    \Joomla\CMS\Language\Text::$strings['PLG_FC_ADDRESSINT_CC_CI'] = 'C&ocirc;te d\'Ivoire';
    $bad_value = array(
        'lat' => '48.85" onmouseover="alert(1)', 'lon' => '2.35}); alert(2); ({', 'zoom' => '12" onclick="alert(3)',
        'addr_display' => "<img src=x onerror=alert(4)>\nLine \"2\"", 'addr1' => '</script><script>alert(5)</script>', 'url' => 'javascript:alert(6)',
    );
    $good_value = array('lat' => '48.8566667', 'lon' => '2.3509871', 'zoom' => '14', 'addr_display' => "5 Main St\nParis", 'addr1' => '5 Main St', 'url' => 'https://maps.example/dir?a=1&b=2');

    $html = $address->render(array($good_value), array('mapapi' => 'googlemap', 'map_embed_type' => 'int'));
    $check(strpos($html, 'data-maplatlon="{lat: 48.8566667, lng: 2.3509871}"') !== false && strpos($html, 'data-mapzoom="14"') !== false && strpos($html, 'data-maptype="google.maps.MapTypeId.ROADMAP"') !== false, 'address (interactive map): ordinary coordinates, zoom and map type are unchanged');
    $check(strpos($html, '<div class="address">5 Main St<br />Paris</div>') !== false && strpos($html, '<a href="https://maps.example/dir?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">') !== false, 'address: ordinary address and directions link are unchanged');
    $html = $address->render(array($bad_value), array('mapapi' => 'googlemap', 'map_embed_type' => 'int', 'map_type_view' => 'roadmap"onmouseover="alert(7)'));
    $check(strpos($html, 'data-maplatlon="{lat: 48.85, lng: 2.35}"') !== false && strpos($html, 'data-mapzoom="12"') !== false && strpos($html, 'data-maptype="google.maps.MapTypeId.ROADMAPONMOUSEOVERALERT"') !== false, 'address (interactive map): coordinates and zoom are numbers, the map type is letters');
    $check(activeContent($html) === array(), 'address (interactive map): no script, handler or script URL from a value');
    $check(strpos($html, '<div class="address">&lt;img src=x onerror=alert(4)&gt;<br />Line &quot;2&quot;</div>') !== false, 'address: the address is encoded text with its line breaks');
    $check(strpos($html, '<a href="https://maps.google.com/maps?q=48.85') !== false, 'address: an unsafe directions link is replaced');

    $html = $address->render(array($good_value), array('mapapi' => 'googlemap', 'map_embed_type' => 'img'));
    $check(strpos($html, '<img src="https://maps.google.com/maps/api/staticmap?center=48.8566667,2.3509871&amp;zoom=14&amp;size=200x150&amp;maptype=roadmap&amp;markers=size:mid%7Ccolor:red%7C|48.8566667,2.3509871&amp;sensor=false" ') !== false, 'address (static map): the image URL of ordinary values is unchanged');
    $check(strpos($html, '<a href="https://maps.example/dir?a=1&amp;b=2" target="_blank">') !== false, 'address (static map): the map link is attribute-encoded');
    $html = $address->render(array($bad_value), array('mapapi' => 'googlemap', 'map_embed_type' => 'img', 'marker_color' => 'red" onerror="alert(8)'));
    $check(activeContent($html) === array() && strpos($html, 'staticmap?center=48.85,2.35&amp;zoom=12&amp;') !== false, 'address (static map): coordinates and zoom are numbers, no handler is added');

    foreach (array('good' => $good_value, 'bad' => $bad_value) as $label => $value) {
        $document->scripts = array();
        $html = $address->render(array($value), array('mapapi' => 'openstreetmap'));
        $js = implode("\n", $document->scripts);
        $numbers = $label === 'good' ? array('48.8566667', '2.3509871', '14') : array('48.85', '2.35', '12');
        $check(preg_match('~theMap = L\.map\("map_canvas_addr_0_5"\)\.setView\(\[' . preg_quote($numbers[0] . ',' . $numbers[1], '~') . '\], ' . $numbers[2] . '\);~', $js) === 1, 'address (OpenStreetMap, ' . $label . '): the map view is numbers');
        $check(strpos($js, 'theMarker = L.marker([' . $numbers[0] . ',' . $numbers[1] . '], {icon: mapIcon}).addTo(theMap);') !== false, 'address (OpenStreetMap, ' . $label . '): the marker position is numbers');
        $ok = preg_match('~var contentPopup = ' . $json_string . ';~', $js, $m) === 1;
        $check($ok && strpbrk($m[1], "<>&'") === false && activeContent((string) json_decode($m[1])) === array(), 'address (OpenStreetMap, ' . $label . '): the popup is one JSON string literal without active content');
        $check(strpos($js, 'alert(') === false || strpos(preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '""', $js), 'alert(') === false, 'address (OpenStreetMap, ' . $label . '): no value becomes script code');
        $check(strpos($js, 'L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",') !== false, 'address (OpenStreetMap, ' . $label . '): the tile server is a string literal');
    }

    // Formatted address: every property is encoded text, a country name of the language file keeps its character reference
    $html = $address->render(array(array('name' => '<script>alert(1)</script>', 'addr1' => 'Tom & Jerry\'s "Best"', 'city' => 'Abidjan', 'country' => 'CI', 'zip' => '01')), array('addr_display_mode' => 'formatted', 'show_map' => ''));
    $check(activeContent($html) === array() && strpos($html, '<h3 class="fc-addrint business-name">&lt;script&gt;alert(1)&lt;/script&gt;</h3>') !== false, 'address (formatted): the name is encoded text');
    $check(strpos($html, '<span class="fc-addrint street-address">Tom &amp; Jerry&#039;s &quot;Best&quot;</span>') !== false && strpos($html, '<span class="fc-addrint country">C&ocirc;te d\'Ivoire</span>') !== false, 'address (formatted): text is encoded once, the country name is not encoded again');
    $html = $address->render(array(array('addr1' => 'x', 'city' => 'y', 'country' => '"><script>alert(1)</script>')), array('addr_display_mode' => 'formatted', 'show_map' => ''));
    $check(activeContent($html) === array() && strpos($html, '<script>') === false, 'address (formatted): a stored country that is no country code is encoded text');

    // Edit form: the coordinates of the map script are numbers
    $form = source('/plugins/flexicontent_fields/addressint/tmpl/field.php');
    $found = preg_match('/\$_coord = static function.*?fcfield_addrint\.LatLon\[.*?\';/s', $form, $m) === 1;
    $check($found, 'address form: coordinates code found');
    foreach (array(array('48.85}; alert(1); ({', '2.35', '{lat: 48.85, lng: 2.35}'), array('', '', '{lat: 0, lng: 0}'), array('-12.5', '130.25', '{lat: -12.5, lng: 130.25}')) as list($lat, $lon, $expected)) {
        $js = runCode('$js = ""; ' . ($found ? $m[0] : ''), array('value' => array('lat' => $lat, 'lon' => $lon), 'elementid_n' => 'custom_addr_0'), 'js');
        $check(trim($js) === 'fcfield_addrint.LatLon["custom_addr_0"] = ' . $expected . ';', 'address form: coordinates ' . json_encode(array($lat, $lon)) . ' are numbers');
    }


    // 5. Core fields, e-mail, term list and page navigation: stored names are encoded text
    final class CoreFixture {
        static $isHtmlViewFE = true;
        public function render($name, array $vars) {
            extract($vars);
            $document = \Joomla\CMS\Factory::getApplication()->getDocument();
            include JPATH_ROOT . '/plugins/flexicontent_fields/core/tmpl/' . $name . '/value_default.php';
            return is_array($field->{$prop}) ? implode(', ', $field->{$prop}) : $field->{$prop};
        }
    }
    $core = new CoreFixture;
    $newField = static function (array $params = array()) { return (object) array('name' => 'f', 'display' => array(), 'value' => array(), 'parameters' => new ParamsStub($params)); };

    foreach ($texts as $text) {
        $label = json_encode($text);
        $item = (object) array('id' => 5, 'catid' => 7, 'title' => $text, 'creator' => $text, 'cuname' => 'user "' . $text);

        $document->tags = array();
        $html = $core->render('title', array('field' => $newField(), 'item' => $item, 'prop' => 'display', 'pretext' => '<i>', 'posttext' => '</i>', 'isMatchedItemView' => true));
        $check(activeContent($html) === array() && preg_match('~^\s*<div style="display:inline" itemprop="name" >\s*<i>' . preg_quote($enc($text), '~') . '</i>\s*</div>$~D', $html) === 1, 'core title: the title is encoded text between prefix and suffix: ' . $label);
        $check($document->tags === array('<meta property="og:title" content="' . flexicontent_html::striptagsandcut('<i>' . $text . '</i>', 300) . '" />'), 'core title: the Open Graph title is built from the stored title as before: ' . $label);
        $html = $core->render('title', array('field' => $newField(array('microdata_itemprop' => '', 'useogp' => 0)), 'item' => $item, 'prop' => 'csv_export', 'pretext' => '', 'posttext' => '', 'isMatchedItemView' => false));
        $check($html === $text, 'core title: a CSV export gets the title as it is stored: ' . $label);

        $html = $core->render('createdby', array('field' => $newField(), 'item' => $item, 'prop' => 'display', 'pretext' => '', 'posttext' => ''));
        $check($html === $enc($text), 'core author: the name is encoded text: ' . $label);
        $html = $core->render('createdby', array('field' => $newField(array('name_username' => 2)), 'item' => $item, 'prop' => 'display', 'pretext' => '', 'posttext' => ''));
        $check($html === $enc('user "' . $text), 'core author: the login name is encoded text: ' . $label);
        $html = $core->render('createdby', array('field' => $newField(), 'item' => $item, 'prop' => 'csv_export', 'pretext' => '', 'posttext' => ''));
        $check($html === $text, 'core author: a CSV export gets the name as it is stored: ' . $label);

        foreach (array(1, 0) as $link_to_view) {
            $field = $newField();
            $html = $core->render('tags', array('field' => $field, 'prop' => 'display', 'pretext' => '', 'posttext' => '', 'tags' => array((object) array('id' => 3, 'name' => $text, 'slug' => '3:tag')), 'tag_links' => array(), 'use_catlinks' => 0, 'link_to_view' => $link_to_view));
            $check(activeContent($html) === array() && strpos($html, '">' . $enc($text) . ($link_to_view ? '</a>' : '</span>')) !== false && $field->value === array($text), 'core tags: the tag name is encoded text (link ' . $link_to_view . '): ' . $label);
            $field = $newField();
            $html = $core->render('categories', array('field' => $field, 'item' => $item, 'prop' => 'display', 'pretext' => '', 'posttext' => '', 'categories' => array((object) array('id' => 7, 'title' => $text, 'slug' => '7:cat')), 'cat_links' => array(), 'globalnoroute' => array(), 'link_to_view' => $link_to_view));
            $check(activeContent($html) === array() && strpos($html, '">' . $enc($text) . ($link_to_view ? '</a>' : '</span>')) !== false && $field->value === array($text), 'core categories: the category title is encoded text (link ' . $link_to_view . '): ' . $label);
        }
        $html = $core->render('tags', array('field' => $newField(), 'prop' => 'csv_export', 'pretext' => '', 'posttext' => '', 'tags' => array((object) array('id' => 3, 'name' => $text, 'slug' => '3:tag')), 'tag_links' => array(), 'use_catlinks' => 0, 'link_to_view' => 1));
        $check($html === $text, 'core tags: a CSV export gets the name as it is stored: ' . $label);

        // E-mail: address inside the href, text as link text
        $mail_vars = array('prop' => 'display', 'is_ingroup' => false, 'usetitle' => 1, 'default_title' => '', 'format' => 'html', 'email_cloaking' => 0, 'pretext' => '', 'posttext' => '', 'multiple' => 0);
        list(, $vars) = renderFile('/plugins/flexicontent_fields/email/tmpl/value_default.php', $mail_vars + array('field' => $newField(), 'mailto_link' => 1, 'values' => array(array('addr' => 'a@example.org' . $text, 'text' => $text))));
        $html = implode('', $vars['field']->display);
        $check(activeContent($html) === array() && $html === '<a href="mailto:' . $enc('a@example.org' . $text) . '" target="_blank" itemprop="email">' . $enc($text) . '</a>', 'e-mail: address and link text are encoded: ' . $label);
        list(, $vars) = renderFile('/plugins/flexicontent_fields/email/tmpl/value_default.php', $mail_vars + array('field' => $newField(), 'mailto_link' => 0, 'values' => array(array('addr' => 'a@example.org', 'text' => $text))));
        $check(implode('', $vars['field']->display) === $enc($text), 'e-mail: text without a link is encoded: ' . $label);

        // Term list: the title is text, the description keeps the HTML that the field configuration allowed
        $term_vars = array('prop' => 'display', 'is_ingroup' => false, 'cut_text' => 0, 'pretext' => '', 'posttext' => '', 'itemprop' => '', 'multiple' => 1);
        list(, $vars) = renderFile('/plugins/flexicontent_fields/termlist/tmpl/value_default.php', $term_vars + array('field' => $newField(), 'encode_output' => 0, 'values' => array(array('title' => $text, 'text' => '<p>Kept <em>HTML</em></p>'))));
        $html = implode('', $vars['field']->display);
        $check(strpos($html, '<label class="fc_termtitle label text-white bg-success label-success">' . $enc($text) . '</label>') !== false && strpos($html, '<div class="fc_termdesc"><p>Kept <em>HTML</em></p></div>') !== false, 'term list: the title is encoded, the description is unchanged: ' . $label);
        list(, $vars) = renderFile('/plugins/flexicontent_fields/termlist/tmpl/value_default.php', $term_vars + array('field' => $newField(), 'encode_output' => 1, 'values' => array(array('title' => $enc($text), 'text' => 'x'))));
        $check(strpos(implode('', $vars['field']->display), 'label-success">' . $enc($text) . '</label>') !== false, 'term list: a title that the field already encoded is not encoded twice: ' . $label);

        // Page navigation: titles of the previous and next item
        foreach (array('value_default', 'value_legacy') as $layout) {
            $nav = $newField();
            $nav->prev = $nav->next = (object) array('id' => 1); $nav->prevtitle = $text; $nav->nexttitle = $text . ' next'; $nav->prevurl = 'index.php?id=1'; $nav->nexturl = 'index.php?id=2'; $nav->prevThumb = $nav->nextThumb = '';
            list(, $vars) = renderFile('/plugins/flexicontent_fields/fcpagenav/tmpl/' . $layout . '.php', array('field' => $nav, 'item' => (object) array('id' => 5), 'img_err_msg' => '', 'use_category_link' => 0, 'use_tooltip' => 0, 'tooltip_class' => '', 'use_title' => 1,
                'prev_label' => 'Previous', 'next_label' => 'Next', 'show_prevnext_count' => 0, 'location' => 1, 'item_count' => 3, 'tooltip_title_prev' => '', 'tooltip_title_next' => '', 'rows' => array(), 'category' => null, 'category_label' => ''));
            $html = $vars['html'];
            $check(activeContent($html) === array() && strpos($html, $enc($text)) !== false && strpos($html, $enc($text . ' next')) !== false && !array_diff(tagNames($html), array('html', 'body', 'div', 'span', 'a', 'i')), 'page navigation (' . $layout . '): item titles are encoded text: ' . $label);
        }
    }
    list(, $vars) = renderFile('/plugins/flexicontent_fields/termlist/tmpl/value_default.php', $term_vars + array('field' => $newField(), 'encode_output' => 0, 'values' => array(array('title' => 'R&amp;D &copy; <b>x</b>', 'text' => ''))));
    $check(strpos(implode('', $vars['field']->display), 'label-success">R&amp;D &copy; &lt;b&gt;x&lt;/b&gt;</label>') !== false, 'term list: character references typed in a title are kept, markup is encoded');


    // 6. File and media file fields: name and description in the value layouts, names and description in the edit form
    foreach (array('/plugins/flexicontent_fields/file/tmpl/value_InlineBoxes.php', '/plugins/flexicontent_fields/mediafile/tmpl/value_InlineBoxes.php', '/plugins/flexicontent_fields/mediafile/tmpl/value_InlineBoxes_fa.php') as $file) {
        $src = source($file);
        $descr_lines = linesWith($src, '$descr_inline = \' <div class="fcfile_descr_inline');
        $name_lines  = linesWith($src, '.($filename_shown && $link_filename ? ');
        $check(count($descr_lines) === 1 && count($name_lines) === 1, basename($file) . ': description and link text code found');
        foreach ($texts as $text) {
            $html = runCode(implode(' ', $descr_lines), array('file_data' => (object) array('description' => $text)), 'descr_inline');
            $check(activeContent($html) === array() && $html === ' <div class="fcfile_descr_inline alert alert-info">' . nl2br($enc($text)) . '</div>', basename($file) . ': the description is encoded text with its line breaks: ' . json_encode($text));
            $html = runCode('$out = \'\' ' . trim(implode(' ', $name_lines)) . ';', array('filename_shown' => 1, 'link_filename' => 1, 'name_str' => $text, 'downloadstext' => 'Download'), 'out');
            $check($html === $enc($text), basename($file) . ': the file name as link text is encoded: ' . json_encode($text));
        }
    }
    foreach (array('/plugins/flexicontent_fields/mediafile/tmpl/field_InlineBoxes.php', '/plugins/flexicontent_fields/mediafile/tmpl/field_Table.php') as $file) {
        $src = source($file);
        $lines = array_merge(linesWith($src, 'class="fc_filedata_storage_name"'), linesWith($src, 'class="fc_filedata_txt_nowrap nowrap_hidden"'), linesWith($src, '[file-desc]" class="fc_filedesc'));
        $check(count($lines) === 3, basename($file) . ': name and description code found');
        foreach ($texts as $text) {
            $html = '';
            foreach ($lines as $line) {
                $html .= runCode('$out = \'' . trim($line) . '\';', array('file_data' => (object) array('filename' => $text, 'altname' => $text, 'description' => '</textarea>' . $text), 'form_data' => array(), 'file_id' => 3, 'elementid_n' => 'custom_file_0', 'fieldname_n' => 'custom[file][0]'), 'out');
            }
            $check(activeContent($html) === array() && tagNames($html) === array('html', 'body', 'div', 'span', 'br', 'textarea'), 'media file form (' . basename($file) . '): names and description are text: ' . json_encode($text));
            $check(preg_match('~<textarea [^<>]*>([^<]*)</textarea>$~D', $html, $m) === 1 && html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') === '</textarea>' . $text, 'media file form (' . basename($file) . '): the description stays inside its textarea: ' . json_encode($text));
        }
    }


    // 7. Site templates: item titles, and the title inside the "read more" text and image
    \Joomla\CMS\Language\Text::$strings['FLEXI_READ_MORE'] = 'Read more about %s&hellip;';
    $view = new ScopeStub(new ParamsStub(array('title_cut_text' => 200)));
    foreach (array('faq' => 2, 'table' => 2, 'grid' => 4, 'items-tabbed' => 1) as $template => $count) {
        $src = source('/site/templates/' . $template . '/category_items.php');
        $check(preg_match('/echo\s+\$item->title\s*;/', $src) === 0 && preg_match('/FLEXI_READ_MORE\', \$item->title\)/', $src) === 0, $template . ' template: no title is printed as it is stored');
        $lines = linesWith($src, '$item->title');
        $lines = array_values(array_filter($lines, static function ($line) { return strpos($line, '<?php echo htmlspecialchars(') !== false && substr_count($line, '<?php') === substr_count($line, '?>'); }));
        $check(count($lines) === $count, $template . ' template: title lines found');
        foreach ($texts as $text) {
            $item = (object) array('id' => 5, 'title' => $text, 'slug' => '5:a', 'categoryslug' => '7:c', 'params' => new ParamsStub);
            foreach ($lines as $n => $line) {
                $html = renderLine($line, array('item' => $item, 'link_url' => 'index.php?id=5'), $view);
                $expected = $template === 'items-tabbed' ? $enc(mb_substr($text, 0, 20, 'UTF-8')) : $enc($text);
                $check(activeContent($html) === array() && strpos($html, $expected) !== false && !array_diff(tagNames($html), array('html', 'body', 'div', 'a', 'h3')), $template . ' template: title line ' . $n . ' prints encoded text: ' . json_encode($text));
            }
        }
    }
    $src = source('/site/templates/grid/category_items.php');
    $found = preg_match_all('/\$read_more_text = .*?\$read_more_text;/s', $src, $blocks);
    $check($found === 4, 'grid template: read more code found');
    foreach ($texts as $text) {
        $item = (object) array('title' => $text, 'params' => new ParamsStub);
        foreach ($blocks[0] as $n => $block) {
            foreach (array(1, 0) as $readon_type) {
                $html = runCode('ob_start(); ' . $block . ' $out = ob_get_clean();', array('item' => $item, 'readon_type' => $readon_type, 'readon_image' => 'images/more.png'), 'out');
                $expected = $readon_type ? '<img src="images/more.png" alt="Read more about ' . $enc($text) . '&hellip;" />' : '<span class="icon-chevron-right"></span> Read more about ' . $enc($text) . '&hellip;';
                $check($html === $expected && activeContent($html) === array(), 'grid template: read more ' . ($readon_type ? 'image' : 'text') . ' ' . $n . ' holds the encoded title: ' . json_encode($text));
            }
        }
    }
    $lines = linesWith(source('/site/templates/items-tabbed/category_items.php'), "Text::sprintf('FLEXI_READ_MORE'");
    $check(count($lines) === 1, 'items-tabbed template: read more line found');
    $html = renderLine($lines[0], array('item' => (object) array('title' => '"><script>alert(1)</script>', 'params' => new ParamsStub)), $view);
    $check(trim($html) === 'Read more about &quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;&hellip;', 'items-tabbed template: read more text holds the encoded title');
    $found = preg_match('/echo htmlspecialchars\(\(string\) \(\( StringHelper::strlen\(\$item->title\).*?;/s', source('/site/templates/items-tabbed/category_items.php'), $m) === 1;
    $check($found, 'items-tabbed template: cut title code found');
    foreach (array(array('<b>bold</b> title', 200, '&lt;b&gt;bold&lt;/b&gt; title'), array('<b>bold</b> title', 6, '&lt;b&gt;bol ...'), array('Plain title', 200, 'Plain title')) as list($title, $cut, $expected)) {
        $cut_view = new ScopeStub(new ParamsStub(array('title_cut_text' => $cut)));
        $html = runCode('use Joomla\String\StringHelper; ob_start(); ' . ($found ? $m[0] : '') . ' $out = ob_get_clean();', array('item' => (object) array('title' => $title)), 'out', $cut_view);
        $check($html === $expected, 'items-tabbed template: the cut title is encoded text: ' . $title . ' / ' . $cut);
    }


    // 8. Module templates: item titles, class suffix, "read more" parameters, category image, tag names, image sizes
    foreach (array('default' => 2, 'news' => 4, 'carousel' => 4, 'swiper' => 4) as $layout => $count) {
        $src = source('/modules/mod_flexicontent/tmpl/' . $layout . '.php');
        $check(preg_match('/echo\s+\$item->title\s*;/', $src) === 0 && strpos($src, "'bootstrap.addSlide', \$itemset_tagid, \$item->title,") === false, 'items module (' . $layout . '): no title is printed as it is stored');
        $lines = linesWith($src, '<?php echo htmlspecialchars((string) $item->title, ENT_QUOTES, \'UTF-8\'); ?>');
        $check(count($lines) === $count, 'items module (' . $layout . '): title lines found');
        foreach ($texts as $text) {
            foreach ($lines as $n => $line) {
                $html = renderLine($line, array('item' => (object) array('title' => $text, 'link' => 'index.php?id=5')));
                $check(activeContent($html) === array() && strpos($html, $enc($text)) !== false && !array_diff(tagNames($html), array('html', 'body', 'div', 'a', 'h3')), 'items module (' . $layout . '): title line ' . $n . ' prints encoded text: ' . json_encode($text));
            }
        }
    }
    $suffix = 'x" onmouseover="alert(1)';
    foreach (array('default', 'news', 'carousel', 'select') as $layout) {
        $vars = array('show_more' => 1, 'params' => new ParamsStub(array('moduleclass_sfx' => $suffix, 'more_blank' => 1)), 'more_link' => 'index.php?a=1&b=2', 'more_css' => 'color:red" onclick="alert(2)', 'more_title' => 'More&hellip; <img src=x onerror=alert(3)>');
        list($html) = renderFile('/modules/mod_flexicontent/tmpl/' . $layout . '/footer.php', $vars);
        $check(activeContent($html) === array() && !array_diff(tagNames($html), array('html', 'body', 'div', 'span', 'a')), 'items module (' . $layout . ' footer): parameters add no handler or element');
        $check(strpos($html, 'class="module_readon' . $enc($suffix) . '" style="' . $enc('color:red" onclick="alert(2)') . '">') !== false && strpos($html, '<span>More&hellip; &lt;img src=x onerror=alert(3)&gt;</span>') !== false, 'items module (' . $layout . ' footer): class suffix, style and title are encoded');
        list($html) = renderFile('/modules/mod_flexicontent/tmpl/' . $layout . '/footer.php', array('more_css' => 'float:right;', 'more_title' => 'Read more...', 'params' => new ParamsStub(array('moduleclass_sfx' => ' wide'))) + $vars);
        $check(preg_match('~<(span|div) class="module_readon wide" style="float:right;">\s*<a class="readon( btn-primary)?" href="index\.php\?a=1&amp;b=2" ><span>Read more\.\.\.</span></a>~', $html) === 1, 'items module (' . $layout . ' footer): ordinary values print as before');
    }
    foreach (array('/modules/mod_flexicontent/tmpl/select.php' => 1, '/modules/mod_flexiadvsearch/tmpl/default.php' => 2) as $file => $count) {
        $lines = linesWith(source($file), "\$params->get('moduleclass_sfx')");
        $check(count($lines) === $count, basename(dirname(dirname($file))) . ': class suffix lines found');
        foreach ($lines as $line) {
            $html = renderLine($line, array('params' => new ParamsStub(array('moduleclass_sfx' => $suffix)), 'module' => (object) array('id' => 7), 'form_name' => 'f', 'form_id' => 'f', 'action' => 'index.php'));
            $check(activeContent($html) === array() && strpos($html, $enc($suffix) . '"') !== false, basename(dirname(dirname($file))) . ': the class suffix stays inside the class attribute');
        }
    }

    // Category image of the items module: the title was encoded by the helper already (double quotes, not apostrophes)
    $lines = linesWith(source('/modules/mod_flexicontent/tmpl_common/category.php'), '$catimage_thumb = \'<img class="catimage_thumb"');
    $check(count($lines) === 1, 'items module: category image code found');
    foreach (array("O'Brien & Sons \"Ltd\"" => 'O&#039;Brien &amp; Sons &quot;Ltd&quot;', "x' onmouseover='alert(1)" => 'x&#039; onmouseover=&#039;alert(1)', 'Plain category' => 'Plain category') as $title => $expected) {
        $html = runCode($lines[0], array('catdata' => (object) array('image' => 'images/c.png', 'title' => flexicontent_html::striptagsandcut($title, 200))), 'catimage_thumb');
        $check($html === '<img class="catimage_thumb" src="images/c.png" alt="' . $expected . '" title="' . $expected . '"/>' && activeContent($html) === array(), 'items module: the category title is one attribute value, encoded once: ' . $title);
    }

    // Tag cloud
    $tag = (object) array('screenreader' => '3 items', 'link' => 'index.php?tag=1', 'size' => 4, 'description' => '', 'name' => '<img src=x onerror=alert(1)> "tag"');
    list($html) = renderFile('/modules/mod_flexitagcloud/tmpl/default.php', array('list' => array($tag), 'params' => new ParamsStub(array('moduleclass_sfx' => $suffix))));
    $check(activeContent($html) === array() && !array_diff(tagNames($html), array('html', 'body', 'div', 'ul', 'li', 'a', 'span')), 'tag cloud: tag name and class suffix add no handler or element');
    $check(strpos($html, '>&lt;img src=x onerror=alert(1)&gt; &quot;tag&quot;</a>') !== false && strpos($html, 'class="mod_flexitagcloud' . $enc($suffix) . '">') !== false, 'tag cloud: tag name and class suffix are encoded');
    $tag->name = 'News';
    list($html) = renderFile('/modules/mod_flexitagcloud/tmpl/default.php', array('list' => array($tag), 'params' => new ParamsStub(array('moduleclass_sfx' => ' wide'))));
    $check(strpos($html, '<ul id="flexicloud" class="mod_flexitagcloud wide">') !== false && strpos($html, '>News</a>') !== false, 'tag cloud: ordinary values print as before');

    // Categories module: image sizes
    $found = preg_match('/\$cat_image_width\s+= \$params->get.*?\$cat_image_height\s+= is_numeric[^\n]*/s', source('/modules/mod_flexicategories/tmpl/default_items.php'), $m) === 1;
    $check($found, 'categories module: image size code found');
    foreach (array(array('24', '32', 24, 32), array('24px;" onload="alert(1)', '32;background:url(x)', '', ''), array('', '', '', ''), array(48, 48.9, 48, 48)) as list($w, $h, $expected_w, $expected_h)) {
        $size_params = new ParamsStub(array('cat_image_width' => $w, 'cat_image_height' => $h));
        $got = array(runCode($found ? $m[0] : '', array('params' => $size_params), 'cat_image_width'), runCode($found ? $m[0] : '', array('params' => $size_params), 'cat_image_height'));
        $check($got === array($expected_w, $expected_h), 'categories module: image sizes ' . json_encode(array($w, $h)) . ' become ' . json_encode(array($expected_w, $expected_h)));
    }

    restore_error_handler();
    $check($php_errors === array(), 'the layouts raise no PHP notice or warning: ' . implode(' | ', array_slice(array_unique($php_errors), 0, 8)));

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
