<?php
/** Render the actual manager controls without booting Joomla or a database. */
namespace Joomla\CMS\HTML {
    class HTMLHelper {
        public static function _(...$arguments) { return ''; }
        public static function tooltipText(...$arguments) { return 'Check in'; }
    }
}
namespace Joomla\CMS\Language {
    class Text {
        public static function _($text) { return $text; }
        public static function sprintf($text, ...$arguments) { return $text; }
    }
}
namespace {
    define('_JEXEC', 1);
    define('FLEXI_J40GE', true);
    require __DIR__ . '/../admin/helpers/html/fcbase.php';
    class CheckinFixture extends \JHtmlFcbase {}
    $user = (object) ['id' => 10];
    foreach ([[0, true], [10, true], [20, true], [20, false]] as [$owner, $allowed]) {
        $row = (object) ['id' => 45, 'checked_out' => $owner, 'canCheckin' => $allowed, 'editor' => 'Tester', 'checked_out_time' => '2026-11-20 01:00:00'];
        $html = CheckinFixture::grid_id(3, 45) . CheckinFixture::checkedout($row, $user, 3);
        if (substr_count($html, 'id="cb3"') !== 1 || substr_count($html, 'name="cid[]"') !== 1) {
            throw new \RuntimeException('A manager row must submit one checkbox, including foreign checkouts.');
        }
        $hasAction = str_contains($html, '.checkin');
        if ($hasAction !== ($owner !== 0 && $allowed)) {
            throw new \RuntimeException('Check-in action does not respect checkout permission.');
        }
    }
    echo "4 manager checkbox and check-in permission cases passed\n";
}
