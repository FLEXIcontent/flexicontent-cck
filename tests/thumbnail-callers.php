<?php
/** Exercise thumbnail URLs produced by Swiper layouts and the direct autocomplete JSON task. */
namespace Joomla\CMS\Uri {
    final class Uri
    {
        public static $path = '/joomla';
        public static function root($pathOnly = false)
        {
            return $pathOnly ? self::$path : 'https://fixture.invalid' . self::$path . '/';
        }
    }
}

namespace {
    define('_JEXEC', 1);
    define('JPATH_SITE', dirname(__DIR__));
    require JPATH_SITE . '/site/classes/helpers/images.php';
    $key = str_repeat('test-key-123!', 4);
    (new ReflectionProperty(flexicontent_images::class, 'phpthumb_config'))->setValue(null, [
        'high_security_password' => $key, 'high_security_enabled' => true, 'high_security_url_separator' => '&',
    ]);

    function expect($condition, $message)
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    function checkUrl($url, $expected, $root = '/joomla')
    {
        global $key;
        expect(parse_url($url, PHP_URL_PATH) === $root . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php', 'Wrong endpoint: ' . $url);
        $query = parse_url($url, PHP_URL_QUERY);
        [$params, $hash] = array_pad(explode('&hash=', $query, 2), 2, '');
        expect(hash_equals(hash_hmac('sha256', $params, $key), $hash), 'Missing or incorrect thumbnail signature');
        parse_str($params, $actual);
        foreach ($expected as $name => $value) {
            expect(($actual[$name] ?? null) === $value, 'Unexpected ' . $name . ' in ' . $url);
        }
    }

    // Run the thumbnail parts of the shipped layouts in their normal variable scope.
    function layoutBlock($file, $start, $end)
    {
        $source = str_replace("\r\n", "\n", file_get_contents(JPATH_SITE . '/' . $file));
        $from = strpos($source, $start);
        $to = $from === false ? false : strpos($source, $end, $from + strlen($start));
        expect($to !== false, 'Thumbnail layout block missing in ' . $file);
        return substr($source, $from, $to - $from);
    }

    $module = layoutBlock('modules/mod_flexicontent/tmpl/swiper.php', '// BOF thumbnail (one per item)', '// EOF thumbnail');
    $img_path = '/joomla/';
    $mod_default_img_path = 'images/default.jpg';
    $sw_thumb_height = 72;
    $sw_thumb_is_vertical = false;
    $sw_thumb_custom_w = 130;
    $sw_thumbs = [];
    $src = '/joomla/images/A + B & C.jpg';
    $previous = flexicontent_images::phpThumbURL(['src' => $src, 'w' => 600]);
    foreach ([
        ['custom', (object) ['image_rendered' => '<img src="' . $previous . '">'], $src, '130', '0'],
        ['m', (object) ['image' => $previous], $src, '0', '72'],
        ['m', (object) ['image_rendered' => '<img src="images/A%20+%20B%20&amp;%20C.jpg">'], $src, '0', '72'],
        ['m', (object) [], '/joomla/images/default.jpg', '0', '72'],
    ] as [$sw_thumb_size, $item, $expectedSrc, $width, $height]) {
        eval($module);
        $html = array_pop($sw_thumbs);
        foreach (['srcset' => 'webp', 'src' => 'jpg'] as $attr => $format) {
            expect(preg_match('/\s' . $attr . '="([^"]+)"/', $html, $match) === 1, 'Missing ' . $attr);
            checkUrl(html_entity_decode($match[1]), ['src' => $expectedSrc, 'w' => $width, 'h' => $height, 'zc' => '0', 'f' => $format]);
        }
    }
    $item = (object) ['_row' => (object) ['fields' => [(object) ['thumbs_src' => ['medium' => ['/prepared.jpg']]]]]];
    eval($module);
    expect(str_contains(array_pop($sw_thumbs), 'src="/prepared.jpg"'), 'Pre-generated module image changed');

    $field = layoutBlock('plugins/flexicontent_fields/image/tmpl/value_gallery_swiper.php', '// Thumbnail strip image', "\n}");
    $swiper_thumb_size = 'custom';
    $thumb_custom_w = 110;
    $isURL = $thumb_is_vertical = false;
    $thumb_height = 80;
    $alt_encoded = '';
    $srco = 'images/A + B & C.jpg';
    $abs_srcm = '/prepared.jpg';
    $swiper_thumbs = [];
    eval($field);
    checkUrl(html_entity_decode($thumb_src), ['src' => $src, 'w' => '110', 'h' => '0', 'zc' => '0', 'f' => 'jpg']);
    $swiper_thumb_size = 'm';
    eval($field);
    expect($thumb_src === $abs_srcm, 'Pre-generated field image changed');

    // Load the task class without running its Joomla request dispatcher.
    $source = file_get_contents(JPATH_SITE . '/site/tasks/core.php');
    eval(substr($source, strpos($source, 'class FlexicontentTasksCore')));
    $class = new ReflectionClass(FlexicontentTasksCore::class);
    $task = $class->newInstanceWithoutConstructor();
    $makeUrl = $class->getMethod('_acThumbUrl');
    foreach (['', '/joomla'] as $root) {
        \Joomla\CMS\Uri\Uri::$path = $root . '/components/com_flexicontent/tasks';
        foreach ([0, 1] as $crop) {
            $url = $makeUrl->invoke($task, $srco, 60, 50, $crop);
            checkUrl($url, ['src' => $root . '/' . $srco, 'w' => '60', 'h' => '50', 'zc' => $crop ? '1' : null, 'f' => 'jpg'], $root);
        }
        $fromText = $class->getMethod('_extractTextImage')->invoke($task, '<img src="site/assets/images/add.png">');
        checkUrl($makeUrl->invoke($task, $fromText, 60, 50, 1), ['src' => $root . '/site/assets/images/add.png'], $root);
    }
    $remote = 'https://fixture.invalid/remote.jpg';
    expect($makeUrl->invoke($task, $remote, 60, 50, 1) === $remote, 'External autocomplete image changed');
    echo "Swiper module, image field and autocomplete thumbnail URLs passed\n";
}
