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

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

jimport('cms.plugin.plugin');

require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

FLEXI_J40GE
	? require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'src'.DS.'Helper'.DS.'RouteHelper.php')
	: require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
if (!FLEXI_J40GE) require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_search'.DS.'helpers'.DS.'search.php');

require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

/**
 * Content Search plugin
 *
 * @package		FLEXIcontent.Plugin
 * @subpackage	Search.flexiadvsearch
 * @since		1.6
 */
class plgSearchFlexiadvsearch extends JPlugin
{
	var $autoloadLanguage = false;

	/**
	 * Constructor
	 *
	 * @access      public
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @since       1.5
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		static $language_loaded = null;
		if (!$this->autoloadLanguage && $language_loaded === null) $language_loaded = JPlugin::loadLanguage('plg_search_flexiadvsearch', JPATH_ADMINISTRATOR);

		// Get the COMPONENT only parameter
		$this->_params = new JRegistry();
		$this->_params->merge(JComponentHelper::getParams('com_flexicontent'));

		// Merge the active menu parameters
		$menu = JFactory::getApplication()->getMenu()->getActive();

		if ($menu)
		{
			$this->_params->merge($menu->getParams());
		}
	}


	/**
	* @return array An array of search areas
	*/
	public function onContentSearchAreas()
	{
		static $areas = array
		(
			'flexicontent' => 'FLEXICONTENT'
		);

		return $areas;
	}


