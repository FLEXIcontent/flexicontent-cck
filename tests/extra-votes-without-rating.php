<?php
/** An item with extra (composite) votes but no row in #__content_rating must not break the loading of item votes. */
namespace Joomla\CMS
{
    final class Factory
    {
        public static $db;
        public static function getContainer() { return new self; }
        public function get($id) { return self::$db; }
    }
}

namespace
{
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    define('FLEXI_ITEMVIEW', 'item');

    final class VotesDbFixture
    {
        public $rating = [];
        public $extra = [];
        public $queries = [];
        private $query = '';
        public function setQuery($query) { $this->query = (string) $query; $this->queries[] = $this->query; return $this; }
        public function loadObjectList($key = '')
        {
            $rows = strpos($this->query, '#__flexicontent_items_extravote') !== false ? $this->extra : $this->rating;
            $list = [];
            foreach ($rows as $row) {
                $row = (object) $row;
                if (strpos($this->query, "'" . $row->content_id . "'") === false) { continue; }
                if (strpos($this->query, 'extra_id') !== false) { $row->extra_id = $row->field_id; }
                if ($key) { $list[$row->$key] = $row; } else { $list[] = $row; }
            }
            return $list;
        }
    }

    $source = file_get_contents(__DIR__ . '/../site/classes/flexicontent.fields.php');
    $start = strpos($source, 'static function _getVotes(');
    if ($start === false) { throw new RuntimeException('Item votes loader missing'); }
    $method = ''; $depth = 0; $opened = false;
    foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
        if (is_array($token) && $token[0] === T_OPEN_TAG) { continue; }
        $method .= is_array($token) ? $token[1] : $token;
        if ($token === '{') { $depth++; $opened = true; }
        if ($token === '}' && --$depth === 0 && $opened) { break; }
    }
    // Model the table-presence flags, including conservative loading in non-page contexts.
    eval('class FlexicontentFields { public static $flags = ["votes" => true, "extravotes" => true]; public static function _hasItemData($kind) { return self::$flags[$kind]; } ' . $method . '}');

    $db = new VotesDbFixture;
    $db->rating = [
        ['content_id' => 7, 'rating_sum' => 9, 'rating_count' => 2, 'lastip' => '127.0.0.1'],
        ['content_id' => 9, 'rating_sum' => 4, 'rating_count' => 1, 'lastip' => '127.0.0.1'],
    ];
    $db->extra = [
        ['content_id' => 7, 'field_id' => 3, 'rating_sum' => 5, 'rating_count' => 1, 'lastip' => '127.0.0.1'],
        ['content_id' => 8, 'field_id' => 3, 'rating_sum' => 4, 'rating_count' => 1, 'lastip' => '127.0.0.1'],
        ['content_id' => 8, 'field_id' => 4, 'rating_sum' => 2, 'rating_count' => 1, 'lastip' => '127.0.0.1'],
    ];
    \Joomla\CMS\Factory::$db = $db;

    $items = [(object) ['id' => 7], (object) ['id' => 8], (object) ['id' => 9], (object) ['id' => 10]];
    $votes = FlexicontentFields::_getVotes($items);
    $count = 0;
    $check = static function ($condition, $message) use (&$count) {
        if (!$condition) { throw new RuntimeException($message); }
        $count++;
    };

    // Ordinary and extra votes
    $check((int) $votes[7]->rating_sum === 9 && (int) $votes[7]->rating_count === 2, 'Ordinary votes changed');
    $check(array_keys($votes[7]->extra) === [3] && (int) $votes[7]->extra[3]->rating_sum === 5, 'Extra votes changed');

    // Extra votes only: this case used to stop the page with "Attempt to modify property "extra" on null"
    $check(isset($votes[8]) && is_object($votes[8]), 'Item with only extra votes has no votes object');
    $check(array_keys($votes[8]->extra) === [3, 4], 'Extra votes of an item without ordinary votes are missing');
    $check((int) $votes[8]->extra[4]->rating_sum === 2 && (int) $votes[8]->extra[4]->extra_id === 4, 'Extra vote data changed');
    $check(!isset($votes[8]->rating_sum) && !isset($votes[8]->rating_count), 'Ordinary votes were invented for an item that has none');

    // Ordinary votes only, and no votes at all
    $check((int) $votes[9]->rating_sum === 4 && !isset($votes[9]->extra), 'Item with only ordinary votes changed');
    $check(!isset($votes[10]), 'Item without votes got a votes object');

    $db->rating = []; $db->queries = [];
    FlexicontentFields::$flags = ['votes' => false, 'extravotes' => true];
    $votes = FlexicontentFields::_getVotes($items);
    $check(count($db->queries) === 1 && strpos($db->queries[0], '#__flexicontent_items_extravote') !== false, 'Extra-only site queries the empty ordinary rating table');
    $check(array_keys($votes[8]->extra) === [3, 4] && !isset($votes[8]->rating_count), 'Extra-only guard changes vote data');

    $db->rating = [['content_id' => 9, 'rating_sum' => 4, 'rating_count' => 1]];
    $db->extra = []; $db->queries = [];
    FlexicontentFields::$flags = ['votes' => true, 'extravotes' => false];
    $votes = FlexicontentFields::_getVotes($items);
    $check(count($db->queries) === 1 && $votes[9]->rating_count === 1, 'Ordinary-only site changes query count or votes');

    $db->queries = [];
    FlexicontentFields::$flags = ['votes' => false, 'extravotes' => false];
    $check(FlexicontentFields::_getVotes($items) === [] && !$db->queries, 'Empty site still queries vote tables');

    restore_error_handler();
    echo $count . " extra votes checks passed\n";
}
