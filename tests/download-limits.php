<?php
/** Download limits: group limit resolution, settings inheritance, and wiring (no database needed). */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('_JEXEC', 1);

eval('namespace Joomla\Registry { class Registry { private $d; function __construct($d = []) { $this->d = $d; } function get($k, $def = null) { return $this->d[$k] ?? $def; } } }');
eval('namespace Joomla\CMS\Component { class ComponentHelper { static $params; static function getParams() { return self::$params; } } }');

eval('namespace Joomla\CMS\Language { class Text { static function sprintf($k, ...$a) { return vsprintf(["FLEXI_DL_LIMIT_FMT_DAYS" => "%1\$d d %2\$d h", "FLEXI_DL_LIMIT_FMT_HOURS" => "%1\$d h %2\$d min", "FLEXI_DL_LIMIT_FMT_MINUTES" => "%1\$d min", "FLEXI_DL_LIMIT_WIN_MINUTES" => "%1\$d min", "FLEXI_DL_LIMIT_WIN_HOURS" => "%1\$d h", "FLEXI_DL_LIMIT_WIN_DAYS" => "%1\$d d"][$k], $a); } } }');

require __DIR__ . '/../site/classes/helpers/downloadlimit.php';

use Joomla\Registry\Registry;
use Joomla\CMS\Component\ComponentHelper;

function check($ok, $what) { if (!$ok) { throw new RuntimeException('FAILED: ' . $what); } }

class FakeUser {
    function __construct(public array $groups, public bool $admin = false) {}
    function getAuthorisedGroups() { return $this->groups; }
    function authorise($a) { return $a === 'core.admin' && $this->admin; }
}

$groups = [
    ['group_id' => '1', 'limit' => '5'],    // Public
    ['group_id' => '2', 'limit' => '10'],   // Registered
    ['group_id' => '10', 'limit' => '100'], // Subscription1 (child of Registered)
    ['group_id' => '11', 'limit' => '-1'],  // Subscription2
    ['group_id' => '12', 'limit' => ''],    // ignored: empty
];
$settings = ['groups' => flexicontent_downloadlimit::parseGroups($groups)];
check($settings['groups'] === [1 => 5, 2 => 10, 10 => 100, 11 => -1], 'groups are parsed, empty rows skipped');
check(flexicontent_downloadlimit::parseGroups((object) ['a' => (object) ['group_id' => 3, 'limit' => 7]]) === [3 => 7], 'stdClass subform rows (as stored by Joomla) are parsed');
check(flexicontent_downloadlimit::parseGroups(null) === [], 'no rows');

check(flexicontent_downloadlimit::getUserLimit($settings, new FakeUser([1])) === 5, 'guest: Public limit');
check(flexicontent_downloadlimit::getUserLimit($settings, new FakeUser([1, 2])) === 10, 'registered: inherits Public, highest wins');
check(flexicontent_downloadlimit::getUserLimit($settings, new FakeUser([1, 2, 10])) === 100, 'subscription 1');
check(flexicontent_downloadlimit::getUserLimit($settings, new FakeUser([1, 2, 11])) === -1, 'subscription 2: unlimited beats everything');
check(flexicontent_downloadlimit::getUserLimit($settings, new FakeUser([1], true)) === -1, 'super users are never limited');
check(flexicontent_downloadlimit::getUserLimit($settings, new FakeUser([7])) === -1, 'no matching group: no limit');
check(flexicontent_downloadlimit::getUserLimit(['groups' => [1 => 0]], new FakeUser([1])) === 0, 'limit 0 blocks all downloads');

// Field settings override the component settings
ComponentHelper::$params = new Registry(['dl_limit_enable' => 0, 'dl_limit_window' => 24]);
check(flexicontent_downloadlimit::getSettings(new Registry([])) === null, 'component switch off: field inherits "no limits"');
ComponentHelper::$params = new Registry(['dl_limit_enable' => 1, 'dl_limit_window' => 48, 'dl_limit_count_mode' => 1, 'dl_limit_groups' => $groups]);
$s = flexicontent_downloadlimit::getSettings(new Registry([]));
check($s && $s['window'] === 48 * 3600 && $s['mode'] === 1 && $s['groups'][2] === 10, 'field inherits component settings');
check(flexicontent_downloadlimit::getSettings(new Registry(['dl_limit_override' => 2])) === null, 'field opts out of limits');
$s = flexicontent_downloadlimit::getSettings(new Registry(['dl_limit_override' => 1, 'dl_limit_window' => 5, 'dl_limit_window_unit' => 1, 'dl_limit_groups' => [['group_id' => 1, 'limit' => 2]]]));
check($s && $s['window'] === 300 && $s['mode'] === 0 && $s['groups'] === [1 => 2], 'field uses its own settings, even if the component switch is on');
ComponentHelper::$params = new Registry(['dl_limit_enable' => 0]);
check(flexicontent_downloadlimit::getSettings(new Registry(['dl_limit_override' => 1])) !== null, 'field own settings work with the component switch off');

// Placeholders in the limit-reached text
check(flexicontent_downloadlimit::formatDuration(30) === '1 min', 'under a minute rounds up to 1 min');
check(flexicontent_downloadlimit::formatDuration(3 * 3600 + 5 * 60) === '3 h 5 min', 'hours and minutes');
check(flexicontent_downloadlimit::formatDuration(26 * 3600) === '1 d 2 h', 'days and hours');
$text = flexicontent_downloadlimit::replacePlaceholders('Retry in {reset_in} / {reset_full_in}, {used}/{limit} per {window} ({reset_seconds}s)',
    ['reset_in' => 600, 'reset_full_in' => 7200, 'limit' => 5, 'used' => 5, 'window' => 5 * 60]);
check($text === 'Retry in 10 min / 2 h 0 min, 5/5 per 5 min (600s)', 'placeholders are replaced: ' . $text);

check(flexicontent_downloadlimit::formatWindow(86400) === '1 d' && flexicontent_downloadlimit::formatWindow(24 * 3600 + 60) === '1441 min' && flexicontent_downloadlimit::formatWindow(7200) === '2 h', 'period text');

// Wiring: the check happens before the hit counter, and only for real downloads
$controller = file_get_contents(__DIR__ . '/../site/controller.php');
$check = strpos($controller, 'flexicontent_downloadlimit::consume(');
$hit   = strpos($controller, '$filetable->hit($file_id)');
check($check !== false && $hit !== false && $check < $hit, 'quota is checked before the file is served');
check(strpos($controller, "if (\$method === 'download')") !== false, 'viewing is not counted');
foreach (['file/file.xml', 'mediafile/mediafile.xml'] as $xml) {
    check(strpos(file_get_contents(__DIR__ . '/../plugins/flexicontent_fields/' . $xml), 'name="dl_limit_override"') !== false, "$xml has the override setting");
}
restore_error_handler();
echo "download limit checks passed\n";
