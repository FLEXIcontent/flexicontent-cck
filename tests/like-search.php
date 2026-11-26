<?php
/**
 * "No space languages": when a text search runs as LIKE %text%, and the condition it builds.
 */
namespace Joomla\CMS {
	class Factory
	{
		public static string $tag = 'en-GB';

		public static function getApplication()
		{
			return new class {
				public function getLanguage()
				{
					return new class {
						public function getTag() { return Factory::$tag; }
					};
				}
			};
		}

		public static function getContainer()
		{
			return new class {
				public function get($name)
				{
					return new class {
						public function escape($text, $extra = false) { return $extra ? addcslashes(addslashes($text), '%_') : addslashes($text); }
						public function Quote($text, $escape = true) { return "'" . ($escape ? addslashes($text) : $text) . "'"; }
					};
				}
			};
		}
	}
}

namespace {
	define('_JEXEC', 1);

	class FlexicontentFields
	{
		// As in the real class: a dictionary word splitter exists for Thai only
		public static function getLangHandler($language, $hasHandlerOnly = false) { return $language === 'th-TH'; }
	}

	class flexicontent_html
	{
		public static function getUserCurrentLang() { return substr(\Joomla\CMS\Factory::$tag, 0, 2); }
	}

	require __DIR__ . '/../site/classes/helpers/db.php';

	$checks = 0;
	$same = function ($expected, $actual, $what) use (&$checks) {
		if ($expected !== $actual)
		{
			throw new RuntimeException($what . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
		}

		$checks++;
	};

	// When the LIKE search is used
	$cases = array(
		// setting, page language, text, expected
		array(0, 'zh-TW', '植牙',       false),  // setting off
		array(1, 'zh-TW', '植牙',       true),
		array(1, 'zh-TW', 'implant',    true),   // as before: every search of a Chinese page
		array(1, 'ja-JP', 'インプラント', true),
		array(1, 'en-GB', '植牙',       true),   // new: Chinese text on a page in another language
		array(1, 'en-GB', 'ひらがな',    true),
		array(1, 'en-GB', 'カタカナ',    true),
		array(1, 'en-GB', 'implant',    false),  // a language with spaces keeps the FULLTEXT index
		array(1, 'fr-FR', 'café crème', false),
		array(1, 'en-GB', 'เก้าอี้',      false),  // Thai text: unchanged, only by the page language
		array(1, 'th-TH', 'เก้าอี้',      false),  // Thai page: the dictionary splitter is used
		array(1, 'th-TH', '植牙',       false),
		array(1, 'en-GB', '',           false),
	);

	foreach ($cases as $case)
	{
		\Joomla\CMS\Factory::$tag = $case[1];
		$same($case[3], flexicontent_db::useLikeSearch($case[0], $case[2]), 'useLikeSearch(' . $case[0] . ', ' . $case[1] . ', ' . $case[2] . ')');
	}

	// The condition
	$col = 'ie.search_index';
	$like = function ($word) use ($col) { return 'LOWER (' . $col . ") LIKE '%" . $word . "%'"; };

	$same(' ' . $like('植牙'), flexicontent_db::getLikeSearchCondition($col, '植牙', 'all'), 'one word');
	$same(' ' . $like('植牙'), flexicontent_db::getLikeSearchCondition($col, '  植牙 ', 'any'), 'one word, any');
	$same(' (' . $like('假牙') . ' AND ' . $like('植牙') . ') ', flexicontent_db::getLikeSearchCondition($col, '假牙 植牙', 'all'), 'two words, all');
	$same(' (' . $like('假牙') . ' OR ' . $like('植牙') . ') ', flexicontent_db::getLikeSearchCondition($col, "假牙\t 植牙", 'any'), 'two words, any');
	$same(' ' . $like('假牙 植牙'), flexicontent_db::getLikeSearchCondition($col, '假牙 植牙', 'exact'), 'exact phrase');
	$same(' ' . $like('隱形牙套'), flexicontent_db::getLikeSearchCondition($col, '"隱形牙套"', 'all'), 'quoted phrase');
	$same(' ' . $like('假牙 植牙'), flexicontent_db::getLikeSearchCondition($col, '"假牙 植牙"', 'any'), 'quoted phrase of two words');
	$same(' ' . $like('植牙'), flexicontent_db::getLikeSearchCondition($col, '植牙 植牙', 'all'), 'a repeated word');
	$same(' 0=1 ', flexicontent_db::getLikeSearchCondition($col, '   ', 'all'), 'no word');

	// A column with prefixed words: single words are matched inside a word, a phrase follows the prefixes
	$same(' (' . $like('kitchen') . ' AND ' . $like('sink') . ') ', flexicontent_db::getLikeSearchCondition($col, 'kitchen sink', 'all', 'vvv'), 'prefix, all');
	$same(' ' . $like('kitchen vvvsink'), flexicontent_db::getLikeSearchCondition($col, 'kitchen sink', 'exact', 'vvv'), 'prefix, exact phrase');
	$same(" LOWER (i.title) LIKE '%kitchen sink%'", flexicontent_db::getLikeSearchCondition('i.title', 'kitchen sink', 'exact'), 'title, exact phrase');

	$same(' (' . $like('coffee table') . ' OR ' . $like('marble bench') . ') ',
		flexicontent_db::getLikeSearchCondition($col, '"coffee table" "marble bench"', 'any'), 'two quoted phrases');
	$same(' (' . $like('coffee vvvtable') . ' AND ' . $like('marble') . ') ',
		flexicontent_db::getLikeSearchCondition($col, '"coffee table" marble', 'all', 'vvv'), 'quoted phrase and word with prefixes');

	// Wildcards and quotes of the text are escaped
	$same(true, strpos(flexicontent_db::getLikeSearchCondition($col, '100%', 'all'), '100\\%') !== false, 'percent sign is escaped');
	$same(true, strpos(flexicontent_db::getLikeSearchCondition($col, "o'neill", 'all'), "o\\'neill") !== false, 'single quote is escaped');

	echo $checks . " LIKE search checks passed\n";
}
