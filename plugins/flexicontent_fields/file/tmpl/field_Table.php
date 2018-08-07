<?php
$FT = 'FILE';
$PRV_TYPE='-2';
$image_placeholder = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

$n = 0;

foreach ($field->value as $file_id)
{
	$file_data = $files_data[$file_id];
	$fieldname_n = $fieldname.'['.$n.']';
	$elementid_n = $elementid.'_'.$n;
	$filename_original = $file_data->filename_original ? $file_data->filename_original : $file_data->filename;

	$preview_css = 'width:100px; height:100px;';
	if ( !in_array(strtolower($file_data->ext), $imageexts)) {
		$preview_src = $image_placeholder;
		if ($form_file_preview==2 && $fields_box_placing) $preview_css .= 'display:none;';  // 2: Automatic, so if not an image hide the preview elements
	} else {
		$img_path = (substr($file_data->filename, 0,7)!='http://' || substr($file_data->filename, 0,8)!='https://') ?
			JUri::root(true) . '/' . (empty($file_data->secure) ? $mediapath : $docspath) . '/' . $file_data->filename :
			$file_data->filename ;
		$preview_src = JUri::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&amp;w=100&amp;h=100&amp;zc=1&amp;q=95&amp;ar=x';
	}

	$info_txt_classes = $file_data->published ? '' : 'file_unpublished '.$tooltip_class;
	$info_txt_tooltip = $file_data->published ? '' : 'title="'.flexicontent_html::getToolTip('FLEXI_FILE_FIELD_FILE_UNPUBLISHED', 'FLEXI_FILE_FIELD_FILE_UNPUBLISHED_DESC', 1, 1).'"';
	$_select_file_lbl = '
			<label class="fc-prop-lbl inlinefile-data-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE', 'FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE_DESC', 1, 1).'" id="'.$elementid_n.'_file-data-lbl" for="'.$elementid_n.'_file-data-txt">
				' . JText::_('FLEXI_FIELD_'.$FT.'_SELECT_FILE') . '
			</label>
	';

	$cols2_exist = $iform_title || $iform_lang || $iform_access || $iform_desc || $iform_dir;

	$field->html[] = '
	<div class="fcclear"></div>
	<div style="display:inline-block;">
		<span class="fc_filedata_storage_name" style="display:none;">'.$file_data->filename.'</span>
		<div class="fc_filedata_txt_nowrap nowrap_hidden">'.$file_data->filename.'<br/>'.$file_data->altname.'</div>
		<input class="fc_filedata_txt inlinefile-data-txt '. $info_txt_classes . $required_class .'" readonly="readonly" name="'.$fieldname_n.'[file-data-txt]" id="'.$elementid_n.'_file-data-txt" '.$info_txt_tooltip.' value="'.htmlspecialchars($filename_original, ENT_COMPAT, 'UTF-8').'" />
		'.(!$iform_title ? '<br/><div class="'.$input_grp_class.'"><span class="' . $add_on_class . ' fc-lbl fc_filedata_title-lbl">'.JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ).'</span><span class="' . $add_on_class . ' fc_filedata_title">'. ($file_data->altname && $filename_original!=$file_data->altname ? $file_data->altname : '-').'</span></div>' : '').'
		<br/>
		'.($form_file_preview ? '<img id="'.$elementid_n.'_img_preview" src="'.$preview_src.'" class="fc_preview_thumb" style="'.$preview_css.'" alt="Preview image placeholder"/>' : '').'
	</div>
	<table class="fc-form-tbl fcinner inlinefile-tbl">
	<tr class="inlinefile-data-row">
		'.($fields_box_placing==1 ? '' : '
		<td class="key inlinefile-data-lbl-cell">
			'.$_select_file_lbl.'
		</td>').'
		<td class="inlinefile-data-cell" '.($fields_box_placing==1 && $cols2_exist ? 'colspan="2"' : '').'>
			<span class="inlinefile-data">
				'.($fields_box_placing==1 ? '<span style="visibility:hidden; z-index:-1; position:absolute;">'.$_select_file_lbl.'</span>' : '').'
				<input type="hidden" id="'.$elementid_n.'_file-id" name="'.$fieldname_n.'[file-id]" value="'.htmlspecialchars($file_id, ENT_COMPAT, 'UTF-8').'" class="fc_fileid" />'.'
				<span class="'./*$input_grp_class.*/'">
					'.(! $field->parameters->get('use_myfiles', '1') ? '
					<span class="btn fc_fileupload_box">
						<span>'.JText::_('FLEXI_FIELD_'.$FT.'_UPLOAD_NEW').'</span>
						<input type="file" id="'.$elementid_n.'_file-data" name="'.$fieldname_n.'[file-data]" class="fc_filedata" data-rowno="'.$n.'" onchange="var file_box = jQuery(this).parent().parent().parent(); fc_loadImagePreview(this.id,\''.$elementid.'_\'+jQuery(this).attr(\'data-rowno\')+\'_img_preview\', \''.$elementid.'_\'+jQuery(this).attr(\'data-rowno\')+\'_file-data-txt\', 100, 0, \''.$PRV_TYPE.'\'); file_box.find(\'.inlinefile-secure-data\').show(400);  file_box.find(\'.inlinefile-secure-info\').hide(400); file_box.find(\'.inlinefile-del\').removeAttr(\'checked\').trigger(\'change\'); " />
					</span>
					' : '
					<a class="btn btn-info addfile_'.$field->id.' hasTooltip" id="'.$elementid_n.'_addfile" title="'.$_prompt_txt.'" href="'.sprintf($addExistingURL, '__rowno__', '__thisid__').'" data-rowno="'.$n.'">
						'.JText::_('FLEXI_FIELD_'.$FT.'_MY_FILES').'
					</a>
					').'
				</span>
			</span>

			'.( (!$multiple || $is_ingroup) && !$required_class ? '
			<br/>
			<input type="checkbox" id="'.$elementid_n.'_file-del" class="inlinefile-del" name="'.$fieldname_n.'[file-del]" value="1" onchange="file_fcfield_del_existing_value'.$field->id.'(this);" />
			<label class="fc-prop-lbl inlinefile-del-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_'.$FT.'_ABOUT_REMOVE_FILE', 'FLEXI_FIELD_'.$FT.'_ABOUT_REMOVE_FILE_DESC', 1, 1).'" id="'.$elementid_n.'_file-del-lbl" for="'.$elementid_n.'_file-del" >
				'.JText::_( 'Remove file' ).'
			</label>
			' : ( (!$multiple || $is_ingroup) && $required_class ? '<br/><div class="alert alert-info fc-small fc-iblock">'.JText::_('FLEXI_FIELD_'.$FT.'_REQUIRED_UPLOAD_NEW_TO_REPLACE').'</div>' : '')).'
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
				'.flexicontent_html::buildradiochecklist( array(1=> JText::_( 'FLEXI_YES' ), 0=> JText::_( 'FLEXI_NO' )) , $fieldname_n.'[secure]', (!isset($form_data[$file_id]) ? $file_data->secure : (int)$form_data[$file_id]['secure']), 0, ' class="fc_filedir" ', $elementid_n.'_secure').'
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
				'.flexicontent_html::buildradiochecklist( array(0=> JText::_( 'FLEXI_NO' ), 1=> JText::_( 'FLEXI_YES' )) , $fieldname_n.'[stamp]', (!isset($form_data[$file_id]) ? $file_data->stamp : (int)$form_data[$file_id]['stamp']), 0, ' class="fc_filestamp" ', $elementid_n.'_stamp').'
			</span>
		</td>
	</tr>' : '').
	'
	</table>
	<div class="fcclear"></div>'
	;
	$n++;
}