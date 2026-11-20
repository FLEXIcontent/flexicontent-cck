<?php
/** Exercise the actual layout's option serialization without a Joomla database. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$source = file_get_contents(__DIR__ . '/../plugins/flexicontent_fields/image/tmpl/value_gallery_fancybox.php');
if (!preg_match('/\$display_infobar\s*=.*?\$options_json = [^\n]+;/s', $source, $match)) throw new RuntimeException('Layout option block not found');
$serialize = static function (array $values) use ($match) {
    $PPFX_ = 'gallery_';
    $field = (object) ['parameters' => new class($values) {
        public function __construct(private array $values) {}
        public function get($name, $default = null) { return $this->values[$name] ?? $default; }
    }];
    eval($match[0]);
    return json_decode($options_json, true, 512, JSON_THROW_ON_ERROR);
};
$check = static function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$defaults = $serialize([]);
$check($defaults['toolbar'] === 'auto' && $defaults['keyboard'] === true && $defaults['loop'] === false, 'Defaults changed');
$options = $serialize(['gallery_diplay_toolbar' => 'false', 'gallery_diplay_thumbs_autostart' => 'true', 'gallery_buttons_shown' => [], 'gallery_animation_effect' => 'false', 'gallery_tansition_effect' => 'slide']);
$check($options['toolbar'] === false && $options['thumbs']['autoStart'] === true, 'Boolean settings lost');
$check($options['buttons'] === [] && $options['animationEffect'] === false, 'Disabled controls lost');
$check($options['transitionEffect'] === 'slide', 'Existing XML transition setting ignored');
$options = $serialize(['gallery_buttons_shown' => '', 'gallery_transition_effect' => 'false', 'gallery_loop_mode' => 'true']);
$check($options['buttons'] === [] && $options['transitionEffect'] === false && $options['loop'] === true, 'Empty/boolean settings mishandled');
$options = $serialize(['gallery_buttons_shown' => ['thumbs', 'close'], 'gallery_animation_effect' => "quote'\"</script>"]);
$check($options['buttons'] === ['thumbs', 'close'] && $options['animationEffect'] === "quote'\"</script>", 'Quoted settings lost');
echo "PASS: actual FancyBox options preserve typed booleans, empty/custom buttons, thumbs, legacy transition and quoted values\n";
