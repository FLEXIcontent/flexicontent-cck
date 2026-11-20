<?php
/**
 * Run with Joomla's vendor autoloader and a disposable MySQL/MariaDB database.
 * FC_TEST_AUTOLOAD, FC_TEST_DATABASE, FC_TEST_HOST, FC_TEST_USER, FC_TEST_PASSWORD.
 * Two random prefixes exercise independent sites in the same installer process.
 */
namespace Joomla\CMS\MVC\Model {
	class BaseDatabaseModel
	{
		protected $_db;
		public function __construct($config) { $this->_db = $config['db']; }
	}
}
namespace Joomla\CMS {
	class Version { public function getShortVersion() { return '6.2.0'; } }
	class Factory
	{
		public static $app;
		public static function getApplication() { return self::$app; }
	}
}
namespace {
	use Joomla\CMS\Factory;
	use Joomla\Database\DatabaseFactory;

	define('_JEXEC', 1);
	$autoload = getenv('FC_TEST_AUTOLOAD');
	$database = getenv('FC_TEST_DATABASE');
	if (!$autoload || !$database)
	{
		throw new RuntimeException('Set FC_TEST_AUTOLOAD and FC_TEST_DATABASE to run the database regression.');
	}
	require $autoload;
	require __DIR__ . '/../admin/helpers/dbcharset.php';
	require __DIR__ . '/../admin/helpers/dbengine.php';
	require __DIR__ . '/../admin/models/flexicontent.php';

	$options = ['host' => getenv('FC_TEST_HOST'), 'user' => getenv('FC_TEST_USER'),
		'password' => getenv('FC_TEST_PASSWORD'), 'database' => $database];
	$prefix = 'fc' . bin2hex(random_bytes(5));
	$factory = new DatabaseFactory;
	$a = $factory->getDriver('mysqli', $options + ['prefix' => $prefix . 'a_']);
	$b = $factory->getDriver('mysqli', $options + ['prefix' => $prefix . 'b_']);
	$created = [];
	$create = static function ($db, $name, $definition) use (&$created) {
		$db->setQuery('CREATE TABLE #__' . $name . ' ' . $definition)->execute();
		$created[] = [$db, $name];
	};
	$equal = static function ($expected, $actual, $what) {
		if ($expected !== $actual)
		{
			throw new RuntimeException($what . ': ' . var_export($actual, true));
		}
	};

	try
	{
		$create($a, 'content', '(title VARCHAR(255), language VARCHAR(7) CHARACTER SET utf8mb3) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
		$create($b, 'content', '(title VARCHAR(255)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
		$create($a, 'flexicontent_items_tmp', '(title VARCHAR(255) NOT NULL) DEFAULT CHARSET=utf8mb4');
		$a->setQuery('INSERT INTO #__flexicontent_items_tmp VALUES (CONVERT(0xF09F9880 USING utf8mb4))')->execute();
		Factory::$app = new class($database, $a->getPrefix()) {
			public function __construct(private $database, private $prefix) {}
			public function get($key) { return $key === 'db' ? $this->database : $this->prefix; }
			public function getSession() { return $this; }
			public function set($key, $value) {}
		};
		foreach (['STRICT_TRANS_TABLES', ''] as $mode)
		{
			$a->setQuery('SET SESSION sql_mode = ' . $a->quote($mode))->execute();
			(new \FlexicontentModelFlexicontent(['db' => $a]))->checkCollations();
			$equal('F09F9880', $a->setQuery('SELECT HEX(title) FROM #__flexicontent_items_tmp')->loadResult(), 'dashboard title bytes');
		}

		foreach ([[$a, 'utf8mb4_unicode_ci', 'MyISAM'], [$b, 'utf8mb4_bin', 'InnoDB']] as [$db, $collation, $engine])
		{
			$equal($collation, FlexicontentDbCharset::getTargetCollation($db), 'site collation');
			$create($db, 'flexicontent_items_ext', '(id INT) ENGINE=' . $engine);
			$create($db, 'flexicontent_new', '(id INT) ENGINE=MyISAM');
			FlexicontentDbEngine::followSiteEngine($db, '#__flexicontent_new');
			$actual = $db->setQuery('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()'
				. ' AND TABLE_NAME=' . $db->quote($db->getPrefix() . 'flexicontent_new'))->loadResult();
			$equal($engine, $actual, 'new search table engine');
		}

		$create($a, 'flexicontent_compact', '(id INT NOT NULL, value VARCHAR(190) NOT NULL, UNIQUE KEY k(id,value)) ENGINE=InnoDB ROW_FORMAT=COMPACT DEFAULT CHARSET=utf8mb3');
		$create($a, 'flexicontent_binary', '(value VARCHAR(10), token VARBINARY(255) UNIQUE) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3');
		$create($a, 'flexicontent_metadata', "(term VARCHAR(255), KEY lookup(term) COMMENT 'reviewed sort') ENGINE=MyISAM DEFAULT CHARSET=utf8mb3");
		// Separate connections, same installation. Another installation's lock must be independent.
		foreach (['charset', 'engine'] as $operation)
		{
			$lock = 'flexicontent_db' . $operation . '_' . substr(hash('sha256', $database . ':' . $a->getPrefix()), 0, 40);
			$b->setQuery('SELECT GET_LOCK(' . $b->quote($lock) . ', 0)')->loadResult();
			try
			{
				if ($operation === 'charset')
				{
					$equal(false, FlexicontentDbCharset::convertPending($a)[''][0], 'concurrent charset conversion');
				}
				else
				{
					$equal(false, FlexicontentDbEngine::convertNext($a)[1], 'concurrent engine conversion');
				}
			}
			finally
			{
				$b->setQuery('SELECT RELEASE_LOCK(' . $b->quote($lock) . ')')->loadResult();
			}
		}
		$tables = FlexicontentDbCharset::getPendingTables($a);
		foreach (['compact', 'binary', 'metadata'] as $name)
		{
			[$ok, $msg] = FlexicontentDbCharset::convertTable($a, $tables[$a->getPrefix() . 'flexicontent_' . $name]);
			$equal(true, $ok, $name . ' ' . $msg);
		}
		$index = $a->setQuery("SHOW INDEX FROM #__flexicontent_metadata WHERE Key_name='lookup'")->loadObject();
		$equal('reviewed sort', $index->Index_comment, 'rebuilt index comment');
		$equal(191, (int) $index->Sub_part, 'rebuilt prefix length');
		echo "Database migration regressions passed\n";
	}
	finally
	{
		foreach (array_reverse($created) as [$db, $name])
		{
			$db->setQuery('DROP TABLE #__' . $name)->execute();
		}
	}
}
