<?php
/**
 * @version 1.0 $Id: flexisearch.php 29 2009-06-19 19:51:29Z vistamedia $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

$mainframe->registerEvent( 'onSearch', 'plgSearchFlexisearch' );
$mainframe->registerEvent( 'onSearchAreas', 'plgSearchFlexisearchAreas' );

//Load the Plugin language file out of the administration
JPlugin::loadLanguage( 'plg_search_flexisearch', JPATH_ADMINISTRATOR);

/**
 * @return array An array of search areas
 */
function &plgSearchFlexisearchAreas() {
	static $areas = array(
	'flexicontent' => 'FLEXICONTENT'
	);
	return $areas;
}

/**
 * Search method
 *
 * The sql must return the following fields that are
 * used in a common display routine: href, title, section, created, text,
 * browsernav
 * @param string Target search string
 * @param string mathcing option, exact|any|all
 * @param string ordering option, newest|oldest|popular|alpha|category
 * @param mixed An array if restricted to areas, null if search all
 */
function plgSearchFlexisearch( $text, $phrase='', $ordering='', $areas=null )
{
	$db		=& JFactory::getDBO();
	$user	=& JFactory::getUser();

	require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

	if (is_array( $areas )) {
		if (!array_intersect( $areas, array_keys( plgSearchFlexisearchAreas() ) )) {
			return array();
		}
	}

	// load plugin params info
	$plugin =& JPluginHelper::getPlugin('search', 'flexisearch');
	$pluginParams = new JParameter( $plugin->params );

	$limit = $pluginParams->def( 'search_limit', 50 );

	$text = trim( $text );
	if ( $text == '' ) {
		return array();
	}

	$text = $db->getEscaped($text);

	$searchFlexicontent = JText::_( 'FLEXICONTENT' );

	switch ($phrase) {
		case 'exact':
			$text		= $db->Quote( '"'.$db->getEscaped( $text, true ).'"', false );
			$where	 	= ' MATCH (ie.search_index) AGAINST ('.$text.' IN BOOLEAN MODE)';
			break;

		case 'all':
			$words = explode( ' ', $text );
			$newtext = '';
			foreach ($words as $word) {
				$newtext .= '+' . $word . ' ';
			}
			$text		= $db->Quote( $db->getEscaped( $newtext, true ), false );
			$where	 	= ' MATCH (ie.search_index) AGAINST ('.$text.' IN BOOLEAN MODE)';
			break;
		case 'any':
		default:
			$text		= $db->Quote( $db->getEscaped( $text, true ), false );
			$where	 	= ' MATCH (ie.search_index) AGAINST ('.$text.' IN BOOLEAN MODE)';
			break;
	}

	switch ( $ordering ) {
		case 'oldest':
			$order = 'a.created ASC';
			break;

		case 'popular':
			$order = 'a.hits DESC';
			break;

		case 'alpha':
			$order = 'a.title ASC';
			break;

		case 'category':
			$order = 'c.title ASC, a.title ASC';
			break;

		case 'newest':
		default:
			$order = 'a.created DESC';
			break;
	}
	
	$query = 'SELECT DISTINCT a.title AS title,'
	. ' a.created AS created,'
	. ' ie.search_index AS text,'
	. ' "2" AS browsernav,'
	. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
	. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug,'
	. ' CONCAT_WS( " / ", '. $db->Quote($searchFlexicontent) .', c.title, a.title ) AS section'
	. ' FROM #__content AS a'
	. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = a.id'
	. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id'
	. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
	. ' WHERE ( '.$where.' )'
	. ' AND a.state IN (1, -4)'
	. ' AND c.published = 1'
	. ' AND c.access <= '.(int) $user->get('aid')
	. ' AND a.access <= '.(int) $user->get('aid')
	. ' GROUP BY a.id'
	. ' ORDER BY '. $order
	;

	$db->setQuery( $query, 0, $limit );
	$list = $db->loadObjectList();
	
	if(isset($list))
	{
		foreach($list as $key => $row) {
			$list[$key]->href = FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug);
		}
	}

	return $list;
}
?>