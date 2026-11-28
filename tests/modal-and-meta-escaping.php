<?php
/**
 * Selection modal onclick handlers, the edit link helper, the comments field markup and the Open Graph meta tags.
 * Runs the real template lines and methods with dummy values; no Joomla bootstrap, no database, no browser.
 */
namespace Joomla\CMS\Language {
    final class Text {
        public static $strings = array();
        public static function _($key, $jsSafe = false) { $s = isset(self::$strings[$key]) ? self::$strings[$key] : $key; return $jsSafe ? addslashes($s) : $s; }
    }
}
namespace Joomla\CMS\HTML {
    final class HTMLHelper { public static function tooltipText($title = '', $content = '', $translate = true, $escape = true) { return htmlspecialchars($title . '::' . $content, ENT_QUOTES, 'UTF-8'); } }
}
namespace Joomla\String {
    final class StringHelper {
        public static function strlen($s) { return mb_strlen($s, 'UTF-8'); }
        public static function substr($s, $start, $length = null) { return mb_substr($s, $start, $length, 'UTF-8'); }
        public static function strrpos($s, $needle) { return mb_strrpos($s, $needle, 0, 'UTF-8'); }
    }
}
namespace Joomla\Utilities {
    // Same output as the Joomla method: values are not encoded
    final class ArrayHelper { public static function toString(array $a) { $o = array(); foreach ($a as $k => $v) $o[] = $k . '="' . $v . '"'; return implode(' ', $o); } }
}
namespace Joomla\CMS\Uri {
    final class Uri {
        public static $current = '';
        public static function root($path = false) { return $path ? '' : 'https://fixture.example/'; }
        public static function getInstance() { return new class { public function __toString() { return Uri::$current; } }; }
    }
}
namespace Joomla\CMS {
    final class Factory { public static $app; public static function getApplication() { return self::$app; } }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_ROOT', dirname(__DIR__));
    define('JPATH_SITE', JPATH_ROOT);

    // The real thumbnail URL helper, with signing on, so the file modal is checked with the URLs it prints
    require JPATH_ROOT . '/site/classes/helpers/images.php';
    (new ReflectionProperty(flexicontent_images::class, 'phpthumb_config'))->setValue(null, array(
        'high_security_password' => str_repeat('test-key-123!', 4), 'high_security_enabled' => true, 'high_security_url_separator' => '&',
    ));

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
    // What a browser hands to the script engine: the attribute value with its character references decoded
    function attributeScript($html, $name = 'onclick') {
        return preg_match('/\s' . $name . '="([^"]*)"/', $html, $m) === 1 ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }
    // Runs one template line as PHP, with $this and the given variables
    function renderLine($line, array $vars, $self, $before = '') {
        $run = function () use ($line, $vars, $before) { extract($vars); ob_start(); eval($before . ' ?>' . $line); return ob_get_clean(); };
        return $run->call($self);
    }

