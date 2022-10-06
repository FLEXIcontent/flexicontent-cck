<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright � 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */


// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;

class modFlexicontentHelper
{
	public static function getList($params, &$totals = null)
	{
		global $modfc_jprof, $mod_fc_run_times;

		$forced_itemid = $params->get('forced_itemid');
		$db   = JFactory::getDbo();
		$user = JFactory::getUser();
		$app  = JFactory::getApplication();

		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');

		// Get IDs of user's access view levels
		$aid_arr = JAccess::getAuthorisedViewLevels($user->id);

		// get module ordering parameters
		$ordering		= $params->get('ordering', array());
		$count			= (int) $params->get('count', 5);
		$featured		= (int) $params->get('count_feat', 1);

		// Default ordering is 'added' if none ordering is set. Also make sure $ordering is an array (of ordering groups)
		if ( empty($ordering) )    $ordering = array('added');
		if (!is_array($ordering))  $ordering = explode(',', $ordering);

		// get other module parameters
		$method_curlang		= (int) $params->get('method_curlang', 0);

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
		$mod_image_ff     = $params->get('mod_image_fallback_field');
		$mod_link_image		= $params->get('mod_link_image');
		$mod_default_img_show = $params->get('mod_default_img_show', 1);
		$mod_default_img_path = $params->get('mod_default_img_path', 'components/com_flexicontent/assets/images/image.png');
		$mod_width 				= (int) $params->get('mod_width', 80);
		$mod_height 			= (int) $params->get('mod_height', 80);
		$mod_method 			= (int) $params->get('mod_method', 1);
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
		$mod_cut_text_feat 		= (int) $params->get('mod_cut_text_feat');
		$mod_do_stripcat_feat	= $params->get('mod_do_stripcat_feat', 1);
		$mod_use_image_feat 	= $params->get('mod_use_image_feat');
		$mod_link_image_feat 	= $params->get('mod_link_image_feat');
		$mod_width_feat 		= (int) $params->get('mod_width_feat', 140);
		$mod_height_feat 		= (int) $params->get('mod_height_feat', 140);
		$mod_method_feat 		= (int) $params->get('mod_method_feat', 1);

		// Common for image of standard/feature image
		$mod_image_custom_display	= $params->get('mod_image_custom_display');
		$mod_image_custom_url	= $params->get('mod_image_custom_url');
		$mod_image_fallback_img = $params->get('mod_image_fallback_img');

		// Retrieve default image for the image field and also create field parameters so that they can be used
		if ($mod_image)
		{
			$query = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = '.(int) $mod_image;
			$db->setQuery($query);
			$mod_image_dbdata = $db->loadObject();
			$mod_image_name = $mod_image_dbdata->name;
			//$img_fieldparams = new JRegistry($mod_image_dbdata->attribs);
		}
		if ($mod_default_img_show) {
			$src = $mod_default_img_path;

			// Default image featured
			$h		= '&amp;h=' . $mod_height;
			$w		= '&amp;w=' . $mod_width;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$ar 	= '&amp;ar=x';
			$zc		= $mod_method ? '&amp;zc=' . $mod_method : '';
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

			$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
			$thumb_default = JUri::root(true) . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;

			// Default image standard
			$h		= '&amp;h=' . $mod_height_feat;
			$w		= '&amp;w=' . $mod_width_feat;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$ar 	= '&amp;ar=x';
			$zc		= $mod_method_feat ? '&amp;zc=' . $mod_method_feat : '';
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

			$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
			$thumb_default_feat = JUri::root(true) . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		}

		// Retrieve custom displayed field data (including their parameters and access):  hits/voting/etc
		if ($display_hits || $display_hits_feat || $display_voting || $display_voting_feat)
		{
			$query = 'SELECT * FROM #__flexicontent_fields';

			$disp_field_where = array();
			if ($display_hits || $display_hits_feat)      $disp_field_where[] = 'field_type="hits"';
			if ($display_voting || $display_voting_feat)  $disp_field_where[] = 'field_type="voting"';
			$query .= ' WHERE ' . implode(' OR ', $disp_field_where);
			$db->setQuery($query);
			$disp_fields_data = $db->loadObjectList('field_type');

			if ($display_hits || $display_hits_feat) {
				$hitsfield = $disp_fields_data['hits'];
				$hitsfield->parameters = new JRegistry($hitsfield->attribs);
				$has_access_hits = in_array($hitsfield->access, $aid_arr);
			}
			if ($display_voting || $display_voting_feat) {
				$votingfield = $disp_fields_data['voting'];
				$votingfield->parameters = new JRegistry($votingfield->attribs);
				$has_access_voting = in_array($votingfield->access, $aid_arr);
			}
		}

		// Custom fields displayed for featured items
		$use_fields_feat 			= $params->get('use_fields_feat', 1);
		$display_label_feat 	= $params->get('display_label_feat');

		$fields_feat = $params->get('fields_feat', array());
		if ( !is_array($fields_feat) )
		{
			$fields_feat = array_map( 'trim', explode(',', $fields_feat) );
			$fields_feat = $fields_feat[0] == '' ? array() : $fields_feat;
		}

		// Custom fields displayed for standard items
		$use_fields    = $params->get('use_fields', 1);
		$display_label = $params->get('display_label');

		$fields = $params->get('fields', array());
		if ( !is_array($fields) )
		{
			$fields = array_map( 'trim', explode(',', $fields) );
			$fields = $fields[0] == '' ? array() : $fields;
		}

		// get fields that when empty cause an item to be skipped
		$skip_items = (int) $params->get('skip_items', 0);

		$skiponempty_fields = !$skip_items ? array() : $params->get('skiponempty_fields', array());
		if ( !is_array($skiponempty_fields) )
		{
			$skiponempty_fields = array_map( 'trim', explode(',', $skiponempty_fields) );
			$skiponempty_fields = $skiponempty_fields[0] == '' ? array() : $skiponempty_fields;
		}
		$params->set('skiponempty_fields', $skiponempty_fields);  // Set calculated array to be used by other code

		if ($params->get('maxskipcount', 50) > 100)
		{
  		$params->set('maxskipcount', 100);
		}

		$striptags_onempty_fields = $params->get('striptags_onempty_fields');
		$onempty_fields_combination = $params->get('onempty_fields_combination');

		//$mod_fc_run_times['query_items']= microtime(1);

		$cat_items_arr = array();
		if ( !is_array($ordering) )
		{
			$ordering = explode(',', $ordering);
		}
		foreach ($ordering as $ord)
		{
			$items_arr = modFlexicontentHelper::getItems($params, $ord, $totals);
			if ( empty($items_arr) ) continue;
			foreach ($items_arr as $catid => $items)
			{
				if ( !isset($cat_items_arr[$catid]) ) $cat_items_arr[$catid] = array();
				for ($i=0; $i<count($items); $i++)
				{
					$items[$i]->fetching = $ord;
					$cat_items_arr[$catid][] = $items[$i];
				}
			}
		}
		//$mod_fc_run_times['query_items'] = microtime(1) - $mod_fc_run_times['query_items'];

		// Impementation of Empty Field Filter.
		// The cost of the following code is minimal.
		// The big time cost goes into rendering the fields ...
		// We need to create the display of the fields before examining if they are empty.
		// The hardcoded limit of max items skipped is 100.
		if ( count($skiponempty_fields) )
		{
			$mod_fc_run_times['empty_fields_filter'] = microtime(1);

			// 0. Add ONLY skipfields to the list of fields to be rendered
			$fields_list = implode(',', $skiponempty_fields);
			//$skip_params = new JRegistry();
			//$skip_params->set('fields',$fields_list);

			$filtered_rows_arr = array();
			foreach ($cat_items_arr as $catid => $cat_items)
			{
				// 1. The filtered rows
				$filtered_rows = array();
				$order_count = array();

				// 2. Get field values (we pass null parameters to only retrieve field values and not render (YET) the 'skip-onempty' fields
				FlexicontentFields::getFields($cat_items, 'module', $skip_params = null);

				// 3. Skip Items with empty fields (if this filter is enabled)
				foreach ($cat_items as $i => $item)
				{
					//echo "$i . {$item->title}<br/>";

					// Check to initialize counter for this ordering
					if (!isset($order_count[$item->fetching]))  $order_count[$item->fetching] = 0;

					// Check if enough encountered for this ordering
					if ($order_count[$item->fetching] >= $count)  continue;

					// Initialize skip property ZERO for 'any' and ONE for 'all'
					$skip_curritem = $onempty_fields_combination == 'any' ? 0 : 1;

					// Now check for empty field display or empty field values, if so item must be skipped
					foreach ($skiponempty_fields as $skipfield_name)
					{
						if (!isset($item->fields[$skipfield_name]))
						{
							if ($onempty_fields_combination=='any')
							{
								$skip_curritem = 1;
								break;
							}
							else continue;
						}

						if ($skip_items==2)
						{
							// We will check field's display
							FlexicontentFields::getFieldDisplay($item, $skipfield_name, null, 'display', 'module');
							$skipfield_data = @ $item->fields[$skipfield_name]->display;
						}
						else
						{
							// We will check field's value
							$skipfield_iscore = $item->fields[$skipfield_name]->iscore;
							$skipfield_id = $item->fields[$skipfield_name]->id;
							$skipfield_data = $skipfield_iscore ? $item->{$skipfield_name} : @ $item->fieldvalues[$skipfield_id];
						}

						// Strip HTML Tags
						if ($striptags_onempty_fields)
						{
							$skipfield_data = strip_tags ($skipfield_data);
						}

						// Decide if field is empty
						$skipfield_isempty = is_array($skipfield_data) ? !count($skipfield_data) : !strlen(trim($skipfield_data));

						if ( $skipfield_isempty )
						{
							if ($onempty_fields_combination=='any')
							{
								$skip_curritem = 1;
								break;
							}
						}
						else
						{
							if ($onempty_fields_combination == 'all')
							{
								$skip_curritem = 0;
								break;
							}
						}
					}

					if ($skip_curritem)
					{
						//echo "Skip: $i . {$item->title}<br/>";
						if(!isset($order_skipcount[$item->fetching]) ) $order_skipcount[$item->fetching] = 0;
						$order_skipcount[$item->fetching]++;
						continue;
					}

					// 4. Increment counter for item's ordering and Add item to list of displayed items
					$order_count[$item->fetching]++;
					$filtered_rows[] =  $item;
				}
				$filtered_rows_arr[$catid] = $filtered_rows;
			}

			$mod_fc_run_times['empty_fields_filter'] = microtime(1) - $mod_fc_run_times['empty_fields_filter'];
		}

		else
		{
			$filtered_rows_arr = & $cat_items_arr;
		}

		$mod_fc_run_times['item_list_creation'] = microtime(1);

		// *** OPTIMIZATION: we only render the fields after skipping unwanted items
		if ( ($use_fields && count($fields)) || ($use_fields_feat && count($fields_feat)) )
		{
			$all_fields = array();
		  if ($use_fields && count($fields))           $all_fields = array_merge($all_fields, $fields);
		  if ($use_fields_feat && count($fields_feat)) $all_fields = array_merge($all_fields, $fields_feat);
		  $all_fields = array_unique($all_fields);
		  $fields_list = implode(',', $all_fields);
		  $params->set('fields',$fields_list);
		}

		// *** OPTIMIZATION: we should create some variables outside the loop ... TODO MORE
		if (($display_hits_feat || $display_hits) && $has_access_hits)
		{
			$hits_icon = FLEXI_J16GE ?
				JHtml::image('components/com_flexicontent/assets/images/'.'user.png', JText::_( 'FLEXI_HITS_L' )) :
				JHtml::_('image.site', 'user.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_HITS_L' ));
		}

