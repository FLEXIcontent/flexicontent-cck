<?php
/**
 * @version 1.2 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent Module
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
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
require_once (JPATH_SITE.DS.'modules'.DS.'mod_flexicontent'.DS.'classes'.DS.'datetime.php');

class modFlexicontentHelper
{
	function getList(&$params)
	{
		global $mainframe, $option;

		$Itemid		= JRequest::getInt('Itemid');
		if (!$Itemid) {
			$app = & JFactory::getApplication();
			$app->route();
			$cid 		= JRequest::getInt('cid');
			$id			= JRequest::getInt('id');
			$Itemid		= JRequest::getInt('Itemid');
		}
		//$force_curr_itemid = (int)$params->get('force_curr_itemid', 1);
		//$forced_itemid = ($force_curr_itemid) ? $Itemid	: 0;

		// get the component parameters
		$flexiparams =& JComponentHelper::getParams('com_flexicontent');
		
		// get module ordering parameters
		$ordering 				= $params->get('ordering');
		$count 					= (int)$params->get('count', 5);
		$featured				= (int)$params->get('count_feat', 1);

		// get module display parameters
		$moduleclass_sfx 		= $params->get('moduleclass_sfx');
		$layout 				= $params->get('layout');
		$add_ccs 				= $params->get('add_ccs');
		$add_tooltips 			= $params->get('add_tooltips', 1);
		// standard
		$display_title 			= $params->get('display_title');
		$link_title 			= $params->get('link_title');
		$cuttitle 				= $params->get('cuttitle');
		$display_text 			= $params->get('display_text');
		$mod_readmore	 		= $params->get('mod_readmore');
		$mod_cut_text 			= $params->get('mod_cut_text');
		$mod_do_stripcat		= $params->get('mod_do_stripcat', 1);
		$mod_use_image 			= $params->get('mod_use_image');
		$mod_image 				= $params->get('mod_image');
		$mod_link_image 		= $params->get('mod_link_image');
		$mod_width 				= (int)$params->get('mod_width', 80);
		$mod_height 			= (int)$params->get('mod_height', 80);
		$mod_method 			= (int)$params->get('mod_method', 1);
		// featured
		$display_title_feat 	= $params->get('display_title_feat');
		$link_title_feat 		= $params->get('link_title_feat');
		$cuttitle_feat 			= $params->get('cuttitle_feat');
		$display_text_feat 		= $params->get('display_text');
		$mod_readmore_feat		= $params->get('mod_readmore_feat');
		$mod_cut_text_feat 		= $params->get('mod_cut_text_feat');
		$mod_do_stripcat_feat	= $params->get('mod_do_stripcat_feat', 1);
		$mod_use_image_feat 	= $params->get('mod_use_image_feat');
		$mod_link_image_feat 	= $params->get('mod_link_image_feat');
		$mod_width_feat 		= (int)$params->get('mod_width_feat', 140);
		$mod_height_feat 		= (int)$params->get('mod_height_feat', 140);
		$mod_method_feat 		= (int)$params->get('mod_method_feat', 1);

		// get module fields parameters
		$use_fields 			= $params->get('use_fields');
		$display_label 			= $params->get('display_label');
		$fields = array_map( 'trim', explode(',', $params->get('fields')) );
		if ($fields[0]=='') $fields = array();
		
		// get fields that when empty cause an item to be skipped
		$skip_items = (int)$params->get('skip_items', 0);
		$skiponempty_fields = array_map( 'trim', explode(',', $params->get('skiponempty_fields')) );
		if ($skiponempty_fields[0]=='') $skiponempty_fields = array();
		
		if ($params->get('maxskipcount',50) > 100) {
  		$params->set('maxskipcount',100);
		}
		
		$striptags_onempty_fields = $params->get('striptags_onempty_fields');
		$onempty_fields_combination = $params->get('onempty_fields_combination');
		
		// featured
		$display_label_feat 	= $params->get('display_label_feat');
		$fields_feat 			= explode(',', $params->get('fields_feat'));
		$fields_feat = array_map( 'trim', explode(',', $params->get('fields_feat')) );
		if ($fields_feat[0]=='') $fields_feat = array();

		$rows = array();
		if (!is_array($ordering)) { $ordering = explode(',', $ordering); }
		foreach ($ordering as $ord) {
			$items = modFlexicontentHelper::getItems($params, $ord);		
			for ($i=0; $i<count($items); $i++) {
				$items[$i]->featured = ($i < $featured) ? 1 : 0;
				$items[$i]->fetching = $ord;
				array_push($rows, $items[$i]);
			}
		}
		
		// Impementation of Empty Field Filter.
		// The cost of the following code is minimal.
		// The big time cost goes into rendering the fields ... 
		// We need to create the display of the fields before examining if they are empty.
		// The hardwired limit of max items skipped is 100.
		if ( ($use_fields && count($fields)) || ($skip_items && count($skiponempty_fields)) ) {
		  // 1. Add skipfields to the list of fields to be rendered
		  $fields_merged = array();
		  foreach ($fields as $fieldname)             $fields_merged[$fieldname] = 1;
		  foreach ($skiponempty_fields as $fieldname) $fields_merged[$fieldname] = 1;
		  $allfields = '';
		  $comma = '';
		  $fields = array();
		  foreach ($fields_merged as $fieldname => $val) {
		    $allfields .= $comma.$fieldname;
		    $comma = ',';
  		  $fields[] = $fieldname;
		  }
		  $params->set('fields',$allfields);
		  
		  // 2. Get fields values for the items
			$items = FlexicontentFields::getFields($rows, 'module', $params);
		  
		  // 3. Skip Items with empty fields (if this filter is enabled)
		  //    Other
		  $rows = array();
		  foreach($items as $item) {
		  	if (!isset($order_count[$item->fetching]))    // Check to initialize counter for this ordering 
		  		$order_count[$item->fetching] = 0;
		  	if ($order_count[$item->fetching] >= $count)   // Check if enough encountered for this ordering 
		  		continue;
		  	
		    // Now check for empty values on field that when empty, the item must be skipped
		    if ($skip_items) {
			  	// Construct display values array
			  	$field_val = array();
			  	foreach($skiponempty_fields as $skipfieldname) {
			  		$field_val[$skipfieldname] = $item->fields[$skipfieldname]->display;
			  	}
			    
  		    if ($onempty_fields_combination == 'any')
  		      $skip_item = 0;
  		    else //if ($skip_items && $onempty_fields_combination == 'all')
  		      $skip_item = 1;		    
  		    
  		    foreach($skiponempty_fields as $skipfieldname) {
  		      $val = $field_val[$skipfieldname];
  		      if ($striptags_onempty_fields) $val = strip_tags ($field_val[$skipfieldname]) ;
  		      $val = trim($val);
  		      if ( !$val) {
  		        if ($onempty_fields_combination=='any') {
      	  	    $skip_item = 1;
      	  	    break;
      	  	  }
      	  	} else {
      	  	  if ($onempty_fields_combination == 'all') {
      	  	    $skip_item = 0;
      	  	    break;
      	  	  }
  		      }
  		    }
  		    if ($skip_item && count($skiponempty_fields)) {
  		      if(!isset($order_skipcount[$item->fetching]) ) $order_skipcount[$item->fetching] = 0;
  		      $order_skipcount[$item->fetching]++;
  		      continue;
  		    }
		    }
		    
		    // 4. Increment counter for item's ordering and Add item to list of displayed items
		    $order_count[$item->fetching]++;
		    $rows[] = $item;
		  }
		}
		
		// For Debuging
		/*foreach ($order_skipcount as $skipordering => $skipcount) {
		  echo "SKIPS $skipordering ==> $skipcount<br>\n";
		}*/

		$lists	= array();
		foreach ( $ordering as $ord )
		{
			$lists[$ord]	= array();
		}
		
		$ord = "__start__";
		foreach ( $rows as $row )  // Single pass of rows
		{
		  if ($ord != $row->fetching) {  // Detect change of next ordering group
		    $ord = $row->fetching;
		    $i = 0;
		  }
		  
			if ($row->featured)
			{						
				// image processing
				$thumb = '';
				if ($mod_use_image_feat) {
					if ($mod_image) {
						if (isset($row->image)) {
							$image	= unserialize($row->image);
							$src	= JURI::base(true) . '/' . $flexiparams->get('file_path') . '/' . $image['originalname'];

		    				$h		= '&h=' . $mod_height_feat;
		   					$w		= '&w=' . $mod_width_feat;
		    				$aoe	= '&aoe=1';
		    				$q		= '&q=95';
		    				$zc		= $mod_method_feat ? '&zc=' . $mod_method_feat : '';
		    				$conf	= $w . $h . $aoe . $q . $zc;
		
		    				$thumb 	= JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
						} else {
							$thumb	= '';
						}
					} else {
						//$thumb	= flexicontent_html::extractimagesrc($row);
						$articleimage = flexicontent_html::extractimagesrc($row);
						if ($articleimage) {
						  $src	= JURI::base(true) . '/' . $articleimage;
						  
		    				$h		= '&h=' . $mod_height_feat;
		   					$w		= '&w=' . $mod_width_feat;
		    				$aoe	= '&aoe=1';
		    				$q		= '&q=95';
		    				$zc		= $mod_method_feat ? '&zc=' . $mod_method_feat : '';
		    				$conf	= $w . $h . $aoe . $q . $zc;
	    				
		    			$thumb 	= JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
		    		} else {
		    		  $thumb = '';
		    		}
					}
				}
				$lists[$ord]['featured'][$i]->id = $row->id;
				//date
				if ($params->get('display_date', 1) == 1) {
					$dateformat = $params->get('date_format_feat', 'DATE_FORMAT_LC3');
					if($dateformat == JText::_('custom'))
						$dateformat = $params->get('custom_date_format_feat', JText::_('DATE_FORMAT_LC3'));
					if ($params->get('date_type_feat', 1) == 1) {
						$lists[$ord]['featured'][$i]->date = $params->get('date_label_feat',1) ? '<span class="date_label_feat">'.JText::_('FLEXI_DATE_CREATED').':</span> ' : '';
						$lists[$ord]['featured'][$i]->date .= '<span class="date_value_feat">' . JHTML::_('date', $row->created, $dateformat) . '</span>';
					} else {
						$lists[$ord]['featured'][$i]->date = $params->get('date_label_feat',1) ? '<span class="date_label_feat">'.JText::_('FLEXI_DATE_MODIFIED').':</span> ' : '';
						$lists[$ord]['featured'][$i]->date .= '<span class="date_value_feat">' . JHTML::_('date', $row->modified, $dateformat) . '</span>';
					}
				}
				$lists[$ord]['featured'][$i]->image 	= $thumb;
				$lists[$ord]['featured'][$i]->link 		= JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug/*, $forced_itemid*/));
				$lists[$ord]['featured'][$i]->title 	= flexicontent_html::striptagsandcut($row->title, $cuttitle_feat);
				$lists[$ord]['featured'][$i]->fulltitle = $row->title;
				$lists[$ord]['featured'][$i]->text = ($mod_do_stripcat_feat)? flexicontent_html::striptagsandcut($row->introtext, $mod_cut_text_feat) : $row->introtext;
				$lists[$ord]['featured'][$i]->typename 	= $row->typename;
				$lists[$ord]['featured'][$i]->access 	= $row->access;
				$lists[$ord]['featured'][$i]->featured 	= 1;
				
				if ($use_fields && $row->fields && $fields_feat) {
					foreach ($fields_feat as $field) {
						if ($display_label_feat) {
							$lists[$ord]['featured'][$i]->fields[$field]->label = @$row->fields[$field]->label ? $row->fields[$field]->label : '';
						}
						$lists[$ord]['featured'][$i]->fields[$field]->display 	= @$row->fields[$field]->display ? $row->fields[$field]->display : '';
					}
				}
				
				$i++;
			} else {
				// image processing
				$thumb = '';
				if ($mod_use_image) {
					if ($mod_image) {
						if (isset($row->image)) {
							$image	= unserialize($row->image);
							$src	= JURI::base(true) . '/' . $flexiparams->get('file_path') . '/' . $image['originalname'];

		    				$h		= '&h=' . $mod_height;
		   					$w		= '&w=' . $mod_width;
		    				$aoe	= '&aoe=1';
		    				$q		= '&q=95';
		    				$zc		= $mod_method ? '&zc=' . $mod_method : '';
		    				$conf	= $w . $h . $aoe . $q . $zc;
		
		    				$thumb 	= JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
						} else {
							$thumb	= '';
						}
					} else {
						//$thumb	= flexicontent_html::extractimagesrc($row);
						$articleimage = flexicontent_html::extractimagesrc($row);
						if ($articleimage) {
						  $src	= JURI::base(true) . '/' . $articleimage;
						  
	    				$h		= '&h=' . $mod_height;
	   					$w		= '&w=' . $mod_width;
	    				$aoe	= '&aoe=1';
	    				$q		= '&q=95';
	    				$zc		= $mod_method ? '&zc=' . $mod_method : '';
	    				$conf	= $w . $h . $aoe . $q . $zc;
	    				
		    			$thumb 	= JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
		    		} else {
		    		  $thumb = '';
		    		}
					}
				}
				$lists[$ord]['standard'][$i]->id = $row->id;
				//date
				if ($params->get('display_date', 1) == 1) {
					$dateformat = $params->get('date_format', 'DATE_FORMAT_LC3');
					if($dateformat == JText::_('custom'))
						$dateformat = $params->get('custom_date_format', JText::_('DATE_FORMAT_LC3'));
					if ($params->get('date_type', 1) == 1) {
						$lists[$ord]['standard'][$i]->date = $params->get('date_label',1) ? '<span class="date_label">'.JText::_('FLEXI_DATE_CREATED').':</span> ' : '';
						$lists[$ord]['standard'][$i]->date .= '<span class="date_value">' . JHTML::_('date', $row->created, $dateformat) . '</span>';
					} else {
						$lists[$ord]['standard'][$i]->date = $params->get('date_label',1) ? '<span class="date_label">'.JText::_('FLEXI_DATE_MODIFIED').':</span> ' : '';
						$lists[$ord]['standard'][$i]->date .= '<span class="date_value">' . JHTML::_('date', $row->modified, $dateformat) . '</span>';
					}
				}
				$lists[$ord]['standard'][$i]->image 	= $thumb;
				$lists[$ord]['standard'][$i]->link 		= JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug/*, $forced_itemid*/));
				$lists[$ord]['standard'][$i]->title 	= flexicontent_html::striptagsandcut($row->title, $cuttitle);
				$lists[$ord]['standard'][$i]->fulltitle = $row->title;
				$lists[$ord]['standard'][$i]->text = ($mod_do_stripcat)? flexicontent_html::striptagsandcut($row->introtext, $mod_cut_text) : $row->introtext;
				$lists[$ord]['standard'][$i]->typename 	= $row->typename;
				$lists[$ord]['standard'][$i]->access 	= $row->access;
				$lists[$ord]['standard'][$i]->featured 	= 0;

				if ($use_fields && $row->fields && $fields) {
					foreach ($fields as $field) {
						if ($display_label) {
							$lists[$ord]['standard'][$i]->fields[$field]->label = @$row->fields[$field]->label ? $row->fields[$field]->label : '';
						}
						$lists[$ord]['standard'][$i]->fields[$field]->display 	= @$row->fields[$field]->display ? $row->fields[$field]->display : '';
					}
				}

				$i++;
			}
		}

		return $lists;
	}

	function getItems(&$params, $ordering)
	{
		global $mainframe, $dump, $globalcats;

		// For specific cache issues
		if (!$globalcats) {
			if (FLEXI_SECTION) {
				if (FLEXI_CACHE) {
				// add the category tree to categories cache
				$catscache 	=& JFactory::getCache('com_flexicontent_cats');
				$catscache->setCaching(1); 		//force cache
				$catscache->setLifeTime(84600); //set expiry to one day
			    $globalcats = $catscache->call(array('plgSystemFlexisystem', 'getCategoriesTree'));
				} else {
					$globalcats = plgSystemFlexisystem::getCategoriesTree();
				}
			}
		}

		// Initialize variables
		$db				=& JFactory::getDBO();
		$user			=& JFactory::getUser();
		$gid			= (int) $user->get('aid');
		$now			= $mainframe->get('requestTime');
		$nullDate 		= $db->getNullDate();
		$view			= JRequest::getVar('view');
		$option			= JRequest::getVar('option');
		$fparams 		=& $mainframe->getParams('com_flexicontent');
		$show_noauth 	= $fparams->get('show_noauth', 0);
		
		// current item scope parameters
		$method_curitem	= (int)$params->get('method_curitem', 1);
			
		// current language scope parameters
		$method_curlang	= (int)$params->get('method_curlang', 1);
		
		// categories scope parameters
		$method_cat 		= (int)$params->get('method_cat', 1);
		$catids 			= $params->get('catids');
		$behaviour_cat 		= $params->get('behaviour_cat', 0);
		$treeinclude 		= $params->get('treeinclude');

		// types scope parameters
		$method_types 		= (int)$params->get('method_types', 1);
		$types 				= $params->get('types');
		$behaviour_types 	= $params->get('behaviour_types', 0);

		// authors scope parameters
		$method_auth 		= (int)$params->get('method_auth', 1);
		$authors 			= trim($params->get('authors'));
		$behaviour_auth 	= $params->get('behaviour_auth');
		
		// items scope parameters
		$method_items 		= (int)$params->get('method_items', 1);
		$items	 			= trim($params->get('items'));
		$behaviour_items 	= $params->get('behaviour_items', 0);

		// date scope parameters
		$date_type 			= (int)$params->get('date_type', 0);
		$bdate 				= $params->get('bdate', '');
		$edate 				= $params->get('edate', '');
		$behaviour_dates 	= $params->get('behaviour_dates', 0);
		$date_compare 		= $params->get('date_compare', 0);
		// Server date		
		$sdate 			= explode(' ', $now);
		$cdate 			= $sdate[0] . ' 00:00:00';
		// Set date comparators
		if ($date_type == 1) { // modified
			$comp = 'modified';
		} elseif ($date_type == 2) { // publish up
			$comp = 'publish_up';
		} else { // created
			$comp = 'created';
		}
		
		// get module fetching parameters
		if ($params->get('skip_items',0) ) {
		  $count = (int)$params->get('maxskipcount', 50);
		} else {
		  $count = (int)$params->get('count', 5);
		}

		// get module display parameters
		$mod_image 			= $params->get('mod_image');
		
		$where 	= ' WHERE i.sectionid = ' . FLEXI_SECTION;
		$where .= ' AND c.published = 1';
		$where .= ' AND i.state IN ( 1, -5 )';
		$where .= ' AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$db->Quote($now).' )';
		$where .= ' AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$db->Quote($now).' )';

		// filter by permissions
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
				if (isset($readperms['item'])) {
					$where .= ' AND ( i.access <= '.$gid.' OR i.id IN ('.implode(",", $readperms['item']).') )';
				} else {
					$where .= ' AND i.access <= '.$gid;
				}
			} else {
				$where .= ' AND i.access <= '.$gid;
			}
		}

		if ( ($behaviour_cat || $behaviour_auth || $behaviour_items || $behaviour_types || $date_compare) && ((($option == 'com_flexicontent') && ($view == 'items')) || (($option == 'com_nutrition') && ($view == 'frAliment'))) )  {
			// initialize variables
			$cid 		= JRequest::getInt('cid');
			$id			= JRequest::getInt('id');
			$Itemid		= JRequest::getInt('Itemid');
			
			$q 	 		= 'SELECT c.*, ie.type_id, GROUP_CONCAT(ci.catid SEPARATOR ",") as itemcats FROM #__content as c'
						. ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = c.id'
						. ' LEFT JOIN #__flexicontent_cats_item_relations AS ci on ci.itemid = c.id'
						. ' WHERE c.id = ' . $id
						. ' GROUP BY ci.itemid'
						;
			$db->setQuery($q);
			$curitem	= $db->loadObject();

			// Get item dates
			if ($date_type == 1) {
				$idate = $curitem->modified;
			} elseif ($date_type == 2) {
				$idate = $curitem->publish_up;
			} else {
				$idate = $curitem->created;			
			}
			$idate 	= explode(' ', $idate);
			$cdate 	= $idate[0] . ' 00:00:00';
			$curritemcats = $idate 	= explode(',', $curitem->itemcats);
		}


		// current item scope
		$currid			= JRequest::getInt('id');
  	if ($method_curitem == 1) { // exclude method  ---  exclude current item
		  $where .=  ' AND i.id <> ' . $currid;
		} else if ($method_curitem == 2) { // include method  ---  include current item ONLY
		  $where .=  ' AND i.id = ' . $currid;
		} else {
		  // All Items including current
		}

		// current language scope
		$lang = JRequest::getWord('lang', '' );
		if(empty($lang)){
			$langFactory= JFactory::getLanguage();
			$tagLang = $langFactory->getTag();
			//Well, the substr is not even required as flexi saves the Joomla language tag... so we could have kept the $tagLang tag variable directly.
			$lang = substr($tagLang ,0,2);
		}
		if ($method_curlang == 1) { // exclude method  ---  exclude items of current language
			$where .= ' AND ie.language NOT LIKE ' . $db->Quote( $lang .'%' );
		} else if ($method_curlang == 2) { // include method  ---  include items of current language ONLY
			$where .= ' AND ie.language LIKE ' . $db->Quote( $lang .'%' );
		} else {
		  // Items of any language
		}
		
		// categories scope
		if (!$behaviour_cat) {
			$catids		= is_array($catids) ? implode(',', $catids) : $catids;
			if ($method_cat == 2) { // exclude method
				$where .= ' AND c.id NOT IN (' . $catids . ')';		
			} else if ($method_cat == 3) { // include method
				$where .= ' AND c.id IN (' . $catids . ')';		
			}
		} else {
			if (($option != 'com_flexicontent') || ($view != 'items')) {
				return;
			} else {							
				$menus		=& JApplication::getMenu('site', array());
				$menu 		=& $menus->getItem($Itemid);
				$routecat	=  @$menu->query['cid'] ? $menu->query['cid'] : 0;
				$cid		= $cid ? $cid : ($routecat ? $routecat : $curitem->catid);

				switch ($treeinclude) {
					case 0: // current category only
						if ($behaviour_cat == 1) {
							$where .= ' AND c.id = ' . $cid;
						} else {
							$where .= ' AND c.id <> ' . $cid;
						}
					break;
					case 1: // current category + children
						if ($behaviour_cat == 1) {
							$where .= ' AND c.id IN (' . implode(',', $globalcats[$cid]->descendantsarray) . ')';
						} else {
							$where .= ' AND c.id NOT IN (' . implode(',', $globalcats[$cid]->descendantsarray) . ')';
						}
					break;
					case 2: // current category + parents
						if ($behaviour_cat == 1) {
							$where .= ' AND c.id IN (' . implode(',', $globalcats[$cid]->ancestorsarray) . ')';
						} else {
							$where .= ' AND c.id NOT IN (' . implode(',', $globalcats[$cid]->ancestorsarray) . ')';
						}
					break;
					case 3: // current category + children + parents
						$relatedcats = array_unique(array_merge($globalcats[$cid]->descendantsarray, $globalcats[$cid]->ancestorsarray));						
						
						if ($behaviour_cat == 1) {
							$where .= ' AND c.id IN (' . implode(',', $relatedcats) . ')';
						} else {
							$where .= ' AND c.id NOT IN (' . implode(',', $relatedcats) . ')';
						}
					break;
					case 4: // all item's categories
						if ($behaviour_cat == 1) {
							$where .= ' AND c.id IN (' . implode(',', $curritemcats) . ')';
						} else {
							$where .= ' AND c.id NOT IN (' . implode(',', $curritemcats) . ')';
						}
					break;
				}
			}
		}

		// types scope
		if (!$behaviour_types) {
			$types		= is_array($types) ? implode(',', $types) : $types;
			if ($method_types == 2) { // exclude method
				$where .= ' AND ie.type_id NOT IN (' . $types . ')';		
			} else if ($method_types == 3) { // include method
				$where .= ' AND ie.type_id IN (' . $types . ')';		
			}
		} else {
			if (($option != 'com_flexicontent') || ($view != 'items')) {
				return;
			} else {
				if ($behaviour_types == 1) {
					$where .= ' AND ie.type_id = ' . (int)$curitem->type_id;		
				} else {
					$where .= ' AND ie.type_id <> ' . (int)$curitem->type_id;		
				}
			}
		}

		// author scope
		if (!$behaviour_auth) {
			if ($method_auth == 2) { // exclude method
				$where .= ' AND i.created_by NOT IN (' . $authors . ')';		
			} else if ($method_auth == 3) { // include method
				$where .= ' AND i.created_by IN (' . $authors . ')';		
			}
		} else {
			if (($option != 'com_flexicontent') || ($view != 'items')) {
				return;
			} else {			
				if ($behaviour_types == 1) {
					$where .= ' AND i.created_by = ' . (int)$curitem->created_by;		
				} else {
					$where .= ' AND i.created_by <> ' . (int)$curitem->created_by;		
				}
			}
		}

		// items scope
		if (!$behaviour_items) {
			if ($method_items == 2) { // exclude method
				$where .= ' AND i.id NOT IN (' . $items . ')';		
			} else if ($method_items == 3) { // include method
				$where .= ' AND i.id IN (' . $items . ')';		
			}
		} else {
			if (($option == 'com_flexicontent') && ($view == 'items')) {
				// select the tags associated to the item
				$query2 = 'SELECT tid' .
						' FROM #__flexicontent_tags_item_relations' .
						' WHERE itemid = '.(int) $id;
				$db->setQuery($query2);
				$tags = $db->loadResultArray();
	
				if ($tags) {
					$where2 = (count($tags) > 1) ? ' AND tid IN ('.implode(',', $tags).')' : ' AND tid = '.$tags[0];
					
					// select the tags associated to the item
					$query3 = 'SELECT DISTINCT itemid' .
							' FROM #__flexicontent_tags_item_relations' .
							' WHERE itemid <> '.(int) $id .
							$where2
							;
					$db->setQuery($query3);
					$related = $db->loadResultArray();
				}
								
				if (isset($related)) {
					$where .= (count($related) > 1) ? ' AND i.id IN ('.implode(',', $related).')' : ' AND i.id = '.$related[0];
				} else {
					return;
				}
			/*
			** BOF adaptation spécifique pour le com_nutrition de Valérie
			*/
			} elseif (($option == 'com_nutrition') && ($view == 'frAliment')) {
				// select the tags associated to the aliment
				$query2 = 'SELECT tid' .
						' FROM #__nut_fr_tags_item_relations' .
						' WHERE alimentid = '.(int) $id;
				$db->setQuery($query2);
				$tags = $db->loadResultArray();
	
				if ($tags) {
					$where2 = (count($tags) > 1) ? ' AND tid IN ('.implode(',', $tags).')' : ' AND tid = '.$tags[0];
					
					// select the tags associated to the item
					$query3 = 'SELECT DISTINCT itemid' .
							' FROM #__flexicontent_tags_item_relations' .
							' WHERE itemid <> '.(int) $id .
							$where2
							;
					$db->setQuery($query3);
					$related = $db->loadResultArray();
				}
								
				if (isset($related)) {
					$where .= (count($related) > 1) ? ' AND i.id IN ('.implode(',', $related).')' : ' AND i.id = '.$related[0];
				} else {
					return;
				}
			/*
			** EOF adaptation spécifique pour le com_nutrition de Valérie
			*/
			} else {			
				return;
			}
		}

		// date scope
		if (!$behaviour_dates) {
			if ($edate)
				$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' <= '.$db->Quote($edate).' )';
			if ($bdate)
				$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' >= '.$db->Quote($bdate).' )';
		} else {
			if ( (($option != 'com_flexicontent') || ($view != 'items')) && ($date_compare == 1) ) {
				return;
			} else {
				switch ($behaviour_dates) 
				{
					case '1' : // custom offset
						if ($edate) {
							$edate = explode(' ', $edate);
							$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, $edate[0], $edate[1])).' )';
						}
						if ($bdate) {
							$bdate = explode(' ', $bdate);
							$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, $bdate[0], $bdate[1])).' )';
						}
					break;

					case '2' : // same month
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-'.$cdate[1].'-01 00:00:00';

						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 1, 'm')).' )';
						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' >= '.$db->Quote($cdate).' )';
					break;

					case '3' : // same year
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-01-01 00:00:00';

						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 1, 'Y')).' )';
						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' >= '.$db->Quote($cdate).' )';
					break;

					case '4' : // previous month
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-'.$cdate[1].'-01 00:00:00';

						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' < '.$db->Quote($cdate).' )';
						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, -1, 'm')).' )';
					break;

					case '5' : // previous year
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-01-01 00:00:00';

						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' < '.$db->Quote($cdate).' )';
						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, -1, 'Y')).' )';
					break;

					case '6' : // next month
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-'.$cdate[1].'-01 00:00:00';

						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 2, 'm')).' )';
						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, 1, 'm')).' )';
					break;

					case '7' : // next year
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-01-01 00:00:00';

						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 2, 'Y')).' )';
						$where .= ' AND ( i.'.$comp.' = '.$db->Quote($nullDate).' OR i.'.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, 1, 'Y')).' )';
					break;
				}
			}
		}

		if ($mod_image) {
			$select_image 	= ' value AS image,';
			$join_image 	= '	LEFT JOIN #__flexicontent_fields_item_relations AS firel'
							. '	ON ( i.id = firel.item_id AND firel.valueorder = 1 AND firel.field_id = '.$mod_image.' )';
		} else {
			$select_image	= '';
			$join_image		= '';
		}

		switch ($ordering) {
			case 'popular':
				$orderby = ' ORDER BY i.hits DESC';
			break;
				
			case 'commented':
				$query 	= 'SELECT i.*, ie.type_id, count(com.object_id) AS nr, ty.name AS typename,'
						. $select_image
						. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
						. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
						. ' FROM #__content AS i'
						. ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = i.id'
						. ' LEFT JOIN #__flexicontent_types AS ty on ie.type_id = ty.id'
						. ' LEFT JOIN #__jcomments AS com ON com.object_id = i.id'
						. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
						. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
						. $join_image
						. $where
						. ' AND com.object_group = ' . $db->Quote('com_flexicontent')
						. ' AND com.published = 1'
						. ' GROUP BY i.id'
						. ' ORDER BY nr DESC'
						;
				break;

			case 'rated':
				$query 	= 'SELECT i.*, (cr.rating_sum / cr.rating_count) * 20 AS votes, ie.type_id, ty.name AS typename,'
						. $select_image
						. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
						. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
						. ' FROM #__content AS i'
						. ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = i.id'
						. ' LEFT JOIN #__flexicontent_types AS ty on ie.type_id = ty.id'
						. ' INNER JOIN #__content_rating AS cr ON cr.content_id = i.id'
						. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
						. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
						. $join_image
						. $where
						. ' GROUP BY i.id'
						. ' ORDER BY votes DESC'
						;
				break;
				
			case 'added':
				$orderby = ' ORDER BY i.created DESC';
				break;

			case 'updated':
				$orderby = ' ORDER BY i.modified DESC';
				break;

			case 'alpha':
				$orderby = ' ORDER BY i.title ASC';
				break;

			case 'alpharev':
				$orderby = ' ORDER BY i.title DESC';
				break;

			case 'catorder':
				$orderby = ' ORDER BY rel.ordering ASC';
				break;

			case 'random':
				$orderby = ' ORDER BY RAND()';
				break;
			default:
				$orderby = '';
		}
		
		if (!isset($query)) {
			$query 	= 'SELECT i.*, ie.type_id, ty.name AS typename,'
					. $select_image
					. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
					. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = i.id'
					. ' LEFT JOIN #__flexicontent_types AS ty on ie.type_id = ty.id'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
					. $join_image
					. $where
					. ' GROUP BY i.id'
					. $orderby
					;
		}
		
		$db->setQuery($query, 0, $count);
		$rows = $db->loadObjectList();

		return $rows;
	}

	function getCategoryData(&$params) {
		$cid  = JRequest::getInt('cid', 0);
		$view = JRequest::getVar('view');
		$config 	=& JFactory::getConfig();
		$currcat_showtitle  = $params->get('currcat_showtitle', 0);
		$currcat_showdescr  = $params->get('currcat_showdescr', 0);
		$currcat_cuttitle   = (int)$params->get('currcat_cuttitle', 20);
		$currcat_cutdescr   = (int)$params->get('currcat_cutdescr', 100);
		$currcat_link_title	= $params->get('currcat_link_title');
		
		$currcat_use_image 		= $params->get('currcat_use_image');
		$currcat_link_image		= $params->get('currcat_link_image');
		$currcat_width 				= (int)$params->get('currcat_width', 80);
		$currcat_height 			= (int)$params->get('currcat_height', 80);
		$currcat_method 			= (int)$params->get('mod_method', 1);
		
				
		$db =& JFactory::getDBO();
				
		$currcatdata = null;
		if ( ($currcat_showtitle || $currcat_showdescr || $currcat_use_image) && $cid && $view=='items' ) {
			// initialize variables
			$q = 'SELECT c.id, c.title, c.description, c.image '
						.( ($currcat_link_title || $currcat_link_image) ? ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug' : '' )
						. ' FROM #__categories AS c'
						. ' WHERE c.id = ' . $cid
						;
			$db->setQuery($q);
			$currcatdata = $db->loadObject();
			
			
			// Try to retrieve the category image source path
			$catimagesrc = '';
			if ($currcat_use_image==2) {
				// We will use category image as defined in category params (if it defined)
				
				if (!empty($currcatdata->image)) {
					$joomla_image_path 	= $config->getValue('config.image_path', 'images/stories');
				  $catimagesrc	= JURI::base(true) .DS. $joomla_image_path .DS. $currcatdata->image;
    		}
    	} else if ($currcat_use_image==1) {
    		// We will extract category image for the category text for image (if it exists)
    		
				$row = new StdClass();
				$text = &$currcatdata->description;
				
				// Search for the {readmore} tag and split the text up accordingly.
				$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
				$tagPos	= preg_match($pattern, $text);
	
				if ($tagPos == 0)	{
					$row->introtext	= $text;
					$row->fulltext = '';
				} else 	{
					list($row->introtext, $row->fulltext) = preg_split($pattern, $text, 2);
				}				
				
				// Try to get image from category text
				$catdescrimage = flexicontent_html::extractimagesrc($row);
				
				if ($catdescrimage) {
				  $catimagesrc	= JURI::base(true) . '/' . $catdescrimage;
    		}
    	}
			
			
			// Category Title
			$currcatdata->title = flexicontent_html::striptagsandcut($currcatdata->title, $currcat_cuttitle);
			
			
			// Category Image
			if (empty($catimagesrc)) {
  			unset($currcatdata->image);
  		} else {
				$h		= '&h=' . $currcat_height;
				$w		= '&w=' . $currcat_width;
				$aoe	= '&aoe=1';
				$q		= '&q=95';
				$zc		= $currcat_method ? '&zc=' . $currcat_method : '';
				$conf	= $w . $h . $aoe . $q . $zc;
				
  			$currcatdata->image = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$catimagesrc.$conf;
  		}
  		
  		
			// Category Description
			if (!$currcat_showdescr) {
				unset($currcatdata->description);
			} else {
				$currcatdata->description = flexicontent_html::striptagsandcut($currcatdata->description, $currcat_cutdescr);
			}
			
			
			// Category Links (title and image links)
			if ($currcat_link_title || $currcat_link_image) {
				$catlink = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($currcatdata->categoryslug));
				if ($currcat_link_title) $currcatdata->titlelink = $catlink;
				if ($currcat_link_image) $currcatdata->imagelink = $catlink;
			}			
		}
		
		return $currcatdata;
	}
}

