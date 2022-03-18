<?php

$use_myfiles = 1;
$per_value_js = "";
$i = -1;  // Count DB values (may contain invalid entries)
$n = 0;   // Count sortable records added (the verified values or a single empty record if no good values)
$count_vals = 0;  // Count non-empty sortable records added
$image_added = false;
$skipped_vals = array();
$uploadLimitsTxt = $this->getUploadLimitsTxt($field);

// Handle file-ids as values
$v = reset($field->value);
if ((string)(int)$v == $v)
{
	$files_data = $this->getFileData( $field->value, $published=false );
}

$field->html = array();  // Make sure this is an array


/**
 * Iterate passing A REFERENCE of THE VALUE to rebuildThumbs() and other methods so that value can be modifled, and data like real image width, height can be added
 */
foreach ($field->value as $index => $value)
{
	// Compatibility for non-serialized values, e.g. Reload user input after form validation error
	// or for NULL values in a field group or file ids as values (minigallery legacy field)
	if ( !is_array($value) )
	{
		if ((string)(int)$value == $value)
		{
			if (isset($files_data[$value]))
			{
				$value = array('originalname' => $files_data[$value]->filename);
			}
			else
			{
				$value = array('originalname' => null);
			}
		}
		else
		{
			$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
			$value = $array ?: array(
				'originalname' => $value,
			);
		}
		$field->value[$index] = $value;
	}
	$i++;

	$fieldname_n = $fieldname.'['.$n.']';
	$elementid_n = $elementid.'_'.$n;

	/**
	 * Make sure 'originalname' if set, but do not set 'existingname' as we use isset later ...
	 * 'existingname' should be present only via form reloading
	 */
	$value['originalname'] = isset($value['originalname']) ? trim($value['originalname']) : '';
	$value['existingname'] = isset($value['existingname']) ? trim($value['existingname']) : '';

	$image_subpath = !empty($value['existingname'])
		? $value['existingname']
		: $value['originalname'];

	// Check if image file is a URL (e.g. we are using Media URLs to videos)
	$value['isURL'] = preg_match("#^(?:[a-z]+:)?//#", $image_subpath);


	/**
	 * Check and rebuild thumbnails if needed, existing name mean newly selected image e.g. after form reload
	 * Also check if rebuilding thumbnails failed (e.g. file has been deleted)
	 */

	$rebuild_res = !$image_subpath
		? false
		: ($value['existingname'] || $value['isURL'] ? true : plgFlexicontent_fieldsImage::rebuildThumbs($field, $value, $item));

	if (!$rebuild_res)
	{
		// For non-empty value set a message when we have examined all values
		if ($image_subpath)
		{
			$skipped_vals[] = $image_subpath;
		}

		// Skip current value but add and an empty image container if :
		// (a) no other image exists or  (b) field is in a group
		if (!$use_ingroup && ($image_added || ($i+1) < count($field->value)))
		{
			continue;
		}

		// 1st value or empty value for fieldgroup position
		$image_subpath = '';
	}

	// Increment count of images if thumbnailing was successful
	else
	{
		$count_vals++;
	}


	if ($image_source === -2 || $image_source === -1)
	{
		$fc_preview_msg = '';  // Joomla Media Manager / and Intro/Full use their own path preview
	}
	else
	{
		$fc_preview_msg = '
			<span class="fc_preview_msg" id="'.$elementid_n.'_fc_preview_msg" name="'.$elementid_n.'_fc_preview_msg" title="'.htmlspecialchars(($value['isURL'] ? $image_subpath : ''), ENT_COMPAT, 'UTF-8').'">' . (
				$value['isURL'] ? JText::_('FLEXI_FIELD_MEDIA_URL') : $image_subpath
			) . '</span>
		';
	}


	$existingname = '';
	$select_existing = '';
	$pick_existing = '';
	$addExistingURL = sprintf($filesElementURL, $elementid_n);
	$addExistingURL_onclick = "fcfield_image.dialog_handle['".$field_name_js."'] = fc_field_dialog_handle_".$field->id." = fc_showDialog(jQuery(this).attr('data-href'), 'fc_modal_popup_container', 0, 0, 0, 0, {title: '".JText::_('FLEXI_SELECT_IMAGE', true)."', paddingW: 10, paddingH: 16});";

	if ($image_source >= 0)
	{
		$existingname = '
			<input type="hidden" class="existingname fcfield_textval" id="'.$elementid_n.'_existingname" name="'.$fieldname_n.'[existingname]" value="'.htmlspecialchars(!empty($value['existingname']) ? $value['existingname'] : '', ENT_COMPAT, 'UTF-8').'" />
		';

		$select_existing = '';
	}

	elseif ($image_source === -2)
	{
		if (!$use_jformfields)
		{
			$mm_id = $elementid_n.'_existingname';
			$img_path = $image_subpath;
			$img_src  = ($img_path && file_exists(JPATH_ROOT . '/' . $img_path))  ?  JUri::root() . $img_path  :  '';
			$img_attr = array('id' => $mm_id . '_preview', 'class' => 'media-preview', 'style' => ' style="max-width:480px; max-height:360" ');
			$img = JHtml::image($img_src ?: 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', JText::_('JLIB_FORM_MEDIA_PREVIEW_ALT'), $img_attr);

			$previewImg = '
			<div id="' . $mm_id . '_preview_img"' . ($img_src ? '' : ' style="display:none"') . '>
				' . $img . '
			</div>';
			$previewImgEmpty = '
			<div id="' . $mm_id . '_preview_empty"' . ($img_src ? ' style="display:none"' : '') . '>
				' . JText::_('JLIB_FORM_MEDIA_PREVIEW_EMPTY') . '
			</div>';

			$tooltip = $previewImgEmpty . $previewImg;
			$tooltip_options = array(
				'title' => JText::_('JLIB_FORM_MEDIA_PREVIEW_SELECTED_IMAGE'),
				'text' => '<span class="icon-eye" aria-hidden="true"></span><span class="icon-image" aria-hidden="true">',
				'class' => 'hasTipPreview'
			);

			$mm_link = 'index.php?option=com_media&amp;view=images&amp;layout=default_fc&amp;tmpl=component&amp;asset=com_flexicontent&amp;author=&amp;fieldid=\'+mm_id+\'&amp;folder=';
			$select_existing = '
			<div class="'.$input_grp_class.'">
				<div class="media-preview ' . $add_on_class . ' ">
					'.JHtml::tooltip($tooltip, $tooltip_options).'
				</div>
				<input type="text" name="'.$fieldname_n.'[existingname]" id="'.$mm_id.'" value="'.htmlspecialchars($img_path, ENT_COMPAT, 'UTF-8').'" readonly="readonly"
					class="existingname input-large field-media-input hasTipImgpath" onchange="fcfield_image.update_path_tip(this);" title="'.htmlspecialchars('<span id="TipImgpath"></span>', ENT_COMPAT, 'UTF-8').'" data-basepath="'.JUri::root().'"
				/>
				<a class="fc_image_field_mm_modal btn '.$tooltip_class.'" title="'.JText::_('FLEXI_SELECT_IMAGE').'" onclick="var mm_id=jQuery(this).parent().find(\'.existingname\').attr(\'id\'); fcfield_image.currElement[\''.$field_name_js.'\']=mm_id; SqueezeBox.open(\''.$mm_link.'\', {size:{x: ((window.innerWidth-120) > 1360 ? 1360 : (window.innerWidth-120)), y: ((window.innerHeight-220) > 800 ? 800 : (window.innerHeight-220))}, handler: \'iframe\', onClose: function() { fcfield_image.incrementValCnt(\''.$field_name_js.'\'); } });  return false;">
					'.JText::_('FLEXI_SELECT').'
				</a>
				<a class="btn '.$tooltip_class.'" href="javascript:;" title="'.JText::_('FLEXI_CLEAR').'" onclick="var mm_id=jQuery(this).parent().find(\'.existingname\').attr(\'id\'); fcfield_image.clearField(this, {}, \''.$field_name_js.'\'); jInsertFieldValue(\'\', mm_id); return false;" >
					<i class="icon-remove"></i>
				</a>
			</div>
			';
		}
		else
		{
			$jfvalue = str_replace('\\', '/', !empty($value['originalname'])  ?  $value['originalname']  :  '');

			$quantum_fieldupload_path = JPATH_ROOT . '/libraries/lib_fields/fields/quantumuploadimage/quantumuploadimage.php';
			$use_quantum = file_exists($quantum_fieldupload_path) && JComponentHelper::isEnabled('com_quantummanager');

			if ($use_quantum)
			{
				JLoader::register('JFormFieldQuantumuploadImage', $quantum_fieldupload_path);
				$xml_field = '<field name="'.$fieldname_n.'[existingname]" id="'.$elementid_n.'_existingname" type="QuantumUploadImage" dropAreaHidden="false" '
					// . 'preview_width="'.(int)$thumb_size_default.'" preview_height="'.(int)$thumb_size_default.'" '
					. ' class="existingname" />';
			}
			else
			{
				$xml_field = '<field name="'.$fieldname_n.'[existingname]" id="'.$elementid_n.'_existingname" type="media" preview="true" '
					. ' preview_width="'.(int)$thumb_size_default.'" preview_height="'.(int)$thumb_size_default.'" '
					. ' class="existingname" />';
			}

			$xml_form = '<form><fields name="attribs"><fieldset name="attribs">'.$xml_field.'</fieldset></fields></form>';
			$jform = new JForm('flexicontent_field.image', array('control' => '' /*'custom'*/, 'load_data' => true));
			$jform->load($xml_form);

			if ($use_quantum)
			{
				$jfield = new JFormFieldQuantumUploadImage($jform);
			}
			else
			{
				$jfield = new JFormFieldMedia($jform);
			}
			
			$jfield->setup(new SimpleXMLElement($xml_field), $jfvalue, '');
			$select_existing = $jfield->input;
		}
	}

	/**
	 * Calculate image preview link
	 */

	// Joomla Media Manager / and Intro/Full use a popup preview
	if ($image_source === -2 || $image_source === -1)
	{
		$img_link = false;
		//$img_link  = JUri::root(true).'/'.$image_subpath;
	}

	// $image_source >= 0, if 'existingname' is set then it is propably a form reload
	elseif ($value['isURL'])
	{
		$img_link = $image_subpath;
	}
	elseif ($image_subpath)
	{
		list($_file_path, $_src_path, $_dest_path, $_field_index, $_extra_prefix) = $this->getThumbPaths($field, $item, $value);

		$rel_url_base = str_replace(JPATH_SITE, '', $_src_path);
		$rel_url_base = ltrim(str_replace('\\', '/', $rel_url_base), '/');
		$abs_url_base = JUri::root(true) . '/' . $rel_url_base;

		$img_link = rawurlencode($abs_url_base . $image_subpath);

		if (isset($value['existingname']))
		{
			$ext = strtolower(flexicontent_upload::getExt($image_subpath));
			$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&f='.$ext : '';
			$img_link = str_replace('\\', '/', $img_link);

			/*$img_link = JUri::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' .
				htmlspecialchars($img_link . '&w='.$preview_thumb_w . '&h=' . $preview_thumb_h . '&zc=1&q=95&ar=x' . $f);*/

			$img_link = htmlspecialchars(phpThumbURL(
				'src=' . $img_link . '&w=' . $preview_thumb_w . '&h=' . $preview_thumb_h . '&zc='.($thumb_method ? 1: 0).'&q=95&ar=x' . $f,
				JUri::root(true) . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php'
			));
		}
	}

	else
	{
		$img_link = '';
	}


	/**
	 * Create the image preview using the image preview link
	 */

	if ($img_link)
	{
		$imgpreview = '<img class="fc_preview_thumb" id="'.$elementid_n.'_preview_image" src="'.$img_link.'" alt="Preview image" />';
	}
	elseif ($img_link !== false)
	{
		$imgpreview = '<img class="fc_preview_thumb" id="'.$elementid_n.'_preview_image" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="Preview image" />';
	}
	else
	{
		$imgpreview = '';
	}

	// originalname form field
	if ( $image_subpath )
	{
		$originalname = '<input name="'.$fieldname_n.'[originalname]" id="'.$elementid_n.'_originalname" type="hidden" class="originalname" value="'.htmlspecialchars($value['originalname'], ENT_COMPAT, 'UTF-8').'" />';
		$originalname .= '<input name="'.$elementid_n.'_hasvalue" id="'.$elementid_n.'" type="text" class="fc_hidden_value hasvalue '.($use_ingroup ? $required_class : '').'" value="1" />';
	} else {
		$originalname = '<input name="'.$fieldname_n.'[originalname]" id="'.$elementid_n.'_originalname" type="hidden" class="originalname" value="" />';
		$originalname .= '<input name="'.$elementid_n.'_hasvalue" id="'.$elementid_n.'" type="text" class="fc_hidden_value hasvalue '.($use_ingroup ? $required_class : '').'" value="" />';
	}


	if ($linkto_url) $urllink =
		'<tr>
			<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_LINKTO_URL' ).'</label></td-->
			<td><input class="imgurllink" size="40" name="'.$fieldname_n.'[urllink]" value="'.htmlspecialchars(isset($value['urllink']) ? $value['urllink'] : '', ENT_COMPAT, 'UTF-8').'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_LINKTO_URL' ), ENT_COMPAT, 'UTF-8').'"/></td>
		</tr>';
	if ($usemediaurl)
	{
		$placeholder = htmlspecialchars(($usemediaurl === 1
			? 'Youtube / Vimeo URL'
			: JText::_('FLEXI_FIELD_MEDIA_URL')
		), ENT_COMPAT, 'UTF-8');
		$mediaurl =
		'<tr>
			<td>
				<div class="fcfield-image-mediaurl-box" ' . (empty($value['mediaurl']) ? ' style="display: none;" ' : '') . '>
					<input class="img_mediaurl" size="40" name="'.$fieldname_n.'[mediaurl]" id="'.$elementid_n.'_mediaurl" value="'.htmlspecialchars(isset($value['mediaurl']) ? $value['mediaurl'] : $default_mediaurl, ENT_COMPAT, 'UTF-8').'" type="text" placeholder="'. $placeholder .'"/>
					<br>
					<div class="' . $input_grp_class . ' fcfield-image-mediaurl-btns">
						<a href="javascript:;" class="'. $tooltip_class .' btn btn-primary btn-small img_fetch_btn" title="'.JText::_('FLEXI_FETCH').'" onclick="fcfield_image.fetchData(\''.$elementid_n.'\', \''.$field_name_js.'\'); return false;">
							<i class="icon-loop"></i> ' . JText::_('FLEXI_FETCH') . '
						</a>
						<a href="javascript:;" class="'. $tooltip_class .' btn btn-warning btn-small img_clear_btn" id="'.$elementid_n.'_clear_btn" title="'.JText::_('FLEXI_CLEAR').'" onclick="fcfield_image.clearData(\''.$elementid_n.'\', \''.$field_name_js.'\'); return false;" >
							<i class="icon-cancel"></i> ' . JText::_('FLEXI_CLEAR') . '
						</a>
					</div>
					<div class="fcfield_message_box" id="fcfield_message_box_'.$elementid_n.'"></div>
				</div>
			</td>
		</tr>
		';
	}
	if ($usealt) $alt =
		'<tr>
			<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_ALT' ).'</label></td-->
			<td><input class="imgalt" size="40" name="'.$fieldname_n.'[alt]" value="'.htmlspecialchars(isset($value['alt']) ? $value['alt'] : $default_alt, ENT_COMPAT, 'UTF-8').'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_ALT' ), ENT_COMPAT, 'UTF-8').'"/></td>
		</tr>';
	if ($usetitle) $title =
		'<tr>
			<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_TITLE' ).' <br/>('.JText::_('FLEXI_FIELD_TOOLTIP').')</label></td-->
			<td><input class="imgtitle" size="40" name="'.$fieldname_n.'[title]" value="'.htmlspecialchars(isset($value['title']) ? $value['title'] : $default_title, ENT_COMPAT, 'UTF-8').'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_TITLE' ), ENT_COMPAT, 'UTF-8').'"/></td>
		</tr>';
	if ($usedesc) $desc =
		'<tr>
			<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_DESC' ).' <br/>('.JText::_('FLEXI_FIELD_TOOLTIP').')</label></td-->
			<td><textarea class="imgdesc" name="'.$fieldname_n.'[desc]" rows="3" cols="24" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_DESC' ), ENT_COMPAT, 'UTF-8').'">'.(isset($value['desc']) ? $value['desc'] : $default_desc).'</textarea></td>
		</tr>';
	if ($usecust1) $cust1 =
		'<tr>
			<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_IMG_CUST1' ).'</label></td-->
			<td><input class="imgcust1" size="40" name="'.$fieldname_n.'[cust1]" value="'.htmlspecialchars(isset($value['cust1']) ? $value['cust1'] : $default_cust1, ENT_COMPAT, 'UTF-8').'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_IMG_CUST1' ), ENT_COMPAT, 'UTF-8').'"/></td>
		</tr>';
	if ($usecust2) $cust2 =
		'<tr>
			<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_IMG_CUST2' ).'</label></td-->
			<td><input class="imgcust2" size="40" name="'.$fieldname_n.'[cust2]" value="'.htmlspecialchars(isset($value['cust2']) ? $value['cust2'] : $default_cust2, ENT_COMPAT, 'UTF-8').'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_IMG_CUST2' ), ENT_COMPAT, 'UTF-8').'"/></td>
		</tr>';

	// DB-mode needs a 'pick_existing_n'
	if ($image_source === 0)
	{
		$pick_existing_n = $pick_existing ? str_replace('__FORMFLDNAME__', $fieldname_n.'[existingname]', $pick_existing) : '';
		$pick_existing_n = $pick_existing ? str_replace('__FORMFLDID__', $elementid_n.'_existingname', $pick_existing_n) : '';
	}

	$toggleUploader_onclick = 'var box = jQuery(this).closest(\'.fcfieldval_container\'); ' .
		'var isVisible = box.find(\'.fc_file_uploader\').is(\':visible\'); ' .
		'isVisible ? jQuery(this).removeClass(\'active\') : jQuery(this).addClass(\'active\'); ' .
		'isVisible ? box.find(\'.fcfield_preview_box\').show() : box.find(\'.fcfield_preview_box\').hide(); ' .
		'';

	if ($use_inline_uploaders)
	{
		$uploader_html = $uploader_html_arr[$n] = JHtml::_('fcuploader.getUploader', $field, $u_item_id, null, $n,
			array(
			'container_class' => (1 || $multiple ? 'fc_inline_uploader fc_uploader_thumbs_view fc-box' : '') . ' fc_compact_uploader fc_auto_uploader thumb_'.$thumb_size_default,
			'upload_maxcount' => 1,
			'autostart_on_select' => true,
			'refresh_on_complete' => false,
			'thumb_size_default' => $thumb_size_default,
			'toggle_btn' => array(
				'class' => ($file_btns_position ? $add_on_class : '') . ' fcfield-uploadvalue dropdown-item' . $font_icon_class,
				'text' => (!$file_btns_position ? '&nbsp; ' . JText::_('FLEXI_UPLOAD') : ''),
				'onclick' => $toggleUploader_onclick,
				'action' => null
			),
			'thumb_size_slider_cfg' => ($thumb_size_resizer ? $thumb_size_slider_cfg : 0),
			'resize_cfg' => ($thumb_size_resizer ? $resize_cfg : 0),
			'handle_FileFiltered' => 'fcfield_FileFiltered_'.$field->id,
			'handle_FileUploaded' => 'fcfield_FileUploaded_'.$field->id
			)
		);

		$multi_icon = $form_font_icons ? ' <span class="icon-stack"></span>' : '<span class="pages_stack"></span>';
		$btn_classes = 'fc-files-modal-link ' . ($file_btns_position ? $add_on_class : '') . ' ' . $font_icon_class;
		$uploader_html->multiUploadBtn = '';  /*'
			<span data-href="'.$addExistingURL.'" onclick="'.$addExistingURL_onclick.'" class="'.$btn_classes.' fc-up fcfield-uploadvalue multi dropdown-item" id="'.$elementid_n.'_mul_uploadvalue">
				&nbsp; ' . $multi_icon . ' ' . (!$file_btns_position || $file_btns_position==2 ? JText::_('FLEXI_UPLOAD') : '') . '
			</span>';*/
		$uploader_html->myFilesBtn = '
			<span data-href="'.$addExistingURL.'" onclick="'.$addExistingURL_onclick.'" class="'.$btn_classes.' fc-sel fcfield-selectvalue multi dropdown-item" data-rowno="'.$n.'" id="'.$elementid_n.'_selectvalue">
				' .  ($file_btns_position ? $multi_icon : '') . ' ' . (!$file_btns_position || $file_btns_position==2 ? '&nbsp; ' . JText::_('FLEXI_MY_FILES') : '') . ' ' . (!$file_btns_position ? $multi_icon : '') .'
			</span>';
		$uploader_html->mediaUrlBtn = !$usemediaurl ? '' : '
			<span class="' . ($file_btns_position ? $add_on_class : '') . ' fcfield-medialurlvalue ' . $font_icon_class . ' dropdown-item" onclick="fcfield_image.toggleMediaURL(\''.$elementid_n.'\', \''.$field_name_js.'\'); return false;">
				' . (!$file_btns_position || $file_btns_position==2 ? '&nbsp; ' . JText::_('FLEXI_FIELD_MEDIA_URL') : '') . '
			</span>';
		$uploader_html->clearBtn = '
			<span class="' . $add_on_class . ' fcfield-clearvalue ' . $font_icon_class . '" title="'.JText::_('FLEXI_CLEAR').'" onclick="fcfield_image.clearField(this, {}, \''.$field_name_js.'\');">
			</span>';
	}

	$drop_btn_class =
		(FLEXI_J40GE
			? 'btn btn-sm toolbar dropdown-toggle dropdown-toggle-split'
			: 'btn btn-small toolbar dropdown-toggle'
		);

	$field->html[] = '
		'.($multiple && !$none_props ? '<div class="fcclear"></div>' : '').'
		'.(!$add_ctrl_btns ? '' : '
		<div class="'.$input_grp_class.' fc-xpended-btns">
			'.$move2.'
			'.$remove_button.'
			'.(!$add_position ? '' : $add_here)
			.($use_inline_uploaders && !$file_btns_position ?'
			<div class="buttons btn-group fc-iblock ' . (FLEXI_J40GE ? ' dropdown ' : '') . '">
				<span role="button" class="' . $drop_btn_class . ' fcfield-addvalue ' . $font_icon_class . '" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span class="caret"></span>
				</span>
				<ul class="dropdown-menu dropdown-menu-right" role="menu">
					<li>'.$uploader_html->toggleBtn.'</li>
					<li>'.$uploader_html->multiUploadBtn.'</li>
					' . ($use_myfiles > 0 ? '<li>'.$uploader_html->myFilesBtn.'</li>' : '') . '
					<li>'.$uploader_html->mediaUrlBtn.'</li>
				</ul>
			</div>
			'.$uploader_html->clearBtn.'
			' : '') . '
		</div>
		'.($fields_box_placing ? '<div class="fcclear"></div>' : '').'
		').'

		<div class="fc-field-props-box" ' . (!$multiple ? 'style="width: 80%; max-width: 1000px; position: relative;"' : ''). '>

			'.($use_inline_uploaders && ($file_btns_position || !$add_ctrl_btns) ? '
			<div class="fcclear"></div>
			<div class="btn-group" style="margin: 4px 0 16px 0; display: inline-block;">
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$uploader_html->toggleBtn.'
					'.$uploader_html->multiUploadBtn.'
					' . ($use_myfiles > 0 ? $uploader_html->myFilesBtn : '') . '
					'.$uploader_html->mediaUrlBtn.'
					'.$uploader_html->clearBtn.'
				</div>
			</div>
			' : '') . '
			'.$fc_preview_msg.'
			'.$originalname.'
			'.$existingname.'

			<div class="fc-field-value-properties-box">

				'.($image_source === -2 || $image_source === -1  ?  // Do not add image preview box if using Joomla Media Manager (or intro/full mode)
					$select_existing.'
					<div class="fcclear"></div>
				' : '
					'.(empty($uploader_html) ? '' : '
						<div style="display: inline-block; vertical-align: top;">
							' . $uploader_html->container . '
						</div>
					').'
					<div class="fcfield_preview_box fc-box thumb_'.$thumb_size_default.'">
						'.$imgpreview.'
						<div class="fcclear"></div>
					'.$select_existing.'
					</div>
				').'
				'

				.(($linkto_url || $usemediaurl || $usealt || $usetitle || $usedesc || $usecust1 || $usecust2) ?
				'
				<div class="fcimg_value_props">
					<table class="fc-form-tbl fcinner fccompact">
						' . @ $urllink . '
						' . @ $mediaurl . '
						' . @ $alt . '
						' . @ $title . '
						' . @ $desc . '
						' . @ $cust1 . '
						' . @ $cust2 . '
					</table>
				</div>'
				: '') .'

			</div><!-- EOF class="fc-field-value-properties-box" -->
		</div><!-- EOF class="fc-field-props-box" -->
		';

	if (!$image_subpath)
	{
		$per_value_js .= "
			fcfield_image.showUploader('" . $field->name . '_' . $n . "', '".$field_name_js."');
		";
	}

	$n++;
	$image_added = true;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}


//document.addEventListener('DOMContentLoaded', function()
$js = ""
. (!$per_value_js ? "" : "
	jQuery(document).ready(function()
	{
		" . $per_value_js . "
	});
");

if ($js) $document->addScriptDeclaration($js);

