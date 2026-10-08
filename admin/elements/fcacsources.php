<?php
/**
 * @package     FLEXIcontent
 * @subpackage  Form element
 * @license     GNU/GPL v2 or later
 *
 * Sortable list of the search auto-complete suggestion sources (words, titles, tags, categories).
 * While the new option is not saved yet, the sources are derived from the previous options
 * (search_autocomplete_titles, search_autocomplete_titles_order, search_autocomplete_tags_order),
 * so that existing configurations keep working.
 */

defined('_JEXEC') or die('Restricted access');

require_once __DIR__ . '/fcsortopts.php';

class JFormFieldFcAcSources extends JFormFieldFcSortOpts
{
	protected $type = 'FcAcSources';

	protected function getFallbackValue()
	{
		$params = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');

		$mode = (int) $params->get('search_autocomplete_titles', 0);

		if ($mode === 1)
		{
			return (int) $params->get('search_autocomplete_titles_order', 0) === 1 ? 'titles,words' : 'words,titles';
		}

		if ($mode === 2)
		{
			return 'titles';
		}

		if ($mode === 3)
		{
			$orders = array('tags,words,titles', 'tags,titles,words', 'words,tags,titles', 'titles,tags,words');
			return $orders[(int) $params->get('search_autocomplete_tags_order', 0)] ?? $orders[0];
		}

		return 'words';
	}
}
