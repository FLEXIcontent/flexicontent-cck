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
jimport('joomla.html.parameter');

require_once (JPATH_SITE.DS.'administrator'.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
require_once (JPATH_SITE.DS.'modules'.DS.'mod_flexicontent'.DS.'classes'.DS.'datetime.php');

class modFlexicontentHelper
{
	
	function getList(&$params)
	{
		global $modfc_jprof, $mod_fc_run_times;
		
		$forced_itemid = $params->get('forced_itemid');
		$db =& JFactory::getDBO();
		
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
		
		// get other module parameters
		$method_curlang	= (int)$params->get('method_curlang', 0);
		
		// standard
		$display_title 		= $params->get('display_title');
		$link_title 			= $params->get('link_title');
		$cuttitle 				= $params->get('cuttitle');
		$display_date			= $params->get('display_date');
		$display_text 		= $params->get('display_text');
		$display_hits			= $params->get('display_hits');
		$display_voting		= $params->get('display_voting');
		$display_comments	= $params->get('display_comments');
		$mod_readmore	 		= $params->get('mod_readmore');
		$mod_cut_text 		= $params->get('mod_cut_text');
		$mod_do_stripcat	= $params->get('mod_do_stripcat', 1);
		$mod_use_image 		= $params->get('mod_use_image');
		$mod_image 				= $params->get('mod_image');
		$mod_link_image		= $params->get('mod_link_image');
		$mod_width 				= (int)$params->get('mod_width', 80);
		$mod_height 			= (int)$params->get('mod_height', 80);
		$mod_method 			= (int)$params->get('mod_method', 1);
		// featured
		$display_title_feat 	= $params->get('display_title_feat');
		$link_title_feat 			= $params->get('link_title_feat');
		$cuttitle_feat 				= $params->get('cuttitle_feat');
		$display_date_feat		= $params->get('display_date_feat');
		$display_text_feat 		= $params->get('display_text');
		$display_hits_feat 		= $params->get('display_hits_feat');
		$display_voting_feat	= $params->get('display_voting_feat');
		$display_comments_feat= $params->get('display_comments_feat');
		$mod_readmore_feat		= $params->get('mod_readmore_feat');
		$mod_cut_text_feat 		= $params->get('mod_cut_text_feat');
		$mod_do_stripcat_feat	= $params->get('mod_do_stripcat_feat', 1);
		$mod_use_image_feat 	= $params->get('mod_use_image_feat');
		$mod_link_image_feat 	= $params->get('mod_link_image_feat');
		$mod_width_feat 		= (int)$params->get('mod_width_feat', 140);
		$mod_height_feat 		= (int)$params->get('mod_height_feat', 140);
		$mod_method_feat 		= (int)$params->get('mod_method_feat', 1);
		
		// Common for image of standard/feature image
		$mod_image_custom_display	= $params->get('mod_image_custom_display');
		$mod_image_custom_url	= $params->get('mod_image_custom_url');
		$mod_image_fallback_img = $params->get('mod_image_fallback_img');

		// Retrieve default image for the image field and also create field parameters so that they can be used
		if ($mod_image) {
			$query = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = '.(int) $mod_image;
			$db->setQuery($query);
			$midata = new stdClass();
			$midata = $db->loadObject();
			$midata->params = new JParameter($midata->attribs);
			
			$midata->default_image = $midata->params->get( 'default_image', '');
			if ( $midata->default_image !== '' ) {
				$midata->default_image_urlpath = JURI::base(true).'/'.str_replace('\\', '/', $midata->default_image);
				$midata->default_image_filename = basename($midata->default_image);
			}
			$img_fieldname = $midata->name;
		}
		
		// Retrieve default image for the image field
		if ($display_hits || $display_hits_feat) {
			$query = 'SELECT * FROM #__flexicontent_fields WHERE field_type="hits"';
			$db->setQuery($query);
			$hitsfield = $db->loadObject();
			$hitsfield->parameters = new JParameter($hitsfield->attribs);
		}
		
		if ($display_voting || $display_voting_feat) {
			$query = 'SELECT * FROM #__flexicontent_fields WHERE field_type="voting"';
			$db->setQuery($query);
			$votingfield = $db->loadObject();
			$votingfield->parameters = new JParameter($votingfield->attribs);
		}
		
		// get module fields parameters
		$use_fields 			= $params->get('use_fields', 1);
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
		$use_fields_feat 			= $params->get('use_fields_feat', 1);
		$display_label_feat 	= $params->get('display_label_feat');
		$fields_feat = array_map( 'trim', explode(',', $params->get('fields_feat')) );
		if ($fields_feat[0]=='') $fields_feat = array();

		$mod_fc_run_times['query_items']= $modfc_jprof->getmicrotime();
		
		$cat_items_arr = array();
		if (!is_array($ordering)) { $ordering = explode(',', $ordering); }
		foreach ($ordering as $ord) {
			$items_arr = modFlexicontentHelper::getItems($params, $ord);
			if (!$items_arr) continue;
			foreach ($items_arr as $cat_counter => $items) {
				for ($i=0; $i<count($items); $i++) {
					$items[$i]->featured = ($i < $featured) ? 1 : 0;
					$items[$i]->fetching = $ord;
					$cat_items_arr[$cat_counter][] = $items[$i];
				}
			}
		}
		$mod_fc_run_times['query_items'] = $modfc_jprof->getmicrotime() - $mod_fc_run_times['query_items'];
		
		// Impementation of Empty Field Filter.
		// The cost of the following code is minimal.
		// The big time cost goes into rendering the fields ... 
		// We need to create the display of the fields before examining if they are empty.
		// The hardcoded limit of max items skipped is 100.
		if ( $skip_items && count($skiponempty_fields) )
		{
			$mod_fc_run_times['empty_fields_filter'] = $modfc_jprof->getmicrotime();
			
			// 0. Add ONLY skipfields to the list of fields to be rendered
			$fields_list = implode(',', $skiponempty_fields);
			//$skip_params = new JParameter("");
			//$skip_params->set('fields',$fields_list);
			
			foreach($cat_items_arr as $cat_items)
			{
				// 1. The filtered rows
				$filtered_rows = array();
				$order_count = array();
				
				// 2. Get field values (we pass null parameters to only retrieve field values and not render (YET) the 'skip-onempty' fields
				FlexicontentFields::getFields($cat_items, 'module', $skip_params = null);
				
				// 3. Skip Items with empty fields (if this filter is enabled)
				foreach($cat_items as $i => $item)
				{
					//echo "$i . {$item->title}<br/>";
					
					// Check to initialize counter for this ordering 
					if (!isset($order_count[$item->fetching]))
						$order_count[$item->fetching] = 0;
						
					// Check if enough encountered for this ordering
					if ($order_count[$item->fetching] >= $count)  continue;
					
					// Initialize skip property ZERO for 'any' and ONE for 'all'
					$skip_curritem = $onempty_fields_combination == 'any' ? 0 : 1;
					
					// Now check for empty field display or empty field values, if so item must be skipped
					foreach($skiponempty_fields as $skipfield_name)
					{
						if ($skip_items==2) {
							// We will check field's display
							FlexicontentFields::getFieldDisplay($item, $skipfield_name, null, 'display', 'module');
							$skipfield_data = @ $item->fields[$skipfield_name]->display;
						} else {
							// We will check field's value
							$skipfield_iscore = $item->fields[$skipfield_name]->iscore;
							$skipfield_id = $item->fields[$skipfield_name]->id;
							$skipfield_data = $skipfield_iscore ? $item->{$skipfield_name} : @ $item->fieldvalues[$skipfield_id];
						}
						
						// Strip HTML Tags
						if ($striptags_onempty_fields)
							$skipfield_data = strip_tags ($skipfield_data);
						
						// Decide if field is empty
						$skipfield_isempty = is_array($skipfield_data) ? !count($skipfield_data) : !strlen(trim($skipfield_data));
						
						if ( $skipfield_isempty ) {
							if ($onempty_fields_combination=='any') {
								$skip_curritem = 1;
								break;
							}
						} else {
							if ($onempty_fields_combination == 'all') {
								$skip_curritem = 0;
								break;
							}
						}
					}
					if ($skip_curritem) {
						//echo "Skip: $i . {$item->title}<br/>";
						if(!isset($order_skipcount[$item->fetching]) ) $order_skipcount[$item->fetching] = 0;
						$order_skipcount[$item->fetching]++;
						continue;
					}
					
					// 4. Increment counter for item's ordering and Add item to list of displayed items
					$order_count[$item->fetching]++;
					$filtered_rows[] =  $item;
				}
				$filtered_rows_arr[] = $filtered_rows;
			}
			
			$mod_fc_run_times['empty_fields_filter'] = $modfc_jprof->getmicrotime() - $mod_fc_run_times['empty_fields_filter'];
		} else {
			$filtered_rows_arr = & $cat_items_arr;
		}
		
		$mod_fc_run_times['item_list_creation'] = $modfc_jprof->getmicrotime();
		
		// *** OPTIMIZATION: we only render the fields after skipping unwanted items
		if ( ($use_fields && count($fields)) || ($use_fields_feat && count($fields_feat)) ) {
			$all_fields = array();
		  if ($use_fields && count($fields))           $all_fields = array_merge($all_fields, $fields);
		  if ($use_fields_feat && count($fields_feat)) $all_fields = array_merge($all_fields, $fields_feat);
		  $all_fields = array_unique($all_fields);
		  $fields_list = implode(',', $all_fields);
		  $params->set('fields',$fields_list);
		}
		
		$lists_arr = array();
		foreach($filtered_rows_arr as $filtered_rows)
		{	
			if ( ($use_fields && count($fields)) || ($use_fields_feat && count($fields_feat)) ) {
				$rows = & FlexicontentFields::getFields($filtered_rows, 'module', $params);
			} else {
				$rows = & $filtered_rows;
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
					$thumb_rendered = '';
					if ($mod_use_image_feat)
					{
						if ($mod_image_custom_display)
						{
							list($fieldname, $varname) = preg_split('/##/',$mod_image_custom_display);
							$fieldname = trim($fieldname); $varname = trim($varname);
							$thumb_rendered = FlexicontentFields::getFieldDisplay($row, $fieldname, null, $varname, 'module');
							$src = '';
						}
						else if ($mod_image_custom_url)
						{
							list($fieldname, $varname) = preg_split('/##/',$mod_image_custom_url);
							$fieldname = trim($fieldname); $varname = trim($varname);
							$src =  FlexicontentFields::getFieldDisplay($row, $fieldname, null, $varname, 'module');
						}
						else if ($mod_image)
						{
							FlexicontentFields::getFieldDisplay($row, $img_fieldname, null, 'display', 'module');
							$img_field = & $row->fields[$img_fieldname];
							$src = str_replace(JURI::root(), '', @ $img_field->thumbs_src[$img_field_size][0] );
							if ( (!$src && $mod_image_fallback_img==1) || ($src && $mod_image_fallback_img==2 && $img_field->using_default_value) ) {
								$src = flexicontent_html::extractimagesrc($row);
							}
						}
						else
						{
							$src = flexicontent_html::extractimagesrc($row);
						}

						if ($src) {
							$h		= '&amp;h=' . $mod_height_feat;
							$w		= '&amp;w=' . $mod_width_feat;
							$aoe	= '&amp;aoe=1';
							$q		= '&amp;q=95';
							$zc		= $mod_method_feat ? '&amp;zc=' . $mod_method_feat : '';
							$ext = pathinfo($src, PATHINFO_EXTENSION);
							$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
							$conf	= $w . $h . $aoe . $q . $zc . $f;
							
    					$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
    					$thumb = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		    		}
					}
					$lists[$ord]['featured'][$i] = new stdClass();
					$lists[$ord]['featured'][$i]->id = $row->id;
					//date
					if ($display_date_feat == 1) {
						$dateformat = JText::_($params->get('date_format_feat', 'DATE_FORMAT_LC3'));
						if($dateformat == JText::_('custom'))
							$dateformat = $params->get('custom_date_format_feat', JText::_('DATE_FORMAT_LC3'));
						
						$date_fields_feat = $params->get('date_fields_feat', array('created'));
						$date_fields_feat = !is_array($date_fields_feat) ? array($date_fields_feat) : $date_fields_feat;
		 			  
	 			  	$lists[$ord]['featured'][$i]->date_created = "";
						if (in_array('created',$date_fields_feat)) { // Created
							$lists[$ord]['featured'][$i]->date_created .= $params->get('date_label_feat',1) ? '<span class="date_label_feat">'.JText::_('FLEXI_DATE_CREATED').':</span> ' : '';
							$lists[$ord]['featured'][$i]->date_created .= '<span class="date_value_feat">' . JHTML::_('date', $row->created, $dateformat) . '</span>';
						}
	 			  	$lists[$ord]['featured'][$i]->date_modified = "";
						if (in_array('modified',$date_fields_feat)) { // Modified
							$lists[$ord]['featured'][$i]->date_modified .= $params->get('date_label_feat',1) ? '<span class="date_label_feat">'.JText::_('FLEXI_DATE_MODIFIED').':</span> ' : '';
							$modified_date = ($row->modified != $db->getNullDate()) ? JHTML::_('date', $row->modified, $dateformat) : JText::_( 'FLEXI_DATE_NEVER' );
							$lists[$ord]['featured'][$i]->date_modified .= '<span class="date_value_feat">' . $modified_date . '</span>';
						}
					}
					$lists[$ord]['featured'][$i]->image_rendered 	= $thumb_rendered;
					$lists[$ord]['featured'][$i]->image = $thumb;
					$lists[$ord]['featured'][$i]->hits	= $row->hits;
					if ($display_hits_feat) {
						FlexicontentFields::loadFieldConfig($hitsfield, $row);
						$lists[$ord]['featured'][$i]->hits_rendered = $params->get('hits_label_feat') ? '<span class="hits_label_feat">'.JText::_($hitsfield->label).':</span> ' : '';
						$lists[$ord]['featured'][$i]->hits_rendered .= JHTML::_('image.site', 'user.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_HITS' ));
						$lists[$ord]['featured'][$i]->hits_rendered .= ' ('.$row->hits.(!$params->get('hits_label_feat') ? ' '.JTEXT::_('FLEXI_HITS_L') : '').')';
					}
					if ($display_voting_feat) {
						FlexicontentFields::loadFieldConfig($votingfield, $row);
						$lists[$ord]['featured'][$i]->voting = $params->get('voting_label_feat') ? '<span class="voting_label_feat">'.JText::_($votingfield->label).':</span> ' : '';
						$lists[$ord]['featured'][$i]->voting .= '<span class="voting_value_feat">' . flexicontent_html::ItemVoteDisplay( $votingfield, $row->id, $row->rating_sum, $row->rating_count, 'main', '', $params->get('vote_stars_feat',1), $params->get('allow_vote_feat',0), $params->get('vote_counter_feat',1), !$params->get('voting_label_feat') ) .'</span>';
					}
					if ($display_comments_feat) {
						$lists[$ord]['featured'][$i]->comments = $row->comments_total;
						$lists[$ord]['featured'][$i]->comments_rendered = $params->get('comments_label_feat') ? '<span class="comments_label_feat">'.JText::_('FLEXI_COMMENTS').':</span> ' : '';
						$lists[$ord]['featured'][$i]->comments_rendered .= JHTML::_('image.site', 'comments.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_COMMENTS' ));
						$lists[$ord]['featured'][$i]->comments_rendered .= ' ('.$row->comments_total.(!$params->get('comments_label_feat') ? ' '.JTEXT::_('FLEXI_COMMENTS_L') : '').')';
					}
					$lists[$ord]['featured'][$i]->catid = $row->catid; 
					$lists[$ord]['featured'][$i]->itemcats = explode("," , $row->itemcats);
					$lists[$ord]['featured'][$i]->link 	= JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug, $forced_itemid).(($method_curlang == 1) ? "&lang=".substr($row->language ,0,2) : ""));
					$lists[$ord]['featured'][$i]->title	= (strlen($row->title) > $cuttitle_feat) ? JString::substr($row->title, 0, $cuttitle_feat) . '...' : $row->title;
					$lists[$ord]['featured'][$i]->alias	= $row->alias;
					$lists[$ord]['featured'][$i]->fulltitle = $row->title;
					$lists[$ord]['featured'][$i]->text = ($mod_do_stripcat_feat)? flexicontent_html::striptagsandcut($row->introtext, $mod_cut_text_feat) : $row->introtext;
					$lists[$ord]['featured'][$i]->typename 	= $row->typename;
					$lists[$ord]['featured'][$i]->access 	= $row->access;
					$lists[$ord]['featured'][$i]->featured 	= 1;
					
					if ($use_fields_feat && @$row->fields && $fields_feat) {
						$lists[$ord]['featured'][$i]->fields = array();
						foreach ($fields_feat as $field) {
							$lists[$ord]['featured'][$i]->fields[$field] = new stdClass();
							if ($display_label_feat) {
								$lists[$ord]['featured'][$i]->fields[$field]->label = @$row->fields[$field]->label ? $row->fields[$field]->label : '';
							}
							$lists[$ord]['featured'][$i]->fields[$field]->display 	= @$row->fields[$field]->display ? $row->fields[$field]->display : '';
							$lists[$ord]['featured'][$i]->fields[$field]->name = $row->fields[$field]->name;
							$lists[$ord]['featured'][$i]->fields[$field]->id   = $row->fields[$field]->id;
						}
					}
					
					$i++;
				} else {
					// image processing
					$thumb = '';
					$thumb_rendered = '';
					if ($mod_use_image)
					{
						if ($mod_image_custom_display)
						{
							list($fieldname, $varname) = preg_split('/##/',$mod_image_custom_display);
							$fieldname = trim($fieldname); $varname = trim($varname);
							$thumb_rendered = FlexicontentFields::getFieldDisplay($row, $fieldname, null, $varname, 'module');
							$src = '';  // Clear src no rendering needed
						}
						else if ($mod_image_custom_url)
						{
							list($fieldname, $varname) = preg_split('/##/',$mod_image_custom_url);
							$fieldname = trim($fieldname); $varname = trim($varname);
							$src =  FlexicontentFields::getFieldDisplay($row, $fieldname, null, $varname, 'module');
						}
						else if ($mod_image)
						{
							FlexicontentFields::getFieldDisplay($row, $img_fieldname, null, 'display', 'module');
							$img_field = & $row->fields[$img_fieldname];
							$src = str_replace(JURI::root(), '', @ $img_field->thumbs_src[$img_field_size][0] );
							if ( (!$src && $mod_image_fallback_img==1) || ($src && $mod_image_fallback_img==2 && $img_field->using_default_value) ) {
								$src = flexicontent_html::extractimagesrc($row);
							}
						}
						else
						{
							$src = flexicontent_html::extractimagesrc($row);
						}
						
						if ($src) {
							$h		= '&amp;h=' . $mod_height;
							$w		= '&amp;w=' . $mod_width;
							$aoe	= '&amp;aoe=1';
							$q		= '&amp;q=95';
							$zc		= $mod_method ? '&amp;zc=' . $mod_method : '';
							$ext = pathinfo($src, PATHINFO_EXTENSION);
							$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
							$conf	= $w . $h . $aoe . $q . $zc . $f;
							
    					$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
    					$thumb = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		    		}
					}
					
					// START population of item's custom properties
					
					$lists[$ord]['standard'][$i] = new stdClass();
					$lists[$ord]['standard'][$i]->id = $row->id;
					//date
					if ($display_date == 1) {
						$dateformat = JText::_($params->get('date_format', 'DATE_FORMAT_LC3'));
						if($dateformat == JText::_('custom'))
							$dateformat = $params->get('custom_date_format', JText::_('DATE_FORMAT_LC3'));
							
						$date_fields = $params->get('date_fields', array('created'));
						$date_fields = !is_array($date_fields) ? array($date_fields) : $date_fields;
		 			  
	 			  	$lists[$ord]['standard'][$i]->date_created = "";
						if (in_array('created',$date_fields)) { // Created
							$lists[$ord]['standard'][$i]->date_created .= $params->get('date_label',1) ? '<span class="date_label">'.JText::_('FLEXI_DATE_CREATED').':</span> ' : '';
							$lists[$ord]['standard'][$i]->date_created .= '<span class="date_value">' . JHTML::_('date', $row->created, $dateformat) . '</span>';
						}
	 			  	$lists[$ord]['standard'][$i]->date_modified = "";
						if (in_array('modified',$date_fields)) { // Modified
							$lists[$ord]['standard'][$i]->date_modified .= $params->get('date_label',1) ? '<span class="date_label">'.JText::_('FLEXI_DATE_MODIFIED').':</span> ' : '';
							$modified_date = ($row->modified != $db->getNullDate()) ? JHTML::_('date', $row->modified, $dateformat) : JText::_( 'FLEXI_DATE_NEVER' );
							$lists[$ord]['standard'][$i]->date_modified .= '<span class="date_value_feat">' . $modified_date . '</span>';
						}
					}
					$lists[$ord]['standard'][$i]->image_rendered 	= $thumb_rendered;
					$lists[$ord]['standard'][$i]->image	= $thumb;
					$lists[$ord]['standard'][$i]->hits	= $row->hits;
					if ($display_hits) {
						FlexicontentFields::loadFieldConfig($hitsfield, $row);
						$lists[$ord]['standard'][$i]->hits_rendered = $params->get('hits_label') ? '<span class="hits_label">'.JText::_($hitsfield->label).':</span> ' : '';
						$lists[$ord]['standard'][$i]->hits_rendered .= JHTML::_('image.site', 'user.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_HITS_L' ));
						$lists[$ord]['standard'][$i]->hits_rendered .= ' ('.$row->hits.(!$params->get('hits_label') ? ' '.JTEXT::_('FLEXI_HITS_L') : '').')';
					}
					if ($display_voting) {
						FlexicontentFields::loadFieldConfig($votingfield, $row);
						$lists[$ord]['standard'][$i]->voting = $params->get('voting_label') ? '<span class="voting_label">'.JText::_($votingfield->label).':</span> ' : '';
						$lists[$ord]['standard'][$i]->voting .= '<span class="voting_value">' . flexicontent_html::ItemVoteDisplay( $votingfield, $row->id, $row->rating_sum, $row->rating_count, 'main', '', $params->get('vote_stars',1), $params->get('allow_vote',0), $params->get('vote_counter',1), !$params->get('voting_label')) .'</span>';
					}
					if ($display_comments) {
						$lists[$ord]['standard'][$i]->comments = $row->comments_total;
						$lists[$ord]['standard'][$i]->comments_rendered = $params->get('comments_label') ? '<span class="comments_label">'.JText::_('FLEXI_COMMENTS').':</span> ' : '';
						$lists[$ord]['standard'][$i]->comments_rendered .= JHTML::_('image.site', 'comments.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_COMMENTS_L' ));
						$lists[$ord]['standard'][$i]->comments_rendered .= ' ('.$row->comments_total.(!$params->get('comments_label') ? ' '.JTEXT::_('FLEXI_COMMENTS_L') : '').')';
					}
					$lists[$ord]['standard'][$i]->catid = $row->catid;
					$lists[$ord]['standard'][$i]->itemcats = explode("," , $row->itemcats);
					$lists[$ord]['standard'][$i]->link	= JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug, $forced_itemid).(($method_curlang == 1) ? "&lang=".substr($row->language ,0,2) : ""));
					$lists[$ord]['standard'][$i]->title	= (strlen($row->title) > $cuttitle) ? JString::substr($row->title, 0, $cuttitle) . '...' : $row->title;
					$lists[$ord]['standard'][$i]->alias	= $row->alias;
					$lists[$ord]['standard'][$i]->fulltitle = $row->title;
					$lists[$ord]['standard'][$i]->text = ($mod_do_stripcat)? flexicontent_html::striptagsandcut($row->introtext, $mod_cut_text) : $row->introtext;
					$lists[$ord]['standard'][$i]->typename 	= $row->typename;
					$lists[$ord]['standard'][$i]->access 	= $row->access;
					$lists[$ord]['standard'][$i]->featured 	= 0;
	
					if ($use_fields && @$row->fields && $fields) {
						$lists[$ord]['standard'][$i]->fields = array();
						foreach ($fields as $field) {
							if ( !isset($row->fields[$field]) ) continue;
							
							$lists[$ord]['standard'][$i]->fields[$field] = new stdClass();
							if ($display_label) {
								$lists[$ord]['standard'][$i]->fields[$field]->label = @$row->fields[$field]->label ? $row->fields[$field]->label : '';
							}
							$lists[$ord]['standard'][$i]->fields[$field]->display 	= @$row->fields[$field]->display ? $row->fields[$field]->display : '';
							$lists[$ord]['standard'][$i]->fields[$field]->name = $row->fields[$field]->name;
							$lists[$ord]['standard'][$i]->fields[$field]->id   = $row->fields[$field]->id;
						}
					}
	
					$i++;
				}
			}
			$lists_arr[] = $lists;
		}
		
		$mod_fc_run_times['item_list_creation'] = $modfc_jprof->getmicrotime() - $mod_fc_run_times['item_list_creation'];
		return $lists_arr;
	}

	function getItems(&$params, $ordering)
	{
		global $dump, $globalcats;
		$mainframe = &JFactory::getApplication();
		JPluginHelper::importPlugin('system', 'flexisystem');

		// For specific cache issues
		if (empty($globalcats)) {
			if (FLEXI_SECTION || FLEXI_CAT_EXTENSION) {
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
		$gid			= !FLEXI_J16GE ? (int)$user->get('aid')  :  max($user->getAuthorisedViewLevels());
		$view			= JRequest::getVar('view');
		$option		= JRequest::getVar('option');
		$fparams 	=& $mainframe->getParams('com_flexicontent');
		$show_noauth 	= $fparams->get('show_noauth', 0);
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$now		= $mainframe->get('requestTime');
		$now			= JFactory::getDate()->toMySQL();
		$nullDate	= $db->getNullDate();
		
		// $display_category_data
		$apply_config_per_category = (int)$params->get('apply_config_per_category', 0);
		
		// *** METHODS that their 'ALL' value is 0, (these do not use current item information)
		
		// current item scope parameters
		$method_curitem	= (int)$params->get('method_curitem', 0);
		
		// current language scope parameters
		$method_curlang	= (int)$params->get('method_curlang', 0);
		
		// current item scope parameters
		$method_curuserfavs = (int)$params->get('method_curuserfavs', 0);
		
		// featured items scope parameters
		$method_featured = (int)$params->get('method_featured', 0);
		
		// featured items scope parameters
		$method_states = (int)$params->get('method_states', 0);
		$item_states   = $params->get('item_states');
		
		// *** METHODS that their 'ALL' value is 1, that also have behaviour variable (most of them)
		
		// categories scope parameters
		$method_cat 		= (int)$params->get('method_cat', 1);
		$catids 			= $params->get('catids', array());
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
		$excluded_tags = $params->get('excluded_tags', array());
		$excluded_tags = (!is_array($excluded_tags)) ? array($excluded_tags) : $excluded_tags;
		$relitems_fields = $params->get('relitems_fields', array());
		$relitems_fields = (!is_array($relitems_fields)) ? array($relitems_fields) : $relitems_fields;
		
		// tags scope parameters
		$method_tags = (int)$params->get('method_tags', 1);
		$tag_ids = $params->get('tag_ids', array());
		$tag_ids = (!is_array($tag_ids)) ? array($tag_ids) : $tag_ids ;

		// date scope parameters
		$date_type	= (int)$params->get('date_type', 0);
		$bdate 			= $params->get('bdate', '');
		$edate 			= $params->get('edate', '');
		$raw_bdate	= $params->get('raw_bdate', 0);
		$raw_edate	= $params->get('raw_edate', 0);
		$behaviour_dates 	= $params->get('behaviour_dates', 0);
		$date_compare 		= $params->get('date_compare', 0);
		$datecomp_field		= $params->get('datecomp_field', 0);
		// Server date
		$sdate 			= explode(' ', $now);
		$cdate 			= $sdate[0] . ' 00:00:00';
		// Set date comparators
		if ($date_type == 0) {        // created
			$comp = 'i.created';
		} else if ($date_type == 1) { // modified
			$comp = 'i.modified';
		} else if ($date_type == 2) { // publish up
			$comp = 'i.publish_up';
		} else { // $date_type == 3
			$comp = 'dfrel.value';
		}
		
		// get module fetching parameters
		if ($params->get('skip_items',0) ) {
		  $count = (int)$params->get('maxskipcount', 50);
		} else {
		  $count = (int)$params->get('count', 5);
		}

		// get module display parameters
		$mod_image 			= $params->get('mod_image');
		

		$where  = ' WHERE c.published = 1';
		$where .= FLEXI_J16GE ? '' : ' AND i.sectionid = ' . FLEXI_SECTION;
		$where .= ' AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$db->Quote($now).' )';
		$where .= ' AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$db->Quote($now).' )';

		// filter by permissions
		if (!$show_noauth) {
			if (FLEXI_ACCESS && class_exists('FAccess')) {
				$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
				if (isset($readperms['item']) && count($readperms['item']) ) {
					$where .= ' AND ( i.access <= '.$gid.' OR i.id IN ('.implode(",", $readperms['item']).') )';
				} else {
					$where .= ' AND i.access <= '.$gid;
				}
			} else {
				$where .= ' AND i.access <= '.$gid;
			}
		}
		
		$isflexi_itemview = ($option == 'com_flexicontent' && $view == FLEXI_ITEMVIEW);
		
		// *** NON-STATIC behavior, get current item information ***
		if ( ($behaviour_cat || $behaviour_auth || $behaviour_items || $behaviour_types || $date_compare) && $isflexi_itemview ) {
			// initialize variables
			$cid 		= JRequest::getInt('cid');
			$id			= JRequest::getInt('id');
			$Itemid		= JRequest::getInt('Itemid');
			if (!$id) return;  // new item nothing to retrieve
			
			$query = 'SELECT c.*, ie.*, GROUP_CONCAT(ci.catid SEPARATOR ",") as itemcats FROM #__content as c'
						. ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = c.id'
						. ' LEFT JOIN #__flexicontent_cats_item_relations AS ci on ci.itemid = c.id'
						. ' WHERE c.id = ' . $id
						. ' GROUP BY ci.itemid'
						;
			$db->setQuery($query);
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
			$curritemcats = explode(',', $curitem->itemcats);
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
		$lang = flexicontent_html::getUserCurrentLang();
		if ($method_curlang == 1) { // exclude method  ---  exclude items of current language
			$where .= ' AND ie.language NOT LIKE ' . $db->Quote( $lang .'%' );
		} else if ($method_curlang == 2) { // include method  ---  include items of current language ONLY
			$where .= ' AND ( ie.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		} else {
		  // Items of any language
		}
		
		// current user favourites scope
		$curruserid = (int)$user->get('id');
  	if ($method_curuserfavs == 1) { // exclude method  ---  exclude currently logged user favourites
			$join_favs  = ' LEFT OUTER JOIN #__flexicontent_favourites AS fav ON fav.itemid = i.id AND fav.userid = '.$curruserid;
			$where .= ' AND fav.itemid IS NULL';
		} else if ($method_curuserfavs == 2) { // include method  ---  include currently logged user favourites
			$join_favs  = ' LEFT JOIN #__flexicontent_favourites AS fav ON fav.itemid = i.id';
			$where .= ' AND fav.userid = '.$curruserid;
		} else {
		  // All Items regardless of being favoured by current user
		  $join_favs = '';
		}
		
		// featured items scope
		if (FLEXI_J16GE) {
	  	if ($method_featured == 1) { // exclude method  ---  exclude currently logged user favourites
				$where .= ' AND i.featured=0';
			} else if ($method_featured == 2) { // include method  ---  include currently logged user favourites
				$where .= ' AND i.featured=1';
			} else {
			  // All Items regardless of being featured or not
			}
		}
		
		// item states scope
		$item_states = is_array($item_states) ? implode(',', $item_states) : $item_states;
		if ($method_states==0) {
		  // method normal: Published item states
			$where .= ' AND i.state IN ( 1, -5 )';
		} else {
			// exclude trashed
			$where .= ' AND i.state <> -2';
			if ($item_states) {
		  	if ($method_states == 1) { // exclude method  ---  exclude specified item states
					$where .= ' AND i.state NOT IN ('. $item_states .')';
				} else if ($method_states == 2) { // include method  ---  include specified item states
					$where .= ' AND i.state IN ('. $item_states .')';
				}
			} else if ($method_states == 2) { // misconfiguration, when using include method with no state selected ...
				echo "<b>WARNING:</b> Misconfigured item states scope, select at least one state or set states scope to Normal <small>(Published)</small><br/>";
				return;
			}
		}
		
		// categories scope
		if (!$behaviour_cat) {
			$catids = is_array($catids) ? $catids : array($catids);

			// retrieve extra categories, such children or parent categories
			$catids_arr = modFlexicontentHelper::getExtraCats($catids, $treeinclude, array());
			
			if (!$catids && $method_cat > 1) {
				// empty ignore and issue a warning
				echo "<b>WARNING:</b> Misconfigured category scope, select at least one category or set category scope to ALL<br/>";
			} else if ($method_cat == 2) { // exclude method
				if ($apply_config_per_category) {
					echo "<b>WARNING:</b> Misconfiguration warning, APPLY CONFIGURATION PER CATEGORY is possible only if CATEGORY SCOPE is set to either (a) INCLUDE(static selection of categories) or (b) items in same category as current item<br/>";
					return;
				}
				$where .= ' AND c.id NOT IN (' . implode(',', $catids_arr) . ')';
			} else if ($method_cat == 3) { // include method
				if (!$apply_config_per_category) {
					$where .= ' AND c.id IN (' . implode(',', $catids_arr) . ')';
				} else {
					// *** Applying configuration per category ***
					foreach($catids_arr as $catid)                // The items retrieval query will be executed ... once per EVERY category
						$multiquery_cats[] = ' AND c.id = '.$catid;
					$params->set('dynamic_catids', serialize($catids_arr));  // Set dynamic catids to be used by the getCategoryData
				}
			}
		} else {
			if ( !$isflexi_itemview ) {
				return;
			} else {	
				
				if ($behaviour_cat == 2 && $apply_config_per_category) {
					echo "<b>WARNING:</b> Misconfiguration warning, APPLY CONFIGURATION PER CATEGORY is possible only if CATEGORY SCOPE is set to either (a) INCLUDE(static selection of categories) or (b) items in same category as current item<br/>";
					return;
				}
				
				// if $cid is not set then use the main category id of the (current) item
				$cid = $cid ? $cid : $curitem->catid;
				
				// retrieve extra categories, such children or parent categories
				$catids_arr = modFlexicontentHelper::getExtraCats(array($cid), $treeinclude, $curritemcats);
				
				if ($behaviour_cat == 1) {
					if (!$apply_config_per_category) {
						$where .= ' AND c.id IN (' . implode(',', $catids_arr) . ')';
					} else {
						// *** Applying configuration per category ***
						foreach($catids_arr as $catid)                // The items retrieval query will be executed ... once per EVERY category
							$multiquery_cats[] = ' AND c.id = '.$catid;
						$params->set('dynamic_catids', serialize($catids_arr));  // Set dynamic catids to be used by the getCategoryData
					}
				} else {
					$where .= ' AND c.id NOT IN (' . implode(',', $catids_arr) . ')';
				}
			}
		}
		// Now check if no items need to be retrieved
		if ($count==0) return;

		// types scope
		if (!$behaviour_types) {
			$types		= is_array($types) ? implode(',', $types) : $types;
			if (!$types && $method_types > 1) {
				// empty ignore and issue a warning
				echo "<b>WARNING:</b> Misconfigured types scope, select at least one item type or set types scope to ALL<br/>";
			} else if ($method_types == 2) { // exclude method
				$where .= ' AND ie.type_id NOT IN (' . $types . ')';		
			} else if ($method_types == 3) { // include method
				$where .= ' AND ie.type_id IN (' . $types . ')';		
			}
		} else {
			if ( !$isflexi_itemview ) {
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
			if (!$authors && $method_auth > 1) {
				// empty ignore and issue a warning
				echo "<b>WARNING:</b> Misconfigured author scope, select at least one author or set author scope to ALL<br/>";
			} else if ($method_auth == 2) { // exclude method
				$where .= ' AND i.created_by NOT IN (' . $authors . ')';		
			} else if ($method_auth == 3) { // include method
				$where .= ' AND i.created_by IN (' . $authors . ')';		
			}
		} else {
			if ( !$isflexi_itemview ) {
				return;
			} else {			
				if ($behaviour_auth == 1) {
					$where .= ' AND i.created_by = ' . (int)$curitem->created_by;		
				} else if ($behaviour_auth == 2) {
					$where .= ' AND i.created_by <> ' . (int)$curitem->created_by;		
				}  else {  // $behaviour_auth == 3
					$where .= ' AND i.created_by = ' . (int)$user->id;
				}
			}
		}

		// items scope
		if (!$behaviour_items) {
			if (!$items && $method_items > 1) {
				// empty ignore and issue a warning
				echo "<b>WARNING:</b> Misconfigured items scope, select at least one item or set items scope to ALL<br/>";
			} else if ($method_items == 2) { // exclude method
				$where .= ' AND i.id NOT IN (' . $items . ')';		
			} else if ($method_items == 3) { // include method
				$where .= ' AND i.id IN (' . $items . ')';		
			}
		} else if ($behaviour_items==2) {
			if ( $isflexi_itemview ) {
				unset($related);
				if (count($relitems_fields)) {
					$where2 = (count($relitems_fields) > 1) ? ' AND field_id IN ('.implode(',', $relitems_fields).')' : ' AND field_id = '.$relitems_fields[0];
					
					// select the item ids that have the common tags
					$query3 = 'SELECT DISTINCT value' .
							' FROM #__flexicontent_fields_item_relations' .
							' WHERE item_id = '.(int) $id .
							$where2
							;
					$db->setQuery($query3);
					$related = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
					$related = is_array($related) ? array_map( 'intval', $related ) : $related;
				}
								
				if (isset($related) && count($related)) {
					$where .= (count($related) > 1) ? ' AND i.id IN ('.implode(',', $related).')' : ' AND i.id = '.$related[0];
				} else {
					return;
				}
			} else {
				return;
			}
		} else if ($behaviour_items==1) {
			if ( $isflexi_itemview ) {
				// select the tags associated to the item
				$query2 = 'SELECT tid' .
						' FROM #__flexicontent_tags_item_relations' .
						' WHERE itemid = '.(int) $id;
				$db->setQuery($query2);
				$tags = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
				$tags = array_diff($tags,$excluded_tags);
				
				unset($related);
				if ($tags) {
					$where2 = (count($tags) > 1) ? ' AND tid IN ('.implode(',', $tags).')' : ' AND tid = '.$tags[0];
					
					// select the item ids that have the common tags
					$query3 = 'SELECT DISTINCT itemid' .
							' FROM #__flexicontent_tags_item_relations' .
							' WHERE itemid <> '.(int) $id .
							$where2
							;
					$db->setQuery($query3);
					$related = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
				}
								
				if (isset($related) && count($related)) {
					$where .= (count($related) > 1) ? ' AND i.id IN ('.implode(',', $related).')' : ' AND i.id = '.$related[0];
				} else {
					return;
				}
			} else {			
				return;
			}
		}
		
		// tags scope
		if ($method_tags > 1) {
			if (!count($tag_ids)) {
				// empty ignore and issue a warning
				echo "<b>WARNING:</b> Misconfigured tags scope, select at least one tag or set tags scope to ALL<br/>";
			} else {
				$where2 = (count($tag_ids) > 1) ? ' AND tid IN ('.implode(',', $tag_ids).')' : ' AND tid = '.$tag_ids[0];
				
				// retieve item ids using the providen tags
				$query3 = 'SELECT DISTINCT itemid' .
						' FROM #__flexicontent_tags_item_relations' .
						' WHERE 1=1 ' .
						$where2
						;
				$db->setQuery($query3);
				$tagged = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
			}
			
			if ( isset($tagged) && count($tagged) ) {
				if ($method_tags == 2) { // exclude method
					$where .= (count($tagged) > 1) ? ' AND i.id NOT IN ('.implode(',', $tagged).')' : ' AND i.id <> '.$tagged[0];
				} else if ($method_tags == 3) { // include method
					$where .= (count($tagged) > 1) ? ' AND i.id IN ('.implode(',', $tagged).')' : ' AND i.id = '.$tagged[0];
				}
			} else if ( isset($tagged) ) {
				// No tagged items found abort if method is 'include' (but continue for 'exclude' method
				if ($method_tags == 3) return;
			}
		}
		
		// date scope
		if (!$behaviour_dates) {
			
			if (!$raw_edate && $edate && !FLEXIUtilities::isSqlValidDate($edate)) {
				echo "<b>WARNING:</b> Misconfigured date scope, you have entered invalid -END- date:<br>(a) Enter a valid date via callendar OR <br>(b) leave blank OR <br>(c) choose (non-static behavior) and enter custom offset e.g. five days ago (be careful with space character): -5 d<br/>";
				$edate = '';
			} else if ($edate) {
				$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' <= '.(!$raw_edate ? $db->Quote($edate) : $edate).' )';
			}
			
			if (!$raw_bdate && $bdate && !FLEXIUtilities::isSqlValidDate($bdate)) {
				echo "<b>WARNING:</b> Misconfigured date scope, you have entered invalid -BEGIN- date:<br>(a) Enter a valid date via callendar OR <br>(b) leave blank OR <br>(c) choose (non-static behavior) and enter custom offset e.g. five days ago (be careful with space character): -5 d<br/>";
				$bdate = '';
			} else if ($bdate) {
				$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' >= '.(!$raw_bdate ? $db->Quote($bdate) : $bdate).' )';
			}
			
		} else {
			
			if ( !$isflexi_itemview && ($date_compare == 1) )
			{
				return;  // date_compare == 1 means compare to current item, but current view is not an item view so we terminate
			}
			else
			{
				// FOR date_compare==0, $cdate is SERVER DATE
				// FOR date_compare==1, $cdate is CURRENT ITEM DATE of type created or modified or publish_up
				switch ($behaviour_dates) 
				{
					case '1' : // custom offset
						if ($edate) {
							$edate = explode(' ', $edate);
							if (count($edate)!=2)
								echo "<b>WARNING:</b> Misconfigured date scope, you have entered invalid -END- date:Custom offset is invalid e.g. in order to enter five days ago (be careful with space character) use: -5 d<br/>";
							else
								$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, $edate[0], $edate[1])).' )';
						}
						if ($bdate) {
							$bdate = explode(' ', $bdate);
							if (count($bdate)!=2)
								echo "<b>WARNING:</b> Misconfigured date scope, you have entered invalid -BEGIN- date:Custom offset is invalid e.g. in order to enter five days ago (be careful with space character) use: -5 d<br/>";
							else
								$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, $bdate[0], $bdate[1])).' )';
						}
					break;

					case '2' : // same month
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-'.$cdate[1].'-01 00:00:00';

						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 1, 'm')).' )';
						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' >= '.$db->Quote($cdate).' )';
					break;

					case '3' : // same year
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-01-01 00:00:00';

						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 1, 'Y')).' )';
						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' >= '.$db->Quote($cdate).' )';
					break;

					case '4' : // previous month
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-'.$cdate[1].'-01 00:00:00';

						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' < '.$db->Quote($cdate).' )';
						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, -1, 'm')).' )';
					break;

					case '5' : // previous year
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-01-01 00:00:00';

						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' < '.$db->Quote($cdate).' )';
						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, -1, 'Y')).' )';
					break;

					case '6' : // next month
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-'.$cdate[1].'-01 00:00:00';

						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 2, 'm')).' )';
						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, 1, 'm')).' )';
					break;

					case '7' : // next year
						$cdate = explode(' ', $cdate);
						$cdate = explode('-', $cdate[0]);
						$cdate = $cdate[0].'-01-01 00:00:00';

						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 2, 'Y')).' )';
						$where .= ' AND ( '.$comp.' = '.$db->Quote($nullDate).' OR '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, 1, 'Y')).' )';
					break;
				}
			}
			
		}
		
		// EXTRA join when comparing to custom date field
		if ( ($bdate || $edate || $behaviour_dates) && $date_type == 3) {
			if ($datecomp_field) {
				$join_date =
						'	LEFT JOIN #__flexicontent_fields_item_relations AS dfrel'
					. '   ON ( i.id = dfrel.item_id AND dfrel.valueorder = 1 AND dfrel.field_id = '.$datecomp_field.' )';
			} else {
				echo "<b>WARNING:</b> Misconfigured date scope, you have set DATE TYPE as CUSTOM DATE Field, but have not select any specific DATE Field to be used<br/>";
				$join_date = '';
			}
		} else {
			$join_date = '';
		}
		
		// EXTRA select and join for special fields: --image--
		/*if ($mod_image) {
			$select_image 	= ' firel.value AS image,';
			$join_image 	= '	LEFT JOIN #__flexicontent_fields_item_relations AS firel'
							. '	ON ( i.id = firel.item_id AND firel.valueorder = 1 AND firel.field_id = '.$mod_image.' )';
		} else {
			$select_image	= '';
			$join_image		= '';
		}*/
		
		// EXTRA join of field used in custom ordering
		if ($ordering=='field' && $params->get('orderbycustomfieldid') ) {
			$join_field = ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.(int)$params->get('orderbycustomfieldid', 0);
		} else {
			$join_field = '';
		}
		
		// some parameter aliases
		$display_comments	= $params->get('display_comments');
		$display_comments_feat = $params->get('display_comments_feat');
		$display_voting	= $params->get('display_voting');
		$display_voting_feat = $params->get('display_voting_feat');
		
		// Check (when needed) if jcomments are installed, and also clear 'commented' ordering if they jcomments is missing
		if ($display_comments_feat || $display_comments || $ordering=='commented') {
			// handle jcomments integration
			if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) {
				echo "jcomments not installed, you need jcomments to use 'Most commented' ordering OR display comments information.<br>\n";
				$jcomments_exist = false;
			} else {
				$jcomments_exist = true;
			}
			if (!$jcomments_exist && $ordering=='commented') $ordering='';
		}
		
		// Decide to JOIN (or not) with comments TABLE, needed when displaying comments and/or when ordering by comments
		$add_comments = ($display_comments_feat || $display_comments || $ordering=='commented') && $jcomments_exist;
		$join_comments_type = $ordering=='commented' ? ' INNER JOIN' : ' LEFT JOIN';
		// Additional select and joins for comments
		$select_comments = $add_comments ? ' count(com.object_id) AS comments_total,' : '';
		$join_comments   = $add_comments ? $join_comments_type.' #__jcomments AS com ON com.object_id = i.id' : '' ;
		
		// Decide to JOIN (or not) with rating TABLE, needed when displaying ratings and/or when ordering by ratings
		$add_rated = $display_voting_feat || $display_voting || $ordering=='rated';
		$join_rated_type = $ordering=='rated' ? ' INNER JOIN' : ' LEFT JOIN';
		// Additional select and joins for ratings
		$select_rated = $ordering=='rated' ? ' (cr.rating_sum / cr.rating_count) * 20 AS votes,' : '';
		$select_rated = $add_rated ? ' cr.rating_sum as rating_sum, cr.rating_count as rating_count,' : '';
		$join_rated   = $add_rated ? $join_rated_type.' #__content_rating AS cr ON cr.content_id = i.id' : '' ;
		
		// Get ordering
		if ($ordering) {
			$orderby = flexicontent_db::buildItemOrderBy(
				$params,
				$ordering, $request_var='', $config_param = '',
				$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
				$default_order = '', $default_order_dir = ''
			);
		} else {
			$orderby = '';
		}
		
		if ( empty($query) ) {  // If a custom query has not been set above then use the default one ...
			$query 	= 'SELECT i.*, ie.*, ty.name AS typename,'
					//. $select_image
					. $select_comments
					. $select_rated
					. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
					. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug,'
						. ' GROUP_CONCAT(rel.catid SEPARATOR ",") as itemcats '
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = i.id'
					. ' LEFT JOIN #__flexicontent_types AS ty on ie.type_id = ty.id'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
					. $join_favs
					//. $join_image
					. $join_date
					. $join_comments
					. $join_rated
					. $join_field
					. $where .' '. ($apply_config_per_category ? '__CID_WHERE__' : '')
					. ' GROUP BY i.id'
					. $orderby
					;
		}
		
		if (!isset($multiquery_cats)) $multiquery_cats = array("");
		foreach($multiquery_cats as $cat_where) {
			$per_cat_query = str_replace('__CID_WHERE__', $cat_where, $query);
			$db->setQuery($per_cat_query, 0, $count);
			$rows = $db->loadObjectList();
			if ( $db->getErrorNum() ) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(nl2br($query."\n".$db->getErrorMsg()."\n"),'error');
			}
			$cat_items_arr[] = $rows;
		}
		
		return $cat_items_arr;
	}

	function getCategoryData(&$params)
	{
		if (!$params->get('apply_config_per_category', 0)) return false;
		
		$db     = & JFactory::getDBO();
		$config = & JFactory::getConfig();
		$view   = JRequest::getVar('view');
		$option = JRequest::getVar('option');
		
		$currcat_custom_display = $params->get('currcat_custom_display', 0);
		$isflexi_itemview       = ($option == 'com_flexicontent' && $view == FLEXI_ITEMVIEW);
		
		if ($currcat_custom_display && $isflexi_itemview) {
			$id   = JRequest::getInt('id', 0);   // id of current item
			$cid  = JRequest::getInt('cid', 0);  // current category id of current item
			
			$catconf = new stdClass();
			$catconf->fallback_maincat  = $params->get('currcat_fallback_maincat', 0);
			$catconf->showtitle  = $params->get('currcat_showtitle', 0);
			$catconf->showdescr  = $params->get('currcat_showdescr', 0);
			$catconf->cuttitle   = (int)$params->get('currcat_cuttitle', 40);
			$catconf->cutdescr   = (int)$params->get('currcat_cutdescr', 200);
			$catconf->link_title	= $params->get('currcat_link_title');
			
			$catconf->show_image 		= $params->get('currcat_show_image');
			$catconf->image_source	= $params->get('currcat_image_source');
			$catconf->link_image		= $params->get('currcat_link_image');
			$catconf->image_width		= (int)$params->get('currcat_image_width', 80);
			$catconf->image_height	= (int)$params->get('currcat_image_height', 80);
			$catconf->image_method	= (int)$params->get('currcat_image_method', 1);
			$catconf->show_default_image = (int)$params->get('currcat_show_default_image', 0);  // parameter not added yet
			$catconf->readmore	= (int)$params->get('currcat_currcat_readmore', 1);
			
			if ($catconf->fallback_maincat && !$cid && $id) {
				$query = 'SELECT catid FROM #__content WHERE id = ' . $id;
				$db->setQuery($query);
				$cid = $db->loadResult();
			}
			if ($cid) $cids = array($cid);
		}
		
		if (empty($cids)) {
			$catconf = new stdClass();
			
			// Check if using a dynamic set of categories, that was decided by getItems()
			$dynamic_cids = $params->get('dynamic_catids', false);
			$static_cids  = $params->get('catids', array());
			
			$cids = $dynamic_cids ? unserialize($dynamic_cids) : $static_cids;
			$cids = (!is_array($cids)) ? array($cids) : $cids;
			
			$catconf->showtitle  = $params->get('cats_showtitle', 0);
			$catconf->showdescr  = $params->get('cats_showdescr', 0);
			$catconf->cuttitle   = (int)$params->get('cats_cuttitle', 40);
			$catconf->cutdescr   = (int)$params->get('cats_cutdescr', 200);
			$catconf->link_title	= $params->get('cats_link_title');
			
			$catconf->show_image 		= $params->get('cats_show_image');
			$catconf->image_source	= $params->get('cats_image_source');
			$catconf->link_image		= $params->get('cats_link_image');
			$catconf->image_width 	= (int)$params->get('cats_image_width', 80);
			$catconf->image_height 	= (int)$params->get('cats_image_height', 80);
			$catconf->image_method 	= (int)$params->get('cats_image_method', 1);
			$catconf->show_default_image = (int)$params->get('cats_show_default_image', 0);  // parameter not added yet
			$catconf->readmore	= (int)$params->get('cats_readmore', 1);
		}
		
		if (empty($cids) || !count($cids)) return false;
		
		// initialize variables
		$query = 'SELECT c.id, c.title, c.description, c.params '
					. ( FLEXI_J16GE ? '' : ', c.image ' )  // NO image column in J1.6 and higher, image is in parameters
					. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
					. ' FROM #__categories AS c'
					. ' WHERE c.id IN (' . implode(',', $cids) . ')';
					;
		$db->setQuery($query);
		$catdata_arr = $db->loadObjectList();
		if ( $db->getErrorNum() ) {
			$jAp=& JFactory::getApplication();
			$jAp->enqueueMessage(nl2br($query."\n".$db->getErrorMsg()."\n"),'error');
			return false;
		}
		
		jimport( 'joomla.html.parameter' );
		$joomla_image_path 	= FLEXI_J16GE ? $config->getValue('config.image_path', '') : $config->getValue('config.image_path', 'images'.DS.'stories');
		foreach( $catdata_arr as $i => $catdata ) {
			$catdata->params = new JParameter($catdata->params);
			
			// Category Title
			$catdata->title = flexicontent_html::striptagsandcut($catdata->title, $catconf->cuttitle);
			$catdata->showtitle = $catconf->showtitle;
			
			// Category image
			$catdata->image = FLEXI_J16GE ? $catdata->params->get('image') : $catdata->image;
			$catimage = "";
			if ($catconf->show_image) {
				$catdata->introtext = & $catdata->description;
				$catdata->fulltext = "";
				
				if ( $catconf->image_source && $catdata->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path .DS. $catdata->image ) ) {
					$src = JURI::base(true)."/".$joomla_image_path."/".$catdata->image;
			
					$h		= '&amp;h=' . $catconf->image_height;
					$w		= '&amp;w=' . $catconf->image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $catconf->image_method ? '&amp;zc=' . $catconf->image_method : '';
					$ext = pathinfo($src, PATHINFO_EXTENSION);
					$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $zc . $f;
			
					$catimage = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
				} else if ( $catconf->image_source!=1 && $src = flexicontent_html::extractimagesrc($catdata) ) {
		
					$h		= '&amp;h=' . $catconf->image_height;
					$w		= '&amp;w=' . $catconf->image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $catconf->image_method ? '&amp;zc=' . $catconf->image_method : '';
					$ext = pathinfo($src, PATHINFO_EXTENSION);
					$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $zc . $f;
		
					$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
					$catimage = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
				}
				
				$catdata->image = $catimage;
			}
  		
			// Category Description
			if (!$catconf->showdescr) {
				unset($catdata->description);
			} else {
				$catdata->description = flexicontent_html::striptagsandcut($catdata->description, $catconf->cutdescr);
			}
			
			// Category Links (title and image links)
			if ($catconf->link_title || $catconf->link_image || $catconf->readmore) {
				$catlink = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($catdata->categoryslug));
				$catdata->titlelink = $catlink;
				$catdata->imagelink = $catlink;
			}
			
			$catdata_arr[$i] = $catdata;
			$catdata_arr[$i]->conf = $catconf;
		}
		
		return $catdata_arr;
	}
	
	
	// Find and return extra parent/children/etc categories of givem categories
	function getExtraCats($cids, $treeinclude, $curritemcats)
	{
		global $globalcats;
		
		$all_cats = $cids;
		foreach ($cids as $cid)
		{
			$cats = array();
			switch ($treeinclude) {
				// current category only
				case 0: default: 
					$cats = array($cid);
				break;
				case 1: // current category + children
					$cats = $globalcats[$cid]->descendantsarray;
				break;
				case 2: // current category + parents
					$cats = $globalcats[$cid]->ancestorsarray;
				break;
				case 3: // current category + children + parents
					$cats = array_unique(array_merge($globalcats[$cid]->descendantsarray, $globalcats[$cid]->ancestorsarray));						
				break;
				case 4: // all item's categories
					$cats = $curritemcats;
				break;
			}
			$all_cats = array_merge($all_cats, $cats);
		}
		return array_unique($all_cats);
	}
	
	
	// Verify parameters, altering them if needed
	function verifyParams( &$params )
	{
		// Calculate menu itemid for item links
		$menus				= & JApplication::getMenu('site', array());
		$itemid_force	= (int)$params->get('itemid_force');
		if ($itemid_force==1) {
			$Itemid					= JRequest::getInt('Itemid');
			$menu						= & $menus->getItem($Itemid);
			$component			= @$menu->query['option'] ? $menu->query['option'] : '';
			$forced_itemid	= $component=="com_flexicontent" ? $Itemid : 0;
		} else if ($itemid_force==2) {
			$itemid_force_value	= (int)$params->get('itemid_force_value', 0);
			$menu								= & $menus->getItem($itemid_force_value);
			$component					= @$menu->query['option'] ? $menu->query['option'] : '';
			$forced_itemid			= $component=="com_flexicontent" ? $itemid_force_value : 0;
		} else {
			$forced_itemid = 0;
		}
		$params->set('forced_itemid', $forced_itemid);
		
		// Disable output of comments if comments component not installed
		if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) {
			$params->set('display_comments', 0);
			$params->set('display_comments_feat', 0);
		}
	}
	
}

