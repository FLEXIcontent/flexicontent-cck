<?php
/** Test the administrator and selector queries on the database configured for database-migration-roundtrip.php. */
namespace Joomla\CMS\MVC\Model {
	class ListModel
	{
		protected $_db;
		private $search;
		public function getState($key) { return $key === 'scope' ? 'ie.search_index' : $this->search; }
		public function searchClause($db, $search)
		{
			$this->_db = $db;
			$this->search = $search;
			return $this->_getTextSearch();
		}
	}
}
namespace Joomla\CMS\Component {
	class ComponentHelper
	{
		public static function getParams($component) { return new \Joomla\Registry\Registry(['add_search_prefix' => 0]); }
	}
}
namespace Joomla\CMS {
	class Factory
	{
		public static $container;
		public static function getContainer() { return self::$container; }
	}
}
namespace {
	define('_JEXEC', 1);
	define('FLEXI_CAT_EXTENSION', 'com_content');
	require getenv('FC_TEST_AUTOLOAD');
	require __DIR__ . '/../site/classes/helpers/db.php';
	require __DIR__ . '/../admin/models/items.php';
	require __DIR__ . '/../admin/models/itemelement.php';

	$db = (new \Joomla\Database\DatabaseFactory)->getDriver('mysqli', [
		'host' => getenv('FC_TEST_HOST'), 'user' => getenv('FC_TEST_USER'), 'password' => getenv('FC_TEST_PASSWORD'),
		'database' => getenv('FC_TEST_DATABASE'), 'prefix' => 'ft' . bin2hex(random_bytes(5)) . '_'
	]);
	\Joomla\CMS\Factory::$container = new class($db) {
		public function __construct(private $db) {}
		public function get($key) { return $this->db; }
	};
	$table = '#__flexicontent_items_ext';
	$db->setQuery("CREATE TABLE $table (id INT PRIMARY KEY, search_index TEXT, FULLTEXT (search_index)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();
	try
	{
		$db->setQuery("INSERT INTO $table VALUES (1, 'blue_widget café'), (2, 'blue widget')")->execute();
		foreach ([FlexicontentModelItems::class, FlexicontentModelItemelement::class] as $class)
		{
			$model = (new ReflectionClass($class))->newInstanceWithoutConstructor();
			foreach (['blue_widget' => [1], 'café' => [1], '*' => [], '+++' => [], 'absent' => []] as $term => $expected)
			{
				$where = implode(' OR ', $model->searchClause($db, $term));
				$actual = array_map('intval', $db->setQuery("SELECT ie.id FROM $table ie WHERE $where ORDER BY ie.id")->loadColumn());
				if ($actual !== $expected)
				{
					throw new RuntimeException($class . ' ' . $term . ': ' . json_encode($actual));
				}
			}
		}
		$db->setQuery("INSERT INTO $table VALUES (3,'coffee table'),(4,'table for coffee'),(5,'marble bench'),(6,'vvvcoffee vvvtable')")->execute();
		foreach (['InnoDB', 'MyISAM'] as $engine)
		{
			if ($engine === 'MyISAM') $db->setQuery("ALTER TABLE $table ENGINE=MyISAM")->execute();
			foreach ([
				['"coffee table"', 'all', '', [3]],
				['"coffee table" "marble bench"', 'any', '', [3, 5]],
				['"coffee table" marble', 'all', '', []],
				['"coffee table" cof', 'all', '', [3]],
				['"coffee table"', 'any', 'vvv', [6]],
			] as [$query, $mode, $prefix, $expected])
			{
				$terms = flexicontent_db::getQuotedSearchTerms($query, $mode);
				$where = flexicontent_db::getQuotedFulltextCondition('search_index', $terms, $mode, $prefix);
				$ids = array_map('intval', $db->setQuery("SELECT id FROM $table WHERE $where ORDER BY id")->loadColumn());
				if ($ids !== $expected) throw new RuntimeException("$engine $query returned " . json_encode($ids));
			}
		}
		echo "FULLTEXT list and selector regressions passed\n";
	}
	finally
	{
		$db->setQuery("DROP TABLE $table")->execute();
	}
}
