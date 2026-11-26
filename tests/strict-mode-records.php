<?php
/**
 * flexicontent_db::fitRecordToColumns(): a record gets the values that the database stores for it when
 * strict mode is off, so that the same INSERT or UPDATE is accepted with strict mode on.
 */
namespace Joomla\String {
	class StringHelper
	{
		public static function strlen($text) { return mb_strlen($text, 'UTF-8'); }
		public static function substr($text, $start, $length = null) { return mb_substr($text, $start, $length, 'UTF-8'); }
	}
}

namespace {
	define('_JEXEC', 1);
	require __DIR__ . '/../site/classes/helpers/db.php';

	$col = function ($type, $null = 'NO', $default = null, $extra = '') {
		return (object) array('Type' => $type, 'Null' => $null, 'Default' => $default, 'Extra' => $extra);
	};
	$columns = array(
		'id'         => $col('int unsigned', 'NO', null, 'auto_increment'),
		'title'      => $col('varchar(10)'),
		'alias'      => $col('varchar(400)'),
		'sub_items'  => $col('text'),
		'attribs'    => $col('mediumtext'),
		'hits'       => $col('int'),
		'rating'     => $col('decimal(5,2)'),
		'type_id'    => $col('int(11) unsigned'),
		'state'      => $col('tinyint', 'NO', '0'),
		'note'       => $col('varchar(255)', 'YES'),
		'created'    => $col('datetime'),
		'language'   => $col('char(7)', 'NO', '*'),
	);
	$checks = 0;
	$same = function ($expected, $actual, $what) use (&$checks) {
		if ($expected !== $actual)
		{
			throw new RuntimeException($what . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
		}

		$checks++;
	};

	// A new row
	$r = (object) array('title' => 'A title of 21 letters', 'hits' => '', 'type_id' => 7, 'alias' => 'short');
	flexicontent_db::fitRecordToColumns($r, $columns, true);
	$same('A title of', $r->title, 'a text is cut to the length of its VARCHAR column');
	$same('short', $r->alias, 'a text that fits is kept');
	$same(0, $r->hits, "'' in a number column becomes 0");
	$same(7, $r->type_id, 'a number that was set is kept');
	$same('', $r->sub_items, 'a required TEXT column without a value gets an empty string');
	$same('', $r->attribs, 'a required MEDIUMTEXT column without a value gets an empty string');
	$same(0, $r->rating, 'a required DECIMAL column without a value gets 0');
	$same(false, isset($r->id), 'the auto-increment key is left to the database');
	$same(false, isset($r->state), 'a column with a default is left to the database');
	$same(false, isset($r->note), 'a column that may be NULL is left alone');
	$same(false, isset($r->created), 'a date column is left alone');
	$same(false, isset($r->language), 'a CHAR column with a default is left to the database');

	// An existing row: nothing is added, so that an UPDATE does not overwrite columns the record does not hold
	$u = (object) array('title' => 'Another long title', 'hits' => '', 'note' => null);
	flexicontent_db::fitRecordToColumns($u, $columns);
	$same('Another lo', $u->title, 'update: a text is cut');
	$same(0, $u->hits, "update: '' in a number column becomes 0");
	$same(false, isset($u->sub_items), 'update: a column that the record does not hold is not added');
	$same(false, isset($u->type_id), 'update: a number column that the record does not hold is not added');
	$same(null, $u->note, 'update: NULL is kept');

	// An existing row that is stored with its NULL properties too (Table::store($updateNulls = true)): a NULL in a
	// NOT NULL column is stored as an empty string or 0 when strict mode is off, and is refused when it is on
	$n = (object) array('title' => null, 'hits' => null, 'state' => null, 'note' => null, 'created' => null, 'id' => 5);
	flexicontent_db::fitRecordToColumns($n, $columns, false, true);
	$same('', $n->title, 'update with NULLs: NULL in a NOT NULL text column becomes an empty string');
	$same(0, $n->hits, 'update with NULLs: NULL in a NOT NULL number column becomes 0');
	$same(0, $n->state, 'update with NULLs: the same for a column that has a default');
	$same(null, $n->note, 'update with NULLs: NULL is kept in a column that may be NULL');
	$same(null, $n->created, 'update with NULLs: a date column is left alone');
	$same(false, property_exists($n, 'sub_items'), 'update with NULLs: a column that the record does not hold is not added');

	// Without the NULL properties (updateObject skips them) nothing changes
	$s = (object) array('title' => null, 'hits' => null);
	flexicontent_db::fitRecordToColumns($s, $columns);
	$same(null, $s->title, 'update: a NULL that is not written is kept');
	$same(null, $s->hits, 'update: a NULL number that is not written is kept');

	// Characters, not bytes
	$m = (object) array('title' => '植牙完整指南一二三四五六七');
	flexicontent_db::fitRecordToColumns($m, $columns);
	$same('植牙完整指南一二三四', $m->title, 'the length is counted in characters');

	// Values that already fit are not touched
	$k = (object) array('title' => '1234567890', 'hits' => '12', 'rating' => 4.5);
	flexicontent_db::fitRecordToColumns($k, $columns, true);
	$same('1234567890', $k->title, 'a text of exactly the column length is kept');
	$same('12', $k->hits, 'a number given as text is kept');
	$same(4.5, $k->rating, 'a decimal is kept');

	echo $checks . " strict mode record checks passed\n";
}
