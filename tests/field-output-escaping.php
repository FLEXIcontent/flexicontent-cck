<?php
/**
 * Image field links / sizes, subform value layout and the reviews list permission filter.
 * Runs the real layouts and methods with dummy values; no Joomla bootstrap, no database.
 */
namespace Joomla\CMS\Uri {
    final class Uri { public static function root($path = false) { return $path ? '' : 'https://fixture.example/'; } }
}
namespace Joomla\Utilities {
    final class ArrayHelper { public static function toInteger($a) { return array_map('intval', (array) $a); } }
}
namespace {
    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('JPATH_ROOT', dirname(__DIR__));
    define('JPATH_SITE', sys_get_temp_dir());
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

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };

    require JPATH_ROOT . '/site/classes/helpers/security.php';

    final class flexicontent_html {
        public static $frameworks = array();
        public static function getToolTip($title, $desc) { return $title . '::' . $desc; }
        public static function loadFramework($name) { self::$frameworks[] = $name; }
    }

    // The real save-and-display link filter, inside a class that can include the real layouts.
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
        public function unserialize_array($v) { return is_array($v) ? $v : false; }
        public static ' . methodSource(file_get_contents(JPATH_ROOT . '/plugins/flexicontent_fields/image/image.php'), 'safeLinkUrl') . '
        public function render(array $values, $url_target, $thumb_size = 2) {
            static $n = 0;
            $field = (object) array("id" => ++$n, "parameters" => new class { public function get($k, $d = null) { return $d; } });
            $item = (object) array("id" => 5);
            $prop = "display"; $field->{$prop} = array(); $view = "item"; $is_ingroup = $use_ingroup = false; $isSingle = false;
            $orig_urlpath = "images/original"; $thumb_urlpath = "images/thumbs"; $extra_prefix = "";
            $usetitle = $usealt = $usedesc = 1; $usecust1 = $usecust2 = 0; $cust1_label = $cust2_label = "";
            $alt_image_prefix = "Image "; $uselegend = 0; $tooltip_class = ""; $grouptype = -1; $useogp = 0; $ogplimit = 0;
            $linkto_item = 0; $showtitle = $showdesc = 0; $pretext = $posttext = $separatorf = $opentag = $closetag = "";
            include JPATH_ROOT . "/plugins/flexicontent_fields/image/tmpl/value_link_to_url.php";
            return is_array($field->{$prop}) ? implode("", $field->{$prop}) : (string) $field->{$prop};
        }
    }');

    // 1. Link filter: http(s) and site-relative only, in every spelling
    $links = array(
        'https://example.com/a b'               => 'https://example.com/a%20b',
        '/index.php?x=1&amp;y=2'                => '/index.php?x=1&y=2',
        'index.php?option=com_content'          => 'index.php?option=com_content',
        'javascript:alert(1)'                   => '',
        'JaVaScRiPt:alert(1)'                   => '',
        "java\tscript:alert(1)"                 => '',
        'javascript&colon;alert(1)'             => '',
        'javascript&amp;colon;alert(1)'         => '',
        'javascript&amp;amp;colon;alert(1)'     => '',
        'javascript:1/alert(1)//'               => '',
        'data:text/html,x'                      => '',
        'vbscript:x'                            => '',
    );
    foreach ($links as $in => $expected) {
        $out = plgFlexicontent_fieldsImage::safeLinkUrl($in);
        $check($out === $expected, 'link filter: ' . json_encode($in) . ' gave ' . json_encode($out));
    }
    $check(plgFlexicontent_fieldsImage::safeLinkUrl(array('x')) === '', 'link filter refuses non-strings');
    $quoted = plgFlexicontent_fieldsImage::safeLinkUrl("https://example.com/'+alert(document.domain)+'");
    $check($quoted !== '' && strpbrk($quoted, "'\"<>") === false, 'link filter percent-encodes quotes');

    // 2. Link layout: stored payloads never become script or extra attributes, in each of the three link modes
    $plugin = new plgFlexicontent_fieldsImage;
    $payloads = array(
        "https://example.com/'+alert(document.domain)+'",
        'javascript:alert(document.domain)',
        'javascript&amp;colon;alert(document.domain)',
        'https://example.com/"><script>alert(1)</script>',
        'https://example.com/</script><script>alert(1)</script>',
    );
    $json_string = '("(?:[^"\\\\]|\\\\.)*")';
    $fancybox_js = '/^jQuery\.fancybox\.open\(\[\{ type: "iframe", src: ' . $json_string . ', topRatio: 0\.9, leftRatio: 0\.9, title: ' . $json_string . ' \}\], \{ padding : 0\}\);$/';
    $multibox_js = '~<script>document\.write\(' . $json_string . '\+\(jQuery\(window\)\.width\(\)-150\)\+",height:"\+\(jQuery\(window\)\.height\(\)-150\)\+' . $json_string . '\)</script>~';
    $normal = 'https://example.com/page?a=1&b=2';
    $payloads[] = $normal;
    foreach (array('_blank', 'fancybox', 'multibox') as $target) {
        foreach ($payloads as $payload) {
            $html = $plugin->render(array(array('originalname' => 'photo.png', 'urllink' => $payload, 'title' => "T'\"<b>", 'desc' => 'D')), $target);
            $safe = plgFlexicontent_fieldsImage::safeLinkUrl($payload);
            $label = $target . ' / ' . $payload;
            $check(stripos($html, 'javascript:') === false && stripos($html, 'javascript&colon;') === false, 'no javascript: URL: ' . $label);
            $check(strpos($html, 'alert(1)</script>') === false && strpos($html, '<script>alert') === false, 'no injected script element: ' . $label);
            $check(strpos($html, "'+alert(") === false, 'no JavaScript string break-out: ' . $label);
            $check(strpos($html, '<b>') === false, 'title and description are encoded: ' . $label);
            if ($safe === '') {
                $check(strpos($html, 'onclick=') === false && strpos($html, 'document.write') === false && strpos($html, '<a ') === false, 'refused link renders the image only: ' . $label);
            } elseif ($target === 'fancybox') {
                $ok = preg_match('/onclick="([^"]*)"/', $html, $m) === 1
                    && preg_match($fancybox_js, html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), $parts) === 1
                    && json_decode($parts[1]) === $safe && is_string(json_decode($parts[2]));
                $check($ok, 'fancybox onclick holds the link as one JSON string literal: ' . $label);
            } elseif ($target === 'multibox') {
                $ok = preg_match($multibox_js, $html, $m) === 1 && json_decode($m[2]) === '">'
                    && strpos((string) json_decode($m[1]), ' href="' . htmlspecialchars($safe, ENT_QUOTES, 'UTF-8') . '" id="mb') !== false;
                $check($ok, 'multibox opening tag is JSON string literals around the window size: ' . $label);
                $ok = preg_match('~<script>document\.write\(' . $json_string . '\)</script>~', $html, $m) === 1 && json_decode($m[1]) === '</a>' && strpos($m[1], '<') === false;
                $check($ok, 'multibox closing tag is a JSON string literal without markup characters: ' . $label);
            } else {
                $check(strpos($html, '<a href="' . htmlspecialchars($safe, ENT_QUOTES, 'UTF-8') . '" target="_blank">') !== false, 'plain link is attribute-escaped: ' . $label);
            }
        }
    }
    $html = $plugin->render(array(array('originalname' => 'photo.png', 'urllink' => 'https://example.com/x')), '_blank" onmouseover="alert(1)');
    $check(strpos($html, 'onmouseover="alert') === false, 'link target is attribute-escaped');

    // 3. Image tag: posted sizes are integers, markup in a stored name is refused
    $html = $plugin->render(array(array('originalname' => 'photo.png', 'size_w_m' => '400" data-w="1', 'size_h_m' => '300" onload="alert(1)', 'size_w_s' => '120" data-s="2')), '_blank');
    $check(strpos($html, 'width="400" height="300"') !== false, 'width and height are integers');
    $check(strpos($html, 'data-w=') === false && strpos($html, 'onload=') === false && strpos($html, 'data-s=') === false, 'size values add no attributes');
    $check(preg_match('/srcset="[^"]* 400w, [^"]* 120w"/', $html) === 1, 'srcset descriptors are integers');
    $html = $plugin->render(array(array('originalname' => 'https://img.example.invalid/a.png" data-src="1')), '_blank');
    $check(strpos($html, 'data-src=') === false && strpos($html, '<img') === false, 'image name with markup is not rendered');
    $html = $plugin->render(array(array('originalname' => 'a&b.png')), '_blank');
    $check(strpos($html, 'src="/images/thumbs/m_a&amp;b.png"') !== false, 'image source is attribute-escaped');

    // 4. Subform layout: keys and values at every depth are text
    $render_subform = static function ($values) {
        $field = new stdClass; $prop = 'display'; $is_ingroup = false; $multiple = true; $itemprop = ''; $pretext = $posttext = '';
        include JPATH_ROOT . '/plugins/flexicontent_fields/subform/tmpl/value_default.php';
        return implode('', $field->{$prop});
    };
    $html = $render_subform(array(array(
        'note' => array('<u>key</u>' => '<b>value</b>', '<img src=x onerror=alert(1)>' => '1'),
        '<script>alert(2)</script>' => 'plain',
        'nested' => array('list' => array('<i>deep</i>' => array('<svg onload=alert(3)>'))),
    )));
    $check(preg_match('/<(?!\/?b>|hr>|br>)/', $html) === 0, 'subform layout prints no markup from keys or values');
    $check(strpos($html, '&lt;u&gt;key&lt;/u&gt;') !== false && strpos($html, '&lt;b&gt;value&lt;/b&gt;') !== false, 'subform keys and values are shown as text');
    $check(strpos($html, '&lt;svg onload=alert(3)&gt;') !== false, 'subform nested arrays are shown as text');
    $html = $render_subform(array(array('name' => 'Plain value', 'count' => 3)));
    $check($html === '<hr><br><b>name</b>: Plain value<br><b>count</b>: 3<br>', 'subform plain values are unchanged');

    // 5. Reviews list model: without the reviews-manager right nothing is changeable, in any client
    eval('use Joomla\Utilities\ArrayHelper;
        class FCModelAdminList { public $canManage = false; public $parentCalls = 0;
        public function filterByPermission($cid, $action) { ++$this->parentCalls; return array(); } }
        final class ReviewsFixture extends FCModelAdminList { public '
        . methodSource(file_get_contents(JPATH_ROOT . '/admin/models/reviews.php'), 'filterByPermission') . ' }');
    $model = new ReviewsFixture;
    $check($model->filterByPermission(array('11', 12), 'core.delete') === array(11, 12) && $model->parentCalls === 0, 'reviews: non-managers may delete no review');
    $check($model->filterByPermission(array(11), 1) === array(11) && $model->parentCalls === 0, 'reviews: non-managers may publish no review');
    $model->canManage = true;
    $check($model->filterByPermission(array(11, 12), 'core.delete') === array() && $model->parentCalls === 1, 'reviews: managers keep the per-record checks');

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