	/**
	 * Search method
	 *
	 * The sql must return the following fields that are used in a common display routine:
	 *
	 *   href, title, section, created, text, browsernav
	 *
	 * @param string Target search string
	 * @param string matching option, natural|natural_expanded|exact|any|all
	 * @param string ordering option, newest|oldest|popular|alpha|category
	 * @param mixed An array if restricted to areas, null if search all
	 */
	public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null)
	{
		// Initialize variables
		$app      = JFactory::getApplication();
		$jinput   = JFactory::getApplication()->input;

		$option = $jinput->getCmd('option', '');
		$view   = $jinput->getCmd('view', '');

		$db       = JFactory::getDbo();
		$user     = JFactory::getUser();

		$app->setUserState('fc_view_total_'.$view, 0);
		$app->setUserState('fc_view_limit_max_'.$view, 0);

		// Check if not requested search areas, inside this search areas of this plugin
		if ( is_array($areas) && !array_intersect($areas, array_keys($this->onContentSearchAreas())) )
		{
			return array();
		}


		// Get parameters
		$params  = $this->_params;

		// some parameter shortcuts for SQL query
		$show_noauth  = $params->get('show_noauth', 0);
		$orderby_override = $params->get('orderby_override', 0);
		$orderby_override_2nd = $params->get('orderby_override_2nd', 0);
		$search_prefix = $params->get('add_search_prefix') ? 'vvv' : '';


		/**
		 * Some parameter shortcuts common among search view and advanced search plugin
		 */

		$canseltypes = (int) $params->get('canseltypes', 1);
		$txtmode     = (int) $params->get('txtmode', 0);  // 0: BASIC Index, 1: ADVANCED Index without search fields user selection, 2: ADVANCED Index with search fields user selection

		// Get if text searching according to specific (single) content type
		$show_txtfields = (int) $params->get('show_txtfields', 1);  // 0: hide, 1: according to content, 2: use custom configuration
		$show_txtfields = !$txtmode ? 0 : $show_txtfields;  // disable this flag if using BASIC index for text search

		// Get if filtering according to specific (single) content type
		$show_filters   = (int) $params->get('show_filters', 1);  // 0: hide, 1: according to content, 2: use custom configuration

		// Force single type selection and showing the content type selector
		$type_based_search = $show_filters === 1 || $show_txtfields === 1;
		$canseltypes = $type_based_search ? 1 : $canseltypes;


		/**
		 * Get Content Types allowed for user selection in the Search Form
		 * Also retrieve their configuration, plus the currently selected types
		 */

		// Get them from configuration
		$contenttypes = $params->get('contenttypes', array(), 'array');

		// Sanitize them as integers and as an array
		$contenttypes = ArrayHelper::toInteger($contenttypes);

		// Make sure these are unique too
		$contenttypes = array_unique($contenttypes);

		// Check for zero content types (can occur during sanitizing content ids to integers)
		foreach($contenttypes as $i => $v)
		{
			if (!$contenttypes[$i])
			{
				unset($contenttypes[$i]);
			}
		}

		// Force hidden content type selection if only 1 content type was initially configured
		$canseltypes = count($contenttypes) === 1 ? 0 : $canseltypes;
		$params->set('canseltypes', $canseltypes);  // SET "type selection FLAG" back into parameters

		// Type data and configuration (parameters), if no content types specified then all will be retrieved
		$typeData = flexicontent_db::getTypeData($contenttypes);
		$contenttypes = array();

		foreach($typeData as $tdata)
		{
			$contenttypes[] = $tdata->id;
		}

		// Get Content Types to use either those currently selected in the Search Form, or those hard-configured in the search menu item
		if ($canseltypes)
		{
			// Get them from user request data
			$form_contenttypes = $jinput->get('contenttypes', array(), 'array');

			// Sanitize them as integers and as an array
			$form_contenttypes = ArrayHelper::toInteger($form_contenttypes);

			// Make sure these are unique too
			$form_contenttypes = array_unique($form_contenttypes);

			// Check for zero content type (can occur during sanitizing content ids to integers)
			foreach($form_contenttypes as $i => $v)
			{
				if (!$form_contenttypes[$i])
				{
					unset($form_contenttypes[$i]);
				}
			}

			// Limit to allowed item types (configuration) if this is empty
			$form_contenttypes = array_intersect($contenttypes, $form_contenttypes);

			// If we found some allowed content types then use them otherwise keep the configuration defaults
			if (!empty($form_contenttypes))
			{
				$contenttypes = $form_contenttypes;
			}
		}

		// Type based seach, get a single content type (first one, if more than 1 were given ...)
		if ($type_based_search && $canseltypes && !empty($form_contenttypes))
		{
			$single_contenttype = reset($form_contenttypes);
			$contenttypes = $form_contenttypes = array($single_contenttype);
		}
		else
		{
			$single_contenttype = false;
		}



		/**
		 * Text Search Fields of the search form
		 */

		if (!$txtmode)
		{
			$txtflds = array();
			$fields_text = array();
		}

		else
		{
			$txtflds = '';

			if ($show_txtfields === 1)
			{
				$txtflds = $single_contenttype
					? $typeData[$single_contenttype]->params->get('searchable', '')
					: '';
			}
			elseif ($show_txtfields)
			{
				$txtflds = $params->get('txtflds', '');
			}

			// Sanitize them
			$txtflds = preg_replace("/[\"'\\\]/u", "", $txtflds);
			$txtflds = array_unique(preg_split("/\s*,\s*/u", $txtflds));
			if ( !strlen($txtflds[0]) ) unset($txtflds[0]);

			// Create a comma list of them
			$txtflds_list = count($txtflds) ? "'".implode("','", $txtflds)."'" : '';

			// Retrieve field properties/parameters, verifying the support to be used as Text Search Fields
			// This will return all supported fields if field limiting list is empty
			$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', $txtflds_list, $contenttypes, $load_params=true, 0, 'search');

			// If all entries of field limiting list were invalid, get ALL
			if (empty($fields_text))
			{
				if (!empty($contenttypes))
				{
					$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'search');
				}
				else
				{
					$fields_text = array();
				}
			}
		}


		/**
		 * Filter Fields of the search form
		 */

		// Get them from type configuration or from search menu item
		$filtflds = '';

		if ($show_filters === 1)
		{
			$filtflds = $single_contenttype
				? $typeData[$single_contenttype]->params->get('filters', '')
				: '';
		}
		elseif ($show_filters)
		{
			$filtflds = $params->get('filtflds', '');
		}

		// Sanitize them
		$filtflds = preg_replace("/[\"'\\\]/u", "", $filtflds);
		$filtflds = array_unique(preg_split("/\s*,\s*/u", $filtflds));

		foreach ($filtflds as $i => $v)
		{
			if (!$v)
			{
				unset($filtflds[$i]);
			}
		}

		// Create a comma list of them
		$filtflds_list = count($filtflds) ? "'" . implode("','", $filtflds) . "'" : '';


		/**
		 * Retrieve field properties/parameters, verifying they support to be used as Filter Fields
		 * This will return all supported fields if field limiting list is empty
		 */

		if (count($filtflds))
		{
			$filters_tmp = FlexicontentFields::getSearchFields($key='name', $indexer='advanced', $filtflds_list, $contenttypes, $load_params=true, 0, 'filter');

			// Use custom order
			$filters = array();

			if ($canseltypes && $show_filters)
			{
				foreach($filtflds as $field_name)
				{
					if (empty($filters_tmp[$field_name]))
					{
						continue;
					}

					$filter_id = $filters_tmp[$field_name]->id;
					$filters[$filter_id] = $filters_tmp[$field_name];
				}
			}

			else
			{
				// Index by filter_id in this case too (for consistency, although we do not use the array index ?)
				foreach( $filters_tmp as $filter)
				{
					$filters[$filter->id] = $filter;
				}
			}

			unset($filters_tmp);
		}


		/**
		 * If configured filters were either not found or were invalid for the current content type(s)
		 * then retrieve all fields marked as filterable for the give content type(s)
		 * this is useful to list per content type filters automatically, even when not set or misconfigured
		 */

		if (empty($filters))
		{
			// If filters are type based and a type was not selected yet, then do not set any filters
			if ($type_based_search && $canseltypes && empty($form_contenttypes))
			{
				$filters = array();
			}

			// Set filters according to currently used content types
			else
			{
				$filters = !empty($contenttypes)
					? FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'filter')
					: array();
			}
		}


		/**
		 * Load Plugin parameters
		 */

		$plugin = JPluginHelper::getPlugin('search', 'flexiadvsearch');
		$pluginParams = new JRegistry($plugin->params);

		// Shortcuts for plugin parameters
		$search_limit    = $params->get( 'search_limit', $pluginParams->get( 'search_limit', 20 ) );      // Limits the returned results of this seach plugin
		$filter_lang     = $params->get( 'filter_lang', $pluginParams->get( 'filter_lang', 1 ) );         // Language filtering enabled
		$search_archived = $params->get( 'search_archived', $pluginParams->get( 'search_archived', 1 ) ); // Include archive items into the search
		$browsernav      = $params->get( 'browsernav', $pluginParams->get( 'browsernav', 2 ) );           // Open search in window (for value 1)


		// ***
		// *** Various other variable USED in the SQL query like (a) current frontend language and (b) -this- plugin specific ordering, (c) null / now dates, (d) etc
		// ***

		// Get current frontend language (fronted user selected)
		$lang = flexicontent_html::getUserCurrentLang();

		// NULL and CURRENT dates,
		// NOTE: the current date needs to use built-in MYSQL function, otherwise filter caching can not work because the CURRENT DATETIME is continuously different !!!
		//$now = JFactory::getDate()->toSql();
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();

		// Section name
		$searchFlexicontent = JText::_( 'FLEXICONTENT' );

		// REMOVED / COMMENTED OUT this feature:
		// Require any OR all Filters ... this can be user selectable
		//$show_filtersop = $params->get('show_filtersop', 1);
		//$default_filtersop = $params->get('default_filtersop', 'all');
		//$FILTERSOP = !$show_filtersop ? $default_filtersop : $app->input->getCmd('filtersop', $default_filtersop);


		// ***
		// *** Create WHERE clause part for Text Search
		// ***

		$select_relevance = array();
		$text_search = $this->_buildTextSearch($text, $phrase, $txtmode, $select_relevance);


		// ***
		// *** Create ORDER clause
		// ***

		// FLEXIcontent search view, use FLEXIcontent ordering
		$orderby_join = '';
		$orderby_col = '';
		if ($app->input->getCmd('option', '') == 'com_flexicontent')
		{
			// Get defaults
			$request_var = $orderby_override || $orderby_override_2nd ? 'orderby' : '';
			$default_order = $app->input->getCmd('filter_order', 'i.title');
			$default_order_dir = $app->input->getCmd('filter_order_Dir', 'ASC');

			$order = '';
			$orderby = flexicontent_db::buildItemOrderBy(
				$params,
				$order, $request_var, $_config_param='orderby',
				$_item_tbl_alias = 'i', $_relcat_tbl_alias = 'rel',
				$default_order, $default_order_dir, $sfx='', $support_2nd_lvl=true
			);

			// Create JOIN for ordering items by a custom field (Level 1)
			if ( 'field' == $order[1] )
			{
				$orderbycustomfieldid = (int)$params->get('orderbycustomfieldid', 0);
				$orderbycustomfieldint = (int)$params->get('orderbycustomfieldint', 0);
				if ($orderbycustomfieldint === 4)
				{
					$orderby_join .= '
						LEFT JOIN (
							SELECT rf.item_id, SUM(fdat.hits) AS file_hits
							FROM #__flexicontent_fields_item_relations AS rf
							LEFT JOIN #__flexicontent_files AS fdat ON fdat.id = rf.value
					 		WHERE rf.field_id='.$orderbycustomfieldid.'
					 		GROUP BY rf.item_id
					 	) AS dl ON dl.item_id = i.id';
				}
				else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$orderbycustomfieldid;
			}

			if ( 'custom:' == substr($order[1], 0, 7) )
			{
				$order_parts = preg_split("/:/", $order[1]);
				$_field_id = (int) @ $order_parts[1];
				$_o_method = @ $order_parts[2];

				if ($_field_id && count($order_parts) === 4)
				{
					if ($_o_method=='file_hits')
					{
						$orderby_join .= '
							LEFT JOIN (
								SELECT rf.item_id, SUM(fdat.hits) AS file_hits
								FROM #__flexicontent_fields_item_relations AS rf
								LEFT JOIN #__flexicontent_files AS fdat ON fdat.id = rf.value
						 		WHERE rf.field_id='.$_field_id.'
						 		GROUP BY rf.item_id
						 	) AS dl ON dl.item_id = i.id';
					}
					else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$_field_id;
				}
			}

			// Create JOIN for ordering items by a custom field (Level 2)
			if ( 'field' == $order[2] )
			{
				$orderbycustomfieldid_2nd = (int)$params->get('orderbycustomfieldid'.'_2nd', 0);
				$orderbycustomfieldint_2nd = (int)$params->get('orderbycustomfieldint'.'_2nd', 0);
				if ($orderbycustomfieldint_2nd === 4)
				{
					$orderby_join .= '
						LEFT JOIN (
							SELECT f2.item_id, SUM(fdat2.hits) AS file_hits2
							FROM #__flexicontent_fields_item_relations AS f2
							LEFT JOIN #__flexicontent_files AS fdat2 ON fdat2.id = f2.value
					 		WHERE f2.field_id='.$orderbycustomfieldid_2nd.'
					 		GROUP BY f2.item_id
					 	) AS dl2 ON dl2.item_id = i.id';
				}
				else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$orderbycustomfieldid_2nd;
			}

			if ( 'custom:' == substr($order[2], 0, 7) )
			{
				$order_parts = preg_split("/:/", $order[2]);
				$_field_id = (int) @ $order_parts[1];
				$_o_method = @ $order_parts[2];

				if ($_field_id && count($order_parts) === 4)
				{
					if ($_o_method=='file_hits')
					{
						$orderby_join .= '
							LEFT JOIN (
								SELECT f2.item_id, SUM(fdat2.hits) AS file_hits2
								FROM #__flexicontent_fields_item_relations AS f2
								LEFT JOIN #__flexicontent_files AS fdat2 ON fdat2.id = f2.value
						 		WHERE f2.field_id='.$_field_id.'
						 		GROUP BY f2.item_id
						 	) AS dl2 ON dl2.item_id = i.id';
					}
					else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$_field_id;
				}
			}

			// Create JOIN for ordering items by author's name
			if ( in_array('author', $order) || in_array('rauthor', $order) )
			{
				$orderby_col = '';
				$orderby_join .= ' LEFT JOIN #__users AS u ON u.id = i.created_by';
			}

			// Create JOIN for ordering items by a most commented
			if ( in_array('commented', $order) )
			{
				$orderby_col   = ', COUNT(DISTINCT com.id) AS comments_total';
				$orderby_join .= ' LEFT JOIN #__jcomments AS com ON com.object_id = i.id AND com.object_group="com_flexicontent" AND com.published="1"';
			}

			// Create JOIN for ordering items by a most rated
			if ( in_array('rated', $order) )
			{
				$rating_join = null;
				$orderby_col   = ', ' . flexicontent_db::buildRatingOrderingColumn($rating_join);
				$orderby_join .= ' LEFT JOIN ' . $rating_join;
			}

			// Create JOIN for ordering items by their ordering attribute (in item's main category)
			if ( in_array('order', $order) )
			{
				$orderby_join .= ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id AND rel.catid = i.catid';
			}
		}

		// non-FLEXIcontent search view, use general ordering of search plugins (this is a parameter passed to this onContentSearch() function)
		else
		{
			switch ( $ordering )
			{
				//case 'relevance': $orderby = ' ORDER BY score DESC, i.title ASC'; break;
				case 'oldest':   $orderby = 'i.created ASC'; break;
				case 'popular':  $orderby = 'i.hits DESC'; break;
				case 'alpha':    $orderby = 'i.title ASC'; break;
				case 'category': $orderby = 'c.title ASC, i.title ASC'; break;
				case 'newest':   $orderby = 'i.created DESC'; break;
				default:         $orderby = 'i.created DESC'; break;
			}
			$orderby = ' ORDER BY '. $orderby;
		}

		// Add relevance columns to ORDER BY and to SELECT clauses
		if ($select_relevance)
		{
			$priorities = array();
			$n = count($select_relevance);
			foreach ($select_relevance as $s)
			{
				$priorities['priority' . $n] = ' (' . $s . ') AS priority' . $n;
				$n--;
			}

			$select_relevance = ', ' . implode(', ', $priorities) ;
			$ord_priorities = implode(' DESC, ', array_keys($priorities)) . ' DESC, ';
			$orderby = str_replace( 'ORDER BY ', 'ORDER BY ' . $ord_priorities, $orderby);
		}
		else
		{
			$select_relevance = '';
		}


		// ***
		// *** Create JOIN clause and WHERE clause part for filtering by current (viewing) access level
		// ***

		$joinaccess	= '';
		$andaccess	= '';
		$select_access = '';

		// Extra access columns for main category and content type (item access will be added as 'access')
		$select_access .= ',  c.access as category_access, ty.access as type_access';

		if ( !$show_noauth )
		{
			// User not allowed to LIST unauthorized items
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$andaccess .= ' AND ty.access IN (0,'.$aid_list.')';
			$andaccess .= ' AND  c.access IN (0,'.$aid_list.')';
			$andaccess .= ' AND  i.access IN (0,'.$aid_list.')';
			$select_access .= ', 1 AS has_access';
		}

		else
		{
			// Access Flags for: content type, main category, item
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$select_access .= ', '
				.' CASE WHEN '
				.'  ty.access IN ('.$aid_list.') AND '
				.'   c.access IN ('.$aid_list.') AND '
				.'   i.access IN ('.$aid_list.') '
				.' THEN 1 ELSE 0 END AS has_access';
		}


		// ***
		// *** Create WHERE clause part for filtering by current active language, and current selected contend types ( !! although this is possible via a filter too ...)
		// ***

		$andlang = '';
		if (	$app->isClient('site') &&
					( FLEXI_FISH || (FLEXI_J16GE && $app->getLanguageFilter()) ) &&
					$filter_lang  // Language filtering enabled
		) {
			$andlang .= ' AND ( ie.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		}

		// Filter by currently selected content types
		$andcontenttypes = count($contenttypes) ? ' AND ie.type_id IN ('. implode(",", $contenttypes) .') ' : '';


		// ***
		// *** Create the AND-WHERE clause parts for the currentl active Field Filters
		// ***

		$return_sql = 2;
		$filters_where = array();
		foreach($filters as $field)
		{
			// Get value of current filter, and SKIP it if value is EMPTY
			$filtervalue = $app->input->get('filter_'.$field->id, '', 'array');
			$empty_filtervalue_array  = is_array($filtervalue)  && !strlen(trim(implode('',$filtervalue)));
			$empty_filtervalue_string = !is_array($filtervalue) && !strlen(trim($filtervalue));
			if ($empty_filtervalue_array || $empty_filtervalue_string) continue;

			// Call field filtering of advanced search to find items matching the field filter (an SQL SUB-QUERY is returned)
			$field_filename = $field->iscore ? 'core' : $field->field_type;
			$filtered = FLEXIUtilities::call_FC_Field_Func($field_filename, 'getFilteredSearch', array( &$field, &$filtervalue, &$return_sql ));

			// An empty return value means no matching values were found
			$filtered = empty($filtered) ? ' AND 0 ' : $filtered;

			// A string mean a subquery was returned, while an array means that item ids we returned
			$filters_where[$field->id] = is_array($filtered) ?  ' AND i.id IN ('. implode(',', $filtered) .')' : $filtered;

			/*if ($filters_where[$field->id]) {
				echo "\n<br/>Filter:". $field->name ." : ";   print_r($filtervalue);
				echo "<br>".$filters_where[$field->id]."<br/>";
			}*/
		}
		//echo "\n<br/><br/>Filters Active: ". count($filters_where)."<br/>";
		//echo "<pre>"; print_r($filters_where);
		//exit;


		// ***
		// *** Create Filters JOIN clauses and AND-WHERE clause parts
		// ***

		// JOIN clause - USED - to limit returned 'text' to the text of TEXT-SEARCHABLE only fields ... (NOT shared with filters)
		if ( !$txtmode )
		{
			$onBasic_textsearch    = $text_search;
			$onAdvanced_textsearch = '';
			$join_textsearch = '';
			$join_textfields = '';
		}

		else
		{
			$onBasic_textsearch    = '';
			$onAdvanced_textsearch = $text_search;
			$join_textsearch = ' JOIN #__flexicontent_advsearch_index as ts ON ts.item_id = i.id '.(count($fields_text) ? 'AND ts.field_id IN ('. implode(',',array_keys($fields_text)) .')' : '');
			$join_textfields = ' JOIN #__flexicontent_fields as txtf ON txtf.id=ts.field_id';
		}

		// JOIN clauses ... (shared with filters)
		$join_clauses =  ''
			. ' JOIN #__categories AS c ON c.id = i.catid'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			;

		$join_clauses_with_text =  ''
			. ' JOIN #__categories AS c ON c.id = i.catid'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id' . $onBasic_textsearch
			. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ($text_search ?
				$join_textsearch . $onAdvanced_textsearch .
				$join_textfields : '');

		$catid = $jinput->getInt('cid', 0);
		$cids = array();

		if ($catid)
		{
			$cids[] = $catid;
			$query = 'SELECT id,lft,rgt FROM `#__categories` WHERE id = ' . (int) $catid;

			if ($cat = $db->setQuery($query)->loadObject())
			{
				$query = 'SELECT sub.id FROM `#__categories` as sub '
					. ' WHERE sub.lft >= ' . (int) $cat->lft . ' AND sub.rgt <= ' . (int) $cat->rgt . ' AND published = 1 ';

				$subs = $db->setQuery($query)->loadColumn();
				if (is_array($subs))
				{
					$subs = ArrayHelper::toInteger($subs);
					$cids = array_merge($cids, $subs);
				}
			}
		}

		// AND-WHERE sub-clauses ... (shared with filters)
		$where_conf = ' WHERE 1 '
			. (count($cids) > 0 ? ' AND i.catid IN (' . implode(',', $cids) . ')' : '')
			. ' AND i.state IN (1,-5'. ($search_archived ? ','.(FLEXI_J16GE ? 2:-1) :'' ) .') '
			. ' AND c.published = 1 '
			. ' AND ( i.publish_up IS NULL OR i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' )'
			. ' AND ( i.publish_down IS NULL OR i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' )'
			. $andaccess
			. $andlang
			. $andcontenttypes
			;

		// AND-WHERE sub-clauses for text search ... (shared with filters)
		$and_where_filters = count($filters_where) ? implode( " ", $filters_where) : '';


		// ***
		// *** Set variables used by filters creation mechanism
		// ***

		global $fc_searchview;
		$fc_searchview['join_clauses'] = $join_clauses;
		$fc_searchview['join_clauses_with_text'] = $join_clauses_with_text;
		$fc_searchview['where_conf_only'] = $where_conf;   // WHERE of the view (mainly configuration dependent)
		$fc_searchview['filters_where'] = $filters_where;  // WHERE of the filters
		$fc_searchview['search'] = $text_search;  // WHERE for text search
		$fc_searchview['params'] = $params; // view's parameters


		// ***
		// *** Execute search query.  NOTE this is skipped it if (a) no text-search and no (b) no filters are active
		// ***

		// Do not check for 'contentypes' this are based on configuration and not on form submitted data,
		// considering contenttypes or other configuration based parameters, will return all items on initial search view display !
		if ( !count($filters_where) && !strlen($text) /*&& !strlen($andcontenttypes)*/ ) return array();

		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }


		// ***
		// *** Overcome possible group concat limitation
		// ***

		$query="SET SESSION group_concat_max_len = 9999999";
		$db->setQuery($query);
		$db->execute();


		// ***
		// *** Get the items
		// ***

		$query = 'SELECT SQL_CALC_FOUND_ROWS i.id'
			. $orderby_col
			. $select_relevance
			. ' FROM #__flexicontent_items_tmp AS i'
			. $join_clauses_with_text
			. $orderby_join
			. $joinaccess
			. $where_conf
			. $and_where_filters
			. ' GROUP BY i.id '
			. $orderby
			;
		//echo "Adv search plugin main SQL query: ".nl2br($query)."<br/><br/>";

		// NOTE: The plugin will return a PRECONFIGURED limited number of results, the SEARCH VIEW to do the pagination, splicing (appropriately) the data returned by all search plugins
		try {
			// Get items, we use direct query because some extensions break the SQL_CALC_FOUND_ROWS, so let's bypass them (at this point it is OK)
			// *** Usage of FOUND_ROWS() will fail when (e.g.) Joom!Fish or Falang are installed, in this case we will be forced to re-execute the query ...
			// PLUS, we don't need Joom!Fish or Falang layer at --this-- STEP which may slow down the query considerably in large sites
			$query_limited = $query . ' LIMIT '.$search_limit.' OFFSET 0';
			$rows = flexicontent_db::directQuery($query_limited);
			$item_ids = array();
			foreach ($rows as $row) $item_ids[] = $row->id;

			// Get current items total for pagination
			$db->setQuery("SELECT FOUND_ROWS()");
			$fc_searchview['view_total'] = $db->loadResult();
			$app->setUserState('fc_view_total_'.$view, $fc_searchview['view_total']);
		}

		catch (Exception $e) {
			// Get items via normal joomla SQL layer
			$db->setQuery(str_replace('SQL_CALC_FOUND_ROWS', '', $query), 0, $search_limit);
			$item_ids = $db->loadColumn(0);
		}

		if ( !count($item_ids) ) return array();  // No items found


		// ***
		// *** Get the item data
		// ***

		$query_data = 'SELECT i.id, i.title AS title, i.created, i.id AS fc_item_id, i.access, ie.type_id, i.language'
			. ', c.title AS maincat_title, c.alias AS maincat_alias'  // Main category data
			. ( !$txtmode ?
				', ie.search_index AS text' :
				', GROUP_CONCAT(ts.search_index ORDER BY txtf.ordering ASC SEPARATOR \' \') AS text'
				)
			. ', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug'
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ', CONCAT_WS( " / ", '. $db->Quote($searchFlexicontent) .', c.title, i.title ) AS section'
			. $select_access
			. ' FROM #__flexicontent_items_tmp AS i'
			. $join_clauses     // without on-join for basic text search
			. $join_textsearch  // without on-join for advanced text search
			. $join_textfields  // we need the text searchable fields to do ordering of text search fields above (minor effect)
			//. $orderby_join
			//. $joinaccess
			. ' WHERE i.id IN ('.implode(',',$item_ids).') '
			. ' GROUP BY i.id '
			. ' ORDER BY FIELD(i.id, '. implode(',', $item_ids) .')'
		;
		//require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'SqlFormatter'.DS.'SqlFormatter.php');
		//echo str_replace('PPP_', '#__', SqlFormatter::format(str_replace('#__', 'PPP_', $query)))."<br/>";

		$list = $db->setQuery($query_data)->loadObjectList();

		if ( $print_logging_info ) @$fc_run_times['search_query_runtime'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// ***
		// *** Create item links and other variables
		// ***

		//echo "<pre>"; print_r($list); echo "</pre>";
		if( $list )
		{
			if ( count($list) >= $search_limit )
				$app->setUserState('fc_view_limit_max_'.$view, $search_limit);

			$item_cats = FlexicontentFields::_getCategories($list);
			foreach($list as $key => $item)
			{
				$item->text = preg_replace('/\b'.$search_prefix.'/', '', $item->text);
				$item->categories = isset($item_cats[$item->id])  ?  $item_cats[$item->id] : array();  // in case of item categories missing

				// If joomla article view is allowed allowed and then search view may optional create Joomla article links
				if( $typeData[$item->type_id]->params->get('allow_jview', 0) == 1 && $typeData[$item->type_id]->params->get('search_jlinks', 1) )
				{
					$item->href = JRoute::_(ContentHelperRoute::getArticleRoute($item->slug, $item->categoryslug, $item->language));
				}
				else
				{
					$item->href = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));
				}
				$item->browsernav = $browsernav;
			}
		}

		return $list;
	}


	/**
	 * Method to build the part of WHERE clause related to Alpha Index
	 *
	 * @access private
	 * @return array
	 */
	function _buildTextSearch($text = null, $phrase = null, $txtmode = 0, & $select_relevance = array())
	{
		$app    = JFactory::getApplication();
		$option = $app->input->getCmd('option', '');
		$db     = JFactory::getDbo();

		static $text_search = null;

		if ($text_search !== null)
		{
			return $text_search;
		}

		$text_search = '';


		/**
		 * Create query CLAUSE for Text Search
		 */

		if (!strlen($text))
		{
			$q = $app->input->getString('q', '');
			$q = $q !== parse_url(@$_SERVER["REQUEST_URI"], PHP_URL_PATH) ? $q : '';

			$original_text = $app->input->getString('filter', $q);
		}
		else
		{
			$original_text = $text;
		}

		// Set _relevant _active_* FLAG
		$this->_active_search = $text;

		// Text search using LIKE %word% (compatibility for language without spaces)
		$filter_word_like_any = (int) $this->_params->get('filter_word_like_any', 0);

		// Text search relevance [title] or [title, search index]
		$filter_word_relevance_order = (int) $this->_params->get('filter_word_relevance_order', 0);

		if ($phrase === null)
		{
			$default_searchphrase = $this->_params->get('default_searchphrase', 'all');
			$phrase = $app->input->get('searchphrase', $app->input->get('p', $default_searchphrase, 'word'), 'word');
		}

		$si_tbl = !$txtmode
			? 'flexicontent_items_ext'
			: 'flexicontent_advsearch_index';

		// Try to add space between words for current language using a dictionary
		$lang_handler = FlexicontentFields::getLangHandler(JFactory::getLanguage()->getTag());

		if ($lang_handler)
		{
			$text = implode(' ', $lang_handler->get_segment_array($clear_previous = true, trim($original_text)));
		}
		else
		{
			$text = trim($original_text);
		}

		// Prefix the words for short word / stop words matching
		$search_prefix = $this->_params->get('add_search_prefix') ? 'vvv' : '';
		$text_np = $text;
		$text = preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix.'$0', $text_np);

		// Split to words
		$words_np = preg_split('/\s\s*/u', $text_np);
		$words = preg_split('/\s\s*/u', $text);

		if (strlen($text))
		{
			$ts = !$txtmode ? 'ie' : 'ts';
			$escaped_text_np = $db->escape($text_np, true);
			$quoted_text_np  = $db->Quote($escaped_text_np, false);

			$escaped_text = $db->escape($text, true);
			$quoted_text  = $db->Quote($escaped_text, false);
			$exact_text   = $db->Quote('%' . $escaped_text . '%', false);

			$isprefix = $phrase !== 'exact';
			$stopwords = array();
			$shortwords = array();

			/*
			 * LIKE %word% search (needed by languages without spaces),
			 * (1) Skip / Ignore this for languages with spaces,
			 * (2) Skip / Ignore this if current language supports splitting via dictionary
			 */
			if ($filter_word_like_any
				&& in_array(flexicontent_html::getUserCurrentLang(), array('zh', 'jp', 'ja', 'th'))
				&& ! FlexicontentFields::getLangHandler(JFactory::getLanguage()->getTag(), $_hasHandlerOnly = true)
			)
			{
				$_index_match = ' LOWER ('.$ts.'.search_index) LIKE '.$db->Quote( '%'.$escaped_text.'%', false );
				$_title_relev = ' LOWER (i.title) LIKE '.$db->Quote( '%'.$escaped_text.'%', false );
			}

			/*
			 * FullText search
			 */
			else
			{
				if (!$search_prefix)
				{
					$words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix);
				}

				// TODO: Check this if using advanced search index we do not have stop words or too short words
				if ($si_tbl=='flexicontent_advsearch_index')
				{
					$words = array_merge($words, $stopwords, $shortwords);
					$stopwords = $shortwords = array();
				}

				// Abort if all words are stop-words or too short, we could try to execute a query that only contains a LIKE %...% , but it would be too slow
				if (empty($words))
				{
					return ' AND 0 = 1 ';
				}

				switch ($phrase)
				{
				case 'natural':
					$_index_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN NATURAL LANGUAGE MODE) ';
					$_title_relev = ' MATCH (i.title) AGAINST ('.$quoted_text_np.' IN NATURAL LANGUAGE MODE) ';
					$_index_relev = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN NATURAL LANGUAGE MODE) ';
					break;

				case 'natural_expanded':
					$_index_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' WITH QUERY EXPANSION) ';
					$_title_relev = ' MATCH (i.title) AGAINST ('.$quoted_text_np.' WITH QUERY EXPANSION) ';
					$_index_relev = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' WITH QUERY EXPANSION) ';
					break;

				case 'exact':
					// Speed optimization ... 2-level searching: first require ALL words via FULLTEXT index, then require exact text via LIKE %phrase%
					$newtext = '+' . implode(' +', $words);
					$escaped_text = $db->escape($newtext, true);
					$quoted_text  = $db->Quote($escaped_text, false);

					// Relevance by title, try to match (EXACT) words, via OR ignoring stop words and short words ...
					$newtext_np = implode(' ', $words_np);
					$escaped_text_np = $db->escape($newtext_np, true);
					$quoted_text_np  = $db->Quote($escaped_text_np, false);

					$_index_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) AND '.$ts.'.search_index LIKE '.$exact_text;
					$_title_relev = ' MATCH (i.title) AGAINST ('.$quoted_text_np.' IN BOOLEAN MODE) ';
					$_index_relev = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					break;

				case 'all':
					$nospace_languages = array('th-TH');

					$is_nospace_language = in_array(JFactory::getLanguage()->getTag(), $nospace_languages);

					// TODO check if not using the * for THAI is appropriate & needed
					$newtext = $is_nospace_language
						? '+' . implode( ' +', $words ) . ''  // This is worked for Thai language.
						: '+' . implode( '* +', $words ) . '*';  // This is not worked for Thai language.

					$escaped_text = $db->escape($newtext, true);
					$quoted_text  = $db->Quote($escaped_text, false);

					// Relevance by title, try to match (PREFIXED) words, via OR ignoring stop words and short words ...
					$newtext_np = implode( '* ', $words_np ) . '*';
					$escaped_text_np = $db->escape($newtext_np, true);
					$quoted_text_np  = $db->Quote($escaped_text_np, false);

					if ($is_nospace_language)
					{
						// Text nLH (no-Language-Handler, aka NO SPACES added to guess and seperate words)
						$text_nLH = trim($original_text);  
						$escaped_text_nLH = $db->escape($text_nLH, true);
						$quoted_text_nLH  = $db->Quote($escaped_text_nLH, false);

						$_index_match = " (MATCH (".$ts.".search_index) AGAINST (".$quoted_text." IN BOOLEAN MODE) OR i.title LIKE '%".$quoted_text_nLH."%') ";
						$_title_relev = " (MATCH (i.title) AGAINST (".$quoted_text_np." IN BOOLEAN MODE) OR i.title LIKE '%".$quoted_text_nLH."%') ";
						$_index_relev = " (MATCH (".$ts.".search_index) AGAINST (".$quoted_text." IN BOOLEAN MODE) OR i.title LIKE '%".$quoted_text_nLH."%') ";
					}
					else
					{
						$_index_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
						$_title_relev = ' MATCH (i.title) AGAINST ('.$quoted_text_np.' IN BOOLEAN MODE) ';
						$_index_relev = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					}
					break;

				case 'any':
				default:
					$newtext = implode( '* ', $words ) . '*';
					$escaped_text = $db->escape($newtext, true);
					$quoted_text  = $db->Quote($escaped_text, false);

					// Relevance by title, try to match (PREFIXED) words, via OR ignoring stop words and short words ...
					$newtext_np = implode( '* ', $words_np ) . '*';
					$escaped_text_np = $db->escape($newtext_np, true);
					$quoted_text_np  = $db->Quote($escaped_text_np, false);

					$_index_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					$_title_relev = ' MATCH (i.title) AGAINST ('.$quoted_text_np.' IN BOOLEAN MODE) ';
					$_index_relev = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					break;
				}
			}

			// Title relevance clause, Search index relevance clause ... (currently search index relevance not DONE)
			if ($filter_word_relevance_order > 0)
			{
				$select_relevance['rel_title'] = $_title_relev;
			}

			if ($filter_word_relevance_order > 1)
			{
				$select_relevance['rel_index'] = $_index_relev;
			}

			// Indicate ignored words
			$app->input->set('ignoredwords', implode(' ', $stopwords));
			$app->input->set('shortwords', implode(' ', $shortwords));

			// Construct TEXT SEARCH limitation clause
			$text_search = ' AND '. $_index_match;
		}

		return $text_search;
	}
}
