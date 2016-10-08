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
		if ($form_file_preview==2 && $fields_box_placing) $preview_css .= 'display:none;';  // 2: Automatic, so if not an image hide the preview elements
	} else {
		$img_path = (substr($file_data->filename, 0,7)!='http://' || substr($file_data->filename, 0,8)!='https://') ?
			JURI::root(true) . '/' . (empty($file_data->secure) ? $mediapath : $docspath) . '/' . $file_data->filename :
			$file_data->filename ;
		$preview_src = JURI::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&amp;w=100&amp;h=100&amp;zc=1&amp;q=95&amp;ar=x';
	}
	
	$info_txt_classes = $file_data->published ? '' : 'file_unpublished '.$tooltip_class;
	$info_txt_tooltip = $file_data->published ? '' : 'title="'.flexicontent_html::getToolTip('FLEXI_FILE_FIELD_FILE_UNPUBLISHED', 'FLEXI_FILE_FIELD_FILE_UNPUBLISHED_DESC', 1, 1).'"';

	// Get from session for new values
	$default_secure_val = isset($form_data[$file_id])  ?  (int)$form_data[$file_id]['secure']  :  1;

	$field->html[] = '
		<div class="inlinefile-data-box">

			<div style="display:inline-block;">
				<div class="fc_filedata_txt_nowrap">'.$filename_original.'</div>
				<input class="fc_filedata_txt inlinefile-data-txt '. $info_txt_classes . $required_class .'" readonly="readonly" name="'.$fieldname_n.'[file-data-txt]" id="'.$elementid_n.'_file-data-txt" '.$info_txt_tooltip.' value="'.$filename_original.'" />
				'.(!$iform_title ? '<br/>
				<div class="'.$input_grp_class.' fc-xpended-row">
					<label class="'.$add_on_class.' fc-lbl fc_filedata_title-lbl">'.JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ).'</label>
					<span class="'.$add_on_class.' fc_filedata_title">'. ($file_data->altname && $filename_original!=$file_data->altname ? $file_data->altname : '-').'</span>
				</div>' : '').'
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

			<div class="fcclear"></div>

			<div class="'.$input_grp_class.' fc-xpended-row inlinefile-data-actions">
				<label class="'.$add_on_class.' fc-lbl inlinefile-data-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE', 'FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE_DESC', 1, 1).'" id="'.$elementid_n.'_file-data-lbl" for="'.$elementid_n.'_file-data-txt">
					'.($fields_box_placing==1 ? $field->label.' - ' : ''). JText::_('FLEXI_FIELD_'.$FT.'_SELECTED_FILE').'
				</label>
				<input type="hidden" id="'.$elementid_n.'_file-id" name="'.$fieldname_n.'[file-id]" value="'.$file_id.'" class="fc_fileid" />'.'
				<span class="btn fc_fileupload_box">
					<span>'.JText::_('FLEXI_FIELD_'.$FT.'_UPLOAD_NEW').'</span>
					<input type="file" id="'.$elementid_n.'_file-data" name="'.$fieldname_n.'[file-data]" class="fc_filedata" data-rowno="'.$n.'" onchange="var file_box = jQuery(this).parent().parent().parent(); fc_loadImagePreview(this.id,\''.$elementid.'_\'+jQuery(this).attr(\'data-rowno\')+\'_img_preview\', \''.$elementid.'_\'+jQuery(this).attr(\'data-rowno\')+\'_file-data-txt\', 100, 0, \''.$PRV_TYPE.'\'); file_box.find(\'.inlinefile-secure-data\').show(400);  file_box.find(\'.inlinefile-secure-info\').hide(400); file_box.find(\'.inlinefile-del\').removeAttr(\'checked\').trigger(\'change\'); " />
				</span>
				'.($field->parameters->get('use_myfiles', '1') ? '
				<a class="btn btn-info addfile_'.$field->id.'" id="'.$elementid_n.'_addfile" title="'.$_prompt_txt.'" href="'.sprintf($addExistingURL, '__rowno__', '__thisid__').'" data-rowno="'.$n.'">
					'.JText::_('FLEXI_FIELD_'.$FT.'_MY_FILES').'
				</a>
				' : '').'
			</div>

		</div>'.
	
	( $iform_title ? '
		<div class="'.$input_grp_class.' fc-xpended-row inlinefile-title-box">
			<label class="'.$add_on_class.' fc-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1).'" id="'.$elementid_n.'_file-title-lbl" for="'.$elementid_n.'_file-title">
				'.JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ).'
			</label>
			<input type="text" id="'.$elementid_n.'_file-title" size="44" name="'.$fieldname_n.'[file-title]" value="'.htmlspecialchars(!isset($form_data[$file_id]) ? $file_data->altname : $form_data[$file_id]['file-title'], ENT_COMPAT, 'UTF-8').'" class="fc_filetitle '.$required_class.' fcfield_textval" />
		</div>' : '').
	
	( $iform_lang ? '
		<div class="'.$input_grp_class.' fc-xpended-row inlinefile-lang-box">
			<label class="'.$add_on_class.' fc-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1).'" id="'.$elementid_n.'_file-lang-lbl" for="'.$elementid_n.'_file-lang">
				'.JText::_( 'FLEXI_LANGUAGE' ).'
			</label>
			'.flexicontent_html::buildlanguageslist($fieldname_n.'[file-lang]', 'class="fc_filelang use_select2_lib"', (!isset($form_data[$file_id]) ? $file_data->language : $form_data[$file_id]['file-lang']), 1).'
		</div>' : '').
	
	( $iform_access ? '
		<div class="'.$input_grp_class.' fc-xpended-row inlinefile-access-box">
			<label class="'.$add_on_class.' fc-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1).'" id="'.$elementid_n.'_file-access-lbl" for="'.$elementid_n.'_file-access">
				'.JText::_( 'FLEXI_ACCESS' ).'
			</label>
				'.JHTML::_('access.assetgrouplist', $fieldname_n.'[file-access]', (!isset($form_data[$file_id]) ? $file_data->access : $form_data[$file_id]['file-access']), $attribs=' class="fc_fileaccess use_select2_lib" ', $config=array(/*'title' => JText::_('FLEXI_SELECT'), */'id' => $elementid_n.'_file-access')).'
		</div>' : '').
	
	( $iform_desc ? '
		<div class="'.$input_grp_class.' fc-xpended-row inlinefile-desc-box">
			<label class="'.$add_on_class.' fc-lbl inlinefile-desc-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1).'" id="'.$elementid_n.'_file-desc-lbl" for="'.$elementid_n.'_file-desc">
				'.JText::_( 'FLEXI_DESCRIPTION' ).'
			</label>
			<textarea id="'.$elementid_n.'_file-desc" cols="24" rows="3" name="'.$fieldname_n.'[file-desc]" class="fc_filedesc">'.(!isset($form_data[$file_id]) ? $file_data->description : $form_data[$file_id]['file-desc']).'</textarea>
		</div>' : '').
	
	( $iform_dir ? '
		<div class="'.$input_grp_class.' fc-xpended-row inlinefile-secure-box">
			<label class="'.$add_on_class.' fc-lbl inlinefile-secure-lbl '.$tooltip_class.'" data-placement="top" title="'.flexicontent_html::getToolTip('FLEXI_URL_SECURE', 'FLEXI_URL_SECURE_DESC', 1, 1).'" id="'.$elementid_n.'_secure-lbl">
				'.JText::_( 'FLEXI_URL_SECURE' ).'
			</label>
			'.($has_values ? '
				<span class="add-on fcinfo" style="font-style: italic; min-width:64px;">'.JText::_($file_data->secure ?  'FLEXI_YES' : 'FLEXI_NO').'</span>
			' : '
				<fieldset class="radio btn-group group-fcinfo" style="'.($has_values ? 'display:none;' : '').'">
					<input class="fc_filedir" id="'.$elementid_n.'_secure0" name="'.$fieldname_n.'[secure]" type="radio" value="1" '.( $default_secure_val==1 ? 'checked="checked"' : '' ).'/>
					<label class="'.$add_on_class.' btn" style="min-width: 48px;" for="'.$elementid_n.'_secure0">'.JText::_('FLEXI_YES').'</label>
					<input class="fc_filedir" id="'.$elementid_n.'_secure1" name="'.$fieldname_n.'[secure]" type="radio" value="0" '.( $default_secure_val==0 ? 'checked="checked"' : '' ).'/>
					<label class="'.$add_on_class.' btn" style="min-width: 48px;" for="'.$elementid_n.'_secure1">'.JText::_('FLEXI_NO').'</label>
				</fieldset>
			').'
		</div>' : '').
	'
	<div class="fcclear"></div>'
	;
	$n++;
}