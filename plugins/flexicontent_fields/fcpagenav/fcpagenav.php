<?php
/**
 * @version 1.0 $Id: fcpagenav.php 1889 2014-04-26 03:25:28Z ggppdk $
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->{$prop} = '';
		
		$option = JRequest::getCmd('option');
		$view  = JRequest::getString('view', FLEXI_ITEMVIEW);
		$print = JRequest::getCMD('print');
		
		// No output if it is not FLEXIcontent item view or view is "print"
		if ($view != FLEXI_ITEMVIEW || $option != 'com_flexicontent' || $print) return;
		
		// parameters shortcuts
		$load_css 			= $field->parameters->get('load_css', 1);
		$use_tooltip		= $field->parameters->get('use_tooltip', 1);
		$use_title			= $field->parameters->get('use_title', 0);
		$use_category_link	= $field->parameters->get('use_category_link', 0);
		$show_prevnext_count= $field->parameters->get('show_prevnext_count', 1);
		$tooltip_title_next	= JText::_($field->parameters->get('tooltip_title_next', 'FLEXI_FIELDS_PAGENAV_GOTONEXT'));
		$tooltip_title_prev	= JText::_($field->parameters->get('tooltip_title_prev', 'FLEXI_FIELDS_PAGENAV_GOTOPREV'));
		$prev_label			= JText::_($field->parameters->get('prev_label', 'FLEXI_FIELDS_PAGENAV_GOTOPREV'));
		$next_label			= JText::_($field->parameters->get('next_label', 'FLEXI_FIELDS_PAGENAV_GOTONEXT'));
		$category_label	= JText::_($field->parameters->get('category_label', 'FLEXI_FIELDS_PAGENAV_CATEGORY'));
		
		$cid = JRequest::getInt('cid');
		$cid = $cid ? $cid : (int)$item->catid;
		
		// Get active category parameters
		$db = JFactory::getDBO();
		$query 	= 'SELECT * FROM #__categories WHERE id = ' . $cid;
		$db->setQuery($query);
		$catdata = $db->loadObject();
		$catdata->parameters = FLEXI_J16GE ? new JRegistry($catdata->params) : new JParameter($catdata->params);
		
		// Get list of ids of selected, TODO retrieve item ids from view:
		// --> this will allow special navigating layouts "mcats,author,myitems,tags,favs" and also utilize current filtering
		$ids = null;
		$list = $this->getItemList($field, $item, $ids, $cid, $catdata->parameters);
		
		// Location of current content item in array list
		$loc_to_ids = array_keys($list);
		$ids_to_loc = array_flip($loc_to_ids);
		$location = isset($ids_to_loc[$item->id]) ? $ids_to_loc[$item->id] : false;
		
		// Get previous and next item data
		$field->prev = null;
		$field->prevtitle = null;
		$field->prevurl = null;
		$field->next = null;
		$field->nexttitle = null;
		$field->nexturl = null;
		$field->category = null;
		$field->categorytitle = null;
		$field->categoryurl = null;
		
		// Get item data
		$rows = false;
		$prev_id = null;
		$next_id = null;
		if ($location !== false)
		{
			$prev_id = ($location - 1) >= 0 ? $loc_to_ids[$location - 1] : null;
			$next_id = ($location + 1) < count($list) ? $loc_to_ids[$location + 1] : null;
			
			$ids = array();
			
			// Previous item if it exists
			if ($prev_id) $ids[] = $prev_id;
			
			// Current item may belong may not be list in main category so retrieve it to get a proper categoryslug
			$ids[] = $item->id;
			
			// Next item if it exists
			if ($next_id) $ids[] = $next_id;
			
			// Query specific ids
			$rows = $this->getItemList($field, $item, $ids, $cid, $catdata->parameters);
			
			// previous content item
			if ($prev_id) {
				$field->prev = $rows[$prev_id];
				$field->prevtitle = $field->prev->title;
				$field->prevurl = JRoute::_(FlexicontentHelperRoute::getItemRoute($field->prev->slug, $field->prev->categoryslug, 0, $field->prev));
			}
			
			// next content item
			if ($next_id) {
				$field->next = $rows[$next_id];
				$field->nexttitle = $field->next->title;
				$field->nexturl = JRoute::_(FlexicontentHelperRoute::getItemRoute($field->next->slug, $field->next->categoryslug, 0, $field->next));
			}
		}
		
		
		// Check if displaying nothing and stop
		if (!$field->prev && !$field->next && !$use_category_link) return;
		
		$html = '<span class="flexi fc-pagenav">';
		$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		
		// CATEGORY back link
		if ($use_category_link)
		{
			$cat_image = $this->getCatThumb($catdata, $field->parameters);
			$limit = count($list);
			$limit = $limit ? $limit : 10;
			$start = floor($location / $limit)*$limit;
			if (!empty($rows[$item->id]->categoryslug)) {
				$html .= '
				<span class="fc-pagenav-return">
					<a class="btn btn-info" href="'. JRoute::_(FlexicontentHelperRoute::getCategoryRoute($rows[$item->id]->categoryslug)).'?start='.$start .'">' . htmlspecialchars($category_label, ENT_NOQUOTES, 'UTF-8')
						.($cat_image ? '
						<br/>
						<img src="'.$cat_image.'" alt="Return"/>' : '') .'
					</a>
				</span>';
			}
		}
		
		// Item location and total count
		$html .= $show_prevnext_count ? '<span class="fc-pagenav-items-cnt badge badge-info">'.($location+1).'/'.count($list).'</span>' : '';
		
		// Get images
		$items_arr = array();
		if ($field->prev) $items_arr[$field->prev->id] = $field->prev;
		if ($field->next) $items_arr[$field->next->id] = $field->next;
		$thumbs = $this->getItemThumbs($field->parameters, $items_arr);
		
		// Next item linking
		if ($field->prev)
		{
			$tooltip = $use_tooltip ? ' title="'. flexicontent_html::getToolTip($tooltip_title_prev, $field->prevtitle, 0) .'"' : '';
			$html .= '
			<span class="fc-pagenav-prev' . ($use_tooltip ? $tooltip_class : '') . '" ' . ($use_tooltip ? $tooltip : '') . '>
				<a class="btn" href="'. $field->prevurl .'">
					<i class="icon-previous"></i>
					' . ( $use_title ? $field->prevtitle : htmlspecialchars($prev_label, ENT_NOQUOTES, 'UTF-8') ).'
					'.(isset($thumbs[$field->prev->id]) ? '
						<br/>
						<img src="'.$thumbs[$field->prev->id].'" alt="Previous"/>
					' : '').'
				</a>
			</span>'
			;
		} else {
			$html .= '
			<span class="fc-pagenav-prev">
				<span class="btn disabled">
					<i class="icon-previous"></i>
					'.htmlspecialchars($prev_label, ENT_NOQUOTES, 'UTF-8').'
				</span>
			</span>'
			;
		}
		
		// Previous item linking
		if ($field->next)
		{
			$tooltip = $use_tooltip ? ' title="'. flexicontent_html::getToolTip($tooltip_title_next, $field->nexttitle, 0) .'"' : '';
			$html .= '
			<span class="fc-pagenav-next' . ($use_tooltip ? $tooltip_class : '') . '" ' . ($use_tooltip ? $tooltip : '') . '>
				<a class="btn" href="'. $field->nexturl .'">
					<i class="icon-next"></i>
					' . ( $use_title ? $field->nexttitle : htmlspecialchars($next_label, ENT_NOQUOTES, 'UTF-8') ).'
					'.(isset($thumbs[$field->next->id]) ? '
						<br/>
						<img src="'.$thumbs[$field->next->id].'" alt="Next"/>
					' : '').'
				</a>
			</span>'
			;
		} else {
			$html .= '
			<span class="fc-pagenav-next">
				<span class="btn disabled">
					<i class="icon-next"></i>
					'.htmlspecialchars($next_label, ENT_NOQUOTES, 'UTF-8').'
				</span>
			</span>'
			;
		}
		
		$html .= '</span>';
		
		// Load needed JS/CSS
		if ($use_tooltip)
			FLEXI_J30GE ? JHtml::_('bootstrap.tooltip') : JHTML::_('behavior.tooltip');
		if ($load_css)
			JFactory::getDocument()->addStyleSheet(JURI::root(true).'/plugins/flexicontent_fields/fcpagenav/'.(FLEXI_J16GE ? 'fcpagenav/' : '').'fcpagenav.css');	
		
		$field->{$prop} = $html;		
	}
	
	
	function getItemThumbs(&$params, &$items, $uprefix='item', $rprefix='nav')
	{
		if ( !$params->get($uprefix.'_use_image', 1) ) return array();
		if ( empty($items) ) return array();
		
		if ( $params->get($uprefix.'_image') ) {
			$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small', 'o'=>'original');
			$img_field_size = $img_size_map[ $params->get($uprefix.'_image_size' , 'l') ];
			$img_field_name = $params->get($uprefix.'_image');
		}
		
		if (!empty($img_field_name)) {
			//$_return = FlexicontentFields::renderFields( false, array_keys($items), array($img_field_name), FLEXI_ITEMVIEW, array('display_'.$img_field_size.'_src'));
			FlexicontentFields::getFieldDisplay($items, $img_field_name, $values=null, 'display_'.$img_field_size.'_src', FLEXI_ITEMVIEW);
		}
		
		$thumbs = array();
		foreach($items as $item_id => $item) {
			if (!empty($img_field_name)) :
				//$src = str_replace(JURI::root(), '', @ $_return[$item_id][$img_field_name] );
				$img_field = & $item->fields[$img_field_name];
				$src = str_replace(JURI::root(), '', @ $img_field->{'display_'.$img_field_size.'_src'});
			else :
				$src = flexicontent_html::extractimagesrc($item);
			endif;
				
			$RESIZE_FLAG = !$params->get($uprefix.'_image') || !$params->get($uprefix.'_image_size');
			if ( $src && $RESIZE_FLAG ) {
				// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
				$w		= '&amp;w=' . $params->get($rprefix.'_width', 200);
				$h		= '&amp;h=' . $params->get($rprefix.'_height', 200);
				$aoe	= '&amp;aoe=1';
				$q		= '&amp;q=95';
				$zc		= $params->get($rprefix.'_method') ? '&amp;zc=' . $params->get($rprefix.'_method') : '';
				$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
				$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $zc . $f;
				
				$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JURI::base(true).'/' : '';
				$thumb = JURI::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
			} else {
				// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
				$thumb = $src;
			}
			if ($thumb) $thumbs[$item_id] = $thumb;
		}
		return $thumbs;
	}
	
	
	function getCatThumb(&$cat, &$params, $uprefix='cat', $rprefix='nav')
	{
		if ( empty($cat->id) || !$params->get($uprefix.'_use_image', 1) ) return '';
		
		// Joomla media folder
		$app = JFactory::getApplication();
		$joomla_image_path = $app->getCfg('image_path',  FLEXI_J16GE ? '' : 'images'.DS.'stories' );
		$joomla_image_url  = str_replace (DS, '/', $joomla_image_path);
		$joomla_image_path = $joomla_image_path ? $joomla_image_path.DS : '';
		$joomla_image_url  = $joomla_image_url  ? $joomla_image_url.'/' : '';
		
		$cat_image_source = $params->get($uprefix.'_image_source');
		
		$cat->image = $cat->parameters->get('image');
		$image_src = "";
		$cat->introtext = & $cat->description;
		$cat->fulltext = "";
		
		if ( $cat_image_source && $cat->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path . $cat->image ) ) {
			$src = JURI::base(true) ."/". $joomla_image_url . $cat->image;
			
			$w		= '&amp;w=' . $params->get($rprefix.'_width', 200);
			$h		= '&amp;h=' . $params->get($rprefix.'_height', 200);
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$zc		= $params->get($rprefix.'_method') ? '&amp;zc=' . $params->get($rprefix.'_method') : '';
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $zc . $f;
			
			$image_src = JURI::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
		} else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($cat) ) {
			// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
			$w		= '&amp;w=' . $params->get($rprefix.'_width', 200);
			$h		= '&amp;h=' . $params->get($rprefix.'_height', 200);
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$zc		= $params->get($rprefix.'_method') ? '&amp;zc=' . $params->get($rprefix.'_method') : '';
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $zc . $f;
			
			$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JURI::base(true).'/' : '';
			$image_src = JURI::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		}
		$cat->image_src = $image_src;
		return $image_src;
	}
	
	
	function getItemList(&$field, &$item, &$ids=null, $cid=null, &$cparams=null)
	{
		// Global parameters
		$gparams   = JFactory::getApplication()->getParams('com_flexicontent');
		$filtercat = $gparams->get('filtercat', 0); // If language filtering is enabled in category view
		
		$db    = JFactory::getDBO();
		$user  = JFactory::getUser();
		$date     = JFactory::getDate();
		$nowDate  = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
		$nullDate	= $db->getNullDate();
		
		if ($ids===null)
		{
			$select = 'SELECT a.id';
			$join = ''
				. ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = a.id'
				. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id '
				;
			
			// Get the site default language in case no language is set in the url
			$cntLang = substr(JFactory::getLanguage()->getTag(), 0,2);  // Current Content language (Can be natively switched in J2.5)
			$urlLang  = JRequest::getWord('lang', '' );                 // Language from URL (Can be switched via Joomfish in J1.5)
			$lang = (FLEXI_J16GE || empty($urlLang)) ? $cntLang : $urlLang;
			
			// parameters shortcuts
			$types_to_exclude	= $field->parameters->get('type_to_exclude', '');
			
			// filter depending on permissions
			if (FLEXI_J16GE) {
				$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
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
			$orderby_join = '';
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
			$orderby = ' ORDER BY '.$orderby;
			
			$types = is_array($types_to_exclude) ? implode(',', $types_to_exclude) : $types_to_exclude;
	
			$where  = ' WHERE rel.catid = ' . $cid;
			$where .=	' AND ( a.state = 1 OR a.state = -5 )' .
						' AND ( publish_up = '.$db->Quote($nullDate).' OR publish_up <= '.$db->Quote($nowDate).' )' .
						' AND ( publish_down = '.$db->Quote($nullDate).' OR publish_down >= '.$db->Quote($nowDate).' )' . 
						($types_to_exclude ? ' AND ie.type_id NOT IN (' . $types . ')' : '')
						;
			if ((FLEXI_FISH || FLEXI_J16GE) && $filtercat) {
				$where .= ' AND ( ie.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
			}
			
		}
		
		// Retrieving specific item data
		else {
			$select = 'SELECT a.*, ie.*,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(":", cc.id, cc.alias) ELSE cc.id END as categoryslug'
				;
			$join = ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = a.id'
				.' JOIN #__categories AS cc ON cc.id = '. $cid;
			$orderby = '';
			$orderby_join = '';
			$where = ' WHERE a.id IN ('. implode(',', $ids) .')';
			$andaccess = '';
		}
		
		// array of articles in same category correctly ordered
		$query 	= $select
				. ' FROM #__content AS a'
				. $join
				. $orderby_join
				. $where
				. $andaccess
				. $orderby
				;
		$db->setQuery($query);
		$list = $db->loadObjectList('id');
		if ($db->getErrorNum()) {
			JError::raiseWarning($db->getErrorNum(), $db->getErrorMsg(). "<br />".$query."<br />");
		}

		// this check needed if incorrect Itemid is given resulting in an incorrect result
		if ( !is_array($list) )  $list = array();
		return $list;
	}
}
