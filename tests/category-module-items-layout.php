<?php
/** Verify template partial selection against Joomla's documented default-layout fallback. */
namespace Joomla\CMS\Helper {
	class ModuleHelper
	{
		public static function getLayoutPath($module, $layout)
		{
			$files = ['default_items' => '/module/default_items.php', 'site:cards_items' => '/site/cards_items.php',
				'site:paired_items_items' => '/site/paired_items_items.php'];
			return $files[$layout] ?? '/module/default.php';
		}
	}
}
namespace {
	define('_JEXEC', 1);
	require __DIR__ . '/../modules/mod_flexicategories/helper.php';
	foreach ([
		'default'          => '/module/default_items.php',
		'default_items'    => '/module/default_items.php',
		'site:cards'       => '/site/cards_items.php',
		'site:cards_items' => '/site/cards_items.php',
		'site:paired_items' => '/site/paired_items_items.php',
		'site:missing_items' => '/module/default_items.php',
		'site:missing'     => '/module/default_items.php',
	] as $layout => $expected)
	{
		if (ModFlexiCategoriesHelper::getItemsLayoutPath($layout) !== $expected)
		{
			throw new RuntimeException('Incorrect category child layout for ' . $layout);
		}
	}
	echo "Category module child layout checks passed\n";
}
