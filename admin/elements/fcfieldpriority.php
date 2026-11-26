<?php
/**
 * @package     FLEXIcontent
 * @subpackage  Form element
 * @license     GNU/GPL v2 or later
 *
 * Sortable list of the kinds of field (title, categories, tags) used to prioritize the search results.
 * Converts the value of the previous option, that was a number (1..6) selecting one of 6 fixed orders.
 */

defined('_JEXEC') or die('Restricted access');

require_once __DIR__ . '/fcsortopts.php';

class JFormFieldFcFieldPriority extends JFormFieldFcSortOpts
{
	protected $type = 'FcFieldPriority';

	protected function normalizeValue($value)
	{
		$orders = array(
			1 => 'title,categories,tags',
			2 => 'title,tags,categories',
			3 => 'categories,title,tags',
			4 => 'categories,tags,title',
			5 => 'tags,title,categories',
			6 => 'tags,categories,title'
		);

		return ctype_digit($value) ? ($orders[(int) $value] ?? '') : $value;
	}
}
