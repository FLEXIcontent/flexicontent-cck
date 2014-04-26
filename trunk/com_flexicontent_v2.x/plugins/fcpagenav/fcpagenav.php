<?php
/**
 * @version 1.0 $Id: fcpagenav.php 1607 2012-12-20 09:04:57Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.file
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsFcpagenav extends JPlugin
{
	static $field_types = array('fcpagenav');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsFcpagenav( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_fcpagenav', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;

		$mainframe = JFactory::getApplication();
		$view = JRequest::getString('view', FLEXI_ITEMVIEW);
		if ($view != FLEXI_ITEMVIEW) return;
		
		// Global parameters
		$gparams   = $mainframe->getParams('com_flexicontent');
		$filtercat = $gparams->get('filtercat', 0); // If language filtering is enabled in category view
		
		// Get the site default language in case no language is set in the url
		$cntLang = substr(JFactory::getLanguage()->getTag(), 0,2);  // Current Content language (Can be natively switched in J2.5)
		$urlLang  = JRequest::getWord('lang', '' );                 // Language from URL (Can be switched via Joomfish in J1.5)
		$lang = (FLEXI_J16GE || empty($urlLang)) ? $cntLang : $urlLang;
		
		// parameters shortcuts
		$load_css 			= $field->parameters->get('load_css', 1);
		$use_tooltip		= $field->parameters->get('use_tooltip', 1);
		$use_title			= $field->parameters->get('use_title', 0);
		$use_category_link	= $field->parameters->get('use_category_link', 0);
		$show_prevnext_count= $field->parameters->get('show_prevnext_count', 1);
		$tooltip_title_next	= $field->parameters->get('tooltip_title_next', JText::_('FLEXI_FIELDS_PAGENAV_GOTONEXT'));
		$tooltip_title_prev	= $field->parameters->get('tooltip_title_prev', JText::_('FLEXI_FIELDS_PAGENAV_GOTOPREV'));
		$types_to_exclude	= $field->parameters->get('type_to_exclude', '');
		$prev_label			= $field->parameters->get('prev_label', JText::_('FLEXI_FIELDS_PAGENAV_GOTOPREV'));
		$next_label			= $field->parameters->get('next_label', JText::_('FLEXI_FIELDS_PAGENAV_GOTONEXT'));
		$category_label	= $field->parameters->get('category_label', JText::_('FLEXI_FIELDS_PAGENAV_CATEGORY'));

		$view		= JRequest::getCmd('view');
		$option		= JRequest::getCmd('option');
		$cid		= JRequest::getInt('cid');
		$id			= JRequest::getInt('id');
	
		if (($view == FLEXI_ITEMVIEW) && ($option == 'com_flexicontent'))
		{

			$html  = '';
			$db    = JFactory::getDBO();
			$user  = JFactory::getUser();
			$document	= JFactory::getDocument();
	
			$date     = JFactory::getDate();
			$nowDate  = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
			$nullDate	= $db->getNullDate();
			
			if ($use_tooltip)
				JHTML::_('behavior.tooltip');
			if ($load_css)
				$document->addStyleSheet(JURI::root(true).'/plugins/flexicontent_fields/fcpagenav/'.(FLEXI_J16GE ? 'fcpagenav/' : '').'fcpagenav.css');	

			// get active category ordering
			$query 	= 'SELECT params FROM #__categories WHERE id = ' . ($cid ? $cid : $item->catid);
			$db->setQuery($query);
			$catparams = $db->loadResult();
			$cparams = FLEXI_J16GE ? new JRegistry($catparams) : new JParameter($catparams);
			
			// filter depending on permissions
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$andaccess = ' AND a.access IN ('.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
					if ( isset($readperms['item']) && count($readperms['item']) ) {
						$andaccess = ' AND ( ( a.access <= '.$aid.' OR a.id IN ('.implode(",", $readperms['item']).') OR a.created_by = '.$user->id.' OR ( a.modified_by = '.$user->id.' AND a.modified_by != 0 ) ) )';
					} else {
						$andaccess = ' AND ( a.access <= '.$aid.' OR a.created_by = '.$user->id.' OR ( a.modified_by = '.$user->id.' AND a.modified_by != 0 ) )';
					}
				} else {
					$andaccess = ' AND ( a.access <= '.$aid.' OR a.created_by = '.$user->id.' OR ( a.modified_by = '.$user->id.' AND a.modified_by != 0 ) )';
				}
			}

			// Determine sort order
			$order = $cparams->get('orderby', '');    // TODO: finish using category ORDERING, now we ignore: commented, rated
			$orderby = '';
			if ((int)$cparams->get('orderbycustomfieldid', 0) != 0) {
				if ($cparams->get('orderbycustomfieldint', 0) != 0) $int = ' + 0'; else $int ='';
				$orderby		= 'f.value'.$int.' '.$cparams->get('orderbycustomfielddir', 'ASC');
				$orderby_join = ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = a.id AND f.field_id = '.(int)$cparams->get('orderbycustomfieldid', 0);
			} else {
				switch ($order)
				{
					case 'date'    : $orderby = 'a.created';  break;
					case 'rdate'   : $orderby = 'a.created DESC';  break;
					case 'modified': $orderby = 'a.modified DESC';  break;
					case 'alpha'   : $orderby = 'a.title'; break;
					case 'ralpha'  : $orderby = 'a.title DESC'; break;
					case 'author'  : $orderby = 'u.name';  break;
					case 'rauthor' : $orderby = 'u.name DESC';  break;
					case 'hits'    : $orderby = 'a.hits';  break;
					case 'rhits'   : $orderby = 'a.hits DESC';  break;
					case 'order'   : $orderby = 'rel.ordering';  break;
				}
				
				// Create JOIN for ordering items by a most rated
				if ($order=='author' || $order=='rauthor') {
					$orderby_join = ' LEFT JOIN #__users AS u ON u.id = a.created_by';
				}
			}
			$orderby = $orderby ? $orderby.', a.title' : 'a.title';
			
			$types		= is_array($types_to_exclude) ? implode(',', $types_to_exclude) : $types_to_exclude;

			$xwhere	=	' AND ( a.state = 1 OR a.state = -5 )' .
						' AND ( publish_up = '.$db->Quote($nullDate).' OR publish_up <= '.$db->Quote($nowDate).' )' .
						' AND ( publish_down = '.$db->Quote($nullDate).' OR publish_down >= '.$db->Quote($nowDate).' )' . 
						($types_to_exclude ? ' AND ie.type_id NOT IN (' . $types . ')' : '')
						;
			if ((FLEXI_FISH || FLEXI_J16GE) && $filtercat) {
				$xwhere .= ' AND ( ie.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
			}
			
			
			// array of articles in same category correctly ordered
			$query 	= 'SELECT a.id, a.title,'
					. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug,'
					. ' CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(":", cc.id, cc.alias) ELSE cc.id END as catslug'
					. ' FROM #__content AS a'
					. ' JOIN #__categories AS cc ON cc.id = '. ($cid ? $cid : (int) $item->catid)
					. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id '
					. @ $orderby_join
					. ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = a.id'
					. ' WHERE rel.catid = ' . ($cid ? $cid : (int) $item->catid)
					. $xwhere
					. $andaccess
					//. ' GROUP BY a.id'  // NOT NEEDED and may mask errors, commented out
					. ' ORDER BY '. $orderby
					;
			$db->setQuery($query);
			$list = $db->loadObjectList('id');
			if ($db->getErrorNum()) {
				JError::raiseWarning($db->getErrorNum(), $db->getErrorMsg(). "<br />".$query."<br />");
			}

			// this check needed if incorrect Itemid is given resulting in an incorrect result
			if ( !is_array($list) ) {
				$list = array();
			}	
			reset($list);
	
			// location of current content item in array list
			$location = array_search($item->id, array_keys($list));
			
			$rows = array_values($list);
			
			
			$field->prev = null;
			$field->prevtitle = null;
			$field->prevurl = null;
			$field->next = null;
			$field->nexttitle = null;
			$field->nexturl = null;
			$field->category = null;
			$field->categorytitle = null;
			$field->categoryurl = null;
			
	
			if ($location -1 >= 0) 	{
				// the previous content item cannot be in the array position -1
				$field->prev = $rows[$location -1];
			}
	
			if (($location +1) < count($rows)) {
				// the next content item cannot be in an array position greater than the number of array postions
				$field->next = $rows[$location +1];
			}
		
			if ($field->prev) {
				$field->prevtitle = $field->prev->title;
				$field->prevurl = JRoute::_(FlexicontentHelperRoute::getItemRoute($field->prev->slug, $field->prev->catslug));
			} else {
				$field->prevtitle = '';
				$field->prevurl = '';
			}
	
			if ($field->next) {
				$field->nexttitle = $field->next->title;
				$field->nexturl = JRoute::_(FlexicontentHelperRoute::getItemRoute($field->next->slug, $field->next->catslug));
			} else {
				$field->nexttitle = '';
				$field->nexturl = '';
			}
	
			// output
			if ($field->prev || $field->next || $use_category_link)
			{

				$html 	 = '<span class="flexi pagination">';

				if ($use_category_link)
				{
					$limit = $cparams->get('limit', 4);
					$limit = $limit ? $limit : 4;
					$start = floor($location / $limit)*$limit;
					if (!empty($rows[$location]->catslug)) {
						$html .= '
						<span class="btn return_category">
							<a href="'. JRoute::_(FlexicontentHelperRoute::getCategoryRoute($rows[$location]->catslug)).'?start='.$start .'">' . htmlspecialchars($category_label, ENT_NOQUOTES) . '</a>
						</span>';
					}
				}
				
				$html .= $show_prevnext_count ? '<span class="prevnext_count">['.($location+1).'/'.count($list).']</span>' : '';
				
				if ($field->prev)
				{
					$prev_count = '';//$show_prevnext_count ? '&nbsp;['.($location).']' : '';
					$html .= '
					<span class="btn pagenav_prev' . ($use_tooltip ? ' hasTip' : '') . '"' . ($use_tooltip ? 'title="'.$tooltip_title_prev.'::'.$field->prevtitle.'"' : '') . '>
						<a href="'. $field->prevurl .'">' . ( $use_title ? $field->prevtitle : htmlspecialchars($prev_label, ENT_NOQUOTES) ) .$prev_count.'</a>
					</span>'
					;
				} else {
					$html .= '
					<span class="btn pagenav_prev">
						<span class="noprevnext">'.htmlspecialchars($prev_label, ENT_NOQUOTES).'</span>
					</span>'
					;
				}

				if ($field->next)
				{
					$next_count = '';//$show_prevnext_count ? '&nbsp;['.(count($list)-$location-1).']' : '';
					$html .= '
					<span class="btn pagenav_next' . ($use_tooltip ? ' hasTip' : '') . '"' . ($use_tooltip ? 'title="'.$tooltip_title_next.'::'.$field->nexttitle.':: "' : '') . '>
						<a href="'. $field->nexturl .'">' . ( $use_title ? $field->nexttitle : htmlspecialchars($next_label, ENT_NOQUOTES) ) .$next_count.'</a>
					</span>'
					;
				} else {
					$html .= '
					<span class="btn pagenav_next">
						<span class="noprevnext">'.htmlspecialchars($next_label, ENT_NOQUOTES).'</span>
					</span>'
					;
				}

				$html 	.= '</span>';

			}
		}
		
		$field->{$prop} = $html;
	}

}
