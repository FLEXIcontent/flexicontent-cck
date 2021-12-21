<?php
/**
 * @version		$Id: search.php 14401 2010-01-26 14:10:00Z louis $
 * @package  Joomla
 * @subpackage	Search
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant to the
 * GNU General Public License, and as distributed it includes or is derivative
 * of works licensed under the GNU General Public License or other free or open
 * source software licenses. See COPYRIGHT.php for copyright notices and
 * details.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

use Joomla\String\StringHelper;
use Joomla\CMS\Language\LanguageHelper;

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

/**
 * @package		Joomla
 * @subpackage	Search
 */
class FLEXIadvsearchHelper
{
	static function santiseSearchWord(&$searchword, $searchphrase, $min=2)
	{
		$ignored = false;
		$lang = JFactory::getLanguage();
		$lang_tag = $lang->getTag();
		$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix

		$search_ignore = array();
		$ignoreFile = LanguageHelper::getLanguagePath().DS.$lang_tag.DS.$lang_tag.'.ignore.php';

		if (file_exists($ignoreFile))
		{
			include $ignoreFile;
		}

	 	// check for words to ignore
		$aterms = explode(' ', StringHelper::strtolower($searchword));

		// first case is single ignored word
		if (count($aterms) === 1 && in_array(StringHelper::strtolower($searchword), $search_ignore))
		{
			$ignored = true;
		}

		// filter out search terms that are too small
		foreach($aterms AS $aterm)
		{
			if (!$search_prefix && StringHelper::strlen( $aterm ) < $min)
			{
				$search_ignore[] = $aterm;
			}
		}

		// next is to remove ignored words from type 'all' or 'any' (not exact) searches with multiple words
		if (count($aterms) > 1 && $searchphrase !== 'exact')
		{
			$pruned = array_diff($aterms, $search_ignore);
			$searchword = implode(' ', $pruned);
		}

		// Set words that were removed due to being too short
		JFactory::getApplication()->input->set('shortwords_sanitize', implode(' ', $search_ignore));

		return $ignored;
	}

	static function limitSearchWord(&$searchword, $min=2, $max=20)
	{
		$restriction = false;

		// maximum searchword length character limit
		if (StringHelper::strlen($searchword) > $max)
		{
			$searchword 	= StringHelper::substr( $searchword, 0, $max );
			$restriction 	= true;
		}

		// minimum searchword length character limit
		if ($searchword && StringHelper::strlen($searchword) < $min)
		{
			$searchword 	= '';
			$restriction 	= true;
		}

		return $restriction;
	}

	static function logSearch( $search_term )
	{
		$db = JFactory::getDbo();
		$params = JComponentHelper::getParams('com_search');
		$enable_log_searches = $params->get('enabled');

		$search_term_quoted = $db->Quote(trim($search_term));

		if ($enable_log_searches)
		{
			$query = 'SELECT hits'
				. ' FROM #__core_log_searches'
				. ' WHERE LOWER( search_term ) = ' . $search_term_quoted;

			$hits = (int) $db->setQuery($query)->loadResult();

			if ($hits)
			{
				$query = 'UPDATE #__core_log_searches'
					. ' SET hits = ( hits + 1 )'
					. ' WHERE LOWER( search_term ) = ' . $search_term_quoted;
				$db->setQuery($query)->execute();
			}
			else
			{
				$query = 'INSERT INTO #__core_log_searches VALUES (' . $search_term_quoted . ', 1 )';
				$db->setQuery($query)->execute();
			}
		}
	}

	/**
	 * Prepares results from search for display
	 *
	 * @param string The source string
	 * @param int Number of chars to trim
	 * @param string The searchword to select around
	 * @return string
	 */
	static function prepareSearchContent( $text, $length, $searchword_arr )
	{
		// strips tags won't remove the actual jscript
		$text = preg_replace( "'<script[^>]*>.*?</script>'si", "", $text );
		$text = preg_replace( '/{.+?}/', '', $text);
		//$text = preg_replace( '/<a\s+.*?href="([^"]+)"[^>]*>([^<]+)<\/a>/is','\2', $text );
		// replace line breaking tags with whitespace
		$text = preg_replace( "'<(br[^/>]*?/|hr[^/>]*?/|/(div|h[1-6]|li|p|td))>'si", ' ', $text );

		// Do not try past the text's length
		$length = min(StringHelper::strlen($text), $length);

		if (($wordpos = StringHelper::strpos($text, ' ', $length)) !== false) {
			$start_part = StringHelper::substr($text, 0, $wordpos) . '&nbsp;...';
		} else {
			$start_part = StringHelper::substr($text, 0, $length);
		}

		$parts = array();
		foreach ($searchword_arr as $searchword) {
			$wordpos = false;
			$part = FLEXIadvsearchHelper::_smartSubstr( strip_tags( $text ), $length, $searchword, $wordpos);
			if ($wordpos !== false) {
				$parts[$searchword] = $part;
				$positions[$wordpos] = $searchword;
			}
		}

		if ( count($parts) ) {
			$oparts = array();
			ksort($positions);
			foreach ($positions as $wordpos => $searchword) {
				$oparts[ $searchword ] = $parts[$searchword];
			}
			return $oparts;
		} else {
			return array('' => $start_part);
		}
	}

