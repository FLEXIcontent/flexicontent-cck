<?php
	$n = 0;

	$box_classes = empty($simple_form_layout)
		? $input_grp_class . ' fc-xpended-row'
		: 'fc-floated-labels-box';
	$lbl_classes = empty($simple_form_layout)
		? $add_on_class
		: 'fc-floated-lbl';
	$input_classes = empty($simple_form_layout)
		? 'fcfield_textval'
		: 'fcfield_textval fc-floated-lbl-input';
	$ff_events = 'onfocus="jQuery(this).prev().addClass(\'fc-has-value\');" onblur="if (this.value===\'\') jQuery(this).prev().removeClass(\'fc-has-value\');"';

	foreach ($field->value as $value)
	{
		// Compatibility for non-serialized values (e.g. reload user input after form validation error) or for NULL values in a field group
		if ( !is_array($value) )
		{
			$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
			$value = $array ?: array(
				'link' => $value, 'title' => '', 'linktext' => '', 'class' => '', 'id' => '', 'hits' => 0
			);
		}
		if ( empty($value['link']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

		$fieldname_n = $fieldname.'['.$n.']';
		$elementid_n = $elementid.'_'.$n;

		// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
		$value['link'] = !empty($value['link']) ? $value['link'] : $default_link;
		$has_value_class = $value['link'] ? ' fc-has-value' : '';

		$link = '
			<div class="' . $box_classes . '">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl urllink-lbl" for="'.$elementid_n.'_link">
					<span class="icon-link" aria-hidden="true"></span>
					' . JText::_( 'FLEXI_FIELD_WEBLINK_LINK' ) . '
				</label>
				<input ' . $ff_events . ' class="urllink ' . $input_classes . ' ' . $link_classes . '" name="'.$fieldname_n.'[link]" id="'.$elementid_n.'_link" type="text" value="'.htmlspecialchars(JStringPunycode::urlToUTF8($value['link']), ENT_COMPAT, 'UTF-8').'" ' . $link_attribs . '/>
			</div>';

		$autoprefix = '';

		if ($allow_relative_addrs == 2)
		{
			$_tip_title  = flexicontent_html::getToolTip(null, 'FLEXI_FIELD_WEBLINK_IS_RELATIVE_DESC', 1, 1);
			$is_absolute = (boolean) parse_url($value['link'], PHP_URL_SCHEME); // preg_match("#^http|^https|^ftp#i", $value['link']);
			$has_value_class = ' fc-has-value';

			$autoprefix = '
			<div class="' . $box_classes . ' fc-lbl-external-box">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl-external fc-lbl '.$tooltip_class.'" title="'.$_tip_title.'">'.JText::_( 'FLEXI_FIELD_WEBLINK_IS_RELATIVE' ).'</label>
				<fieldset class="radio btn-group group-fcinfo">
					<input ' . $ff_events . ' class="autoprefix" id="'.$elementid_n.'_autoprefix_0" name="'.$fieldname_n.'[autoprefix]" type="radio" value="0" '.( !$is_absolute ? 'checked="checked"' : '' ).'/>
					<label class="' . $lbl_classes . ' btn" style="min-width: 48px;" for="'.$elementid_n.'_autoprefix_0">'.JText::_('FLEXI_YES').'</label>
					<input ' . $ff_events . ' class="autoprefix" id="'.$elementid_n.'_autoprefix_1" name="'.$fieldname_n.'[autoprefix]" type="radio" value="1" '.( $is_absolute ? 'checked="checked"' : '' ).'/>
					<label class="' . $lbl_classes . ' btn" style="min-width: 48px;" for="'.$elementid_n.'_autoprefix_1">'.JText::_('FLEXI_NO').'</label>
				</fieldset>
			</div>
			';
		}
		
		$image = '';
		$image_preview = '';

		if ($useimage)
		{
			$mm_id    = $elementid_n.'_image';
			$img_path = !empty($value['image']) ? $value['image'] : '';
			$img_src  = ($img_path && file_exists(JPATH_ROOT . '/' . $img_path))  ?  JUri::root() . $img_path  :  $img_path;
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
				'text' => '<span class="display: block;"><span class="icon-eye" aria-hidden="true"></span><span class="icon-image" aria-hidden="true"></span>' . JText::_('FLEXI_FIELD_WEBLINK_URLIMAGE') . '</span>',
				'class' => 'hasTipPreview',
			);

			$mm_link = 'index.php?option=com_media&amp;view=images&amp;layout=default_fc&amp;tmpl=component&amp;asset=com_flexicontent&amp;author=&amp;fieldid=\'+mm_id+\'&amp;folder=';
			$image_preview = '';  /*'
				<div class="media-preview ' . $add_on_class . '" style="float: left; max-width: 150px;">
					' . $previewImgEmpty . $previewImg .'
				</div>';*/
			$image = '
			<div class="' . $box_classes . '">
				<label class="media-preview ' . $lbl_classes . $has_value_class . ' fc-lbl urlimage-lbl" for="'.$elementid_n.'_image">
					'.JHtml::tooltip($tooltip, $tooltip_options).'
				</label>
				<input ' . $ff_events . ' type="text" name="'.$fieldname_n.'[image]" id="'.$elementid_n.'_image" value="'.htmlspecialchars($img_path, ENT_COMPAT, 'UTF-8').'" readonly="readonly"
					class="urlimage field-media-input hasTipImgpath" style="max-width: 90px;" onchange="fcfield_weblink.update_path_tip(this);" title="'.htmlspecialchars('<span id="TipImgpath"></span>', ENT_COMPAT, 'UTF-8').'" data-basepath=""
				/>
				<a class="fc_weblink_field_mm_modal btn '.$tooltip_class.'" title="'.JText::_('FLEXI_SELECT') . ' ' . JText::_('FLEXI_IMAGE').'" onclick="var mm_id=jQuery(this).parent().find(\'.urlimage\').attr(\'id\'); fcfield_image.currElement[\''.$field_name_js.'\']=mm_id; SqueezeBox.open(\''.$mm_link.'\', {size:{x: ((window.innerWidth-120) > 1360 ? 1360 : (window.innerWidth-120)), y: ((window.innerHeight-220) > 800 ? 800 : (window.innerHeight-220))}, handler: \'iframe\', onClose: function() {jQuery(\'#\' + mm_id).data(\'basepath\', \'' . JUri::root() .'\');} });  return false;">
					<span class="icon-upload"></span> ' /*. JText::_('FLEXI_SELECT')*/ . '
				</a>
				<a href="javascript:;" class="'. $tooltip_class .' btn btn-primary img_fetch_btn" title="'.JText::_('FLEXI_AUTO').'" onclick="fcfield_weblink.fetchData(\''.$elementid_n.'\', \''.$field_name_js.'\'); return false;">
					<i class="icon-loop"></i> ' /*. JText::_('FLEXI_AUTO')*/ . '
				</a>
				<a class="btn '.$tooltip_class.'" href="javascript:;" title="'.JText::_('FLEXI_CLEAR').'" onclick="var mm_id=jQuery(this).parent().find(\'.urlimage\').attr(\'id\'); fcfield_image.clearField(this, {}, \''.$field_name_js.'\'); jInsertFieldValue(\'\', mm_id); return false;" >
					<i class="icon-remove"></i>
				</a>
			</div>
			<div class="fcfield_message_box" id="fcfield_message_box_'.$elementid_n.'"></div>
			';
		}

		$title = '';

		if ($usetitle)
		{
			$value['title'] = !empty($value['title']) ? $value['title'] : $default_title;
			$has_value_class = $value['title'] ? ' fc-has-value' : '';

			$title = '
			<div class="' . $box_classes . '">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl urltitle-lbl" for="'.$elementid_n.'_title">'.JText::_( 'FLEXI_FIELD_WEBLINK_URLTITLE' ).'</label>
				<input ' . $ff_events . ' class="urltitle ' . $input_classes . '" name="'.$fieldname_n.'[title]" id="'.$elementid_n.'_title" type="text" size="'.$size.'" value="'.htmlspecialchars($value['title'], ENT_COMPAT, 'UTF-8').'" />
			</div>';
		}

		$linktext = '';

		if ($usetext)
		{
			$value['linktext'] = !empty($value['linktext']) ? $value['linktext'] : $default_text;
			$has_value_class = $value['linktext'] ? ' fc-has-value' : '';

			$linktext = '
			<div class="' . $box_classes . '">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl urllinktext-lbl" for="'.$elementid_n.'_linktext">'.JText::_( 'FLEXI_FIELD_WEBLINK_URLLINK_TEXT' ).'</label>
				<input ' . $ff_events . ' class="urllinktext ' . $input_classes . '" name="'.$fieldname_n.'[linktext]" id="'.$elementid_n.'_linktext" type="text" size="'.$size.'" value="'.htmlspecialchars($value['linktext'], ENT_COMPAT, 'UTF-8').'" />
			</div>';
		}

		$class = '';

		if ($useclass)
		{
			// Do not load the default from viewing configuration !, this will allow re-configuring default in viewing configuration at any time
			$value['class'] = !empty($value['class']) ? $value['class'] : '';  //$default_class;
			$has_value_class = $value['class'] ? ' fc-has-value' : '';

			if ($useclass==1)
			{
				$class = '
					<div class="' . $box_classes . '">
						<label class="' . $lbl_classes . $has_value_class . ' fc-lbl urlclass-lbl" for="'.$elementid_n.'_class">'.JText::_( 'FLEXI_FIELD_WEBLINK_URLCLASS' ).'</label>
						<input ' . $ff_events . ' class="urlclass ' . $input_classes . '" name="'.$fieldname_n.'[class]" id="'.$elementid_n.'_class" type="text" size="'.$size.'" value="'.htmlspecialchars($value['class'], ENT_COMPAT, 'UTF-8').'" />
					</div>';
			}
			else if ($useclass==2)
			{
				$class_attribs = ' class="urlclass use_select2_lib" ';
				$class = '
					<div class="' . $box_classes . ' fc-lbl-external-box">
						<label class="' . $lbl_classes . $has_value_class . ' fc-lbl-external fc-lbl urlclass-lbl" for="'.$elementid_n.'_class">'.JText::_( 'FLEXI_FIELD_WEBLINK_URLCLASS' ).'</label>
						'.JHtml::_('select.genericlist', $class_options, $fieldname_n.'[class]', $class_attribs, 'value', 'text', $value['class'], $elementid_n.'_class').'
					</div>';
			}
		}

		$id = '';

		if ($useid)
		{
			$value['id'] = !empty($value['id']) ? $value['id'] : $default_id;
			$has_value_class = $value['id'] ? ' fc-has-value' : '';

			$id = '
			<div class="' . $box_classes . '">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl urlid-lbl" for="'.$elementid_n.'_id">'.JText::_( 'FLEXI_FIELD_WEBLINK_URLID' ).'</label>
				<input ' . $ff_events . ' class="urlid ' . $input_classes . '" name="'.$fieldname_n.'[id]" id="'.$elementid_n.'_id" type="text" size="'.$size.'" value="'.htmlspecialchars($value['id'], ENT_COMPAT, 'UTF-8').'" />
			</div>';
		}

		$target = '';

		if ($usetarget)
		{
			// Do not load the default from viewing configuration !, this will allow re-configuring default in viewing configuration at any time
			$value['target'] = !empty($value['target']) ? $value['target'] : '';  //$default_target;
			$has_value_class = $value['target'] ? ' fc-has-value' : '';

			$target_attribs = ' class="urltarget use_select2_lib" ';
			$target_options = array(
				(object) array('value'=>'', 'text'=>JText::_('FLEXI_DEFAULT')),
				(object) array('value'=>'_blank', 'text'=>JText::_('FLEXI_FIELD_LINK_NEW_WIN_TAB')),
				(object) array('value'=>'_parent', 'text'=>JText::_('FLEXI_FIELD_LINK_PARENT_FRM')),
				(object) array('value'=>'_self', 'text'=>JText::_('FLEXI_FIELD_LINK_SAME_FRM_WIN_TAB')),
				(object) array('value'=>'_top', 'text'=>JText::_('FLEXI_FIELD_LINK_TOP_FRM')),
				(object) array('value'=>'_modal', 'text'=>JText::_('FLEXI_FIELD_LINK_MODAL_POPUP_WIN'))
			);
			$target = '
			<div class="' . $box_classes . ' fc-lbl-external-box">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl-external fc-lbl urltarget-lbl" for="'.$elementid_n.'_id">'.JText::_( 'FLEXI_FIELD_WEBLINK_URLTARGET' ).'</label>
				'.JHtml::_('select.genericlist', $target_options, $fieldname_n.'[target]', $target_attribs, 'value', 'text', $value['target'], $elementid_n.'_target').'
			</div>';
		}

		$hits = '';

		if ($usehits)
		{
			$value['hits'] = !empty($value['hits']) ? (int) $value['hits'] : 0;
			$has_value_class = ' fc-has-value';

			$hits = '
				<div class="' . $input_grp_class . ' fc-xpended-row">
					<label class="' . $add_on_class . ' fc-lbl urlhits-lbl" for="'.$elementid_n.'_hits">'.JText::_( 'FLEXI_FIELD_WEBLINK_POPULARITY' ).'</label>
					<input class="urlhits fc_hidden_value ' . $has_value_class . '" name="'.$fieldname_n.'[hits]" id="'.$elementid_n.'_hits" type="text" value="'.htmlspecialchars($value['hits'], ENT_COMPAT, 'UTF-8').'" />
					<span class="' . $add_on_class . ' hitcount"> ' . $value['hits'] . ' ' . JText::_('FLEXI_FIELD_HITS') . '</span>
				</div>
				';
		}

		$field->html[] = '
			'.(!$add_ctrl_btns ? '' : '
			<div class="'.$input_grp_class.' fc-xpended-btns">
				'.$move2.'
				'.$remove_button.'
				'.(!$add_position ? '' : $add_here).'
			</div>
			').'
			'.($fields_box_placing ? '<div class="fcclear"></div>' : '').'
			<div class="fc-field-props-box">
			'.$image.'
			'.$link.'
			'.$autoprefix.'
			'.$title.'
			'.$linktext.'
			'.$target.'
			'.$class.'
			'.$id.'
			'.$hits.'
			</div>
			'.$image_preview.'
			';

		$n++;
		if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
	}