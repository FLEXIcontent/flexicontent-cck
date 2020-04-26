<?php
$FT = 'FILE';
$PRV_TYPE='-2';
$image_placeholder = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

$n = 0;
$per_value_js = "";

foreach ($field->value as $file_id)
{
	$fieldname_n = $fieldname.'['.$n.']';
	$elementid_n = $elementid.'_'.$n;
	$FN_n        = $field_name_js.'_'.$n;

	$file_data         = $files_data[$file_id];
	$filename_original = $file_data->filename_original ? $file_data->filename_original : $file_data->filename;

	$ext = strtolower(flexicontent_upload::getExt($file_data->filename));
	$file_data->waveform_preview = !$file_data->filename ? '' : 'audio_preview/' . str_ireplace('.'.$ext, '', basename($file_data->filename)) . '.mp3';
	$file_data->waveform_peaks   = !$file_data->filename ? '' : 'audio_preview/' . str_ireplace('.'.$ext, '', basename($file_data->filename)) . '.json';

	$preview_css = 'width:100px; height:100px;';

	if (!in_array(strtolower($file_data->ext), $imageexts))
	{
		$preview_src = $image_placeholder;

		// form_file_preview (2): Automatic, so if not an image hide the preview elements
		if ($form_file_preview === 2 && $fields_box_placing)
		{
			$preview_css .= 'display:none;';
		}
	}
	else
	{
		$img_path = (substr($file_data->filename, 0,7)!='http://' || substr($file_data->filename, 0,8)!='https://')
			? JUri::root(true) . '/' . (empty($file_data->secure) ? $mediapath : $docspath) . '/' . $file_data->filename
			: $file_data->filename;
		$preview_src = JUri::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&amp;w=100&amp;h=100&amp;zc=1&amp;q=95&amp;ar=x';
	}

	$info_txt_classes = $file_data->published ? '' : 'file_unpublished '.$tooltip_class;
	$info_txt_tooltip = $file_data->published ? '' : 'title="'.flexicontent_html::getToolTip('FLEXI_FILE_FIELD_FILE_UNPUBLISHED', 'FLEXI_FILE_FIELD_FILE_UNPUBLISHED_DESC', 1, 1).'"';

	$_select_file_lbl = '
			<label class="fc-prop-lbl inlinefile-data-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE', 'FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE_DESC', 1, 1).'" id="'.$elementid_n.'_file-data-lbl" for="'.$elementid_n.'_file-data-txt">
				' . JText::_('FLEXI_FIELD_'.$FT.'_SELECT_FILE') . '
			</label>
	';

	// Get from session for new values
	$default_secure_val = isset($form_data[$file_id])  ?  (int)$form_data[$file_id]['secure']  :  1;

	// Get from session for new values
	$default_stamp_val = isset($form_data[$file_id])  ?  (int)$form_data[$file_id]['stamp']  :  1;

	$addExistingURL_onclick = "fc_openFileSelection_".$field->id."(this);";

	$cols2_exist = $iform_title || $iform_lang || $iform_access || $iform_desc || $iform_dir;

	if ($use_inline_uploaders)
	{
		$uploader_html = $uploader_html_arr[$n] = JHtml::_('fcuploader.getUploader', $field, $u_item_id, null, $n,
			array(
			'container_class' => 'fc_inline_uploader fc_uploader_thumbs_view fc_compact_uploader fc_auto_uploader fc-box thumb_'.$thumb_size_default,
			'upload_maxcount' => 1,
			'autostart_on_select' => true,
			'refresh_on_complete' => false,
			'thumb_size_default' => $thumb_size_default,
			'toggle_btn' => array(
				'class' => ($file_btns_position ? $add_on_class : '') . ' fcfield-uploadvalue' . $font_icon_class,
				'text' => (!$file_btns_position ? '&nbsp; ' . JText::_('FLEXI_UPLOAD') : ''),
				'onclick' => 'var box = jQuery(this).closest(\'.fcfieldval_container\').find(\'.fcimg_preview_box\'); box.parent().find(\'.fc_file_uploader\').is(\':visible\') ? box.show() : box.hide(); box.is(\':visible\') ? jQuery(this).removeClass(\'active\') : jQuery(this).addClass(\'active\'); ',
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
			<span data-href="'.$addExistingURL.'" onclick="'.$addExistingURL_onclick.'" class="'.$btn_classes.' fc-up fcfield-uploadvalue multi" id="'.$elementid_n.'_mul_uploadvalue">
				&nbsp; ' . $multi_icon . ' ' . (!$file_btns_position || $file_btns_position==2 ? JText::_('FLEXI_UPLOAD') : '') . '
			</span>';*/
		$uploader_html->myFilesBtn = '
			<span data-href="'.$addExistingURL.'" onclick="'.$addExistingURL_onclick.'" class="'.$btn_classes.' fc-sel fcfield-selectvalue multi" data-rowno="'.$n.'" id="'.$elementid_n.'_selectvalue">
				' .  ($file_btns_position ? $multi_icon : '') . ' ' . (!$file_btns_position || $file_btns_position==2 ? '&nbsp; ' . JText::_('FLEXI_MY_FILES') : '') . ' ' . (!$file_btns_position ? $multi_icon : '') .'
			</span>';
		$uploader_html->mediaUrlBtn = !$usemediaurl ? '' : '
			<span class="' . ($file_btns_position ? $add_on_class : '') . ' fcfield-medialurlvalue ' . $font_icon_class . '" onclick="fcfield_mediafile.toggleMediaURL(\''.$elementid_n.'\', \''.$field_name_js.'\'); return false;">
				' . (!$file_btns_position || $file_btns_position==2 ? '&nbsp; ' . JText::_('FLEXI_FIELD_MEDIA_URL') : '') . '
			</span>';
		$uploader_html->clearBtn = '
			<span class="' . $add_on_class . ' fcfield-clearvalue ' . $font_icon_class . '" title="'.JText::_('FLEXI_CLEAR').'" onclick="fcfield_mediafile.clearField(this, {}, \''.$field_name_js.'\');">
			</span>';
	}

	$drop_btn_class =
		(FLEXI_J40GE
			? 'btn btn-sm toolbar dropdown-toggle dropdown-toggle-split'
			: 'btn btn-small toolbar dropdown-toggle'
		);

	$field->html[] = '
		<div class="inlinefile-data-box">

			<div style="display:inline-block;">
				<span class="fc_filedata_storage_name" style="display:none;">'.$file_data->filename.'</span>
				<div class="fc_filedata_txt_nowrap nowrap_hidden">'.$file_data->filename.'<br/>'.$file_data->altname.'</div>
				<input class="fc_filedata_txt inlinefile-data-txt '. $info_txt_classes . $required_class .'"
					readonly="readonly" name="'.$fieldname_n.'[file-data-txt]" id="'.$elementid_n.'_file-data-txt" '.$info_txt_tooltip.'
					value="'.htmlspecialchars($filename_original, ENT_COMPAT, 'UTF-8').'"
					data-filename="'.htmlspecialchars($file_data->filename, ENT_COMPAT, 'UTF-8').'"
					data-wfpreview="'.htmlspecialchars($file_data->waveform_preview, ENT_COMPAT, 'UTF-8').'"
					data-wfpeaks="'.htmlspecialchars($file_data->waveform_peaks, ENT_COMPAT, 'UTF-8').'"
				/>
				'.(!$iform_title ? '
				<div class="'.$input_grp_class.'">
					<label class="' . $add_on_class . ' fc-lbl fc_filedata_title-lbl">'.JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ).'</label>
					<span class="' . $add_on_class . ' fc_filedata_title">'. ($file_data->altname && $filename_original!=$file_data->altname ? $file_data->altname : '-').'</span>
				</div>' : '').'
				<br/>
				'.($form_file_preview ? '<div class="fc-iblock fcimg_preview_box" style="'.$preview_css.'"><img id="'.$elementid_n.'_img_preview" src="'.$preview_src.'" class="fc_preview_thumb" alt="Preview image placeholder"/></div>' : '').'
				'.(!empty($uploader_html) ? $uploader_html->container : '').'
			</div>

		</div>

<table class="fc-form-tbl fcinner inlinefile-tbl">

	<tr class="inlinefile-data-row">
		<td class="inlinefile-remove-cell" colspan="2">
			'.( (!$multiple || $is_ingroup) && !$required_class ? '
			<br/>
			<fieldset class="group-fcset">
				<input type="checkbox" id="'.$elementid_n.'_file-del" class="inlinefile-del" name="'.$fieldname_n.'[file-del]" value="1" onchange="file_fcfield_del_existing_value'.$field->id.'(this);" />
				<label class="fc-prop-lbl inlinefile-del-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_'.$FT.'_ABOUT_REMOVE_FILE', 'FLEXI_FIELD_'.$FT.'_ABOUT_REMOVE_FILE_DESC', 1, 1).'" id="'.$elementid_n.'_file-del-lbl" for="'.$elementid_n.'_file-del" >
					'.JText::_( 'Remove file' ).'
				</label>
			</fieldset>
			' : ( (!$multiple || $is_ingroup) && $required_class && $file_data->filename ? '<div class="alert alert-info fc-small fc-iblock">'.JText::_('FLEXI_FIELD_'.$FT.'_REQUIRED_UPLOAD_NEW_TO_REPLACE').'</div>' : '')).'
		</td>

	</tr>
	<tr class="inlinefile-data-row">

		'.($fields_box_placing==1 ? '' : '
		<td class="key inlinefile-data-lbl-cell">
			'.$_select_file_lbl.'
		</td>').'
		<td class="inlinefile-data-cell" '.($fields_box_placing==1 && $cols2_exist ? 'colspan="2"' : '').'>
			<div class="inlinefile-data">
				'.($fields_box_placing==1 ? '<span style="visibility:hidden; z-index:-1; position:absolute;">'.$_select_file_lbl.'</span>' : '').'
				<input type="hidden" id="'.$elementid_n.'_file-id" name="'.$fieldname_n.'[file-id]" value="'.htmlspecialchars($file_id, ENT_COMPAT, 'UTF-8').'" class="fc_fileid" />'.'
				<span class="'./*$input_grp_class.*/'">
					'.(! $field->parameters->get('use_myfiles', '1') ? '
					<span class="btn btn-info fc_fileupload_box">
						<span>'.JText::_('FLEXI_FIELD_'.$FT.'_UPLOAD_NEW').'</span>
						<input type="file" id="'.$elementid_n.'_file-data" name="'.$fieldname_n.'[file-data]" class="fc_filedata" data-rowno="'.$n.'" onchange="var file_box = jQuery(this).parent().parent().parent(); fc_loadImagePreview(this.id,\''.$elementid.'_\'+jQuery(this).attr(\'data-rowno\')+\'_img_preview\', \''.$elementid.'_\'+jQuery(this).attr(\'data-rowno\')+\'_file-data-txt\', 100, 0, \''.$PRV_TYPE.'\'); file_box.find(\'.inlinefile-secure-data\').show(400);  file_box.find(\'.inlinefile-secure-info\').hide(400); file_box.find(\'.inlinefile-del\').removeAttr(\'checked\').trigger(\'change\'); " />
					</span>
					' : '
					<span class="btn btn-info addfile hasTooltip" id="'.$elementid_n.'_addfile" title="'.$_prompt_txt.'" data-href="'.$addExistingURL.'" onclick="'.$addExistingURL_onclick.'" data-rowno="'.$n.'">
						'.JText::_('FLEXI_FIELD_'.$FT.'_MY_FILES').'
					</span>
					').'
				</span>
			</div>

		</td>
	</tr>'.

	( $iform_title ? '
	<tr class="inlinefile-title-row">
		<td class="key inlinefile-title-lbl-cell">
			<label class="fc-prop-lbl inlinefile-title-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1).'" id="'.$elementid_n.'_file-title-lbl" for="'.$elementid_n.'_file-title">
				'.JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ).'
			</label>
		</td>
		<td class="inlinefile-title-data-cell">
			<span class="inlinefile-title-data">
				<input type="text" id="'.$elementid_n.'_file-title" size="44" name="'.$fieldname_n.'[file-title]" value="'.htmlspecialchars(!isset($form_data[$file_id]) ? $file_data->altname : $form_data[$file_id]['file-title'], ENT_COMPAT, 'UTF-8').'" class="fc_filetitle '.$required_class.'" />
			</span>
		</td>
	</tr>' : '').

	( $iform_lang ? '
	<tr class="inlinefile-lang-row">
		<td class="key inlinefile-lang-lbl-cell">
			<label class="fc-prop-lbl inlinefile-lang-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1).'" id="'.$elementid_n.'_file-lang-lbl" for="'.$elementid_n.'_file-lang">
				'.JText::_( 'FLEXI_LANGUAGE' ).'
			</label>
		</td>
		<td class="inlinefile-lang-data-cell">
			<span class="inlinefile-lang-data">
				'.flexicontent_html::buildlanguageslist($fieldname_n.'[file-lang]', 'class="fc_filelang use_select2_lib"', (!isset($form_data[$file_id]) ? $file_data->language : $form_data[$file_id]['file-lang']), 1).'
			</span>
		</td>
	</tr>' : '').

	( $iform_access ? '
	<tr class="inlinefile-access-row">
		<td class="key inlinefile-access-lbl-cell">
			<label class="fc-prop-lbl inlinefile-access-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1).'" id="'.$elementid_n.'_file-access-lbl" for="'.$elementid_n.'_file-access">
				'.JText::_( 'FLEXI_ACCESS' ).'
			</label>
		</td>
		<td class="inlinefile-access-data-cell">
			<span class="inlinefile-access-data">
				'.JHtml::_('access.assetgrouplist', $fieldname_n.'[file-access]', (!isset($form_data[$file_id]) ? $file_data->access : $form_data[$file_id]['file-access']), $attribs=' class="fc_fileaccess use_select2_lib" ', $config=array(/*'title' => JText::_('FLEXI_SELECT'), */'id' => $elementid_n.'_file-access')).'
			</span>
		</td>
	</tr>' : '').

	( $iform_desc ? '
	<tr class="inlinefile-desc-row">
		<td class="key inlinefile-desc-lbl-cell">
			<label class="fc-prop-lbl inlinefile-desc-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1).'" id="'.$elementid_n.'_file-desc-lbl" for="'.$elementid_n.'_file-desc">
				'.JText::_( 'FLEXI_DESCRIPTION' ).'
			</label>
		</td>
		<td class="inlinefile-desc-data-cell">
			<span class="inlinefile-desc-data">
				<textarea id="'.$elementid_n.'_file-desc" cols="24" rows="3" name="'.$fieldname_n.'[file-desc]" class="fc_filedesc">'.(!isset($form_data[$file_id]) ? $file_data->description : $form_data[$file_id]['file-desc']).'</textarea>
			</span>
		</td>
	</tr>' : '').

	( $iform_dir ? '
	<tr class="inlinefile-secure-row">
		<td class="key inlinefile-secure-lbl-cell">
			<label class="fc-prop-lbl inlinefile-secure-lbl '.$tooltip_class.'" data-placement="top" title="'.flexicontent_html::getToolTip('FLEXI_URL_SECURE', 'FLEXI_URL_SECURE_DESC', 1, 1).'" id="'.$elementid_n.'_secure-lbl">
				'.JText::_( 'FLEXI_URL_SECURE' ).'
			</label>
		</td>
		<td class="inlinefile-secure-data-cell">
			'.($has_values ? '
			<span class="inlinefile-secure-info" style="'.(!$has_values ? 'display:none;' : '').'">
				<span class="badge badge-info">'.JText::_($file_data->secure ?  'FLEXI_YES' : 'FLEXI_NO').'</span>
			</span>' : '').'
			<span class="inlinefile-secure-data" style="'.($has_values ? 'display:none;' : '').'">
				'.flexicontent_html::buildradiochecklist( array(1=> JText::_( 'FLEXI_YES' ), 0=> JText::_( 'FLEXI_NO' )) , $fieldname_n.'[secure]', (!isset($form_data[$file_id]) ? $file_data->secure : (int)$form_data[$file_id]['secure']), 1, ' class="fc_filedir" ', $elementid_n.'_secure').'
			</span>
		</td>
	</tr>' : '').

	( $iform_stamp ? '
	<tr class="inlinefile-stamp-row">
		<td class="key inlinefile-stamp-lbl-cell">
			<label class="fc-prop-lbl inlinefile-stamp-lbl '.$tooltip_class.'" data-placement="top" title="'.flexicontent_html::getToolTip('FLEXI_DOWNLOAD_STAMPING', 'FLEXI_FILE_DOWNLOAD_STAMPING_DESC', 1, 1).'" id="'.$elementid_n.'_stamp-lbl">
				'.JText::_( 'FLEXI_DOWNLOAD_STAMPING' ).'
			</label>
		</td>
		<td class="inlinefile-stamp-data-cell">
			<span class="inlinefile-stamp-data">
				'.flexicontent_html::buildradiochecklist( array(1=> JText::_( 'FLEXI_YES' ), 0=> JText::_( 'FLEXI_NO' )) , $fieldname_n.'[stamp]', (!isset($form_data[$file_id]) ? $file_data->stamp : (int)$form_data[$file_id]['stamp']), 1, ' class="fc_filestamp" ', $elementid_n.'_stamp').'
			</span>
		</td>
	</tr>' : '').
	'
</table>

	<div class="fcclear"></div>'
	. '
	<div style="position: relative;">
		<div class="fc_mediafile_controls_iformbox">
			<!--div id="fc_mediafile_current_time_' . $FN_n . '" class="media_time">00:00:00</div-->
			<div id="fc_mediafile_controls_' . $FN_n . '" class="fc_mediafile_controls">
				<a href="javascript:;" class="btn playBtn">
					<span class="icon-play-circle controls"></span><span class="btnControlsText">' . JText::_('FLEXI_FIELD_MEDIAFILE_PLAY') . '</span>
				</a>
				<a href="javascript:;" class="btn pauseBtn" style="display: none;">
					<span class="icon-pause-circle controls"></span><span class="btnControlsText">' . JText::_('FLEXI_FIELD_MEDIAFILE_PAUSE') . '</span>
				</a>
				<a href="javascript:;" class="btn stopBtn" style="display: none;">
					<span class="icon-stop-circle controls"></span><span class="btnControlsText">' . JText::_('FLEXI_FIELD_MEDIAFILE_STOP') . '</span>
				</a>
				<a href="javascript:;" class="btn loadBtn" style="display: none;">
					<span class="icon-loop controls"></span><span class="btnControlsText">' . JText::_('FLEXI_FIELD_MEDIAFILE_LOAD') . '</span>
				</a>
			</div>
		</div>
		<div id="fc_mediafile_audio_spectrum_box_' . $FN_n . '" class="fc_mediafile_audio_spectrum_box"
			data-fc_tagid="' . $field->name . '_' . $n . '"
			data-fc_fname="' .$field_name_js . '"
			style="display: block; margin-top: 8px; position: relative; border: 1px dashed;"
		>
			<div class="progress progress-striped active" style="visibility: visible; position: absolute; width: 70%; top: 40%; left: 15%;">
				<div class="bar" style="width: 0%;"></div>
			</div>
			<div id="fc_mediafile_audio_spectrum_' . $FN_n . '" class="fc_mediafile_audio_spectrum"></div>
		</div>
	</div>
		';
		';


	/*if ($filename_original)
	{
		$per_value_js .= "
			fcfield_mediafile.initValue('" . $field->name . '_' . $n . "', '".$field_name_js."');
		";
	}*/

	if (!$filename_original)
	{
		$per_value_js .= "
			fcfield_mediafile.showUploader('" . $field->name . '_' . $n . "', '".$field_name_js."');
		";
	}

	$n++;
	if (!$multiple) break;
}


$document->addScriptDeclaration("
	fcfield_mediafile_base_url['".$field_name_js."'] = '".$base_url."';

	//document.addEventListener('DOMContentLoaded', function()
	jQuery(document).ready(function()
	{
		" . $per_value_js . "
	});
");