	/**
	 * Checks an object for search terms (after stripping fields of HTML)
	 *
	 * @param object The object to check
	 * @param string Search words to check for
	 * @param array List of object variables to check against
	 * @returns boolean True if searchTerm is in object, false otherwise
	 */
	static function checkNoHtml($object, $searchTerm, $fields) {
		$searchRegex = array(
				'#<script[^>]*>.*?</script>#si',
				'#<style[^>]*>.*?</style>#si',
				'#<!.*?(--|]])>#si',
				'#<[^>]*>#i'
				);
		$terms = explode(' ', $searchTerm);
		if(empty($fields)) return false;
		foreach($fields AS $field) {
			if(!isset($object->$field)) continue;
			$text = $object->$field;
			foreach($searchRegex As $regex) {
				$text = preg_replace($regex, '', $text);
			}
			foreach($terms AS $term) {
				if(StringHelper::stristr($text, $term) !== false) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * returns substring of characters around a searchword
	 *
	 * @param string The source string
	 * @param int Number of chars to return
	 * @param string The searchword to select around
	 * @return string
	 */
	static function _smartSubstr($text, $length, $searchword, &$wordpos)
	{
		$textlen = StringHelper::strlen($text);
		$lsearchword = StringHelper::strtolower($searchword);
		$wordpos = false;
		$pos = 0;
		while ($wordpos === false && $pos < $textlen)
		{
			// Do not try past the text's length
			$offset = min($textlen, $pos + $length);

			if (($wordpos = StringHelper::strpos($text, ' ', $offset)) !== false) {
				$chunk_size = $wordpos - $pos;
			} else {
				$chunk_size = $length;
			}
			$chunk = StringHelper::substr($text, $pos, $chunk_size);
			$wordpos = StringHelper::strpos(StringHelper::strtolower($chunk), $lsearchword);
			if ($wordpos === false) {
				$pos += $chunk_size + 1;
			}
		}

		if ($wordpos !== false) {
			return (($pos > 0) ? '...&nbsp;' : '') . $chunk . '&nbsp;...';
		} else {
			if (($wordpos = @StringHelper::strpos($text, ' ', $length)) !== false) {
				return StringHelper::substr($text, 0, $wordpos) . '&nbsp;...';
			} else {
				return StringHelper::substr($text, 0, $length);
			}
		}
	}

	static function decideCats(& $params)
	{
		global $globalcats;
		
		$display_cat_list = (int) $params->get('display_cat_list', 0);
		$catids = $params->get('catids', array());
		$usesubcats = (int) $params->get('usesubcats', 0);

		// Get user's allowed categories
		if ($display_cat_list)
		{
			$tree = flexicontent_cats::getCategoriesTree();

			// (only) For include mode, use all categories if no cats were selected via configuration
			if ($display_cat_list === 1 && empty($catids))
			{
				global $globalcats;
				$catids = array_keys($globalcats);
			}
		}

		// Find descendants of the categories
		if ($usesubcats)
		{
			$subcats = array();

			foreach ($catids as $catid)
			{
				$subcats = array_merge($subcats, array_map('trim', explode(',', $globalcats[$catid]->descendants)) );
			}

			$catids = array_unique($subcats);
		}
		
		switch ($display_cat_list)
		{
			// Include method
			case 1:
				$allowedtree = array();

				foreach ($catids as $catid)
				{
					$allowedtree[$catid] = $tree[$catid];
				}
				break;

			// Exclude method
			case 2:
				foreach ($catids as $catid)
				{
					unset($tree[$catid]);
				}
				$allowedtree = & $tree;
				break;

			// Not using category selector
			case 0:
			default:
				$allowedtree = array();
				break;
		}

		return $allowedtree;
	}
}
