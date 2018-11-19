<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsFcpagenav extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		$field->html = '';
	}


	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		$field->{$prop} = '';

		global $globalcats;
		$app  = JFactory::getApplication();
		$db   = JFactory::getDbo();
		$user = JFactory::getUser();
		$option = $app->input->get('option', '', 'cmd');
		$view   = $app->input->get('view', 'item', 'cmd');
		$print  = $app->input->get('print', '', 'cmd');
		$add_tooltips = JComponentHelper::getParams('com_flexicontent')->get('add_tooltips', 1);

		// No output if it is not FLEXIcontent item view or view is "print"
		if ($view != FLEXI_ITEMVIEW || $option != 'com_flexicontent' || $print)
		{
			return;
		}


		// ***
		// *** Parameters shortcuts
		// ***

		$tooltip_class = 'hasTooltip';
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
		$loop_prevnext = (int) $field->parameters->get('loop_prevnext', 1);

		$field->{$prop} = null;
		$cid = $app->input->get('cid', 0 , 'int');
		$cid = !$cid || !isset($globalcats[$cid])
			? (int) $item->catid
			: $cid;

		$item_count = $app->getUserState( $option.'.'.$cid.'nav_item_count', 0);
		$loc_to_ids = $app->getUserState( $option.'.'.$cid.'nav_loc_to_ids', array());
		$ids_to_loc = array_flip($loc_to_ids);


		// ***
		// *** Get category parameters
		// ***

		$query 	= 'SELECT * FROM #__categories WHERE id = ' . $cid;
		$db->setQuery($query);
		$category = $db->loadObject();
		$category->parameters = new JRegistry($category->params);


		// ***
		// *** First check if location-IDs map has been already populated for this category id (for current user)
		// ***

		if ( isset($ids_to_loc[$item->id]) )
		{
			$location = $ids_to_loc[$item->id];
		}


		// ***
		// *** Get location-IDs map, we pass [ids = null] to indicate to return an item ids array. TODO retrieve item ids from view:
		// *** this will allow special navigating layouts "mcats,author,myitems,tags,favs" and also utilize current filtering
		// ***

		else
		{
			$ids = null;
			$loc_to_ids = $this->getItemList($ids, $cid, $user->id);

			$ids_to_loc = array_flip($loc_to_ids);  // Item ID to location MAP
			$item_count = count($loc_to_ids);    // Total items in category
			$location   = isset($ids_to_loc[$item->id]) ? $ids_to_loc[$item->id] : false;  // Location of current content item in array list

			if ($location!==false)
			{
				$offset = $location-50 > 0 ? $location-50 : 0;
				$length = $offset+100 < $item_count ? 100 : $item_count-$offset;
				$nav_loc_to_ids = array_slice( $loc_to_ids, $offset, $length, true);

				// Also add ID of first and last item to allow looping but add them
				// as negative to prevent same index twice when array is flipped
				$loc_to_ids[-1] = $nav_loc_to_ids[-1] = - $loc_to_ids[0];
				$loc_to_ids[-2] = $nav_loc_to_ids[-2] = - $loc_to_ids[$item_count - 1];

				$app->setUserState( $option.'.'.$cid.'nav_item_count', $item_count);
				$app->setUserState( $option.'.'.$cid.'nav_loc_to_ids', $nav_loc_to_ids);
			}
			else
			{
				$app->setUserState( $option.'.'.$cid.'nav_item_count', null);
				$app->setUserState( $option.'.'.$cid.'nav_loc_to_ids', null);
			}
		}


		// ***
		// *** Initialize to empty before getting data of: previous item / next item / category
		// ***

		$field->prev = null;
		$field->prevtitle = null;
		$field->prevurl = null;
		$field->next = null;
		$field->nexttitle = null;
		$field->nexturl = null;
		$field->category = null;
		$field->categorytitle = null;
		$field->categoryurl = null;


		// ***
		// *** Get item data
		// ***

		$rows = false;
		$prev_id = null;
		$next_id = null;
		if ($location !== false)
		{
			$prev_id = ($location - 1) >= 0
				? $loc_to_ids[$location - 1]
				: ($loop_prevnext && isset($loc_to_ids[-2]) ?  (- $loc_to_ids[-2]) : null);  // -2 index is last item

			$next_id = ($location + 1) < $item_count
				? $loc_to_ids[$location + 1]
				: ($loop_prevnext && isset($loc_to_ids[-1]) ? (- $loc_to_ids[-1]) : null);  // -1 index is first item

			$ids = array();

			// Previous item if it exists
			if ($prev_id) $ids[] = $prev_id;

			// Current item may not be listed in main category so retrieve it to get a proper categoryslug
			$ids[] = $item->id;

			// Next item if it exists
			if ($next_id) $ids[] = $next_id;

			// Query specific ids
			$rows = $this->getItemList($ids, $cid, $user->id);

			// Previous content item
			if ($prev_id)
			{
				$field->prev = $rows[$prev_id];
				$field->prevtitle = $field->prev->title;
				$field->prevurl = JRoute::_(FlexicontentHelperRoute::getItemRoute($field->prev->slug, $field->prev->categoryslug, 0, $field->prev));
			}

			// Next content item
			if ($next_id)
			{
				$field->next = $rows[$next_id];
				$field->nexttitle = $field->next->title;
				$field->nexturl = JRoute::_(FlexicontentHelperRoute::getItemRoute($field->next->slug, $field->next->categoryslug, 0, $field->next));
			}
		}


		// ***
		// *** Check if displaying nothing and stop
		// ***

		if (!$field->prev && !$field->next && (!$use_category_link || empty($rows[$item->id]->categoryslug)))
		{
			return;
		}


		// ***
		// *** Get images
		// ***

		$items_arr = array();
		if ($field->prev) $items_arr[$field->prev->id] = $field->prev;
		if ($field->next) $items_arr[$field->next->id] = $field->next;

		$img_err_msg = '';
		$thumbs = $this->getItemThumbs($field->parameters, $items_arr, $img_err_msg, $uprefix='item', $rprefix='nav');

		$field->prevThumb = $field->prev && isset($thumbs[$field->prev->id]) ? $thumbs[$field->prev->id] : '';
		$field->nextThumb = $field->next && isset($thumbs[$field->next->id]) ? $thumbs[$field->next->id] : '';


		// ***
		// *** Load view layout
		// ***

		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

		include(self::getViewPath($field->field_type, $viewlayout));


		// ***
		// *** Load needed JS/CSS
		// ***

		if ($add_tooltips && $use_tooltip)
		{
			JHtml::_('bootstrap.tooltip');
		}
		if ($load_css)
		{
			JFactory::getDocument()->addStyleSheet(JUri::root(true).'/plugins/flexicontent_fields/fcpagenav/'.(FLEXI_J16GE ? 'fcpagenav/' : '').'fcpagenav.css');
		}

		$field->{$prop} = $html;
	}


	function getItemThumbs(&$params, &$items, & $img_err_msg, $uprefix='item', $rprefix='nav')
	{
		if ( !$params->get($uprefix.'_use_image', 0) ) return array();
		if ( empty($items) ) return array();

		if ( $params->get($uprefix.'_image') ) {
			$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small', 'o'=>'original');
			$img_field_size = $img_size_map[ $params->get($uprefix.'_image_size' , 'l') ];
			$img_field_name = $params->get($uprefix.'_image');
		}

		if (!empty($img_field_name))
		{
			FlexicontentFields::getFieldDisplay($items, $img_field_name, $values=null, 'display_'.$img_field_size.'_src', FLEXI_ITEMVIEW);
		}

		$thumbs = array();
		foreach($items as $item_id => $item)
		{
			if ( !empty($img_field_name) ) {
				$src = '';
				// This is not set when image field is not assigned to the item type
				if ( !empty($item->fields[$img_field_name]) )
				{
					$img_field = $item->fields[$img_field_name];
					$src = str_replace(JUri::root(), '', @ $img_field->{'display_'.$img_field_size.'_src'});
				}
			}
			else {
				$src = flexicontent_html::extractimagesrc($item);
			}

			$RESIZE_FLAG = !$params->get($uprefix.'_image') || !$params->get($uprefix.'_image_size');
			if ( $src && $RESIZE_FLAG ) {
				// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
				$w		= '&amp;w=' . $params->get($rprefix.'_width', 200);
				$h		= '&amp;h=' . $params->get($rprefix.'_height', 200);
				$aoe	= '&amp;aoe=1';
				$q		= '&amp;q=95';
				$ar 	= '&amp;ar=x';
				$zc		= $params->get($rprefix.'_method') ? '&amp;zc=' . $params->get($rprefix.'_method') : '';
				$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
				$f = in_array( $ext, array('png', 'ico', 'gif', 'jpg', 'jpeg') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

				$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
				$thumb = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
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
		if ( empty($cat->id) || !$params->get($uprefix.'_use_image', 0) ) return '';

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
			$src = JUri::base(true) ."/". $joomla_image_url . $cat->image;

			$w		= '&amp;w=' . $params->get($rprefix.'_width', 200);
			$h		= '&amp;h=' . $params->get($rprefix.'_height', 200);
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$ar 	= '&amp;ar=x';
			$zc		= $params->get($rprefix.'_method') ? '&amp;zc=' . $params->get($rprefix.'_method') : '';
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'ico', 'gif', 'jpg', 'jpeg') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

			$image_src = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
		} else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($cat) ) {
			// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
			$w		= '&amp;w=' . $params->get($rprefix.'_width', 200);
			$h		= '&amp;h=' . $params->get($rprefix.'_height', 200);
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$ar 	= '&amp;ar=x';
			$zc		= $params->get($rprefix.'_method') ? '&amp;zc=' . $params->get($rprefix.'_method') : '';
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'ico', 'gif', 'jpg', 'jpeg') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

			$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
			$image_src = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		}
		$cat->image_src = $image_src;
		return $image_src;
	}


	function getItemList(&$ids=null, $cid=null, &$userid=0)
	{
		$app  = JFactory::getApplication();
		$db = JFactory::getDbo();

		if ($ids===null)
		{
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'category.php');

			$saved_cid = $app->input->get('cid', '', 'string');   // save cid ...
			$saved_layout = $app->input->get('layout', '', 'string'); // save layout ...
			$saved_option = $app->input->get('option', '', 'string'); // save option ...
			$saved_view = $app->input->get('view', '', 'string'); // save layout ...

			//$target_layout = $mcats_selection || !$catid ? 'mcats' : '';
			//$app->input->set('layout', $target_layout);
			//$app->input->set($target_layout=='mcats' ? 'cids' : 'cid', $limit_filters_to_cat ? $catid : 0);
			$app->input->set('layout', '');
			$app->input->set('cid', $cid);
			$app->input->set('option', 'com_flexicontent');
			$app->input->set('view', 'category');

			// Get/Create current category model ... according to configuration set above into the HTTP Request variables
			$catmodel = new FlexicontentModelCategory();
			$category = $catmodel->getCategory($pk=null, $raiseErrors=false, $checkAccess=false, $checkPublished=false);

			$query = $catmodel->_buildQuery(false, $count_total=false);
			$db->setQuery($query, 0, 50000);
			$list = $db->loadColumn();
			$list = is_array($list) ? $list : array();

			// Restore variables
			$app->input->set('cid', $saved_cid); // restore cid
			$app->input->set('layout', $saved_layout); // restore layout
			$app->input->set('option', $saved_option); // restore option
			$app->input->set('view', $saved_view); // restore view

			return $list;
		}

		// Retrieving specific item data
		else
		{
			$query 	= 'SELECT i.*, ie.*,'
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(":", i.id, i.alias) ELSE i.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(":", cc.id, cc.alias) ELSE cc.id END as categoryslug'
				. ' FROM #__content AS i'
				. ' JOIN #__flexicontent_items_ext AS ie on ie.item_id = i.id'
				. ' JOIN #__categories AS cc ON cc.id = '. $cid
				. ' WHERE i.id IN ('. implode(',', $ids) .')';
			$db->setQuery($query);

			try {
				$list = $db->loadObjectList('id');
			}
			catch (Exception $e) {
				if ($db->getErrorNum()) JError::raiseWarning($db->getErrorNum(), $db->getErrorMsg(). "<br />".$query."<br />");
				return array();
			}

			$list = is_array($list) ? $list : array();
			return $list;
		}
	}



	/**
	 * Retrieve subcategory ids of a given category
	 *
	 * @access public
	 * @return string
	 */
	function &_getDataCats($id_arr, &$cparams)
	{
		global $globalcats;
		$db   = JFactory::getDbo();
		$user = JFactory::getUser();
		$ordering = 'c.lft ASC';

		$show_noauth = $cparams->get('show_noauth', 0);   // show unauthorized items
		$display_subcats = $cparams->get('display_subcategories_items', 2);   // include subcategory items

		// Select only categories that user has view access, if listing of unauthorized content is not enabled
		$joinaccess = '';
		$andaccess = '';
		if (!$show_noauth)
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$andaccess .= ' AND c.access IN (0,'.$aid_list.')';
		}

		// Calculate categories to use for retrieving items
		$query_catids = array();
		foreach ($id_arr as $id)
		{
			$query_catids[$id] = 1;
			if ( $display_subcats==2 && !empty($globalcats[$id]->descendantsarray) )
			{
				foreach ($globalcats[$id]->descendantsarray as $subcatid) $query_catids[$subcatid] = 1;
			}
		}
		$query_catids = array_keys($query_catids);

		// Items in featured categories
		/*
		$cats_featured = $cparams->get('display_cats_featured', 0);
		$featured_cats_parent = $cparams->get('featured_cats_parent', 0);
		$query_catids_exclude = array();
		if ($cats_featured && $featured_cats_parent)
		{
			foreach ($globalcats[$featured_cats_parent]->descendantsarray as $subcatid) $query_catids_exclude[$subcatid] = 1;
		}
		*/


		// ***
		// *** Filter by depth level
		// ***

		// Include categories
		if ($display_subcats==0)
		{
			$anddepth = ' AND c.id IN (' .implode(',', $query_catids). ')';
		}

		// Include categories and their subcategories
		else if ($display_subcats==1)
		{
			$anddepth  = ' AND ( c.parent_id IN (' .implode(',', $query_catids). ')  OR  c.id IN (' .implode(',', $query_catids). ') )';
		}

		// Include categories and their descendants
		else
		{
			$anddepth = ' AND c.id IN (' .implode(',', $query_catids). ')';
		}

		// Finally create the query to get the category ids.
		// NOTE: this query is not just needed to get 1st level subcats, but it always needed TO ALSO CHECK the ACCESS LEVEL
		$query = 'SELECT c.id'
			. ' FROM #__categories AS c'
			. $joinaccess
			. ' WHERE c.published = 1'
			. $andaccess
			. $anddepth
			. ' ORDER BY '.$ordering
			;

		$db->setQuery($query);
		$this->_data_cats = $db->loadColumn();
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');

		return $this->_data_cats;
	}
}
