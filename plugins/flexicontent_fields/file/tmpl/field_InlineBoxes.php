<?php
$FT = 'FILE';
$PRV_TYPE='-2';
$image_placeholder = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
$fields_box_placing = $field->parameters->get('fields_box_placing', '0');
$form_file_preview = $field->parameters->get('form_file_preview', '2');

$n = 0;
foreach($field->value as $file_id)
{
	$file_data = $files_data[$file_id];
	$fieldname_n = $fieldname.'['.$n.']';
	$elementid_n = $elementid.'_'.$n;
	$filename_original = $file_data->filename_original ? $file_data->filename_original : $file_data->filename;
	
	$preview_css = 'width:100px; height:100px;';
	if ( !in_array(strtolower($file_data->ext), $imageexts)) {
		$preview_src = $image_placeholder;
		if ($form_file_preview==2) $preview_css .= 'display:none;';  // 2: Automatic, so if not an image hide the preview elements
	} else {
		$img_path = (substr($file_data->filename, 0,7)!='http://' || substr($file_data->filename, 0,8)!='https://') ?
			JURI::root(true) . '/' . (empty($file_data->secure) ? $mediapath : $docspath) . '/' . $file_data->filename :
			$file_data->filename ;
		$preview_src = JURI::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&amp;w=100&amp;h=100&amp;zc=1&amp;q=95&amp;ar=x';
	}
	
	$info_txt_classes = $file_data->published ? '' : 'file_unpublished '.$tooltip_class;
	$info_txt_tooltip = $file_data->published ? '' : 'title="'.flexicontent_html::getToolTip('FLEXI_FILE_FIELD_FILE_UNPUBLISHED', 'FLEXI_FILE_FIELD_FILE_UNPUBLISHED_DESC', 1, 1).'"';
	$_select_file_lbl = '
			<label class="label inlinefile-data-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE', 'FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE_DESC', 1, 1).'" id="'.$elementid_n.'_file-data-lbl" for="'.$elementid_n.'_file-data-txt">
				'.($fields_box_placing==1 ? $field->label.' - ' : ''). JText::_('FLEXI_FIELD_'.$FT.'_SELECTED_FILE').'
			</label>
	';
	
	$field->html[] = '
		<div class="nowrap_box inlinefile-data-box">
		'.($fields_box_placing==1 ? '' : '
			'.$_select_file_lbl.'
			').'
			
			<span class="inlinefile-data">
				'.($fields_box_placing==1 ? '<span style="visibility:hidden; z-index:-1; position:absolute;">'.$_select_file_lbl.'</span>' : '').'
				<input type="hidden" id="'.$elementid_n.'_file-id" name="'.$fieldname_n.'[file-id]" value="'.$file_id.'" class="fc_fileid" />'.'
				<span class="'.$input_grp_class.' fc-xpended">
					<span class="fc_fileupload_box btn">
						<span>'.JText::_('FLEXI_FIELD_'.$FT.'_UPLOAD_NEW').'</span>
						<input type="file" id="'.$elementid_n.'_file-data" name="'.$fieldname_n.'[file-data]" class="fc_filedata" data-rowno="'.$n.'" onchange="var file_box = jQuery(this).parent().parent().parent(); fc_loadImagePreview(this.id,\''.$elementid.'_\'+jQuery(this).attr(\'data-rowno\')+\'_img_preview\', \''.$elementid.'_\'+jQuery(this).attr(\'data-rowno\')+\'_file-data-txt\', 100, 0, \''.$PRV_TYPE.'\'); file_box.find(\'.inlinefile-secure-data\').show(400);  file_box.find(\'.inlinefile-secure-info\').hide(400); file_box.find(\'.inlinefile-del\').removeAttr(\'checked\').trigger(\'change\'); " />
					</span>
					'.($field->parameters->get('use_myfiles', '1') ? '
					<a class="btn btn-info addfile_'.$field->id.'" id="'.$elementid_n.'_addfile" title="'.$_prompt_txt.'" href="'.sprintf($addExistingURL, '__rowno__', '__thisid__').'" data-rowno="'.$n.'">
						'.JText::_('FLEXI_FIELD_'.$FT.'_MY_FILES').'
					</a>
				</span>
				' : '').'
			</span>
			
			<div class="fcclear"></div>
			<div style="display:inline-block;">
				<div class="fc_filedata_txt_nowrap nowrap_hidden">'.$filename_original.'</div>
				<input class="fc_filedata_txt inlinefile-data-txt '. $info_txt_classes . $required_class .'" readonly="readonly" name="'.$fieldname_n.'[file-data-txt]" id="'.$elementid_n.'_file-data-txt" '.$info_txt_tooltip.' value="'.$filename_original.'" />
				'.(!$iform_title ? '<br/><div class="'.$input_grp_class.' fc-xpended"><span class="'.$add_on_class.' fc-lbl fc_filedata_title-lbl">'.JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ).'</span><span class="'.$add_on_class.' fc_filedata_title">'. ($file_data->altname && $filename_original!=$file_data->altname ? $file_data->altname : '-').'</span></div>' : '').'
				<br/>
				'.($form_file_preview ? '<img id="'.$elementid_n.'_img_preview" src="'.$preview_src.'" class="fc_preview_thumb" style="'.$preview_css.'" alt="Preview image placeholder"/>' : '').'
			</div>
			
			'.( (!$multiple || $is_ingroup) && !$required_class ? '
			<br/>
			<input type="checkbox" id="'.$elementid_n.'_file-del" class="inlinefile-del" name="'.$fieldname_n.'[file-del]" value="1" onchange="file_fcfield_del_existing_value'.$field->id.'(this);" />
			<label class="label inlinefile-del-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_'.$FT.'_ABOUT_REMOVE_FILE', 'FLEXI_FIELD_'.$FT.'_ABOUT_REMOVE_FILE_DESC', 1, 1).'" id="'.$elementid_n.'_file-del-lbl" for="'.$elementid_n.'_file-del" >
				'.JText::_( 'Remove file' ).'
			</label>
			' : ( (!$multiple || $is_ingroup) && $required_class ? '<br/><div class="alert alert-info fc-small fc-iblock">'.JText::_('FLEXI_FIELD_'.$FT.'_REQUIRED_UPLOAD_NEW_TO_REPLACE').'</div>' : '')).'
		</div>'.
	
	( $iform_title ? '
		<div class="nowrap_box inlinefile-title-box">
			<label class="label inlinefile-title-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1).'" id="'.$elementid_n.'_file-title-lbl" for="'.$elementid_n.'_file-title">
				'.JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ).'
			</label>
			<span class="inlinefile-title-data">
				<input type="text" id="'.$elementid_n.'_file-title" size="44" name="'.$fieldname_n.'[file-title]" value="'.htmlspecialchars(!isset($form_data[$file_id]) ? $file_data->altname : $form_data[$file_id]['file-title'], ENT_COMPAT, 'UTF-8').'" class="fc_filetitle '.$required_class.'" />
			</span>
		</div>' : '').
	
	( $iform_lang ? '
		<div class="nowrap_box inlinefile-lang-box">
			<label class="label inlinefile-lang-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1).'" id="'.$elementid_n.'_file-lang-lbl" for="'.$elementid_n.'_file-lang">
				'.JText::_( 'FLEXI_LANGUAGE' ).'
			</label>
			<span class="inlinefile-lang-data">
				'.flexicontent_html::buildlanguageslist($fieldname_n.'[file-lang]', 'class="fc_filelang use_select2_lib"', (!isset($form_data[$file_id]) ? $file_data->language : $form_data[$file_id]['file-lang']), 1).'
			</span>
		</div>' : '').
	
	( $iform_desc ? '
		<div class="nowrap_box inlinefile-desc-box">
			<label class="label inlinefile-desc-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1).'" id="'.$elementid_n.'_file-desc-lbl" for="'.$elementid_n.'_file-desc">
				'.JText::_( 'FLEXI_DESCRIPTION' ).'
			</label>
			<span class="inlinefile-desc-data">
				<textarea id="'.$elementid_n.'_file-desc" cols="24" rows="3" name="'.$fieldname_n.'[file-desc]" class="fc_filedesc">'.(!isset($form_data[$file_id]) ? $file_data->description : $form_data[$file_id]['file-desc']).'</textarea>
			</span>
		</div>' : '').
	
	( $iform_dir ? '
		<div class="nowrap_box inlinefile-secure-box">
			<label class="label inlinefile-secure-lbl '.$tooltip_class.'" data-placement="top" title="'.flexicontent_html::getToolTip('FLEXI_URL_SECURE', 'FLEXI_URL_SECURE_DESC', 1, 1).'" id="'.$elementid_n.'_secure-lbl">
				'.JText::_( 'FLEXI_URL_SECURE' ).'
			</label>
			'.($has_values ? '
			<span class="inlinefile-secure-info" style="'.(!$has_values ? 'display:none;' : '').'">
				<span class="badge badge-info">'.JText::_($file_data->secure ?  'FLEXI_YES' : 'FLEXI_NO').'</span>
			</span>' : '').'
			<span class="inlinefile-secure-data" style="'.($has_values ? 'display:none;' : '').'">
				'.flexicontent_html::buildradiochecklist( array(1=> JText::_( 'FLEXI_YES' ), 0=> JText::_( 'FLEXI_NO' )) , $fieldname_n.'[secure]', (!isset($form_data[$file_id]) ? 1 : (int)$form_data[$file_id]['secure']), 0, ' class="fc_filedir" ', $elementid_n.'_secure').'
			</span>
		</div>' : '').
	'
	<div class="fcclear"></div>'
	;
	$n++;
}