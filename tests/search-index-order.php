<?php
/** The basic and advanced index lists use different aliases and different unique keys. */
namespace Joomla\CMS\MVC\Model {
	class ListModel {}
}
namespace {
	define('_JEXEC', 1);
	require __DIR__ . '/../admin/models/search.php';
	$model = new class extends \FLEXIcontentModelSearch {
		public $filters;
		public function __construct() {}
		public function getState($name) { return $this->filters[$name]; }
	};
	foreach (['basic' => 'ext.item_id', 'advanced' => 'ai.sid'] as $type => $id)
	{
		foreach (['ASC', 'DESC'] as $dir)
		{
			$model->filters = ['filter_order' => 'a.title', 'filter_order_Dir' => $dir, 'filter_indextype' => $type];
			if (trim($model->_buildOrderBy()) !== "ORDER BY a.title $dir, $id")
			{
				throw new RuntimeException('Missing stable row order for ' . $type);
			}
		}
	}
	echo "Search index ordering checks passed\n";
}