    final class ViewStub {
        public $filter_cats = 0; public $tooltip_class = "hasTooltip"; public $assign_mode = 0; public $fieldid = 33; public $thumb_w = 120; public $thumb_h = 90;
        public function __construct($assign_mode = 0) { $this->assign_mode = $assign_mode; }
    }
    final class ParamsStub {
        private $data;
        public function __construct(array $data = array()) { $this->data = $data; }
        public function get($key, $default = null) { return array_key_exists($key, $this->data) ? $this->data[$key] : $default; }
    }
    final class DocumentStub {
        public $tags = array();
        public function addCustomTag($html) { $this->tags[] = trim($html); return $this; }
        public function getWebAssetManager() { return new class { public function addInlineStyle($css) {} }; }
    }
    final class ApplicationStub {
        public $document; public $sitename = '';
        public function __construct() { $this->document = new DocumentStub; }
        public function getDocument() { return $this->document; }
        public function getIdentity() { return (object) array('id' => 7); }
        public function getLanguage() { return new class { public function getTag() { return 'en-GB'; } }; }
        public function get($key, $default = null) { return $key === 'sitename' ? $this->sitename : $default; }
    }

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };

    \Joomla\CMS\Factory::$app = new ApplicationStub;
    $json_string = '("(?:[^"\\\\]|\\\\.)*")';
    $line_separator = "\xE2\x80\xA8";

    // Stored names: the quote / backslash and character reference break-outs, markup, and ordinary text that must arrive unchanged
    $names = array(
        "x\\');alert(document.domain);//",
        "x\\'+alert(document.domain)+\\'",
        '&#39;+alert(document.domain)+&#39;',
        '&apos;);alert(document.domain);//',
        '");alert(document.domain);//',
        '</script><script>alert(1)</script>',
        '" onmouseover="alert(1)',
        'AT&amp;T &quot;literal entities&quot;',
        "Tom & Jerry's \"Best\" café – 日本語 Ελληνικά",
        "back\\slash, new\nline, separator" . $line_separator . 'end\\',
        'Plain title',
    );
    // The value as the handler receives it, and no character in the literal that HTML decoding could change
    $literalHolds = static function ($literal, $expected) {
        return json_decode($literal) === $expected && strpbrk($literal, "&'<>") === false;
    };

    // 1. Item, category and tag selection modals: the title is one JavaScript string literal inside the onclick attribute
    $view = new ViewStub;

    $item_tmpl   = file_get_contents(JPATH_ROOT . '/admin/views/itemelement/tmpl/default.php');
    $item_lines  = linesWith($item_tmpl, 'window.parent.fcSelectItem(');
    $item_before = implode(' ', linesWith($item_tmpl, '$title_js = '));
    $check(count($item_lines) === 3, 'item modal: three selection handlers found');
    $item_js = '/^window\.parent\.fcSelectItem\(\'12\', \'7\', ' . $json_string . '(, this, 1)?\);$/D';

    $cat_tmpl  = file_get_contents(JPATH_ROOT . '/admin/views/fccategoryelement/tmpl/default.php');
    $cat_lines = linesWith($cat_tmpl, 'window.parent.fcSelectCategory(');
    $check(count($cat_lines) === 1, 'category modal: selection handler found');
    $cat_js = '/^window\.parent\.fcSelectCategory\(\'12\', ' . $json_string . '\);$/D';

    $tag_tmpl  = file_get_contents(JPATH_ROOT . '/admin/views/tagelement/tmpl/default.php');
    $tag_lines = linesWith($tag_tmpl, 'window.parent.qfSelectTag(');
    $check(count($tag_lines) === 1, 'tag modal: selection handler found');
    $tag_js = '/^window\.parent\.qfSelectTag\(\'12\', ' . $json_string . '\);$/D';

    foreach ($names as $name) {
        $label = json_encode($name);
        $row = (object) array('id' => 12, 'catid' => 7, 'title' => $name, 'name' => $name);

        foreach ($item_lines as $n => $line) {
            $html = renderLine($line, array('row' => $row), $view, $item_before);
            $ok = preg_match($item_js, (string) attributeScript($html), $m) === 1 && $literalHolds($m[1], $name);
            $check($ok, 'item modal handler ' . $n . ' passes the title as one string literal: ' . $label);
            $check(preg_match('/^\s*(<a [^<>]*)?onclick="[^"<>]*">\s*$/', $html) === 1, 'item modal handler ' . $n . ' adds no markup or attribute: ' . $label);
        }

        $html = renderLine($cat_lines[0], array('row' => $row, 'pcpath' => 'Parent'), $view);
        $ok = preg_match($cat_js, (string) attributeScript($html), $m) === 1 && $literalHolds($m[1], $name);
        $check($ok, 'category modal handler passes the title as one string literal: ' . $label);
        $check(preg_match('/^\s*<a style="cursor: pointer;" href="javascript:;" onclick="[^"<>]*" class="hasTooltip" title="[^"<>]*" >\s*$/', $html) === 1, 'category modal handler adds no markup or attribute: ' . $label);

        $html = renderLine($tag_lines[0], array('row' => $row), $view);
        $ok = preg_match($tag_js, (string) attributeScript($html), $m) === 1 && $literalHolds($m[1], $name);
        $check($ok, 'tag modal handler passes the name as one string literal: ' . $label);
        $check(preg_match('/^\s*<a style="cursor:pointer" onclick="[^"<>]*">\s*$/', $html) === 1, 'tag modal handler adds no markup or attribute: ' . $label);
    }

    // 2. Edit link helper: the caller's JavaScript is encoded for the attribute, handlers arrive unchanged
    eval('use Joomla\String\StringHelper; use Joomla\Utilities\ArrayHelper;
        final class EditLinkFixture { static $title_propname = "title"; static $translateable_props = array(); static $name = "record";
        public static ' . methodSource(file_get_contents(JPATH_ROOT . '/admin/helpers/html/fcbase.php'), 'edit_link') . ' }');
    $record = (object) array('id' => 5, 'title' => 'Record', 'checked_out' => 0);

    $handler = 'if (a && b < 2 || c > "3") { say(\'x;y\', "p;q"); } return f(";")';
    $html = EditLinkFixture::edit_link($record, 0, true, array('onclick' => $handler));
    $check(attributeScript($html) === $handler, 'edit link: a handler with quotes, ampersands and comparisons arrives unchanged');
    $check(preg_match('/ onclick="[^"<>]*">/', $html) === 1, 'edit link: the handler cannot end the attribute or the tag');
    $html = EditLinkFixture::edit_link($record, 0, true, array('onclick' => 'x" onmouseover="alert(1)'));
    $check(strpos($html, 'onmouseover="') === false && attributeScript($html) === 'x" onmouseover="alert(1)', 'edit link: a double quote stays inside the handler');

    // The dashboard and search lists end their handler with a stray quote, the handler must still be complete JavaScript
    \Joomla\CMS\Language\Text::$strings['FLEXI_EDIT'] = 'Edit "it\'s"';
    $expected = 'var url = this.getAttribute(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'Edit \\"it\\\'s\\"\', loadFunc: fc_edit_fcitem_modal_load}); return false;';
    $found = 0;
    foreach (array('/admin/views/flexicontent/tmpl/default.php', '/admin/views/search/tmpl/default.php') as $file) {
        foreach (linesWith(file_get_contents(JPATH_ROOT . $file), "'onclick'  => 'var url = this.getAttribute(") as $line) {
            ++$found;
            $config = eval('return array(' . $line . ');');
            $html = EditLinkFixture::edit_link($record, 0, true, $config + array('ctrl' => 'items', 'view' => 'item'));
            $check(attributeScript($html) === $expected, 'edit link: modal edit handler of ' . $file . ' is unchanged and complete');
        }
    }
    $check($found > 0, 'edit link: modal edit handlers of the dashboard and search lists found');
    \Joomla\CMS\Language\Text::$strings = array();

    // 3. File selection modal: the assign link built by the template, printed by the edit link helper
    $fm_tmpl = file_get_contents(JPATH_ROOT . '/admin/views/filemanager/tmpl/default.php');
    $found = preg_match('/\$fc_js_flags = JSON_HEX_TAG.*?\$file_preview_js \. "\);";/s', $fm_tmpl, $m) === 1;
    $check($found, 'file modal: assign link code found');
    $assign_block  = $found ? $m[0] : '';
    $preview_lines = linesWith($fm_tmpl, '$file_preview = !in_array($ext, $imagesExt)');
    $caller_lines  = linesWith($fm_tmpl, "'onclick' => 'if (this.classList.contains(");
    $check(count($preview_lines) === 1 && count($caller_lines) === 1, 'file modal: preview URL and edit link call found');
    $assign = eval('use Joomla\CMS\Uri\Uri; return function ($row, $i) {
        $imagesExt = array("png", "jpg"); $ext = "jpg"; $file_format = "jpeg"; $file_src = "media/" . $row->filename; $file_inroot = true;
        ' . implode(' ', $preview_lines) . ' ' . $assign_block . '
        $config = array(' . implode(' ', $caller_lines) . ');
        return array($config["onclick"], $file_preview);
    };');

    $assign_js = '/^if \(this\.classList\.contains\(\'striketext\'\)\) return; window\.parent\.fcfield_assignImage33\(fcfiles_targetid, ' . $json_string . ', ' . $json_string . ', fcfiles_keep_modal, ' . $json_string . '\); document\.getElementById\(\'file5\'\)\.className=\'striketext\';$/D';
    $insert_js = '/^if \(this\.classList\.contains\(\'striketext\'\)\) return; fc_fileselement_assign_file\(fcfiles_targetid, _file_data\[\'3\'\], ' . $json_string . '\);$/D';
    $anchor    = '/^\s*<a href="javascript:;" data-href="[^"<>]*" id="file5" class="fc_set_file_assignment" onclick="[^"<>]*">/';
    $attribs   = array('id' => 'file5', 'class' => 'fc_set_file_assignment');

    foreach ($names as $name) {
        foreach (array($name . '.jpg', '') as $original) {
            $label = json_encode($name) . ($original === '' ? ' (no original name)' : '');
            $row = (object) array('id' => 5, 'title' => 'File', 'checked_out' => 0, 'filename' => 'stored ' . $name . '.jpg', 'filename_original' => $original);

            list($onclick, $preview) = $assign->call(new ViewStub(1), $row, 3);
            $html = EditLinkFixture::edit_link($row, 3, true, array('onclick' => $onclick, 'attribs' => $attribs));
            $ok = preg_match($assign_js, (string) attributeScript($html), $m) === 1
                && $literalHolds($m[1], $row->filename)
                && $literalHolds($m[2], str_replace('&amp;', '&', $preview)) && strpos($preview, '&amp;w=120&amp;h=90') !== false
                && $literalHolds($m[3], $original !== '' ? $original : $row->filename);
            $check($ok, 'file modal: image assign handler passes the names and preview URL as string literals: ' . $label);
            $check(preg_match($anchor, $html) === 1, 'file modal: image assign link adds no markup or attribute: ' . $label);

            list($onclick, $preview) = $assign->call(new ViewStub(0), $row, 3);
            $html = EditLinkFixture::edit_link($row, 3, true, array('onclick' => $onclick, 'attribs' => $attribs));
            $ok = preg_match($insert_js, (string) attributeScript($html), $m) === 1 && $literalHolds($m[1], str_replace('&amp;', '&', $preview));
            $check($ok && preg_match($anchor, $html) === 1, 'file modal: file insert handler is unchanged: ' . $label);
        }
    }

    // 4. Comments field: the request URL cannot leave the data-href attribute (Facebook) or the script comment (Disqus)
    eval('final class CommentsFixture { static $field_types = array("comments"); static $css_added = array();
        public ' . methodSource(file_get_contents(JPATH_ROOT . '/plugins/flexicontent_fields/comments/comments.php'), 'onDisplayFieldValue') . ' }');
    $comments = new CommentsFixture;
    $item = (object) array('id' => 5, 'catid' => 9);

    $_SERVER['SERVER_NAME'] = 'fixture.example';
    $requests = array(
        "/blog/12'onmouseover='alert(document.domain)/item",
        "/blog/item?a=1&b=2&copy=3'style='x\"><svg onload=alert(1)>",
        '/blog/item?a=1&b=2',
        '/blog/' . rawurlencode('日本語') . '/item',
    );
    foreach ($requests as $request) {
        $_SERVER['REQUEST_URI'] = $request;
        $field = (object) array('id' => 3, 'name' => 'comments', 'field_type' => 'comments', 'parameters' => new ParamsStub(array('comment_api' => 'facebook')));
        $comments->onDisplayFieldValue($field, $item);
        $ok = preg_match('~^<div id=\'fb-root\'></div><div class=\'fb-comments\' data-href=\'([^\'<>]*)\'\s*></div>$~D', $field->display, $m) === 1
            && html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') === 'http://fixture.example' . $request;
        $check($ok, 'comments (Facebook): data-href holds the page URL and nothing else: ' . $request);
    }

    $page = 'https://fixture.example/item?x=*/alert(document.domain);/*&y=</script><script>alert(2)</script>';
    \Joomla\CMS\Uri\Uri::$current = $page;
    \Joomla\CMS\Factory::$app->document->tags = array();
    $field = (object) array('id' => 3, 'name' => 'comments', 'field_type' => 'comments', 'parameters' => new ParamsStub(array('comment_api' => 'disqus', 'pforum_shortname' => 'fixture')));
    $comments->onDisplayFieldValue($field, $item);
    $tag = implode('', \Joomla\CMS\Factory::$app->document->tags);
    $check(substr_count($tag, '<script') === 1 && substr_count($tag, '</script') === 1, 'comments (Disqus): the page URL adds no script element');
    $code = preg_replace('~/\*.*?\*/~s', '', $tag);
    $check(strpos($code, 'alert(') === false && strpos($code, 'this.page.url') === false, 'comments (Disqus): the page URL stays inside the script comment');
    $ok = preg_match('~this\.page\.url = ' . $json_string . ';~', $tag, $m) === 1 && $literalHolds($m[1], $page);
    $check($ok, 'comments (Disqus): the page URL is one string literal');
    $check(strpos($code, "s.src = 'https://fixture.disqus.com/embed.js';") !== false, 'comments (Disqus): the embed script is still loaded');

    // 5. Open Graph tags of the core text field and the toolbar field: values cannot leave the content attribute
    eval('use Joomla\String\StringHelper;
        final class flexicontent_html { public static ' . methodSource(file_get_contents(JPATH_ROOT . '/site/classes/helpers/html.php'), 'striptagsandcut') . ' }');
    $metaContent = static function ($tags, $property) {
        $found = null;
        foreach ($tags as $tag) {
            if (strpos($tag, 'property="' . $property . '"') === false) continue;
            if ($found !== null || preg_match('~^<meta property="' . $property . '" content="([^"<>]*)" />$~D', $tag, $m) !== 1) return false;
            $found = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $found;
    };

    $core = file_get_contents(JPATH_ROOT . '/plugins/flexicontent_fields/core/core.php');
    $found = preg_match('/\$item->metadesc\s*\?\s*\$document->addCustomTag\(.*?\)\s*:\s*\$document->addCustomTag\(.*?\);/s', $core, $m) === 1;
    $check($found, 'core text field: og:description code found');
    $core_ogp = eval('return function ($item, $text) {
        $document = new DocumentStub; $field = new stdClass; $prop = "display"; $field->{$prop} = $text; $ogpmaxlen = 300;
        ' . ($found ? $m[0] : '') . '
        return $document->tags;
    };');

    $toolbar = file_get_contents(JPATH_ROOT . '/plugins/flexicontent_fields/toolbar/toolbar.php');
    $from = strpos($toolbar, '// OPEN GRAPH: site name'); $to = strpos($toolbar, '// OPEN GRAPH: type');
    $check($from !== false && $to > $from, 'toolbar field: Open Graph code found');
    $toolbar_ogp = eval('return function ($item) {
        $document = new DocumentStub; $view = "item";
        $field = (object) array("parameters" => new ParamsStub(array("add_og_site_name" => 1, "add_og_title" => 1, "add_og_descr" => 1)));
        ' . substr($toolbar, (int) $from, $to - $from) . '
        return $document->tags;
    };');

    $descriptions = array(
        'Harmless summary" data-test="v38',
        'Summary"><script>alert(1)</script>',
        'Summary" http-equiv="refresh" content="0;url=https://attacker.example/',
        "Tom & Jerry's summary: 1 > 0, café – 日本語",
        'AT&amp;T as typed',
        'Plain summary',
    );
    foreach ($descriptions as $text) {
        $item = (object) array('title' => $text, 'metadesc' => $text, 'text' => '<p>Body</p>');
        \Joomla\CMS\Factory::$app->sitename = $text;

        $tags = $core_ogp($item, '<p>Body</p>');
        $check(count($tags) === 1 && $metaContent($tags, 'og:description') === $text, 'core text field: og:description holds the meta description and nothing else: ' . $text);

        $tags = $toolbar_ogp($item);
        $check(count($tags) === 3 && $metaContent($tags, 'og:description') === $text, 'toolbar field: og:description holds the meta description and nothing else: ' . $text);
        $check($metaContent($tags, 'og:site_name') === $text, 'toolbar field: og:site_name holds the site name and nothing else: ' . $text);
        $check(is_string($metaContent($tags, 'og:title')), 'toolbar field: og:title stays inside its attribute: ' . $text);
    }

    // Without a meta description the text is used, already encoded by the helper
    $item = (object) array('title' => 'Title', 'metadesc' => '', 'text' => '<p>Body "quoted" & <b>bold</b></p>');
    $tags = $core_ogp($item, $item->text);
    $check(trim((string) $metaContent($tags, 'og:description')) === 'Body "quoted" & bold', 'core text field: og:description falls back to the text');
    $tags = $toolbar_ogp($item);
    $check(trim((string) $metaContent($tags, 'og:description')) === 'Body "quoted" & bold', 'toolbar field: og:description falls back to the text');

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
