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

jimport( 'joomla.plugin.plugin' );

require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_search'.DS.'helpers'.DS.'search.php');


/**
 * FLEXIcontent Advanced Search plugin
 *
 * @package		Joomla.Plugin
 * @subpackage	Search.contacts
 * @since		1.6
 */
class plgSearchFlexiadvsearch extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @since       1.5
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$extension_name = 'plg_search_flexiadvsearch';
		//$this->loadLanguage();
		//$this->loadLanguage( '$extension_name, JPATH_ADMINISTRATOR);
		JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, 'en-GB'	, true);
		JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, null		, true);
	}

	/**
	* @return array An array of search areas
	*/
	function onContentSearchAreas() {
		static $areas = array(
		'flexicontent' => 'FLEXICONTENT'
		);
		return $areas;
	}
	
	// Also add J1.5 function signature
	function onSearchAreas() { return $this->onContentSearchAreas(); }

	/**
	 * Search method
	 *
	 * The sql must return the following fields that are
	 * used in a common display routine: href, title, section, created, text,
	 * browsernav
	 * @param string Target search string
	 * @param string matching option, exact|any|all
	 * @param string ordering option, newest|oldest|popular|alpha|category
	 * @param mixed An array if restricted to areas, null if search all
	 */
	function onContentSearch( $text, $phrase='', $ordering='', $areas=null )
	{
		$app = &JFactory::getApplication();
		
		$db		= & JFactory::getDBO();
		$user	= & JFactory::getUser();
		$menus    = & JSite::getMenu();
		$menu     = $menus->getActive();
		
		// Get the PAGE/COMPONENT parameters (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
		$params = clone($app->isSite()  ?  $app->getParams('com_flexicontent')  : JComponentHelper::getParams('com_flexicontent'));
		
		if ($menu) {
			$menuParams = new JParameter($menu->params);
			// In J1.6+ the above function does not merge current menu item parameters,
			// it behaves like JComponentHelper::getParams('com_flexicontent') was called
			if (FLEXI_J16GE) $params->merge($menuParams);
		}
		
		// ***********************************************
		// Create WHERE and ORDER BY clauses for the query
		// ***********************************************
		
		if($cantypes = $params->get('cantypes', 1)) {
			$contenttypes = JRequest::getVar('contenttypes', array());
		}
		if(!$cantypes || (count($contenttypes)<=0)) {
			$contenttypes = $params->get('contenttypes', array());
		}
		if((count($contenttypes)>0) && !is_array($contenttypes)) $contenttypes = array($contenttypes);
		//$dispatcher =& JDispatcher::getInstance();
		
		// define section
		if (!defined('FLEXI_SECTION')) define('FLEXI_SECTION', $params->get('flexi_section'));
		
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);
		
		if (is_array( $areas )) {
			if (!array_intersect( $areas, array_keys( $this->onContentSearchAreas() ) )) {
				return array();
			}
		}
		
		// load plugin params info
		$plugin =& JPluginHelper::getPlugin('search', 'flexiadvsearch');
		$pluginParams = new JParameter( $plugin->params );
		
		// shortcode of the site active language (joomfish)
		$cntLang = substr(JFactory::getLanguage()->getTag(), 0,2);  // Current Content language (Can be natively switched in J2.5)
		$urlLang  = JRequest::getWord('lang', '' );                 // Language from URL (Can be switched via Joomfish in J1.5)
		$lang = (FLEXI_J16GE || empty($urlLang)) ? $cntLang : $urlLang;
		
		$limit        = $pluginParams->get( 'search_limit', 50 );
		$limitstart   = JRequest::getVar('limitstart', 0);
		$filter_lang 	= $pluginParams->get( 'filter_lang', 1 );
		$browsernav 	= (int)$pluginParams->get( 'browsernav', 2 );
		
		// Dates for publish up & down items
		$nullDate = $db->getNullDate();
		$date =& JFactory::getDate();
		$now = $date->toMySQL();
		
		$text = trim( $text );
		
		// FORCE LOGICAL 'AND' between (a) text search and (b) search filters.
		// If no text search words are given, then force operator to be OR to ignore text search having no results
		$SEARCH_FILTERS_OP_TEXT_SEARCH = strlen($text) ? 'AND' : 'OR';
		
		// Require any OR all Filters ... this can be user selectable, but just to be insane check if it is allowed to the user
		$show_filtersop = $params->get('show_filtersop', 1);
		$default_filtersop = $params->get('default_filtersop', 'all');
		$FILTERSOP = !$show_filtersop ? $default_filtersop : JRequest::getVar('filtersop', $default_filtersop);
		
		if ( strlen($text)==0 ) {$SEARCH_FILTERS_OP_TEXT_SEARCH = 'OR';}
		
		$searchFlexicontent = JText::_( 'FLEXICONTENT' );
		if($text!='') {
			$text = $db->getEscaped($text);
			switch ($phrase)
			{
				case 'exact':
					$text		= $db->Quote( '"'.$db->getEscaped( $text, false ).'"', false );
					$where	 	= ' MATCH (ie.search_index) AGAINST ('.$text.' IN BOOLEAN MODE)';
					break;
				
				case 'all':
					$words = explode( ' ', $text );
					$newtext = '+' . implode( ' +', $words );
					$text		= $db->Quote( $db->getEscaped( $newtext, false ), false );
					$where	 	= ' MATCH (ie.search_index) AGAINST ('.$text.' IN BOOLEAN MODE)';
					break;
				
				case 'any':
				default:
					$text		= $db->Quote( $db->getEscaped( $text, false ), false );
					$where	 	= ' MATCH (ie.search_index) AGAINST ('.$text.' IN BOOLEAN MODE)';
					break;
			}
		} else {
			$where = '0';
		}
		
		switch ( $ordering )
		{
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
		if (	$app->isSite() &&
					( FLEXI_FISH || (FLEXI_J16GE && $app->getLanguageFilter()) ) &&
					$filter_lang
		) {
			$andlang .= ' AND ( ie.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		}
		$contenttypes_str = "'".implode("','", $contenttypes)."'";
		$search_fields = $params->get('search_fields', '');
		$search_fields = "'".preg_replace("/\s*,\s*/u", "','", $search_fields)."'";
		$query = "SELECT f.* "
			//." FROM #__flexicontent_fields_item_relations as fir "
			//." JOIN #__flexicontent_fields_type_relations as ftr ON f.id=ftr.field_id"
			//." LEFT JOIN #__flexicontent_fields as f ON f.id=fir.field_id"
			." FROM #__flexicontent_fields as f " //." ON f.id=fir.field_id"
			//." WHERE f.published='1' AND f.isadvsearch='1' AND ftr.type_id IN({$contenttypes_str})"
			." WHERE f.published='1' AND f.isadvsearch='1' AND f.name IN({$search_fields})"
			//." GROUP BY fir.field_id,fir.item_id"
		;
		$db->setQuery($query);
		$fields = $db->loadObjectList();
		$fields = is_array($fields) ? $fields : array();
		
		$CONDITION = '';
		$items = array();
		$field_results_arr = array();
		if (FLEXI_J16GE) {
			$custom = JRequest::getVar('custom', array());
		}
		
		
		// *************************************************************************************************
		// Once per (advanced searchable) field TYPE we will search for ITEMs having specified field value(s)
		// *************************************************************************************************
		
		//JPluginHelper::importPlugin( 'flexicontent_fields');
		$fields_matched_arr = array();
		foreach($fields as $field)
		{
			$field->parameters = new JParameter($field->attribs);
			
			//echo "<br/>Field name:". $field->name;
			
			// Call advanced search 
			$fieldname = $field->iscore ? 'core' : $field->field_type;
			FLEXIUtilities::call_FC_Field_Func($fieldname, 'onFLEXIAdvSearch', array( &$field ));
			
			// A not SET results property, indicates field filter was not used in the search, skip it
			if( !isset($field->results) ) continue;
			
			//echo "<pre>"; print_r($field->results);echo "</pre>"; 
			
			// Add field to fields that were used in the search
			$fields_matched_arr[$field->id] = array();
			foreach($field->results as $r) {
				if($r) {
					$items[] = $r->item_id;
					$fields_matched_arr[$field->id][] = $r->item_id;
					$field_results_arr[$r->item_id][] = $r;
				}
			}
		}
		//echo "<pre>foundfields: "; print_r($fields_matched_arr);echo "</pre>";
		//echo "<pre>foundfields: "; print_r($fields_matched_arr);echo "</pre>";
		//echo "<pre>resultfields: "; print_r($field_results_arr);echo "</pre>";
		
		if ( count($items) )
		{
			if ($FILTERSOP == 'OR') {
				$items = array_unique($items);
				$items = "'".implode("','", $items)."'";
				$CONDITION = " {$SEARCH_FILTERS_OP_TEXT_SEARCH} a.id IN ({$items}) ";
			} else {
				$itemid_arr = array();
				
				// Count number of time each item was matched by search filters
				foreach($fields_matched_arr as $fieldid => $itemid_matched_arr) {
					foreach ($itemid_matched_arr as $itemid_matched) 
						$itemid_match_count[$itemid_matched] = !isset($itemid_match_count[$itemid_matched]) ? 1 : $itemid_match_count[$itemid_matched] + 1;
				}
				
				// Only accept items that were matched by all search filters
				$ffcount = count($fields_matched_arr);
				$items = array();
				foreach ($itemid_match_count as $itemid_matched => $infields_count) {
					if ($infields_count == $ffcount) $items[] = $itemid_matched;
				}
				$items = "'".implode("','", $items)."'";
				$CONDITION = " {$SEARCH_FILTERS_OP_TEXT_SEARCH} a.id IN ({$items}) ";
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
			. (count($contenttypes)?" AND ie.type_id IN ({$contenttypes_str})":"")
			. ' ORDER BY '. $order
		;
		$db->setQuery( $query, $limitstart, $limit );
		//$db->setQuery( $query );
		$list = $db->loadObjectList();
		if(isset($list)) {
			foreach($list as $key => $row) {
				$list[$key]->fields_text = '';
				if( FLEXI_J16GE || $row->sectionid==FLEXI_SECTION ) {
					if(isset($field_results_arr[$row->id])) {
						foreach($field_results_arr[$row->id] as $r) {
							//$list[$key]->text .= "[br /]".$r->label.":[span=highlight]".$r->value."[/span]";
							$list[$key]->fields_text .= "[br /]".$r->label.":[span=highlight]".$r->value."[/span]";
						}
					}
					$list[$key]->href = JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug));
				} else {
					$list[$key]->href = JRoute::_(ContentHelperRoute::getArticleRoute($row->slug, $row->catslug, $row->sectionid));
				}
			}
		}
		return $list;
	}
	
	// Also add J1.5 function signature
	function onSearch( $text, $phrase='', $ordering='', $areas=null )
	{
		return $this->onContentSearch( $text, $phrase, $ordering, $areas );
	}
}



// When not having exactly named CLASS function, but in J1.5 (only) the triggerEvent() checks if functions being registered
// as Event Listener methods, exist outside the class, so we must define wrapper classes outside the class,
// these can be used by triggerEvent and will only contain a call to the respective class method

// A different approach is to create wrapper class methods, that have the name of the event, we did this above

/*if (!function_exists('onContentSearchAreas')) {
	function onContentSearchAreas() {
		//return plgSearchFlexiadvsearch::onContentSearchAreas();
	}
}

if (!function_exists('onContentSearch')) {
	function onContentSearch( $text, $phrase='', $ordering='', $areas=null ) {
		//return plgSearchFlexiadvsearch::onContentSearch( $text, $phrase, $ordering, $areas );
	}
}

$app = JFactory::getApplication();
if (!FLEXI_J16GE) {
	$app->registerEvent( 'onSearchAreas', 'onContentSearchAreas' );
	$app->registerEvent( 'onSearch', 'onContentSearch');
}*/

?>