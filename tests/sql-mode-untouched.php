<?php
/**
 * The component runs in the SQL mode that Joomla sets (strict mode on): the system plugin no longer changes it, and
 * the saves that relied on strict mode being off set the values the database needs.
 */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	throw new \ErrorException($message, 0, $severity, $file, $line);
});

$checks = 0;
$same = function ($expected, $actual, $what) use (&$checks) {
	if ($expected !== $actual)
	{
		throw new RuntimeException($what . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
	}

	$checks++;
};

// The code of a file without its comments
$code = function ($file) {
	$out = '';

	foreach (token_get_all(file_get_contents(__DIR__ . '/../' . $file)) as $token)
	{
		if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
		$out .= is_array($token) ? $token[1] : $token;
	}

	return $out;
};

// 1. No code of the system plugin sets the session's SQL mode
$same(false, stripos($code('plugins/system/flexisystem/flexisystem.php'), 'sql_mode'), 'the system plugin does not change the SQL mode');

// 2. saveaccess(): one access level for one or more records, or one level per record id
$source = file_get_contents(__DIR__ . '/../admin/models/base/baselist.php');
$start  = strpos($source, 'public function saveaccess(');

if ($start === false)
{
	throw new RuntimeException('saveaccess() is missing');
}

$method = '';
$depth  = 0;
$opened = false;

foreach (token_get_all('<?php ' . substr($source, $start)) as $token)
{
	if (is_array($token) && $token[0] === T_OPEN_TAG) continue;

	$method .= is_array($token) ? $token[1] : $token;

	if ($token === '{')
	{
		$depth++;
		$opened = true;
	}

	if ($token === '}' && --$depth === 0 && $opened) break;
}

eval('class SaveAccessFixture
{
	public $records_jtable = "fixture";
	public $stored = array();
	public $error;

	public function getTable($name, $prefix)
	{
		$model = $this;

		return new class ($model)
		{
			public $id, $access = 7;
			private $model;
			public function __construct($model) { $this->model = $model; }
			public function load($id) { $this->id = $id; $this->access = 7; return true; }
			public function check() { return true; }
			public function store() { $this->model->stored[$this->id] = $this->access; return true; }
			public function getError() { return ""; }
		};
	}

	public function setError($message) { $this->error = $message; }

	' . $method . '
}');

$m = new SaveAccessFixture;
$same(true, $m->saveaccess(10, 2), 'one id and one level: saved');
$same(array(10 => 2), $m->stored, 'one id and one level: the record gets that level, not NULL');

$m = new SaveAccessFixture;
$same(true, $m->saveaccess(array(10, 11), 3), 'several ids and one level: saved');
$same(array(10 => 3, 11 => 3), $m->stored, 'several ids and one level: every record gets it');

$m = new SaveAccessFixture;
$same(true, $m->saveaccess(array(10, 11), array(10 => 1, 11 => 4)), 'a level per record id: saved');
$same(array(10 => 1, 11 => 4), $m->stored, 'a level per record id: each record gets its own');

$m = new SaveAccessFixture;
$same(true, $m->saveaccess(10, array(10 => 5, 11 => 6)), 'one id and a list of levels: saved');
$same(array(10 => 5), $m->stored, 'one id and a list of levels: only that record is saved');

// 3. A new record saved through the base model is fitted as a new row (its required columns get a value)
$same(true, strpos($code('admin/models/base/base.php'), 'fitRecordToColumns($record, $record->getFields(), $isNew)') !== false, 'the base model fits a new record as a new row');

// 4. The old-tables clean-up task loads the item columns it reads
$task = $code('admin/controllers/flexicontent.raw.php');
$task = substr($task, strpos($task, 'function cleanupOldTables('), 9000);
$same(true, strpos($task, 'SELECT catid, introtext, `fulltext`, modified FROM #__content WHERE id = ') !== false, 'the clean-up task loads catid, introtext, fulltext and modified');
$same(true, strpos($task, '$nullDate = $db->getNullDate()') !== false && strpos($task, '$nullDate = $db->getNullDate()') < strpos($task, '$row->modified != $nullDate'), 'the clean-up task defines the null date it compares with');

// 5. The "create DB indexes" task fills the typed value columns with casts: IGNORE keeps strict mode from refusing it
$same(true, strpos($code('admin/models/flexicontent.php'), "'UPDATE IGNORE #__' . \$tblname . ' SET '") !== false, 'the index task fills the typed value columns with UPDATE IGNORE');

// 6. Blocking and unblocking a user stores 1 or 0, not a boolean (false reaches the database as an empty string)
$same(true, strpos($code('admin/controllers/users.php'), '$user->block = (int) $block;') !== false, 'the user list stores block as 0 or 1');

echo $checks . " SQL mode checks passed\n";