		if ($display_comments_feat || $display_comments)
		{
			$comments_icon = FLEXI_J16GE ?
				JHtml::image('components/com_flexicontent/assets/images/'.'comments.png', JText::_( 'FLEXI_COMMENTS_L' )) :
				JHtml::_('image.site', 'comments.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_COMMENTS_L' ));
		}

		// Needed if forcing language
		if ($method_curlang === 1)
		{
			$site_languages = FLEXIUtilities::getLanguages();
		}

		$id = $jinput->get('id', 0, 'int');   // id of current item

		$is_content_ext   = $option == 'com_flexicontent' || $option == 'com_content';
		$isflexi_itemview = $is_content_ext && ($view == 'item' || $view == 'article') && $id;
		$active_item_id   = $id;

		$lists_arr = array();
		foreach ($filtered_rows_arr as $catid => $filtered_rows)
		{
			if (empty($filtered_rows))
			{
				$rows = array();
			}
			elseif (($use_fields && count($fields)) || ($use_fields_feat && count($fields_feat)))
			{
				$rows = FlexicontentFields::getFields($filtered_rows, 'module', $params);
			}
			else
			{
				$rows = & $filtered_rows;
			}

			// For Debuging
			/*foreach ($order_skipcount as $skipordering => $skipcount) {
			  echo "SKIPS $skipordering ==> $skipcount<br>\n";
			}*/

			$lists = array();

			foreach ($ordering as $ord)
			{
				$lists[$ord] = array();
			}

			$ord = "__start__";
			foreach ( $rows as $row )  // Single pass of rows
			{
			  if ($ord != $row->fetching)  // Detect change of next ordering group
				{
					$ord = $row->fetching;
			    $i = 0;
			  }

				if ($i < $featured)
				{
					// image processing
					$thumb = '';
					$thumb_rendered = '';
					$_thumb_w = $mod_width_feat;
					$_thumb_h = $mod_height_feat;
					if ($mod_use_image_feat)
					{
						if ($mod_image_custom_display)
						{
							@list($fieldname, $varname) = preg_split('/##/',$mod_image_custom_display);
							$fieldname = trim($fieldname); $varname = trim($varname);
							$varname = $varname ? $varname : 'display';
							$thumb_rendered = FlexicontentFields::getFieldDisplay($row, $fieldname, null, $varname, 'module');
							$src = '';
							$_thumb_w = $_thumb_h = 0;
						}
						elseif ($mod_image_custom_url)
						{
							@list($fieldname, $varname) = preg_split('/##/',$mod_image_custom_url);
							$fieldname = trim($fieldname); $varname = trim($varname);
							$varname = $varname ? $varname : 'display';
							$src =  FlexicontentFields::getFieldDisplay($row, $fieldname, null, $varname, 'module');
						}
						elseif ($mod_image)
						{
							$image_url = FlexicontentFields::getFieldDisplay($row, $mod_image_name, null, 'display_large_src', 'module');  // just makes sure thumbs are created by requesting a '*_src' display

							$src = '';
							$thumb = '';

							if ($image_url)
							{
								$img_field = $row->fields[$mod_image_name];

								if ($mod_use_image_feat==1)
								{
									$src = str_replace(JUri::root(), '', ($img_field->thumbs_src['large'][0] ?? ''));
								}
								else
								{
									$thumb = $img_field->thumbs_src[ $mod_use_image_feat ][0] ?? '';
									$_thumb_w = $thumb ? $img_field->parameters->get('w_'.$mod_use_image_feat[0], 120) : 0;
									$_thumb_h = $thumb ? $img_field->parameters->get('h_'.$mod_use_image_feat[0], 90) : 0;
								}
							}

							if ((!$src && $mod_image_fallback_img==1) || ($src && $mod_image_fallback_img==2 && $img_field->using_default_value))
							{
								$src = flexicontent_html::extractimagesrc($row);
							}
							elseif(!$src && $mod_image_ff && $mod_image_fallback_img==3)
							{
								$image_url2 = FlexicontentFields::getFieldDisplay($row, $mod_image_ff, null, 'display_large_src', 'module');

								if ($image_url2)
								{
									$img_field2 = $row->fields[$mod_image_ff];

									if ($mod_use_image_feat==1)
									{
										$src = str_replace(JUri::root(), '', ($img_field2->thumbs_src['large'][0] ?? '') );
									}
									else
									{
										$thumb = $img_field2->thumbs_src[ $mod_use_image_feat ][0] ?? '';
										$_thumb_w = $thumb ? $img_field2->parameters->get('w_'.$mod_use_image_feat[0], 120) : 0;
										$_thumb_h = $thumb ? $img_field2->parameters->get('h_'.$mod_use_image_feat[0], 90) : 0;
									}
								}
							}
						}
						else
						{
							$src = flexicontent_html::extractimagesrc($row);
						}

						if (!$thumb && !$src && $mod_default_img_show) {
							$thumb = $thumb_default_feat;
						}

						if ($src) {
							$h		= '&amp;h=' . $mod_height_feat;
							$w		= '&amp;w=' . $mod_width_feat;
							$aoe	= '&amp;aoe=1';
							$q		= '&amp;q=95';
							$zc		= $mod_method_feat ? '&amp;zc=' . $mod_method_feat : '';
							$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
							$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
							$conf	= $w . $h . $aoe . $q . $zc . $f;

    					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
    					$thumb = JUri::root(true) . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		    		}
					}
					$lists[$ord]['featured'][$i] = new stdClass();
					$lists[$ord]['featured'][$i]->_row = $row;
					$lists[$ord]['featured'][$i]->id = $row->id;
					$lists[$ord]['featured'][$i]->type_id = $row->type_id;
					$lists[$ord]['featured'][$i]->is_active_item = ($isflexi_itemview && $row->id==$active_item_id);

					//date
					if ($display_date_feat == 1) {
						$dateformat = JText::_($params->get('date_format_feat', 'DATE_FORMAT_LC3'));
						if($dateformat == JText::_('custom'))
							$dateformat = $params->get('custom_date_format_feat', JText::_('DATE_FORMAT_LC3'));

						$date_fields_feat = $params->get('date_fields_feat', array('created'));
						$date_fields_feat = !is_array($date_fields_feat) ? array($date_fields_feat) : $date_fields_feat;

	 			  	$lists[$ord]['featured'][$i]->date_created = "";
						if (in_array('created',$date_fields_feat)) { // Created
							$lists[$ord]['featured'][$i]->date_created .= $params->get('date_label_feat',1) ? '<span class="date_label_feat">'.JText::_('FLEXI_DATE_CREATED').'</span> ' : '';
							$lists[$ord]['featured'][$i]->date_created .= '<span class="date_value_feat">' . JHtml::_('date', $row->created, $dateformat) . '</span>';
						}
	 			  	$lists[$ord]['featured'][$i]->date_modified = "";
						if (in_array('modified',$date_fields_feat)) { // Modified
							$lists[$ord]['featured'][$i]->date_modified .= $params->get('date_label_feat',1) ? '<span class="date_label_feat">'.JText::_('FLEXI_DATE_MODIFIED').'</span> ' : '';
							$modified_date = ($row->modified != $db->getNullDate()) ? JHtml::_('date', $row->modified, $dateformat) : JText::_( 'FLEXI_DATE_NEVER' );
							$lists[$ord]['featured'][$i]->date_modified .= '<span class="date_value_feat">' . $modified_date . '</span>';
						}
					}
					$lists[$ord]['featured'][$i]->image_rendered = $thumb_rendered;
					$lists[$ord]['featured'][$i]->image = $thumb;
					$lists[$ord]['featured'][$i]->image_w	= $_thumb_w;
					$lists[$ord]['featured'][$i]->image_h	= $_thumb_h;
					$lists[$ord]['featured'][$i]->hits	= $row->hits;
					$lists[$ord]['featured'][$i]->hits_rendered = '';

					if ($display_hits_feat && $has_access_hits)
					{
						FlexicontentFields::loadFieldConfig($hitsfield, $row);
						$lists[$ord]['featured'][$i]->hits_rendered .= $params->get('hits_label_feat') ? '<span class="hits_label_feat">'.JText::_($hitsfield->label).'</span> ' : '';
						$lists[$ord]['featured'][$i]->hits_rendered .= $hits_icon;
						$lists[$ord]['featured'][$i]->hits_rendered .= ' ('.$row->hits.(!$params->get('hits_label_feat') ? ' '.JTEXT::_('FLEXI_HITS_L') : '').')';
					}

					$lists[$ord]['featured'][$i]->voting = '';

					if ($display_voting_feat && $has_access_voting)
					{
						FlexicontentFields::loadFieldConfig($votingfield, $row);
						$votingfield->item_id    = $row->id;
						$votingfield->item_title = $row->title;
						$lists[$ord]['featured'][$i]->voting .= $params->get('voting_label_feat') ? '<span class="voting_label_feat">'.JText::_($votingfield->label).'</span> ' : '';
						$lists[$ord]['featured'][$i]->voting .= '<div class="voting_value_feat">' . flexicontent_html::ItemVoteDisplay($votingfield, $row->id, $row->rating_sum, $row->rating_count, 'main', '', $params->get('vote_stars_feat',1), $params->get('allow_vote_feat',0), $params->get('vote_counter_feat',1), !$params->get('voting_label_feat') ) .'</div>';
					}

					if ($display_comments_feat)
					{
						$lists[$ord]['featured'][$i]->comments = $row->comments_total;
						$lists[$ord]['featured'][$i]->comments_rendered = $params->get('comments_label_feat') ? '<span class="comments_label_feat">'.JText::_('FLEXI_COMMENTS').'</span> ' : '';
						$lists[$ord]['featured'][$i]->comments_rendered .= $comments_icon;
						$lists[$ord]['featured'][$i]->comments_rendered .= ' ('.$row->comments_total.(!$params->get('comments_label_feat') ? ' '.JTEXT::_('FLEXI_COMMENTS_L') : '').')';
					}

					$lists[$ord]['featured'][$i]->catid = $row->catid;
					$lists[$ord]['featured'][$i]->itemcats = explode("," , $row->itemcats);

					$sef_lang = $method_curlang === 1 && $row->language != '*' && isset($site_languages->{$row->language}) ? $site_languages->{$row->language}->sef : '';
					$non_sef_link =
						FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug, $forced_itemid, $row)
						. ($sef_lang ? '&lang=' . $sef_lang : '');

					$lists[$ord]['featured'][$i]->link	= JRoute::_($non_sef_link);
					$lists[$ord]['featured'][$i]->title	= StringHelper::strlen($row->title) > $cuttitle_feat  ?  StringHelper::substr($row->title, 0, $cuttitle_feat) . '...'  :  $row->title;
					$lists[$ord]['featured'][$i]->alias	= $row->alias;
					$lists[$ord]['featured'][$i]->fulltitle = $row->title;
					$lists[$ord]['featured'][$i]->text = ($mod_do_stripcat_feat)? flexicontent_html::striptagsandcut($row->introtext, $mod_cut_text_feat) : $row->introtext;
					$lists[$ord]['featured'][$i]->typename 	= $row->typename;
					$lists[$ord]['featured'][$i]->access 	= $row->access;
					$lists[$ord]['featured'][$i]->featured 	= 1;

					if ($use_fields_feat && @$row->fields && $fields_feat) {
						$lists[$ord]['featured'][$i]->fields = array();
						foreach ($fields_feat as $field) {
							if ( !isset($row->fields[$field]) ) continue;
							/*$lists[$ord]['featured'][$i]->fields[$field] = new stdClass();
							$lists[$ord]['featured'][$i]->fields[$field]->display 	= @$row->fields[$field]->display ? $row->fields[$field]->display : '';
							$lists[$ord]['featured'][$i]->fields[$field]->name = $row->fields[$field]->name;
							$lists[$ord]['featured'][$i]->fields[$field]->id   = $row->fields[$field]->id;*/
							// Expose field to the module template  ... the template should NOT modify this ...
							if ( !isset($row->fields[$field]->display) )
							{
								$row->fields[$field]->display = '';
							}
							$lists[$ord]['featured'][$i]->fields[$field] = $row->fields[$field];
						}
					}

					$i++;
				} else {
					// image processing
					$thumb = '';
					$thumb_rendered = '';
					$_thumb_w = $mod_width;
					$_thumb_h = $mod_height;
					if ($mod_use_image)
					{
						if ($mod_image_custom_display)
						{
							@list($fieldname, $varname) = preg_split('/##/',$mod_image_custom_display);
							$fieldname = trim($fieldname); $varname = trim($varname);
							$varname = $varname ? $varname : 'display';
							$thumb_rendered = FlexicontentFields::getFieldDisplay($row, $fieldname, null, $varname, 'module');
							$src = '';  // Clear src no rendering needed
							$_thumb_w = $_thumb_h = 0;
						}
						elseif ($mod_image_custom_url)
						{
							@list($fieldname, $varname) = preg_split('/##/',$mod_image_custom_url);
							$fieldname = trim($fieldname); $varname = trim($varname);
							$varname = $varname ? $varname : 'display';
							$src =  FlexicontentFields::getFieldDisplay($row, $fieldname, null, $varname, 'module');
						}
						elseif ($mod_image)
						{
							$image_url = FlexicontentFields::getFieldDisplay($row, $mod_image_name, null, 'display_large_src', 'module');  // just makes sure thumbs are created by requesting a '*_src' display

							$src = '';
							$thumb = '';

							if ($image_url)
							{
								$img_field = $row->fields[$mod_image_name];

								if ($mod_use_image==1)
								{
									$src = str_replace(JUri::root(), '', ($img_field->thumbs_src['large'][0] ?? '') );
								}
								else
								{
									$thumb = $img_field->thumbs_src[ $mod_use_image ][0] ?? '';
									$_thumb_w = $thumb ? $img_field->parameters->get('w_'.$mod_use_image[0], 120) : 0;
									$_thumb_h = $thumb ? $img_field->parameters->get('h_'.$mod_use_image[0], 90) : 0;
								}
							}

							if ((!$src && $mod_image_fallback_img==1) || ($src && $mod_image_fallback_img==2 && $img_field->using_default_value))
							{
								$src = flexicontent_html::extractimagesrc($row);
							}
							elseif(!$src && $mod_image_ff && $mod_image_fallback_img==3)
							{
								$image_url2 = FlexicontentFields::getFieldDisplay($row, $mod_image_ff, null, 'display_large_src', 'module');

								if ($image_url2)
								{
									$img_field2 = $row->fields[$mod_image_ff];

									if ($mod_use_image==1)
									{
										$src = str_replace(JUri::root(), '', ($img_field2->thumbs_src['large'][0] ?? '') );
									}
									else
									{
										$thumb = $img_field2->thumbs_src[ $mod_use_image ][0] ?? '';
										$_thumb_w = $thumb ? $img_field2->parameters->get('w_'.$mod_use_image[0], 120) : 0;
										$_thumb_h = $thumb ? $img_field2->parameters->get('h_'.$mod_use_image[0], 90) : 0;
									}
								}
							}
						}
						else
						{
							$src = flexicontent_html::extractimagesrc($row);
						}

						if (!$thumb && !$src && $mod_default_img_show) {
							$thumb = $thumb_default;
						}

						if ($src) {
							$h		= '&amp;h=' . $mod_height;
							$w		= '&amp;w=' . $mod_width;
							$aoe	= '&amp;aoe=1';
							$q		= '&amp;q=95';
							$zc		= $mod_method ? '&amp;zc=' . $mod_method : '';
							$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
							$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
							$conf	= $w . $h . $aoe . $q . $zc . $f;

    					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
    					$thumb = JUri::root(true) . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		    		}
					}

					// START population of item's custom properties

					$lists[$ord]['standard'][$i] = new stdClass();
					$lists[$ord]['standard'][$i]->_row = $row;
					$lists[$ord]['standard'][$i]->id = $row->id;
					$lists[$ord]['standard'][$i]->type_id = $row->type_id;
					$lists[$ord]['standard'][$i]->is_active_item = ($isflexi_itemview && $row->id==$active_item_id);

					//date
					if ($display_date == 1) {
						$dateformat = JText::_($params->get('date_format', 'DATE_FORMAT_LC3'));
						if($dateformat == JText::_('custom'))
							$dateformat = $params->get('custom_date_format', JText::_('DATE_FORMAT_LC3'));

						$date_fields = $params->get('date_fields', array('created'));
						$date_fields = !is_array($date_fields) ? array($date_fields) : $date_fields;

	 			  	$lists[$ord]['standard'][$i]->date_created = "";
						if (in_array('created',$date_fields)) { // Created
							$lists[$ord]['standard'][$i]->date_created .= $params->get('date_label',1) ? '<span class="date_label">'.JText::_('FLEXI_DATE_CREATED').'</span> ' : '';
							$lists[$ord]['standard'][$i]->date_created .= '<span class="date_value">' . JHtml::_('date', $row->created, $dateformat) . '</span>';
						}
	 			  	$lists[$ord]['standard'][$i]->date_modified = "";
						if (in_array('modified',$date_fields)) { // Modified
							$lists[$ord]['standard'][$i]->date_modified .= $params->get('date_label',1) ? '<span class="date_label">'.JText::_('FLEXI_DATE_MODIFIED').'</span> ' : '';
							$modified_date = ($row->modified != $db->getNullDate()) ? JHtml::_('date', $row->modified, $dateformat) : JText::_( 'FLEXI_DATE_NEVER' );
							$lists[$ord]['standard'][$i]->date_modified .= '<span class="date_value_feat">' . $modified_date . '</span>';
						}
					}
					$lists[$ord]['standard'][$i]->image_rendered = $thumb_rendered;
					$lists[$ord]['standard'][$i]->image = $thumb;
					$lists[$ord]['standard'][$i]->image_w	= $_thumb_w;
					$lists[$ord]['standard'][$i]->image_h	= $_thumb_h;
					$lists[$ord]['standard'][$i]->hits	= $row->hits;
					$lists[$ord]['standard'][$i]->hits_rendered = '';
					if ($display_hits && $has_access_hits) {
						FlexicontentFields::loadFieldConfig($hitsfield, $row);
						$lists[$ord]['standard'][$i]->hits_rendered .= $params->get('hits_label') ? '<span class="hits_label">'.JText::_($hitsfield->label).'</span> ' : '';
						$lists[$ord]['standard'][$i]->hits_rendered .= $hits_icon;
						$lists[$ord]['standard'][$i]->hits_rendered .= ' ('.$row->hits.(!$params->get('hits_label') ? ' '.JTEXT::_('FLEXI_HITS_L') : '').')';
					}

					$lists[$ord]['standard'][$i]->voting = '';

					if ($display_voting && $has_access_voting)
					{
						FlexicontentFields::loadFieldConfig($votingfield, $row);
						$votingfield->item_id    = $row->id;
						$votingfield->item_title = $row->title;
						$lists[$ord]['standard'][$i]->voting .= $params->get('voting_label') ? '<span class="voting_label">'.JText::_($votingfield->label).'</span> ' : '';
						$lists[$ord]['standard'][$i]->voting .= '<div class="voting_value">' . flexicontent_html::ItemVoteDisplay($votingfield, $row->id, $row->rating_sum, $row->rating_count, 'main', '', $params->get('vote_stars',1), $params->get('allow_vote',0), $params->get('vote_counter',1), !$params->get('voting_label')) .'</div>';
					}

					if ($display_comments)
					{
						$lists[$ord]['standard'][$i]->comments = $row->comments_total;
						$lists[$ord]['standard'][$i]->comments_rendered = $params->get('comments_label') ? '<span class="comments_label">'.JText::_('FLEXI_COMMENTS').'</span> ' : '';
						$lists[$ord]['standard'][$i]->comments_rendered .= $comments_icon;
						$lists[$ord]['standard'][$i]->comments_rendered .= ' ('.$row->comments_total.(!$params->get('comments_label') ? ' '.JTEXT::_('FLEXI_COMMENTS_L') : '').')';
					}

					$lists[$ord]['standard'][$i]->catid = $row->catid;
					$lists[$ord]['standard'][$i]->itemcats = explode("," , $row->itemcats);

					$sef_lang = $method_curlang === 1 && $row->language != '*' && isset($site_languages->{$row->language}) ? $site_languages->{$row->language}->sef : '';
					$non_sef_link =
						FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug, $forced_itemid, $row)
						. ($sef_lang ? '&lang=' . $sef_lang : '');

					$lists[$ord]['standard'][$i]->link	= JRoute::_($non_sef_link);
					$lists[$ord]['standard'][$i]->title	= StringHelper::strlen($row->title) > $cuttitle  ?  StringHelper::substr($row->title, 0, $cuttitle) . '...'  :  $row->title;
					$lists[$ord]['standard'][$i]->alias	= $row->alias;
					$lists[$ord]['standard'][$i]->fulltitle = $row->title;
					$lists[$ord]['standard'][$i]->text = ($mod_do_stripcat)? flexicontent_html::striptagsandcut($row->introtext, $mod_cut_text) : $row->introtext;
					$lists[$ord]['standard'][$i]->typename 	= $row->typename;
					$lists[$ord]['standard'][$i]->access 	= $row->access;
					$lists[$ord]['standard'][$i]->featured 	= 0;

					if ($use_fields && @$row->fields && $fields)
					{
						$lists[$ord]['standard'][$i]->fields = array();
						foreach ($fields as $field)
						{
							if ( !isset($row->fields[$field]) ) continue;
							/*$lists[$ord]['standard'][$i]->fields[$field] = new stdClass();
							$lists[$ord]['standard'][$i]->fields[$field]->display 	= @$row->fields[$field]->display ? $row->fields[$field]->display : '';
							$lists[$ord]['standard'][$i]->fields[$field]->name = $row->fields[$field]->name;
							$lists[$ord]['standard'][$i]->fields[$field]->id   = $row->fields[$field]->id;*/
							// Expose field to the module template  ... the template should NOT modify this ...
							if ( !isset($row->fields[$field]->display) )
							{
								$row->fields[$field]->display = '';
							}
							$lists[$ord]['standard'][$i]->fields[$field] = $row->fields[$field];  // Expose field to the module template  ... but template may modify it ...
						}
					}

					$i++;
				}
			}
			$lists_arr[$catid] = $lists;
		}

		$mod_fc_run_times['item_list_creation'] = microtime(1) - $mod_fc_run_times['item_list_creation'];
		return $lists_arr;
	}

	public static function getItems($params, $ordering, &$totals=null)
	{
		global $dump, $globalcats;
		global $modfc_jprof, $mod_fc_run_times;

		// ***
		// *** Get module fetching parameters
		// ***

		$skiponempty_fields = $params->get('skip_items', 0)
			? $params->get('skiponempty_fields')
			: array();

		$count = count($skiponempty_fields)
			? (int) $params->get('maxskipcount', 50)
			: (int) $params->get('count', 5);

		// Now check if no items need to be retrieved
		if ( $count === 0 && $totals === null )
		{
			return;
		}


		// For specific cache issues
		if (empty($globalcats))
		{
			JPluginHelper::importPlugin('system', 'flexisystem');
			if (FLEXI_CACHE)
			{
				// add the category tree to categories cache
				$catscache 	= JFactory::getCache('com_flexicontent_cats');
				$catscache->setCaching(1); 		//force cache
				$catscache->setLifeTime(84600); //set expiry to one day
				$globalcats = $catscache->get(
					array('plgSystemFlexisystem', 'getCategoriesTree'),
					array()
				);
			}
			else
			{
				$globalcats = plgSystemFlexisystem::getCategoriesTree();
			}
		}

		// Initialize variables
		$db   = JFactory::getDbo();
		$user = JFactory::getUser();
		$app  = JFactory::getApplication();

		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');

		$flexiparams 	= $app->getParams('com_flexicontent');
		$show_noauth 	= $flexiparams->get('show_noauth', 0);

		// $display_category_data
		$apply_config_per_category = (int) $params->get('apply_config_per_category', 0);

		// *** METHODS that their 'ALL' value is 0, (these do not use current item information)

		// current item scope parameters
		$method_curitem	= (int) $params->get('method_curitem', 0);

		// current language scope parameters
		$method_curlang	= (int) $params->get('method_curlang', 0);

		// current item scope parameters
		$method_curuserfavs = (int) $params->get('method_curuserfavs', 0);

		// featured items scope parameters
		$method_featured = (int) $params->get('method_featured', 0);

		// featured items scope parameters
		$method_states = (int) $params->get('method_states', 0);
		$item_states   = $params->get('item_states');

		$show_nocontent_msg = (int) $params->get('show_nocontent_msg', 1);

		// *** METHODS that their 'ALL' value is 1, that also have behaviour variable (most of them)

		// categories scope parameters
		$method_cat 		= (int) $params->get('method_cat', 1);
		$catids 				= $params->get('catids', array());
		$behaviour_cat 	= (int) $params->get('behaviour_cat', 0);
		$link_via_main  = (int) $params->get('link_via_main', 0);
		$treeinclude 		= (int) $params->get('treeinclude');
		$cat_combine    = (int) $params->get('cat_combine', 0);

		// types scope parameters
		$method_types 	= (int) $params->get('method_types', 1);
		$types 					= $params->get('types');
		$behaviour_types= (int) $params->get('behaviour_types', 0);

		// authors scope parameters
		$method_auth 		= (int) $params->get('method_auth', 1);
		$authors 				= trim($params->get('authors', ''));
		$behaviour_auth	= (int) $params->get('behaviour_auth');

		// items scope parameters
		$method_items 		= (int) $params->get('method_items', 1);
		$items	 					= trim($params->get('items', ''));
		$items_use_order  = (int) $params->get('items_use_order', 0);
		$behaviour_items 	= (int) $params->get('behaviour_items', 0);
		$excluded_tags		= $params->get('excluded_tags', array());
		$excluded_tags		= !is_array($excluded_tags) ? array($excluded_tags) : $excluded_tags;
		$relitems_fields	= $params->get('relitems_fields', array());
		$relitems_fields	= !is_array($relitems_fields) ? array($relitems_fields) : $relitems_fields;

		// tags scope parameters
		$method_tags	= (int) $params->get('method_tags', 1);
		$tag_ids			= $params->get('tag_ids', array());
		$tag_combine	= (int) $params->get('tag_combine', 0);

		// date scope parameters
		$method_dates	= (int) $params->get('method_dates', 1);
		$date_type		= (int) $params->get('date_type', 0);
		$nulldates		= (int) $params->get('nulldates', 0);
		$bdate 				= $params->get('bdate', '');
		$edate 				= $params->get('edate', '');
		$raw_bdate		= $params->get('raw_bdate', 0);
		$raw_edate		= $params->get('raw_edate', 0);
		$behaviour_dates 	= (int) $params->get('behaviour_dates', 0);
		$date_compare 		= (int) $params->get('date_compare', 0);
		$datecomp_field		= (int) $params->get('datecomp_field', 0);

		// Retrieve default image for the image field and also create field parameters so that they can be used
		$use_local_time = false;

		if ($behaviour_dates && $date_type === 3 && $date_compare === 0 && $datecomp_field)
		{
			$query = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = '.(int) $datecomp_field;
			$db->setQuery($query);
			$date_field_dbdata = $db->loadObject();
			$date_field_params = new JRegistry($date_field_dbdata->attribs);
			$use_local_time = $date_field_params->get('date_allowtime', 0) && $date_field_params->get('use_editor_tz', 0);
		}

		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		$nowDate  = JFactory::getDate('now')->toSql();
		$nullDate	= $db->getNullDate();
		if ($use_local_time)
		{
			$nowDate = JHtml::_('date', $nowDate, 'Y-m-d H:i:s', $app->getCfg('offset') );
		}

		// Server date
		$sdate = explode(' ', $nowDate);
		$cdate = $sdate[0] . ' 00:00:00';

		// Set date comparators
		switch($date_type)
		{
			case 0:
				$comp = 'i.created';
				break;
			case 1:
				$comp = 'i.modified';
				break;
			case 2:
				$comp = 'i.publish_up';
				break;
			case 4:
				$comp = 'i.publish_down';
				break;
			case 3:
			default:
				$comp = 'dfrel.value';
				break;
		}

		// custom field scope
		$method_filt			= (int) $params->get('method_filt', 1);  // parameter added later, maybe not to break compatibility this should be INCLUDE=3 by default ?
		$behaviour_filt		= (int) $params->get('behaviour_filt', 0);
		$static_filters		= $params->get('static_filters', '');
		$dynamic_filters	= $params->get('dynamic_filters', '');


		// ***
		// *** Get module display parameters
		// ***

		$mod_image 			= $params->get('mod_image');


		// ***
		// *** Filter by publication state, (except for item state which is a special scope, below)
		// ***

		$where  = ' WHERE c.published = 1';
		$where .= FLEXI_J16GE ? '' : ' AND i.sectionid = ' . FLEXI_SECTION;

		$ignore_up_down_dates = $params->get('ignore_up_down_dates', 0);  // 1: ignore publish_up, 2: ignore publish_donw, 3: ignore both
		$ignoreState =  $params->get('use_list_items_in_any_state_acl', 0) && $user->authorise('flexicontent.ignoreviewstate', 'com_flexicontent');
		if (!$ignoreState && $ignore_up_down_dates != 3 && $ignore_up_down_dates != 1)
			$where .= ' AND ( i.publish_up is NULL OR i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$db->Quote($nowDate).' )';
		if (!$ignoreState && $ignore_up_down_dates != 3 && $ignore_up_down_dates != 2)
			$where .= ' AND ( i.publish_down is NULL OR i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$db->Quote($nowDate).' )';


		// ***
		// *** Filter by permissions
		// ***

		$joinaccess = '';
		if (!$show_noauth)
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$where .= ' AND ty.access IN (0,'.$aid_list.')';
			$where .= ' AND mc.access IN (0,'.$aid_list.')';
			$where .= ' AND  i.access IN (0,'.$aid_list.')';
		}


		// ***
		// *** NON-STATIC behaviors that need current item information
		// ***

		$id   = $jinput->get('id', 0, 'int');   // id of current item
		$cid  = $jinput->get(($option == 'com_content' ? 'id' : 'cid'), 0, 'int');   // current category ID or category ID of current item

		$is_content_ext   = $option == 'com_flexicontent' || $option == 'com_content';
		$isflexi_itemview = $is_content_ext && ($view == 'item' || $view == 'article') && $id;
		$isflexi_catview  = $is_content_ext && $view == 'category' && ( $cid || $jinput->get('cids', '', 'string') );

		$curritem_date_field_needed =
			$behaviour_dates &&  // Dynamic
			$date_compare && // Comparing to current item
			$date_type==3 && // Comparing to custom date field
			$datecomp_field;  // Date field selected

		if ($isflexi_itemview && ($behaviour_cat || $behaviour_types || $behaviour_auth || $behaviour_items || $curritem_date_field_needed || $behaviour_filt))
		{
			// initialize variables
			$Itemid = $jinput->get('Itemid', 0, 'int');

			// NOTE: aborting execution if item view is required, but current view is not item view
			// and also proper usage of current item, both of these will be handled by SCOPEs

			$sel_date = '';
			$join_date = '';

			if ($curritem_date_field_needed)
			{
				$sel_date = ', dfrel.value as custom_date';
				$join_date =
						'	LEFT JOIN #__flexicontent_fields_item_relations AS dfrel'
					. '   ON ( i.id = dfrel.item_id AND dfrel.valueorder = 1 AND dfrel.field_id = '.$datecomp_field.' )';
			}

			// Check for new item form, aka nothing to retrieve
			if ($id)
			{
				$query = 'SELECT i.*, ie.*, GROUP_CONCAT(ci.catid SEPARATOR ",") as itemcats'
					. $sel_date
					. ' FROM #__content as i'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie on ie.item_id = i.id'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS ci on ci.itemid = i.id'
					. $join_date
					. ' WHERE i.id = ' . $id
					. ' GROUP BY ci.itemid';
				$curitem = $db->setQuery($query)->loadObject();

				// Get item dates
				$idate = null;

				switch ($date_type)
				{
					case 0:
						$idate = $curitem->created;
						break;

					case 1:
						$idate = $curitem->modified;
						break;

					case 2:
						$idate = $curitem->publish_up;
						break;

					case 3:
						if (isset($curitem->custom_date))
						{
							$idate = $curitem->custom_date;
						}
						break;
				}

				if ($idate)
				{
					$idate = explode(' ', $idate);
					$cdate = $idate[0] . ' 00:00:00';
				}

				$curritemcats = explode(',', $curitem->itemcats);
			}
		}


		/**
		 * Current item scope, only try if current view is indeed an FC item or Joomla article view
		 */

		if ($isflexi_itemview)
		{
			// Get id of current item or article view
			$currid = $jinput->get('id', 0, 'int');
			$currid = is_integer($currid) ? $currid : 0;

			if ($currid)
			{
				// Exclude method  ---  exclude current item
				if ($method_curitem === 1)
				{
					$where .=  ' AND i.id <> ' . $currid;
				}

				// Include method  ---  include current item ONLY
				elseif ($method_curitem === 2)
				{
					$where .=  ' AND i.id = ' . $currid;
				}

				// All Items including current
				else ;
			}
		}


		/**
		 * Current language scope
		 */

		$lang = flexicontent_html::getUserCurrentLang();

		// Exclude method  ---  exclude items of current language
		if ($method_curlang === 1)
		{
			$where .= ' AND ie.language NOT LIKE ' . $db->Quote($lang . '%');
		}

		// Include method  ---  include items of current language ONLY
		elseif ($method_curlang === 2)
		{
			$where .= ' AND ( ie.language LIKE ' . $db->Quote($lang . '%') . ' OR ie.language = "*" ' . ' ) ';
		}

		// Items of any language
		else ;


		/**
		 * Current user favourites scope, GUEST users have favourites via cookie
		 */

		// Favourites via cookie
		$favs = array_keys(flexicontent_favs::getInstance()->getRecords('item'));

		// Exclude method
  	if ($method_curuserfavs === 1)
  	{
			$join_favs = ' LEFT JOIN #__flexicontent_favourites AS fav ON fav.itemid = i.id AND fav.userid = ' . (int) $user->get('id');

			$where .= ' AND (fav.itemid IS NULL '
				. (empty($favs) ? '' : ' AND i.id NOT IN (' . implode(',', $favs) . ')')
				. ')';
		}

		// Include method
		elseif ($method_curuserfavs === 2)
		{
			$join_favs = ' LEFT JOIN #__flexicontent_favourites AS fav ON fav.itemid = i.id';

			$where_favs = array();
			$where_favs[] = $user->get('id') ? 'fav.userid = ' . (int) $user->get('id') : '0';
			$where_favs[] = !empty($favs) ? 'i.id IN (' . implode(',', $favs) . ')' : '0';

			$where .= ' AND (' . implode(' OR ', $where_favs) . ')';
		}

		// All Items regardless of being favoured by current user
		else
		{
		  $join_favs = '';
		}


		/**
		 * Joomla featured flag scope
		 */

		// Exclude method  ---  exclude currently logged user favourites
  	if ($method_featured === 1)
		{
			$where .= ' AND i.featured=0';
		}

		// Include method  ---  include currently logged user favourites
		elseif ($method_featured === 2)
		{
			$where .= ' AND i.featured=1';
		}

	  // All Items regardless of being featured or not
		else ;


		/**
		 * Item states scope
		 */

		$item_states = is_array($item_states)
			? implode(',', $item_states)
			: $item_states;

		if ($method_states === 0)
		{
			if (!$ignoreState)
			{
			  // method normal: Published item states
				$where .= ' AND i.state IN ( 1, -5 )';
			}
		}
		
		else
		{
			// Exclude trashed
			$where .= ' AND i.state <> -2';

			if ($item_states)
			{
				// Exclude method  ---  exclude specified item states
		  	if ($method_states === 1)
				{
					$where .= ' AND i.state NOT IN ('. $item_states .')';
				}

				// Include method  ---  include specified item states
				elseif ($method_states === 2)
				{
					$where .= ' AND i.state IN ('. $item_states .')';
				}
			}

			// Misconfiguration, when using include method with no state selected ...
			elseif ($method_states === 2)
			{
				echo '
					<div class="alert alert-warning">
						<b>WARNING:</b> Misconfigured item states scope, select at least one state or set states scope to Normal <small>(Published)</small>
					</div>';
				return;
			}
		}


		/**
		 * Categories scope
		 */

		// ZERO 'behaviour' means statically selected records, but METHOD 1 is ALL records ... so NOTHING to do
		if (!$behaviour_cat && $method_cat === 1)
		{
			if ($apply_config_per_category)
			{
				echo '
					<div class="alert alert-warning">
						<b>WARNING:</b> Misconfiguration warning, APPLY CONFIGURATION PER CATEGORY is possible only if CATEGORY SCOPE is set to
							either (a) INCLUDE(static selection of categories)
							or (b) items in same category as current item / or current category of category view
					</div>';
				return;
			}
		}

		// ZERO 'behaviour' means statically decided records, and METHOD is either 2 (INCLUDE), or 3 (EXCLUDE)
		elseif (!$behaviour_cat)
		{
			// Check for empty statically selected records, and abort with error message
			if (empty($catids))
			{
				echo '
					<div class="alert alert-warning">
						<b>WARNING:</b> Misconfigured category scope, select at least one category or set category scope to ALL
					</div>';
				return;
			}

			// Make sure categories is an array
			$catids = is_array($catids)
				? $catids
				: array($catids);

			// Retrieve extra categories, such children or parent categories
			$catids_arr = flexicontent_cats::getExtraCats($catids, $treeinclude, array());

			if (empty($catids_arr))
			{
				if ($show_nocontent_msg)
				{
					echo '
						<div class="alert alert-notice">
							' . JText::_("MOD_FLEXI_NO_CONTENT_CURRENTVIEW_ACCESS_LEVEL") . '
						</div>';
				}
				return;
			}

			// Exclude method
			if ($method_cat === 2)
			{
				if ($apply_config_per_category)
				{
					echo '
						<div class="alert alert-warning">
						' . JText::_("MOD_FLEXI_WARNING_MESSAGE_CAT_SCOPE") . '
						</div>';
					return;
				}
				$where .= ' AND c.id NOT IN (' . implode(',', $catids_arr) . ')';
			}

			// Include method
			elseif ($method_cat === 3)
			{
				if (!$apply_config_per_category)
				{
					if ($cat_combine)
					{
						$where .= ' AND i.id IN ('
							. ' SELECT DISTINCT itemid'
							. ' FROM #__flexicontent_cats_item_relations'
							. ' WHERE catid IN (' . implode(',', $catids_arr) . ')'
							. ' GROUP by itemid HAVING COUNT(*) >= ' . count($catids_arr) . ')'
						;
					}
					else
					{
						$where .= ' AND c.id IN (' . implode(',', $catids_arr) . ')';
					}
				}

				// Applying configuration per category
				else
				{
					// The items retrieval query will be executed ... once per EVERY category
					foreach ($catids_arr as $catid)
					{
						$multiquery_cats[$catid] = ' AND c.id = '.$catid;
					}
					$params->set('dynamic_catids', serialize($catids_arr));  // Set dynamic catids to be used by the getCategoryData
				}

			}
		}

		// non-ZERO 'behaviour' means dynamically decided records
		else
		{
			if (($behaviour_cat == 2 || $behaviour_cat == 4) && $apply_config_per_category) {
				echo  JText::_("MOD_FLEXI_WARNING_MESSAGE_CAT_SCOPE");
				return;
			}

			$currcat_valid_case = ($behaviour_cat==1 && $isflexi_itemview) || ($behaviour_cat==3 && $isflexi_catview);
			if ( !$currcat_valid_case ) {
				return;  // current view is not item OR category view ... , nothing to display
			}

			if ($isflexi_itemview)
			{
				// IF $cid is not set then use the main category id of the (current) item
				$cid = $cid ? $cid : $curitem->catid;

				// Retrieve extra categories, such children or parent categories
				$catids_arr = flexicontent_cats::getExtraCats(array($cid), $treeinclude, $curritemcats);
			}

			elseif ($isflexi_catview)
			{
				$cid = $jinput->get(($option == 'com_content' ? 'id' : 'cid'), 0, 'int');   // current category ID or category ID of current item
				if (!$cid)
				{
					$_cids = $jinput->get('cids', '', 'string');
					if ( !is_array($_cids) )
					{
						$_cids = preg_replace( '/[^0-9,]/i', '', (string) $_cids );
						$_cids = explode(',', $_cids);
					}
					// make sure given data are integers ... !!
					$cids = array();
					foreach ($_cids as $i => $_id)  if ((int) $_id) $cids[] = (int) $_id;

					// Retrieve extra categories, such children or parent categories
					$catids_arr = flexicontent_cats::getExtraCats(array($cid), $treeinclude, array());
				}
			}

			// Nothing to display
			else
			{
				return;
			}

			// Retrieve extra categories, such children or parent categories
			$catids_arr = flexicontent_cats::getExtraCats(array($cid), $treeinclude, $isflexi_itemview ? $curritemcats : array());

			if (empty($catids_arr))
			{
				if ($show_nocontent_msg)
				{
					echo '
						<div class="alert alert-notice">
							' . JText::_("MOD_FLEXI_NO_CONTENT_CURRENTVIEW_ACCESS_LEVEL") . '
						</div>';
				}
				return;
			}

			if ($behaviour_cat === 1 || $behaviour_cat === 3)
			{
				if (!$apply_config_per_category)
				{
					$where .= ' AND c.id IN (' . implode(',', $catids_arr) . ')';
				}

				// Applying configuration per category
				else
				{
					// The items retrieval query will be executed ... once per EVERY category
					foreach ($catids_arr as $catid)
					{
						$multiquery_cats[$catid] = ' AND c.id = ' . $catid;
					}

					// Set dynamic catids to be used by the getCategoryData
					$params->set('dynamic_catids', serialize($catids_arr));
				}
			}

			else
			{
				$where .= ' AND c.id NOT IN (' . implode(',', $catids_arr) . ')';
			}
		}


		/**
		 * Types scope
		 */

		// ZERO 'behaviour' means statically selected records, but METHOD 1 is ALL records ... so NOTHING to do
		if (!$behaviour_types && $method_types === 1)
		{
		}

		// ZERO 'behaviour' means statically decided records, and METHOD is either 2 (INCLUDE), or 3 (EXCLUDE)
		elseif (!$behaviour_types)
		{
			// Check for empty statically selected records, and abort with error message
			if (empty($types))
			{
				echo '
				<div class="alert alert-warning">
				' . JText::_("MOD_FLEXI_WARNING_TYPE_SCOPE") . '
				</div>';
				return;
			}

			// Make types a comma separated string of ids
			$types = is_array($types) ? implode(',', $types) : $types;

			// Exclude method
			if ($method_types === 2)
			{
				$where .= ' AND ie.type_id NOT IN (' . $types . ')';
			}

			// Include method
			elseif ($method_types === 3)
			{
				$where .= ' AND ie.type_id IN (' . $types . ')';
			}
		}

		// non-ZERO 'behaviour' means dynamically decided records
		else
		{
			// Check if current view is not item view ... , nothing to display
			if (!$isflexi_itemview)
			{
				return;
			}

			if ($behaviour_types === 1)
			{
				$where .= ' AND ie.type_id = ' . (int) $curitem->type_id;
			}
			elseif ($behaviour_types === 2)
			{
				$where .= ' AND ie.type_id <> ' . (int) $curitem->type_id;
			}
		}


		/**
		 * Author scope
		 */

		// ZERO 'behaviour' means statically selected records, but METHOD 1 is ALL records ... so NOTHING to do
		if (!$behaviour_auth && $method_auth === 1)
		{
		}

		// ZERO 'behaviour' means statically decided records, and METHOD is either 2 (INCLUDE), or 3 (EXCLUDE)
		elseif (!$behaviour_auth)
		{
			// Check for empty statically selected records, and abort with error message
			if (empty($authors))
			{
				echo '
					<div class="alert alert-warning">
						' . JText::_("MOD_FLEXI_WARNING_AUTHOR_SCOPE") . '
					</div>';
				return;
			}

			// Exclude method
			if ($method_auth === 2)
			{
				$where .= ' AND i.created_by NOT IN (' . $authors . ')';
			}

			// Include method
			elseif ($method_auth === 3)
			{
				$where .= ' AND i.created_by IN (' . $authors . ')';
			}
		}

		// non-ZERO 'behaviour' means dynamically decided records
		else
		{
			// Check if current view is not item view ... , nothing to display, but do this ONLY IF behaviour <> 3 (= current user) thus not related to current item
			if (!$isflexi_itemview && $behaviour_auth !== 3)
			{
				return;
			}

			if ($behaviour_auth === 1)
			{
				$where .= ' AND i.created_by = ' . (int) $curitem->created_by;
			}
			elseif ($behaviour_auth === 2)
			{
				$where .= ' AND i.created_by <> ' . (int) $curitem->created_by;
			}
			elseif ($behaviour_auth === 3)
			{
				$where .= ' AND i.created_by = ' . (int) $user->id;
			}
		}


		/**
		 * Items scope
		 */

		// ZERO 'behaviour' means statically selected records, but METHOD 1 is ALL records ... so NOTHING to do
		if ( !$behaviour_items && $method_items == 1 )
		{
		}

		// ZERO 'behaviour' means statically decided records, and METHOD is either 2 (INCLUDE), or 3 (EXCLUDE)
		elseif (!$behaviour_items)
		{
			// Check for empty statically selected records, and abort with error message
			if (empty($items))
			{
				echo '
					<div class="alert alert-warning">
						' . JText::_("MOD_FLEXI_WARNING_ITEMS_SCOPE") . '
					</div>';
				return;
			}

			// Exclude method
			if ($method_items === 2)
			{
				$where .= ' AND i.id NOT IN (' . $items . ')';
			}

			// Include method
			elseif ($method_items === 3)
			{
				$where .= ' AND i.id IN (' . $items . ')';
			}
		}

		// 'behaviour' 2 means records that are related to current item via the relation Field
		elseif ($behaviour_items === 2 || $behaviour_items === 3)
		{
			// Check if current view is not item view ... , nothing to display
			if (!$isflexi_itemview)
			{
				return;
			}

			// Make sure this is no set ...
			unset($related);

			if (count($relitems_fields))
			{
				$where2 = (count($relitems_fields) > 1) ? ' AND field_id IN ('.implode(',', $relitems_fields).')' : ' AND field_id = '.$relitems_fields[0];

				// select the item ids related to current item via the relation fields
				$query2 = 'SELECT DISTINCT ' . ($behaviour_items === 2 ? 'value' : 'item_id')
					. ' FROM #__flexicontent_fields_item_relations'
					. ' WHERE ' . ($behaviour_items==2 ? 'item_id' : 'value') . ' = ' . (int) $id
					. $where2;
				$related = $db->setQuery($query2)->loadColumn();
			}

			// Check if no related items were found
			if (empty($related))
			{
				return;
			}

			$related = array_map('intval', $related);
			$where .= ' AND i.id IN (' . implode(',', $related) . ')';
		}

		// 'behaviour' 1 means records that are related to current item via common TAGS
		elseif ($behaviour_items === 1)
		{
			// Check if current view is not item view ... , nothing to display
			if (!$isflexi_itemview)
			{
				return;
			}

			// select the tags associated to the item
			$query2 = 'SELECT tid'
				. ' FROM #__flexicontent_tags_item_relations'
				. ' WHERE itemid = ' . (int) $id;
			$tags = $db->setQuery($query2)->loadColumn();
			$tags = array_diff($tags, $excluded_tags);

			// Make sure this is no set ...
			unset($related);

			if ($tags)
			{
				$where2 = ' AND tid IN (' . implode(',', $tags) . ')';

				// select the item ids related to current item via common tags
				$query2 = 'SELECT DISTINCT itemid'
					. ' FROM #__flexicontent_tags_item_relations'
					. ' WHERE itemid <> '.(int) $id
					. $where2;
				$related = $db->setQuery($query2)->loadColumn();
			}

			if (isset($related) && count($related)) {
				$where .= (count($related) > 1) ? ' AND i.id IN ('.implode(',', $related).')' : ' AND i.id = '.$related[0];
			} else {
				// No related items were found
				return;
			}
		}


		/**
		 * Tags scope
		 */

		if ($method_tags > 1)
		{
			// Check for empty statically selected records, and abort with error message
			if (empty($tag_ids))
			{
				echo '
					<div class="alert alert-warning">
					' . JText::_("MOD_FLEXI_WARNING_TAGS_SCOPE") . '
					</div>';
				return;
			}

			// Make sure tag_ids is an array
			$tag_ids = !is_array($tag_ids) ? array($tag_ids) : $tag_ids;

			// Require ALL is meant only for "include" method
			if ($method_tags === 2)
			{
				$tag_combine = 0;
			}

			// Create query to match item ids using the selected tags
			$query2 = 'SELECT '.($tag_combine ? 'itemid' : 'DISTINCT itemid')
				. ' FROM #__flexicontent_tags_item_relations'
				. ' WHERE tid IN ('.implode(',', $tag_ids).')'
				. ($tag_combine ? ' GROUP by itemid HAVING COUNT(*) >= ' . count($tag_ids) : '');

			// Exclude method
			if ($method_tags === 2)
			{
				$where .= ' AND i.id NOT IN (' . $query2 . ')';
			}

			// Include method
			elseif ($method_tags === 3)
			{
				$where .= ' AND i.id IN (' . $query2 . ')';
			}
		}


		/**
		 * Date scope
		 */

		// ZERO 'behaviour' means statically selected records, but METHOD 1 is ALL records ... so NOTHING to do
		// NOTE: currently we only have ALL, INCLUDE methods
		if (!$behaviour_dates && $method_dates === 1)
		{
		}

		// ZERO 'behaviour' means statically selected date limits
		elseif (!$behaviour_dates)
		{
			$negate_op = $method_dates === 2 ? 'NOT' : '';

			if (!$raw_edate && $edate && !FLEXIUtilities::isSqlValidDate($edate))
			{
				echo '
					<div class="alert alert-warning">
					' . JText::_("MOD_FLEXI_WARNING_DATES_SCOPE") . '
					</div>';
				return;
			}
			elseif ($edate)
			{
				$where .= ' AND ( '
					.$negate_op.' ( '.$comp.' <= '.(!$raw_edate ? $db->Quote($edate) : $edate).' )'
					.($nulldates ? ' OR '.$comp.' IS NULL OR '.$comp.'="" ' : '')
				.' )';
			}

			if (!$raw_bdate && $bdate && !FLEXIUtilities::isSqlValidDate($bdate))
			{
				echo '
					<div class="alert alert-warning">
						' . JText::_("MOD_FLEXI_WARNING_DATES_SCOPE") . '
					</div>';
				return;
			}
			elseif ($bdate)
			{
				$where .= ' AND ( '
					.$negate_op.' ( '.$comp.' >= '.(!$raw_bdate ? $db->Quote($bdate) : $bdate).' )'
					.($nulldates ? ' OR '.$comp.' IS NULL OR '.$comp.'="" ' : '')
				.' )';
			}
		}

		// non-ZERO 'behaviour' means dynamically decided date limits
		else
		{
			// Check if current view is not item view ... , nothing to display, but do this ONLY IF date_compare 1 (= compare to current item)
			if (!$isflexi_itemview && $date_compare === 1)
			{
				return;
			}

			// FOR date_compare==0, $cdate is SERVER DATE
			// FOR date_compare==1, $cdate is CURRENT ITEM DATE of type created or modified or publish_up or CUSTOM date field
			switch ($behaviour_dates)
			{
				case '1' : // custom offset
					if ($edate)
					{
						$edate = array(
							0 => preg_replace("/[^-+0-9\s]/", "", $edate),
							1 => preg_replace("/[0-9-+\s]/", "", $edate)
						);
						if (empty($edate[1]))
						{
							echo JText::_("MOD_FLEXI_WARNING_DATES_SCOPE");
							return;
						}
						else
						{
							$where .= ' AND ( '
								.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, $edate[0], $edate[1]))
								.($nulldates ? ' OR '.$comp.' IS NULL OR '.$comp.'="" ' : '')
							.' )';
						}
					}
					if ($bdate)
					{
						$bdate = array(
							0 => preg_replace("/[^-+0-9\s]/", "", $bdate),
							1 => preg_replace("/[0-9-+\s]/", "", $bdate)
						);
						if (empty($bdate[1]))
						{
							echo JText::_("MOD_FLEXI_WARNING_DATES_SCOPE_BEGIN");
							return;
						}
						else
						{
							$where .= ' AND ( '
								.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, $bdate[0], $bdate[1]))
								.($nulldates ? ' OR '.$comp.' IS NULL OR '.$comp.'="" ' : '')
							.' )';
						}
					}
				break;

				case '8' : // same day
					$cdate = explode(' ', $cdate);
					$cdate = explode('-', $cdate[0]);
					$cdate = $cdate[0].'-'.$cdate[1].'-'.$cdate[2].' 00:00:00';

					$where .= ' AND ( '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 1, 'd')).' )';
					$where .= ' AND ( '.$comp.' >= '.$db->Quote($cdate).' )';
				break;

				case '2' : // same month
					$cdate = explode(' ', $cdate);
					$cdate = explode('-', $cdate[0]);
					$cdate = $cdate[0].'-'.$cdate[1].'-01 00:00:00';

					$where .= ' AND ( '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 1, 'm')).' )';
					$where .= ' AND ( '.$comp.' >= '.$db->Quote($cdate).' )';
				break;

				case '3' : // same year
					$cdate = explode(' ', $cdate);
					$cdate = explode('-', $cdate[0]);
					$cdate = $cdate[0].'-01-01 00:00:00';

					$where .= ' AND ( '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 1, 'Y')).' )';
					$where .= ' AND ( '.$comp.' >= '.$db->Quote($cdate).' )';
				break;

				case '9' : // previous day
					$cdate = explode(' ', $cdate);
					$cdate = explode('-', $cdate[0]);
					$cdate = $cdate[0].'-'.$cdate[1].'-'.$cdate[2].' 00:00:00';

					$where .= ' AND ( '.$comp.' < '.$db->Quote($cdate).' )';
					$where .= ' AND ( '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, -1, 'd')).' )';
				break;

				case '4' : // previous month
					$cdate = explode(' ', $cdate);
					$cdate = explode('-', $cdate[0]);
					$cdate = $cdate[0].'-'.$cdate[1].'-01 00:00:00';

					$where .= ' AND ( '.$comp.' < '.$db->Quote($cdate).' )';
					$where .= ' AND ( '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, -1, 'm')).' )';
				break;

				case '5' : // previous year
					$cdate = explode(' ', $cdate);
					$cdate = explode('-', $cdate[0]);
					$cdate = $cdate[0].'-01-01 00:00:00';

					$where .= ' AND ( '.$comp.' < '.$db->Quote($cdate).' )';
					$where .= ' AND ( '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, -1, 'Y')).' )';
				break;

				case '10' : // next day
					$cdate = explode(' ', $cdate);
					$cdate = explode('-', $cdate[0]);
					$cdate = $cdate[0].'-'.$cdate[1].'-'.$cdate[2].' 00:00:00';

					$where .= ' AND ( '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 2, 'd')).' )';
					$where .= ' AND ( '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, 1, 'd')).' )';
				break;

				case '6' : // next month
					$cdate = explode(' ', $cdate);
					$cdate = explode('-', $cdate[0]);
					$cdate = $cdate[0].'-'.$cdate[1].'-01 00:00:00';

					$where .= ' AND ( '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 2, 'm')).' )';
					$where .= ' AND ( '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, 1, 'm')).' )';
				break;

				case '7' : // next year
					$cdate = explode(' ', $cdate);
					$cdate = explode('-', $cdate[0]);
					$cdate = $cdate[0].'-01-01 00:00:00';

					$where .= ' AND ( '.$comp.' < '.$db->Quote(date_time::shift_dates($cdate, 2, 'Y')).' )';
					$where .= ' AND ( '.$comp.' >= '.$db->Quote(date_time::shift_dates($cdate, 1, 'Y')).' )';
				break;

				case '11' : // same day of month, ignore year
					$where .= ' AND ( DAYOFMONTH('.$comp.') = '.'DAYOFMONTH('.$db->Quote($cdate).') AND MONTH('.$comp.') = '.'MONTH('.$db->Quote($cdate).') )';
				break;

				case '12' : // [-3d,+3d] days of month, IGNORE YEAR
					$where .= ' AND ((DAYOFMONTH('.$db->Quote($cdate).')-3) <= DAYOFMONTH('.$comp.') AND DAYOFMONTH('.$comp.') <= (DAYOFMONTH('.$db->Quote($cdate).')+4) AND MONTH('.$comp.') = '.'MONTH('.$db->Quote($cdate).') )';
				break;

				case '13' : // same week of month, IGNORE YEAR
					$week_start = (int) $params->get('week_start', 0);  // 0 is sunday, 5 is monday
					$week_of_month = '(WEEK(%s,5) - WEEK(DATE_SUB(%s, INTERVAL DAYOFMONTH(%s)-1 DAY),5)+1)';
					$where .= ' AND ('. str_replace('%s', $comp, $week_of_month).' = '.str_replace('%s', $db->Quote($cdate), $week_of_month) .' AND ( MONTH('.$comp.') = '.'MONTH('.$db->Quote($cdate).') ) )';
				break;

				case '14' : // same week of year, IGNORE YEAR
					$week_start = (int) $params->get('week_start', 0);  // 0 is sunday, 5 is monday
					$where .= ' AND ( WEEK('.$comp.') = '.'WEEK('.$db->Quote($cdate).','.$week_start.') )';
				break;

				case '15' : // same month of year, IGNORE YEAR
					$where .= ' AND ( MONTH('.$comp.') = '.'MONTH('.$db->Quote($cdate).') )';
				break;

				case '16' : // same day of month, IGNORE MONTH, YEAR
					$where .= ' AND ( DAYOFMONTH('.$comp.') = '.'DAYOFMONTH('.$db->Quote($cdate).') )';
				break;

				case '17' : // [-3d,+3d] days of month, IGNORE  MONTH, YEAR
					$where .= ' AND ((DAYOFMONTH('.$db->Quote($cdate).')-3) <= DAYOFMONTH('.$comp.') AND DAYOFMONTH('.$comp.') <= (DAYOFMONTH('.$db->Quote($cdate).')+4) )';
				break;

				case '18' : // same week of month, IGNORE MONTH, YEAR
					$week_start = (int) $params->get('week_start', 0);  // 0 is sunday, 5 is monday
					$week_of_month = '(WEEK(%s,5) - WEEK(DATE_SUB(%s, INTERVAL DAYOFMONTH(%s)-1 DAY),5)+1)';
					$where .= ' AND ('. str_replace('%s', $comp, $week_of_month).' = '.str_replace('%s', $db->Quote($cdate), $week_of_month) .' )';
				break;
			}
		}



		/**
		 * EXTRA joins for special cases
		 */

		// EXTRA joins when comparing to custom date field
		$join_date = '';

		// Date SCOPE: dynamic behaviour, or static date behavior with (static) method != ALL(=1)
		if ($behaviour_dates || $method_dates !== 1)
		{
			if (($bdate || $edate || $behaviour_dates) && $date_type === 3)
			{
				if ($datecomp_field)
				{
					$join_date = ' LEFT JOIN #__flexicontent_fields_item_relations AS dfrel ON (i.id = dfrel.item_id AND dfrel.field_id = ' . $datecomp_field . ')';
				}
				else
				{
					echo '
						<div class="alert alert-warning">
							' . JText::_("MOD_FLEXI_WARNING_DATES_SCOPE_INI") . '
						</div>';
					return;
				}
			}
		}


		// *****************************************************************************************************************************
		// Get orderby SQL CLAUSE ('ordering' is passed by reference but no frontend user override is used (we give empty 'request_var')
		// *****************************************************************************************************************************

		$orderby = flexicontent_db::buildItemOrderBy(
			$params,
			$ordering, $request_var='', $config_param = 'ordering',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order = '', $default_order_dir = '', $sfx='', $support_2nd_lvl=true
		);
		//echo "<br/>" . print_r($ordering, true) ."<br/>";


		// EXTRA join of field used in custom ordering
		// NOTE: if (1st/2nd level) custom field id is not set, THEN 'field' ordering was changed to level's default, by the ORDER CLAUSE creating function
		$orderby_join = '';

		// Create JOIN for ordering items by a custom field (Level 1)
		if ('field' === $ordering[1])
		{
			$orderbycustomfieldid = (int) $params->get('orderbycustomfieldid', 0);
			$orderbycustomfieldint = (int) $params->get('orderbycustomfieldint', 0);

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
			else
			{
				$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$orderbycustomfieldid;
			}
		}

		// Create JOIN for ordering items by a custom field (Level 2)
		if ( 'field' == $ordering[2] ) {
			$orderbycustomfieldid_2nd = (int) $params->get('orderbycustomfieldid'.'_2nd', 0);
			$orderbycustomfieldint_2nd = (int) $params->get('orderbycustomfieldint'.'_2nd', 0);
			if ($orderbycustomfieldint_2nd==4) {
				$orderby_join .= '
					LEFT JOIN (
						SELECT f2.item_id, SUM(fdat2.hits) AS file_hits2
						FROM #__flexicontent_fields_item_relations AS f2
						LEFT JOIN #__flexicontent_files AS fdat2 ON fdat2.id = f2.value
				 		WHERE f2.field_id='.$orderbycustomfieldid_2nd.'
				 		GROUP BY f2.item_id
				 	) AS dl2 ON dl2.item_id = i.id';
			}
			else
			{
				$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$orderbycustomfieldid_2nd;
			}
		}

		// Create JOIN for ordering items by author's name
		if (in_array('author', $ordering) || in_array('rauthor', $ordering))
		{
			$orderby_join .= ' LEFT JOIN #__users AS u ON u.id = i.created_by';
		}


		/**
		 * Decide Select Sub-Clause and Join-Clause for comments
		 */

		$display_comments	= (int) $params->get('display_comments', 0);
		$display_comments_feat = (int) $params->get('display_comments_feat', 0);

		if ($display_comments_feat || $display_comments || in_array('commented', $ordering))
		{
			/**
			 * Currently this is implemented only for JComments
			 * No need to reset 'commented' ordering if jcomments is not installed, and neither
			 * print message, the ORDER CLAUSE creating function should have done this already
			 */
			$jcomments_exist = file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php');
		}

		// Decide to JOIN (or not) with comments TABLE, needed when displaying comments and/or when ordering by comments
		$add_comments = ($display_comments_feat || $display_comments || in_array('commented', $ordering)) && $jcomments_exist;

		// Additional select and joins for comments
		$select_comments     = $add_comments ? ', COUNT(DISTINCT com.id) AS comments_total' : '';
		$join_comments_type  = $ordering[1]=='commented' ? ' INNER JOIN' : ' LEFT JOIN';   // Do not require most commented for 2nd level ordering
		$join_comments       = $add_comments
			? $join_comments_type . ' #__jcomments AS com ON com.object_id = i.id AND com.object_group="com_flexicontent" AND com.published = "1"'
			: '' ;


		/**
		 * Decide Select Sub-Clause and Join-Clause for voting/rating
		 */

		$display_voting	= $params->get('display_voting');
		$display_voting_feat = $params->get('display_voting_feat');

		// Decide to JOIN (or not) with rating TABLE, needed when displaying ratings and/or when ordering by ratings
		$add_rated = $display_voting_feat || $display_voting || in_array('rated', $ordering);

		// Additional select and joins for ratings
		if ($add_rated)
		{
			$select_rated = ', cr.rating_sum as rating_sum, cr.rating_count as rating_count';

			$rating_join = null;
			$rating_col = flexicontent_db::buildRatingOrderingColumn($rating_join);

			if (in_array('rated', $ordering))
			{
				$select_rated .= ', ' . $rating_col;
			}

			// We will not exclude non-rated items by using INNER JOIN, since we now have configuration for not-yet rated items ... (e.g. 70%)
			$join_rated_type  = ' LEFT JOIN ';  // in_array('rated', $ordering) ? ' INNER JOIN' : ' LEFT JOIN';
			$join_rated       = $join_rated_type . $rating_join;
		}
		else
		{
			$select_rated = '';
			$join_rated = '';
		}


		/**
		 * Finally put together the query to retrieve the listed items
		 */

		/**
		 * Custom FIELD scope
		 */

		$where_field_filters = '';
		$join_field_filters = '';

		// ZERO 'behaviour' means statically selected records, but METHOD 1 is ALL records ... so NOTHING to do
		if (!$behaviour_filt && $method_filt === 1)
		{
		}

		elseif ($behaviour_filt === 0 || $behaviour_filt === 2)
		{
			$negate_op = $method_filt == 2 ? 'NOT' : '';

			// These field filters apply a STATIC filtering, regardless of current item being displayed.
			// Static Field Filters (These are a string that MAPs filter ID TO filter VALUES)
			$static_filters_data = FlexicontentFields::setFilterValues( $params, 'static_filters', $is_persistent=1, $set_method="array");

			// Dynamic Field Filters (THIS is filter IDs list)
			// These field filters apply a DYNAMIC filtering, that depend on current item being displayed. The items that have same value as currently displayed item will be included in the list.
			//$dynamic_filters = FlexicontentFields::setFilterValues( $params, 'dynamic_filters', $is_persistent=0);

			foreach ($static_filters_data as $filter_id => $filter_values)
			{
				$relation_field_id = 0;  // Set if current Field is a relation / relation reverse field
				$ritem_field_id = 0;     // The field of items related via 'relation_field_id' to apply limitation

				// Check if we will apply limitation via relation field
				if (is_array($filter_values) && count($filter_values) === 1)
				{
					$ritem_field_id = key($filter_values);
					$ritem_field_id = is_int($ritem_field_id) && $ritem_field_id < 0
						? - $ritem_field_id
						: 0;
				}

				// Check existance of the relation field
				if ($ritem_field_id)
				{
					$is_relation = true;
					$filter_values = reset($filter_values);

					$_fields = FlexicontentFields::getFieldsByIds(array($filter_id));
					if (!empty($_fields))
					{
						$ri_field = reset($_fields);
						$ri_item = null;
						FlexicontentFields::loadFieldConfig($ri_field, $ri_item);
						$is_relation = $ri_field->parameters->get('reverse_field', 0, 'INT') === 0;
						$relation_field_id = $ri_field->parameters->get('reverse_field', 0, 'INT') ?: $filter_id;
					}
				}

				// Table alias of 1st join with items-values-relation table
				$rel = 'rel' . $filter_id;
				$c = 'i';

				// Case 1: Require that filter values are in --Related / Reverse Related-- items of the returned items
				if ($relation_field_id)
				{
					// Find items that are directly / indirectly related via a RELATION / REVERSE RELATION field
					$match_rel_items = $is_relation
						? $c . '.id = ' . $rel . '.item_id'
						: $c . '.id = ' . $rel . '.value_integer';
					$join_field_filters .= ' JOIN #__flexicontent_fields_item_relations AS ' . $rel . ' ON ' . $match_rel_items . ' AND ' . $rel . '.field_id = ' . $relation_field_id;

					$val_tbl = $rel . '_ritems';
					$val_field_id = $ritem_field_id;

					// RELATED / REVERSE RELATED Items must have given values
					$val_on_items = $is_relation
						? $val_tbl . '.item_id = ' . $rel . '.value_integer'
						: $val_tbl . '.item_id = ' . $rel . '.item_id';
				}

				// Case 2: Require that filter values are in --returned-- items themselves
				else
				{
					$val_tbl = $rel;
					$val_field_id = $filter_id;

					// RETURNED Items must have given values
					$val_on_items = $val_tbl . '.item_id = ' . $c . '.id';
				}

				// Join with values table 'ON' the current filter field id and 'ON' the items at interest ... below we will add an extra 'ON' clause to limit to the given field values
				$join_field_filters .= ' JOIN #__flexicontent_fields_item_relations AS ' . $val_tbl . ' ON ' . $val_on_items . ' AND ' . $val_tbl . '.field_id = ' . $val_field_id;

				// Handle single-valued filter as multi-valued
				if ( !is_array($filter_values) )
				{
					$filter_values = array(0 => $filter_values);
				}

				// Single or Multi valued filter
				if ( isset($filter_values[0]) )
				{
					$in_values = array();
					foreach ($filter_values as $val) $in_values[] = $db->Quote( $val );   // Quote in case they are strings !!
					$join_field_filters .= ' AND '.$negate_op.' (' . $val_tbl . '.value IN ('.implode(',', $in_values).') ) ';
				}

				// Range value filter
				else
				{
					// Special case only one part of range provided ... must MATCH/INCLUDE empty values or NULL values ...
					$value_empty = !strlen(@ $filter_values[1]) && strlen(@ $filter_values[2]) ? ' OR ' . $val_tbl . '.value="" OR ' . $val_tbl . '.value IS NULL ' : '';

					if ( strlen(@ $filter_values[1]) || strlen(@ $filter_values[2]) )
					{
						$join_field_filters .= ' AND '.$negate_op.' ( 1 ';
						if ( strlen(@ $filter_values[1]) ) $join_field_filters .= ' AND (' . $val_tbl . '.value >=' . $filter_values[1] . ') ';
						if ( strlen(@ $filter_values[2]) ) $join_field_filters .= ' AND (' . $val_tbl . '.value <=' . $filter_values[2] . $value_empty . ') ';
						$join_field_filters .= ' )';
					}
				}
			}
			//echo $join_field_filters;
		}

		if ($behaviour_filt==1 || $behaviour_filt==2)
		{
			// Check if current view is not item view ... , nothing to display
			if (!$isflexi_itemview)
			{
				return;
			}

			// 1. Get ids of dynamic filters
			$_dynamic_filter_ids = FLEXIUtilities::paramToArray($dynamic_filters, "/[\s]*,[\s]*/", "intval");
			$dynamic_filter_ids = array();
			foreach($_dynamic_filter_ids as $dynamic_filter_id)
			{
				if ($dynamic_filter_id) $dynamic_filter_ids[] = $dynamic_filter_id;
			}

			if (empty($dynamic_filter_ids))
			{
				echo "Please enter at least 1 field ID (integer) in Custom field filtering SCOPE, or set behaviour to static";
			}

			else
			{
				// 2. Get values of dynamic filters
				$where2 = (count($dynamic_filter_ids) > 1) ? ' AND field_id IN ('.implode(',', $dynamic_filter_ids).')' : ' AND field_id = '.$dynamic_filter_ids[0];

				// select the item ids related to current item via the relation fields
				$query2 = 'SELECT DISTINCT value, field_id'
					. ' FROM #__flexicontent_fields_item_relations'
					. ' WHERE item_id = '.(int) $id
					. $where2;
				$curritem_vals = $db->setQuery($query2)->loadObjectList();

				// 3. Group values by field
				$_vals = array();
				foreach ($curritem_vals as $v)
				{
					$_vals[$v->field_id][] = $v->value;
				}

				foreach ($dynamic_filter_ids as $filter_id)
				{
					// Handle non-existent value by requiring that matching item do not have a value for this field either
					if (!isset($_vals[$filter_id]))
					{
						$where_field_filters .= ' AND reldyn'.$filter_id.'.value IS NULL';
					}

					// Single or Multi valued filter , handle by requiring ANY value
					else
					{
						$in_values = array();
						foreach ($_vals[$filter_id] as $v) $in_values[] = $db->Quote( $v );
						$where_field_filters .= ' AND reldyn'.$filter_id.'.value IN ('.implode(',', $in_values).') ' ."\n";
					}

					$join_field_filters .= ' JOIN #__flexicontent_fields_item_relations AS reldyn'.$filter_id.' ON reldyn'.$filter_id.'.item_id=i.id AND reldyn'.$filter_id.'.field_id = ' . $filter_id ."\n";
				}
				//echo "<pre>"."\n\n".$join_field_filters ."\n\n".$where_field_filters."</pre>";
			}
		}


		/**
		 * Create query to get item ids
		 */

		$items_query 	= 'SELECT ' . ($totals !== null ? 'SQL_CALC_FOUND_ROWS' : '')
			. ' i.id '
			. (in_array('commented', $ordering) ? $select_comments : '')
			. (in_array('rated', $ordering) ? $select_rated : '')
			. ' FROM #__flexicontent_items_tmp AS i'
			. ' JOIN #__flexicontent_items_ext AS ie on ie.item_id = i.id'
			. ' JOIN #__flexicontent_types AS ty on ie.type_id = ty.id'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' JOIN #__categories AS  c ON  c.id = rel.catid'
			. ' JOIN #__categories AS mc ON mc.id = i.catid'
			. $joinaccess
			. $join_favs
			. $join_date
			. (in_array('commented', $ordering) ? $join_comments : '')
			. (in_array('rated', $ordering) ? $join_rated : '')
			. $orderby_join
			. $join_field_filters
			. $where .' '. ($apply_config_per_category ? '__CID_WHERE__' : '')
			. $where_field_filters
			. ' GROUP BY i.id'
			. (!$behaviour_items && $method_items == 3 && $items_use_order ? ' ORDER BY FIELD(i.id, '. $items .')' : $orderby)
			;


		/**
		 * Create query to get item data
		 */

		// if using CATEGORY SCOPE INCLUDE ... then link though them ... otherwise via main category
		$_cl = !$behaviour_cat && $method_cat == 3 && !$link_via_main ? 'c' : 'mc';
		$items_query_data 	= 'SELECT '
			. ' i.*, ie.*, ty.name AS typename'
			. $select_comments
			. $select_rated
			. ', mc.title AS maincat_title, mc.alias AS maincat_alias'   // Main category data
			. ', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug'
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', '.$_cl.'.id, '.$_cl.'.alias) ELSE '.$_cl.'.id END as categoryslug'
			. ', GROUP_CONCAT(rel.catid SEPARATOR ",") as itemcats'
			. ' FROM #__content AS i'
			. ' JOIN #__flexicontent_items_ext AS ie on ie.item_id = i.id'
			. ' JOIN #__flexicontent_types AS ty on ie.type_id = ty.id'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' JOIN #__categories AS  c ON  c.id = rel.catid'
			. ' JOIN #__categories AS mc ON mc.id = i.catid'
			. $joinaccess
			. $join_favs
			. $join_date
			. $join_comments
			. $join_rated
			. $orderby_join
			. ' WHERE i.id IN (__content__)'
			. ' GROUP BY i.id'
			;


		/**
		 * Execute query once OR per category
		 */

		if (!isset($multiquery_cats))
		{
			$multiquery_cats = array(0 => '');
		}

		foreach ($multiquery_cats as $catid => $cat_where)
		{
			$_microtime = microtime(1);

			// Get content list per given category
			$per_cat_query = str_replace('__CID_WHERE__', $cat_where, $items_query);
			//require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'SqlFormatter'.DS.'SqlFormatter.php');
			//echo str_replace('PPP_', '#__', SqlFormatter::format(str_replace('#__', 'PPP_', $query)))."<br/>";
			$db->setQuery($per_cat_query, 0, $count ? $count : 1);

			if ($count)
			{
				$content = $db->loadColumn(0);
			}
			else
			{
				$db->execute();
				$content = array();
			}

			if ($totals !== null)
			{
				$db->setQuery("SELECT FOUND_ROWS()");
				$totals[$catid] = $db->loadResult();
			}

			@ $mod_fc_run_times['query_items'] += microtime(1) - $_microtime;

			// Check for no content found for given category
			if (empty($content))
			{
				$cat_items_arr[$catid] = array();
				continue;
			}

			$_microtime = microtime(1);
			// Get content list data per given category
			$per_cat_query = str_replace('__content__', implode(',',$content), $items_query_data);
			$db->setQuery($per_cat_query, 0, $count);
			$_rows = $db->loadObjectList('item_id');
			@ $mod_fc_run_times['query_items_sec'] += microtime(1) - $_microtime;

			// Secondary content list ordering and assign content list per category
			$rows = array();

			foreach ($content as $_id)
			{
				$rows[] = $_rows[$_id];
			}

			$cat_items_arr[$catid] = $rows;

			// Get Original content ids for creating some untranslatable fields that have share data (like shared folders)
			flexicontent_db::getOriginalContentItemids($cat_items_arr[$catid]);
		}


		/**
		 * Return items indexed per category id OR via empty string if not apply configuration per category
		 */
		return $cat_items_arr;
	}


	/*
	 * Find which categories will be shown, retrieve their data and return them
	 */
	public static function getCategoryData($params)
	{
		if (!$params->get('apply_config_per_category', 0)) return false;

		$db   = JFactory::getDbo();
		$app  = JFactory::getApplication();

		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');

		$currcat_custom_display = $params->get('currcat_custom_display', 0);
		$currcat_source = $params->get('currcat_source', 0);  // 0 item view, 1 category view, 2 both

		$id   = $jinput->get('id', 0, 'int');   // id of current item
		$cid  = $jinput->get(($option == 'com_content' ? 'id' : 'cid'), 0, 'int');   // current category ID or category ID of current item

		$is_content_ext   = $option == 'com_flexicontent' || $option == 'com_content';
		$isflexi_itemview = $is_content_ext && ($view == 'item' || $view == 'article') && $id;
		$isflexi_catview  = $is_content_ext && $view == 'category' && $cid;

		$currcat_valid_case =
			($currcat_source==2 && ($isflexi_itemview || $isflexi_catview))
			|| ($currcat_source==0 && $isflexi_itemview)
			|| ($currcat_source==1 && $isflexi_catview);

		if ($currcat_custom_display && $currcat_valid_case)
		{
			$catconf = new stdClass();

			$catconf->orderby = '';
			$catconf->fallback_maincat  = $params->get('currcat_fallback_maincat', 0);
			$catconf->showtitle  = $params->get('currcat_showtitle', 0);
			$catconf->showdescr  = $params->get('currcat_showdescr', 0);
			$catconf->do_cutdescr= (int) $params->get('currcat_do_cutdescr', 1);
			$catconf->cuttitle   = (int) $params->get('currcat_cuttitle', 40);
			$catconf->cutdescr   = (int) $params->get('currcat_cutdescr', 200);
			$catconf->link_title = $params->get('currcat_link_title');

			$catconf->show_image 		= $params->get('currcat_show_image');
			$catconf->image_source	= $params->get('currcat_image_source');
			$catconf->link_image		= $params->get('currcat_link_image');
			$catconf->image_width		= (int) $params->get('currcat_image_width', 80);
			$catconf->image_height	= (int) $params->get('currcat_image_height', 80);
			$catconf->image_method	= (int) $params->get('currcat_image_method', 1);
			$catconf->show_default_image = (int) $params->get('currcat_show_default_image', 0);  // parameter not added yet
			$catconf->readmore	= (int) $params->get('currcat_currcat_readmore', 1);

			if ($isflexi_itemview && $catconf->fallback_maincat && !$cid && $id)
			{
				$query = 'SELECT catid FROM #__content WHERE id = ' . $id;
				$cid = $db->setQuery($query)->loadResult();
			}
			if ($cid) $cids = array($cid);
		}

		if (empty($cids))
		{
			$catconf = new stdClass();

			// Check if using a dynamic set of categories, that was decided by getItems()
			$dynamic_cids = $params->get('dynamic_catids', false);
			$static_cids  = $params->get('catids', array());

			$cids = $dynamic_cids ? unserialize($dynamic_cids) : $static_cids;
			$cids = (!is_array($cids)) ? array($cids) : $cids;

			$catconf->orderby    = $params->get('cats_orderby', 'alpha');
			$catconf->showtitle  = $params->get('cats_showtitle', 0);
			$catconf->showdescr  = $params->get('cats_showdescr', 0);
			$catconf->do_cutdescr= (int) $params->get('cats_do_cutdescr', 1);
			$catconf->cuttitle   = (int) $params->get('cats_cuttitle', 40);
			$catconf->cutdescr   = (int) $params->get('cats_cutdescr', 200);
			$catconf->link_title = $params->get('cats_link_title');

			$catconf->show_image 		= $params->get('cats_show_image');
			$catconf->image_source	= $params->get('cats_image_source');
			$catconf->link_image		= $params->get('cats_link_image');
			$catconf->image_width 	= (int) $params->get('cats_image_width', 80);
			$catconf->image_height 	= (int) $params->get('cats_image_height', 80);
			$catconf->image_method 	= (int) $params->get('cats_image_method', 1);
			$catconf->show_default_image = (int) $params->get('cats_show_default_image', 0);  // parameter not added yet
			$catconf->readmore	= (int) $params->get('cats_readmore', 1);
		}

		if (empty($cids) || !count($cids))
		{
			return false;
		}

		// initialize variables
		$orderby = '';
		if ($catconf->orderby) $orderby = flexicontent_db::buildCatOrderBy(
			$params, $catconf->orderby, $request_var='', $config_param='',
			$cat_tbl_alias = 'c', $user_tbl_alias = 'u', $default_order = '', $default_order_dir = ''
		);
		$query = 'SELECT c.id, c.title, c.description, c.params '
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__categories AS c'
			. ' LEFT JOIN #__users AS u ON u.id = c.created_user_id'
			. ' WHERE c.id IN (' . implode(',', $cids) . ')'
			. $orderby
			;
		$catdata_arr = $db->setQuery($query)->loadObjectList('id');

		if (!$catdata_arr)
		{
			return false;
		}

		$joomla_image_path = $app->getCfg('image_path',  FLEXI_J16GE ? '' : 'images'.DS.'stories' );
		foreach ($catdata_arr as $i => $catdata)
		{
			$catdata->params = new JRegistry($catdata->params);

			// Category Title
			$catdata->title = flexicontent_html::striptagsandcut($catdata->title, $catconf->cuttitle);
			$catdata->showtitle = $catconf->showtitle;

			// Category image
			$catdata->image = FLEXI_J16GE ? $catdata->params->get('image') : $catdata->image;
			$catimage = "";

			if ($catconf->show_image)
			{
				$catdata->introtext = & $catdata->description;
				$catdata->fulltext = "";

				if ($catconf->image_source && $catdata->image && JFile::exists(JPATH_SITE .DS. $joomla_image_path .DS. $catdata->image))
				{
					$src = JUri::base(true)."/".$joomla_image_path."/".$catdata->image;

					$h		= '&amp;h=' . $catconf->image_height;
					$w		= '&amp;w=' . $catconf->image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $catconf->image_method ? '&amp;zc=' . $catconf->image_method : '';
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $zc . $f;

					$catimage = JUri::root(true) . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
				}
				elseif ($catconf->image_source!=1 && $src = flexicontent_html::extractimagesrc($catdata))
				{
					$h		= '&amp;h=' . $catconf->image_height;
					$w		= '&amp;w=' . $catconf->image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $catconf->image_method ? '&amp;zc=' . $catconf->image_method : '';
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $zc . $f;

					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
					$catimage = JUri::root(true) . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
				}

				$catdata->image = $catimage;
			}

			// Category Description
			if (!$catconf->showdescr)
			{
				unset($catdata->description);
			}
			elseif ($catconf->do_cutdescr)
			{
				$catdata->description = flexicontent_html::striptagsandcut($catdata->description, $catconf->cutdescr);
			}
			// else do not strip / cut description

			// Category Links (title and image links)
			if ($catconf->link_title || $catconf->link_image || $catconf->readmore)
			{
				$catlink = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($catdata->categoryslug));
				$catdata->titlelink = $catlink;
				$catdata->imagelink = $catlink;
			}

			$catdata->conf = $catconf;
		}

		return $catdata_arr;
	}


	/*
	 * Verify parameters, altering them if needed
	 */
	public static function verifyParams($params)
	{
		// Calculate menu itemid for item links
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$menus  = $app->getMenu();

		$itemid_force = (int) $params->get('itemid_force');
		if ($itemid_force === 1)
		{
			$Itemid					= $jinput->get('Itemid', 0, 'int');
			$menu						= $menus->getItem($Itemid);
			$component			= !empty($menu->query['option']) ? $menu->query['option'] : '';
			$forced_itemid	= $component=="com_flexicontent" ? $Itemid : 0;
		}
		elseif ($itemid_force === 2)
		{
			$itemid_force_value	= (int) $params->get('itemid_force_value', 0);
			$menu								= $menus->getItem($itemid_force_value);
			$component					= !empty($menu->query['option']) ? $menu->query['option'] : '';
			$forced_itemid			= $component=="com_flexicontent" ? $itemid_force_value : 0;
		}
		else
		{
			$forced_itemid = 0;
		}

		$params->set('forced_itemid', $forced_itemid);

		// Disable output of comments if comments component not installed
		if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php'))
		{
			$params->set('display_comments', 0);
			$params->set('display_comments_feat', 0);
		}
	}


	/*
	 * Retrieve comments for given items, item array has the structure: items[catid][ordername][]
	 */
	public static function getComments($params, &$items)
	{
		$db = JFactory::getDbo();

		$list_comments = $params->get('list_comments');
		$list_comments_feat = $params->get('list_comments_feat');
		if (!$list_comments && !$list_comments_feat) return array();

		$item_ids = array();
		foreach ($items as $catid => $cat_items)
		{
			foreach ($cat_items as $ord => $ord_items)
			{
				if ($list_comments)
				{
					foreach ($ord_items['standard'] as $item)
					{
						$item_ids[] = $item->id;
					}
				}

				if ($list_comments_feat)
				{
					foreach ($ord_items['featured'] as $item)
					{
						$item_ids[] = $item->id;
					}
				}
			}
		}

		if (empty($item_ids)) return array();

		// Get comment ids ordered
		$query = 'SELECT id FROM #__jcomments AS com '
			.' WHERE com.object_id IN (' . implode(',', $item_ids) .') AND com.object_group="com_flexicontent" AND com.published="1"'
			.' ORDER BY com.object_id, com.date DESC';
		$comment_ids = $db->setQuery($query)->loadColumn(0);

		// Get comments data
		$query = 'SELECT * FROM #__jcomments AS com '
			.' WHERE com.id IN (' . implode(',', $comment_ids) .')';
		$_comments = $db->setQuery($query)->loadObjectList('id');

		// Order comments and return them, indexing them at first level by item ID
		$comments = array();

		foreach ($comment_ids as $_id)
		{
			$comment = $_comments[$_id];
			$comments[$comment->object_id][] = $comment;
		}

		return $comments;
	}


	public static function loadBuilderLayoutAssets($module, $params, $layout_name, $css_prefix)
	{
		/**
		 * This will compile CSS from LESS, if not done already
		 */
		$location   = '/modules/mod_flexicontent/builder/';
		$css_file   = 'css/' . $layout_name . '_' . $module->id . '.css';

		JHtml::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
		JHtml::_('fclayoutbuilder.createCss',
			$module,
			$params,
			$config = (object) array(
				'location'    => $location,
				'css_prefix'  => $css_prefix,
				'layout_name' => $layout_name,
			)
		);

		/**
		 * Load CSS / JS files
		 */
		flexicontent_html::loadframework('grapesjs_view');

		JFactory::getDocument()->addStyleSheet(
			JUri::base(true) . $location . $css_file,
			array('version' => $params->get($layout_name . '_hash'))
		);
	}
}
