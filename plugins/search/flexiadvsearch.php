<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2011 flexicontent.org
 * @license GNU/GPL v3
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

//Load the Plugin language file out of the administration
JPlugin::loadLanguage( 'plg_search_flexisearch', JPATH_ADMINISTRATOR);

/**
 * @return array An array of search areas
 */
if(!function_exists("plgSearchFlexiContentAreas")) {
	function &plgSearchFlexiContentAreas() {
		static $areas = array(
		'flexicontent' => 'FLEXICONTENT'
		);
		return $areas;
	}
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
function plgSearchFlexiadvsearch( $text, $phrase='', $ordering='', $areas=null )
{
	$mainframe = &JFactory::getApplication();

	$db		= & JFactory::getDBO();
	$user	= & JFactory::getUser();
	
	// Get the WHERE and ORDER BY clauses for the query
	$params 	= & $mainframe->getParams('com_flexicontent');
	
	if($cantypes = $params->get('cantypes', 1)) {
		$fieldtypes = JRequest::getVar('fieldtypes', array());
	}
	if(!$cantypes || (count($fieldtypes)<=0)) {
		$fieldtypes = $params->get('fieldtypes', array());
	}
	if((count($fieldtypes)>0) && !is_array($fieldtypes)) $fieldtypes = array($fieldtypes);
	$dispatcher =& JDispatcher::getInstance();

	// define section
	if (!defined('FLEXI_SECTION')) define('FLEXI_SECTION', $params->get('flexi_section'));

	// show unauthorized items
	$show_noauth = $params->get('show_noauth', 0);

	require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
	require_once(JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_search'.DS.'helpers'.DS.'search.php');

	if (is_array( $areas )) {
		if (!array_intersect( $areas, array_keys( plgSearchFlexiContentAreas() ) )) {
			return array();
		}
	}

	// load plugin params info
	$plugin =& JPluginHelper::getPlugin('search', 'flexiadvsearch');
	$pluginParams = new JParameter( $plugin->params );

	// shortcode of the site active language (joomfish)
	$lang 		= JRequest::getWord('lang', '' );

	//$limit 			= $pluginParams->def( 'search_limit', 50 );
	$limit 			= $pluginParams->get( 'search_limit', 50 );
	$limitstart = JRequest::getVar('limitstart', 0);
	//$filter_lang 	= $pluginParams->def( 'filter_lang', 1 );
	$filter_lang 	= $pluginParams->get( 'filter_lang', 1 );
	//$browsernav 	= (int)$pluginParams->def( 'browsernav', 2 );
	$browsernav 	= (int)$pluginParams->get( 'browsernav', 2 );

	// Dates for publish up & down items
	$nullDate = $db->getNullDate();
	$date =& JFactory::getDate();
	$now = $date->toMySQL();

	$text = trim( $text );
	JRequest::setVar('title', array($text));
	/*if ( $text == '' ) {	return array();	} */
	
	$searchFlexicontent = JText::_( 'FLEXICONTENT' );
	if($text!='') {
		$text = $db->getEscaped($text);
		switch ($phrase) {
			case 'exact':
				//$text		= $db->Quote( '"'.$db->getEscaped( $text, true ).'"', false );
				$text		= $db->Quote( '"'.$db->getEscaped( $text, false ).'"', false );
				$where	 	= ' MATCH (ie.search_index) AGAINST ('.$text.' IN BOOLEAN MODE)';
				break;

			case 'all':
				$words = explode( ' ', $text );
				$newtext = '+' . implode( ' +', $words );
				//$text		= $db->Quote( $db->getEscaped( $newtext, true ), false );
				$text		= $db->Quote( $db->getEscaped( $newtext, false ), false );
				$where	 	= ' MATCH (ie.search_index) AGAINST ('.$text.' IN BOOLEAN MODE)';
				break;
			case 'any':
			default:
				//$text		= $db->Quote( $db->getEscaped( $text, true ), false );
				$text		= $db->Quote( $db->getEscaped( $text, false ), false );
				$where	 	= ' MATCH (ie.search_index) AGAINST ('.$text.' IN BOOLEAN MODE)';
				break;
		}
	} else $where = '0';

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
	
	// Select only items user has access to if he is not allowed to show unauthorized items
	$joinaccess	= '';
	$andaccess	= '';
	if (!$show_noauth) {
		if (FLEXI_J16GE) {
			$aid_arr = $user->getAuthorisedViewLevels();
			$aid_list = implode(",", $aid_arr);
			$andaccess  .= ' AND c.access IN ('.$aid_list.')';
			$andaccess  .= ' AND a.access IN ('.$aid_list.')';
		} else if (FLEXI_ACCESS) {
			$aid = (int) $user->get('aid');
			$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
			$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON a.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
			$andaccess	.= ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. (int) $aid . ')';
			$andaccess  .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR a.access <= '. (int) $aid . ')';
		} else {
			$andaccess  .= ' AND c.access <= '.$gid;
			$andaccess  .= ' AND a.access <= '.$gid;
		}
	}

	// filter by active language
	$andlang = '';
	if ((FLEXI_FISH || FLEXI_J16GE) && $filter_lang) {
		$andlang .= ' AND ( ie.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
	}
	$fieldtypes_str = "'".implode("','", $fieldtypes)."'";
	$search_fields = $params->get('search_fields', '');
	$search_fields = "'".str_replace(",", "','", $search_fields)."','title'";
	$query = "SELECT f.* " //f.id,f.field_type,f.name,f.label,f.attribs" // .", fir.value,fir.item_id"
		//." FROM #__flexicontent_fields_item_relations as fir "
		//." JOIN #__flexicontent_fields_type_relations as ftr ON f.id=ftr.field_id"
		//." LEFT JOIN #__flexicontent_fields as f ON f.id=fir.field_id"
		." FROM #__flexicontent_fields as f " //." ON f.id=fir.field_id"
		//." WHERE f.published='1' AND f.isadvsearch='1' AND ftr.type_id IN({$fieldtypes_str})"
		." WHERE f.published='1' AND f.isadvsearch='1' AND f.name IN({$search_fields})"
		//." GROUP BY fir.field_id,fir.item_id"
	;
	$db->setQuery($query);
	$fields = $db->loadObjectList();
	
	$fields = is_array($fields)?$fields:array();
	$CONDITION = '';
	$OPERATOR = JRequest::getVar('operator', 'OR');
	$FOPERATOR = JRequest::getVar('foperator', 'OR');
	$items = array();
	$resultfields = array();
	if (FLEXI_J16GE) {
		$custom = JRequest::getVar('custom', array());
	}
	JPluginHelper::importPlugin( 'flexicontent_fields');
	$foundfields = array();
	foreach($fields as $field) {
		// Once per (advanced searchable) field TYPE
		$field->parameters = new JParameter($field->attribs);
		$fieldsearch = JRequest::getVar($field->name, array());
		$fieldsearch = is_array($fieldsearch)?$fieldsearch:array(trim($fieldsearch));
		if(isset($fieldsearch[0]) && (strlen(trim($fieldsearch[0]))>0)) {
			$foundfields[$field->id] = array();
			//var_dump($field->id, $fieldsearch[0]);echo "<br />";
			$fieldsearch = $fieldsearch[0];
			//echo $fieldsearch ."<br>";
			$fieldsearch = explode(" ", $fieldsearch);
			$dispatcher->trigger( 'onFLEXIAdvSearch', array(&$field, $fieldsearch));
			if(isset($field->results) && (count($field->results)>0)) {
				//echo "<pre>"; print_r($results);echo "</pre>"; 
				foreach($field->results as $r) {
					if($r) {
						$items[] = $r->item_id;
						$foundfields[$field->id][] = $r->item_id;
						$resultfields[$r->item_id][] = $r;
					}
				}
			}
		}
	}
	
	if(count($items)) {
		if($FOPERATOR=='OR') {
			$items = array_unique($items);
			$items = "'".implode("','", $items)."'";
			$CONDITION = " {$OPERATOR} a.id IN ({$items}) ";
		}else{
			$codestr = "\$items = array_intersect(";
			$codestr_a = array();
			foreach($foundfields as $k=>$a) {
				$codestr_a[] = "\$foundfields[{$k}]";
			}
			$codestr .= implode(", ", $codestr_a);
			$codestr .= ");";
			$items = array();
			if(count($codestr_a)==1) {
				$items = $foundfields[$k];
				$items = "'".implode("','", $foundfields[$k])."'";
			}elseif(count($codestr_a)>1) {
				eval($codestr);
				$items = "'".implode("','", $items)."'";
			}
			$CONDITION = " {$OPERATOR} a.id IN ({$items}) ";
		}
	}
	$query 	= 'SELECT DISTINCT a.id,a.title AS title, a.sectionid,'
		. ' a.created AS created,'
		. ' ie.search_index AS text,'
		. ' "'.$browsernav.'" AS browsernav,'
		. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
		. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug,'
		. ' CONCAT_WS( " / ", '. $db->Quote($searchFlexicontent) .', c.title, a.title ) AS section'
		. ' FROM #__content AS a'
		. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = a.id'
		. ' LEFT JOIN #__categories AS c ON c.id = a.catid'
		. $joinaccess
		. ' WHERE '
		. '('
		. '( '.$where.' )'
		. $CONDITION
		. ')'
		. ' AND a.state IN (1, -5)'
		. ' AND c.published = 1'
		. ' AND ( a.publish_up = '.$db->Quote($nullDate).' OR a.publish_up <= '.$db->Quote($now).' )'
		. ' AND ( a.publish_down = '.$db->Quote($nullDate).' OR a.publish_down >= '.$db->Quote($now).' )'
		. $andaccess
		. $andlang
		. (count($fieldtypes)?" AND ie.type_id IN ({$fieldtypes_str})":"")
		. ' ORDER BY '. $order
	;
	$db->setQuery( $query, $limitstart, $limit );
	//$db->setQuery( $query );
	$list = $db->loadObjectList();
	if(isset($list)) {
		foreach($list as $key => $row) {
			if($row->sectionid==FLEXI_SECTION) {
				if(isset($resultfields[$row->id])) {
					foreach($resultfields[$row->id] as $r) {
						$list[$key]->text .= "[br /]".$r->label.":[span=highlight]".$r->value."[/span]";
					}
				}
				$list[$key]->href = JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug));
			}else
				$list[$key]->href = JRoute::_(ContentHelperRoute::getArticleRoute($row->slug, $row->catslug, $row->sectionid));
		}
	}
	return $list;
}

$jAp=& JFactory::getApplication();
$jAp->registerEvent( 'onSearch', 'plgSearchFlexiadvsearch' );
$jAp->registerEvent( 'onSearchAreas', 'plgSearchFlexiContentAreas' );
?>
