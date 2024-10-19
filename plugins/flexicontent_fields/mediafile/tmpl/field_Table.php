<?php

use Joomla\CMS\Language\Text;

$FT                = 'FILE';
$PRV_TYPE          ='-2';
$image_placeholder = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

$allowdownloads   = false;
$compactWaveform  = true;
$wf_zoom_slider   = $field->parameters->get('wf_zoom_slider', 1);
$wf_load_progress = $field->parameters->get('wf_load_progress', 1);

$per_value_js = "";
$n = 0;

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

	if (!in_array(strtolower($file_data->ext), $imagesExt))
	{
		$preview_src = $image_placeholder;

		// form_file_preview (2): Automatic, so if not an image hide the preview elements
		if ($form_file_preview === 2 && $fields_box_placing)
		{
			$preview_css .= 'display:none;';
		}
		$preview_text = mb_strtoupper($file_data->ext);
		$has_preview = false;
	}
	else
	{
		switch((int) $file_data->url)
		{
			case 0:
				$img_path = \Joomla\CMS\Uri\Uri::root(true) . '/' . (empty($file_data->secure) ? $mediapath : $docspath) . '/' . $file_data->filename;
				break;
			case 1:
				$img_path = $file_data->filename;
				break;
			case 2:
			default:
				$img_path = \Joomla\CMS\Uri\Uri::root(true) . '/' . $file_data->filename;
				break;
		}
		$preview_src = \Joomla\CMS\Uri\Uri::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&amp;w=100&amp;h=100&amp;zc=1&amp;q=95&amp;ar=x';
		$preview_text = '';
		$has_preview = true;
	}

	$info_txt_classes = $file_data->published ? '' : 'file_unpublished '.$tooltip_class;
	$info_txt_tooltip = $file_data->published ? '' : 'title="'.flexicontent_html::getToolTip('FLEXI_FILE_FIELD_FILE_UNPUBLISHED', 'FLEXI_FILE_FIELD_FILE_UNPUBLISHED_DESC', 1, 1).'"';

	$_select_file_lbl = '
			<label class="fc-prop-lbl inlinefile-data-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE', 'FLEXI_FIELD_'.$FT.'_ABOUT_SELECTED_FILE_DESC', 1, 1).'" id="'.$elementid_n.'_file-data-lbl" for="'.$elementid_n.'_file-data-txt">
				' . Text::_('FLEXI_FIELD_'.$FT.'_SELECT_FILE') . '
			</label>
	';

	// Get from session for new values
	$default_secure_val = isset($form_data[$file_id])  ?  (int)$form_data[$file_id]['secure']  :  1;

	// Get from session for new values
	$default_stamp_val = isset($form_data[$file_id])  ?  (int)$form_data[$file_id]['stamp']  :  1;

	$addExistingURL_onclick = "fc_openFileSelection_".$field->id."(this);";
	$toggleUploader_onclick = 'var box = jQuery(this).closest(\'.fcfieldval_container\'); ' .
		'var isVisible = box.find(\'.fc_file_uploader\').is(\':visible\'); ' .
		'isVisible ? jQuery(this).removeClass(\'active\') : jQuery(this).addClass(\'active\'); ' .
		'isVisible ? box.find(\'.fcfield_preview_box\').show() : box.find(\'.fcfield_preview_box\').hide(); ' .
		'isVisible ? box.find(\'.inlinefile-prv-box\').addClass(\'empty\') : box.find(\'.inlinefile-prv-box\').removeClass(\'empty\');';

	if ($use_inline_uploaders)
	{
		$uploader_html = $uploader_html_arr[$n] = \Joomla\CMS\HTML\HTMLHelper::_('fcuploader.getUploader', $field, $u_item_id, null, $n,
			array(
				'container_class' => ($multiple ? 'fc_inline_uploader fc_uploader_thumbs_view fc-box' : '') . ' fc_compact_uploader fc_auto_uploader thumb_'.$thumb_size_default,
				'upload_maxcount' => 1,
				'autostart_on_select' => true,
				'refresh_on_complete' => false,
				'thumb_size_default' => $thumb_size_default,
				'toggle_btn' => array(
					'class' => ($file_btns_position ? 'dropdown-item' : '') . ' ' . $btn_item_class,
					'text' => '<span class="fcfield-uploadvalue fcfont-icon-inline '.$font_icon_class.'"></span>' . (!$file_btns_position ? '&nbsp; ' . Text::_('FLEXI_UPLOAD') : ''),
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
		$btn_classes = ($file_btns_position ? 'dropdown-item' : '') . ' ' . $btn_item_class;
		$uploader_html->multiUploadBtn = '';  /*'
			<span data-href="'.$addExistingURL.'" onclick="'.$addExistingURL_onclick.'" class="'.$btn_classes.' fc-up fcfield-uploadvalue multi" id="'.$elementid_n.'_mul_uploadvalue">
				&nbsp; ' . $multi_icon . ' ' . (!$file_btns_position || $file_btns_position==2 ? Text::_('FLEXI_UPLOAD') : '') . '
			</span>';*/
		$uploader_html->myFilesBtn = '
			<span data-href="'.$addExistingURL.'" onclick="'.$addExistingURL_onclick.'" class="'.$btn_classes.'" data-rowno="'.$n.'" id="'.$elementid_n.'_selectvalue">
				<span class="fc-files-modal-link  fc-sel fcfield-selectvalue multi fcfont-icon-inline ' . $font_icon_class . '"></span>
				' .  ($file_btns_position ? $multi_icon : '') . ' ' . (!$file_btns_position || $file_btns_position==2 ? ' ' . Text::_('FLEXI_MY_FILES') : '') . ' ' .'
			</span>';
		$uploader_html->mediaUrlBtn = !$usemediaurl ? '' : '
			<span class="' . ($file_btns_position ? 'dropdown-item' : '') . ' ' . $btn_item_class .'" onclick="fcfield_mediafile.toggleMediaURL(\''.$elementid_n.'\', \''.$field_name_js.'\'); return false;">
				<span class="fcfield-medialurlvalue fcfont-icon-inline ' . $font_icon_class . '"></span>
				' . (!$file_btns_position || $file_btns_position==2 ? '&nbsp; ' . Text::_('FLEXI_FIELD_MEDIA_URL') : '') . '
			</span>';
		$uploader_html->clearBtn = '
			 <span class="' . $btn_item_class . ' fcfield-clearvalue ' . $font_icon_class . '" title="'.Text::_('FLEXI_CLEAR').'" onclick="fcfield_mediafile.clearField(this, {}, \''.$field_name_js.'\');">
			</span>';
	}

	$fnn = $FN_n;   // Alias ...

	$media_field_html = '';

	if ($use_myfiles == 4)
	{
		$media_field_style = '';//($file_data->filename ? 'display:none' : '');
		$media_field_class = '';

		// Currently for quantum, specifying subpath only works properly if subpath is inside 'images'
		$use_quantum = ($jmedia_topdir === 'images' || $jmedia_subpath === '') && ComponentHelper::isEnabled('com_quantummanager');
		if ($use_quantum)
		{
			$modal_url   = "index.php?option=com_ajax&view=default&tmpl=component&asset=com_content&author=&plugin=quantummanagermedia&format=html";
			$modal_url  .= $jmedia_subpath ? "&folder=".$jmedia_subpath : '';			$modal_title = 'Select file'; $width = 0; $height = 0;
			$onclick_js  = "var url = jQuery(this).attr('data-href'); var fieldid = jQuery(this).closest('.fc_media_file_box').find('input').attr('id'); url = url+ '&fieldid=' + fieldid;"
				. " var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, {$width}, {$height}, null, "
				. " {title:'{$modal_title}', loadFunc: null}); return false;";
			$select_file_btn =
				'<a class="form-control btn btn-info customform-btn fit-contents"
												   onclick="'.$onclick_js.'" href="javascript:" data-href="'.$modal_url.'"
	><i class="icon-search"></i></a>';  // &nbsp; Select
			$juri_root = JURI::root(true);
			$file_placeholder_text = 'No file selected';
			$file_placeholder_src  = '';//$juri_root . '/' .'......./person_placeholder.jpg';
			$file_clear_value_js   = "jQuery(this).parent().find('input[type=text]').val(''); fcfield_file.clearMediaFile(this, '".$file_placeholder_src."');";

			$file_is_img  = !$file_data->filename ? false : in_array(strtolower(pathinfo($file_data->filename, PATHINFO_EXTENSION)), $imagesExt);
			$filename_ext = pathinfo($file_data->filename, PATHINFO_EXTENSION);
			$preview_alt  = ''; //strtoupper($filename_ext);

			$value_src  = $file_data->filename ? $juri_root . '/' . $file_data->filename : $file_placeholder_src;
			$image_src  = $file_is_img ? $value_src : '';
			$object_src = $file_is_img ? '' : $value_src;

			$image_style = $file_is_img ? '' : 'display:none;';
			$object_style = $file_is_img ? 'display:none;' : '';

			$media_field_html = <<<HTML
										<div class="control-group fc_media_file_box {$media_field_class}" style="{$media_field_style}">
											<div class="controls">
												<div style="display:flex; align-items:center; width:100%; flex-direction: column;" data-juri-root="{$juri_root}">
													<img alt="{$preview_alt}" src="{$image_src}" class="inline-preview-img" style="{$image_style} max-width:480px"/>
													<object data="{$object_src}" type="" width="480" height="180" class="inline-preview-obj" style="{$object_style}; background:#eee; border-radius: 6px 6px 0 0;"></object>
												</div>
												<div class="input-group">
													<input class="form-control input-group-prepend fc_mediafile" type="text" readonly="" style="flex-grow:20; min-width:unset" value="{$file_data->filename}" id="{$elementid_n}_mediafile" name="{$fieldname_n}[mediafile]" placeholder="{$file_placeholder_text}" data-config_name={$field_name_js}" />
													{$select_file_btn}
													<button type="button" href="#" title="Clear" class="form-control btn input-group-append fit-contents clear-btn" onclick="{$file_clear_value_js}"><i class="icon-cancel"></i></button>
												</div>
											</div>
										</div>
HTML;
		} else {
			/*
			// Creation of modal URL in J3 LAYOUT
			$url    = ($readonly ? ''
				: ($link ?: 'index.php?option=com_media&amp;view=images&amp;tmpl=component&amp;asset='
					. $asset . '&amp;author=' . $authorId)
				. '&amp;fieldid={field-media-id}&amp;ismoo=0&amp;folder=' . $folder);

			// Creation of modal URL in J4+ LAYOUT
			$url = ($readonly ? ''
				: ($link ?: 'index.php?option=com_media&view=media&tmpl=component&mediatypes=' . $mediaTypes
					. '&asset=' . $asset . '&author=' . $authorId)
				. '&fieldid={field-media-id}&path=' . $folder);
			*/

			$modal_url = version_compare(\Joomla\CMS\Version::MAJOR_VERSION, '4', 'lt')
				? 'index.php?option=com_media&amp;view=media&amp;tmpl=component&amp;asset=com_flexicontent&amp;filetypes=' . $fileTypes . '&amp;author='
				: 'index.php?option=com_media&amp;view=media&amp;tmpl=component&amp;asset=com_flexicontent&amp;mediatypes=' . $mediaTypes . '&amp;author=';

			// SEE top of file: layouts/joomla/form/field/media.php
			$jMedia_file_displayData = [
				'disabled' => false,
				'preview' => 'true',   // 'false', 'none', 'true', 'show', 'tooltip'
				'readonly' => false,
				'class' => '',
				'link' => $modal_url,
				'asset' => 'com_flexicontent',
				'authorId' => '',
				'previewWidth' => 480,
				'previewHeight' => 180,
				'name' => $fieldname_n . '[mediafile]',
				'id' => $elementid_n . '_mediafile',
				'value' => $file_data->filename,

				// J3 sub-path inside JPATH_ROOT/images
				// J4 sub-path inside JPATH_ROOT/top-level-directory, default is JPATH_ROOT/media
				'folder' => (version_compare(\Joomla\CMS\Version::MAJOR_VERSION, '4', 'lt')
					? $jmedia_subpath
					: 'local-' . $jmedia_topdir .  ':/' . $jmedia_subpath),
			];

			if (version_compare(\Joomla\CMS\Version::MAJOR_VERSION, '4', 'ge'))
			{
				$jMedia_file_displayData += [
					// J4 only, Miscellaneous data attributes preprocessed for HTML output, e.g. ' data-somename1="somevalue1" data-somename2="somevalue2" '
					'dataAttribute' => '',

					// J4 only, supported media types for the Media Manager
					'mediatypes'   => $mediaTypes,  // e.g. '0,3' Supported values '0,1,2,3', 0: images, 1: audios, 2: videos, 3: documents * 'folders' is always included in J4
					'imagesExt'    => $imagesExt,
					'audiosExt'    => $audiosExt,
					'videosExt'    => $videosExt,
					'documentsExt' => $documentsExt,
				];
			}
			else {
				$jMedia_file_displayData += [
					// J3 supported media types for the Media Manager
					'filetypes'   => $fileTypes,     // e.g. 'folders,images,docs' Supported values: 'folders,images,docs,videos' * audios will be ignored in J3
				];
			}

			$media_field = \Joomla\CMS\Layout\LayoutHelper::render($media_field_layout = 'joomla.form.field.media', $jMedia_file_displayData, $layouts_path = null);
			$media_field_html = str_replace('{field-media-id}', 'field-media-data' , $media_field);
			//$media_field_html = str_replace('button-clear"', 'button-clear" onclick="fcfield_file.clearMediaFile(this, \'\');" ', $media_field);
			$media_field_html = '<div class="fc_media_file_box '.$media_field_class.'"  style="'.$media_field_style.'">' . $media_field_html . '</div>';
		}
	}


	$field->html[] = '

		<span class="fc_filedata_storage_name" style="display:none;">'.$file_data->filename.'</span>
		<div class="fc_filedata_txt_nowrap nowrap_hidden">'.$file_data->filename.'<br/>'.$file_data->altname.'</div>
		<input class="fc_filedata_txt inlinefile-data-txt '. $info_txt_classes . $required_class .'" style="'.(($use_myfiles == 4 && !$use_quantum) || !in_array($form_info_header, [1,3]) ? 'display:none' : '').'"
			readonly="readonly" name="'.$fieldname_n.'[file-data-txt]" id="'.$elementid_n.'_file-data-txt" '.$info_txt_tooltip.'
			value="'.htmlspecialchars($filename_original, ENT_COMPAT, 'UTF-8').'"
			data-label_text="'.$field->label.'"
			data-filename="'.htmlspecialchars($file_data->filename, ENT_COMPAT, 'UTF-8').'"
			data-wfpreview="'.htmlspecialchars($file_data->waveform_preview, ENT_COMPAT, 'UTF-8').'"
			data-wfpeaks="'.htmlspecialchars($file_data->waveform_peaks, ENT_COMPAT, 'UTF-8').'"
		/>
		<input type="hidden" id="'.$elementid_n.'_file-id" name="'.$fieldname_n.'[file-id]" value="'.htmlspecialchars($file_id, ENT_COMPAT, 'UTF-8').'" />'.'

		'.( (!$multiple || $use_ingroup) && !$required_class && $use_myfiles != 4 ? '
		<div class="fcclear"></div>
		<fieldset class="group-fcset">
			<input type="checkbox" id="'.$elementid_n.'_file-del" class="inlinefile-del" name="'.$fieldname_n.'[file-del]" value="1" onchange="file_fcfield_del_existing_value'.$field->id.'(this);" />
			<label class="fc-prop-lbl inlinefile-del-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_'.$FT.'_ABOUT_REMOVE_FILE', 'FLEXI_FIELD_'.$FT.'_ABOUT_REMOVE_FILE_DESC', 1, 1).'" id="'.$elementid_n.'_file-del-lbl" for="'.$elementid_n.'_file-del" >
				'.Text::_( 'FLEXI_FIELD_FILE_DELETE_FROM_SERVER_STORAGE' ).'
			</label>
		</fieldset>
		<div class="fcclear"></div>

		' : ( (!$multiple || $use_ingroup) && $required_class && $file_data->filename ? '
			<div class="fcclear"></div>
			<div class="alert alert-info fc-small fc-iblock" style="margin: 8px 0;">'.Text::_('FLEXI_FIELD_'.$FT.'_REQUIRED_UPLOAD_NEW_TO_REPLACE').'</div>
			<div class="fcclear"></div>
		' : '')).'

		'.(in_array($form_info_header, [2,3]) && $use_myfiles != 4 ? '
		<div class="fcclear"></div>
		<div class="'.$input_grp_class.' fc-xpended-row">
			<label class="' . $add_on_class . ' badge fc-lbl fc_filedata_title-lbl" style="margin:0">'.Text::_( 'FLEXI_ORIGINAL_FILENAME' ).'</label>
			<input type="text" disabled class="' . $add_on_class . ' fc_filedata_title" value="'. htmlentities($filename_original, ENT_COMPAT, 'UTF-8') .'" />
		</div>' : '').'

		<div class="fcclear"></div>

		<div class="fc_uploader_n_props_box">

			<div class="inlinefile-prv-box" style="'. ($use_myfiles == 4 && $inputmode == 1 && !$use_quantum ? 'width: 100%' : 'flex-basis: auto;') . '">
				'.($form_file_preview && $use_myfiles != 4 ? '<div class="fcfield_preview_box' . ($form_file_preview === 2 ? ' auto' : '') . '" style="'.$preview_css.'">
					<div class="fc_preview_text">' . $preview_text . '</div>
					<img id="'.$elementid_n.'_image_preview" src="'.$preview_src.'" class="fc_preview_thumb" alt="Preview image placeholder"/></div>' : '').'
				'.(!$media_field_html && !empty($uploader_html) ? $uploader_html->container : $media_field_html).'
			</div>


			<div class="inlinefile-data-box" style="flex-basis: auto; flex-shrink: 50;">
			<table class="fc-form-tbl fcinner inlinefile-tbl">
				'.

			( $iform_title ? '
			<tr class="inlinefile-title-row">
				<td class="key inlinefile-title-lbl-cell">
					<label class="fc-prop-lbl inlinefile-title-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1).'" id="'.$elementid_n.'_file-title-lbl" for="'.$elementid_n.'_file-title">
						'.Text::_( 'FLEXI_FILE_DISPLAY_TITLE' ).'
					</label>
				</td>
				<td class="inlinefile-title-data-cell">
					<span class="inlinefile-title-data">
					<input type="text" id="'.$elementid_n.'_file-title" size="44" name="'.$fieldname_n.'[file-title]" value="'.htmlspecialchars(!isset($form_data[$file_id]) ? $file_data->altname : $form_data[$file_id]['file-title'], ENT_COMPAT, 'UTF-8').'" class="fc_filetitle '.$required_class.' fcfield_textval" />
					</span>
				</td>
			</tr>' : '').

			( $iform_lang ? '
			<tr class="inlinefile-lang-row">
				<td class="key inlinefile-lang-lbl-cell">
					<label class="fc-prop-lbl inlinefile-lang-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1).'" id="'.$elementid_n.'_file-lang-lbl" for="'.$elementid_n.'_file-lang">
						'.Text::_( 'FLEXI_LANGUAGE' ).'
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
						'.Text::_( 'FLEXI_ACCESS' ).'
					</label>
				</td>
				<td class="inlinefile-access-data-cell">
					<span class="inlinefile-access-data">
					'.\Joomla\CMS\HTML\HTMLHelper::_('access.assetgrouplist', $fieldname_n.'[file-access]', (!isset($form_data[$file_id]) ? $file_data->access : $form_data[$file_id]['file-access']), $attribs=' class="fc_fileaccess use_select2_lib" ', $config=array(/*'title' => Text::_('FLEXI_SELECT'), */'id' => $elementid_n.'_file-access')).'
					</span>
				</td>
			</tr>' : '').

			( $iform_desc ? '
			<tr class="inlinefile-desc-row">
				<td class="key inlinefile-desc-lbl-cell">
					<label class="fc-prop-lbl inlinefile-desc-lbl '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1).'" id="'.$elementid_n.'_file-desc-lbl" for="'.$elementid_n.'_file-desc">
						'.Text::_( 'FLEXI_DESCRIPTION' ).'
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
						'.Text::_( 'FLEXI_URL_SECURE' ).'
					</label>
				</td>
				<td class="inlinefile-secure-data-cell">
					'.($has_values ? '
					<span class="inlinefile-secure-info" style="'.(!$has_values ? 'display:none;' : '').'">
						<span class="badge bg-info badge-info">'.Text::_($file_data->secure ?  'FLEXI_YES' : 'FLEXI_NO').'</span>
					</span>' : '').'
					<span class="inlinefile-secure-data" style="'.($has_values ? 'display:none;' : '').'">
						'.flexicontent_html::buildradiochecklist( array(1=> Text::_( 'FLEXI_YES' ), 0=> Text::_( 'FLEXI_NO' )) , $fieldname_n.'[secure]', (!isset($form_data[$file_id]) ? $file_data->secure : (int)$form_data[$file_id]['secure']), 1, ' class="fc_filedir" ', $elementid_n.'_secure').'
					</span>
				</td>
			</tr>' : '').

			( $iform_stamp ? '
			<tr class="inlinefile-stamp-row">
				<td class="key inlinefile-stamp-lbl-cell">
					<label class="fc-prop-lbl inlinefile-stamp-lbl '.$tooltip_class.'" data-placement="top" title="'.flexicontent_html::getToolTip('FLEXI_DOWNLOAD_STAMPING', 'FLEXI_FILE_DOWNLOAD_STAMPING_DESC', 1, 1).'" id="'.$elementid_n.'_stamp-lbl">
						'.Text::_( 'FLEXI_DOWNLOAD_STAMPING' ).'
					</label>
				</td>
				<td class="inlinefile-stamp-data-cell">
					<span class="inlinefile-stamp-data">
					'.flexicontent_html::buildradiochecklist( array(1=> Text::_( 'FLEXI_YES' ), 0=> Text::_( 'FLEXI_NO' )) , $fieldname_n.'[stamp]', (!isset($form_data[$file_id]) ? $file_data->stamp : (int)$form_data[$file_id]['stamp']), 1, ' class="fc_filestamp" ', $elementid_n.'_stamp').'
					</span>
				</td>
			</tr>' : '').
			'
			</table>

			</div>
		</div>

		<div class="fcclear"></div>

		<div class="fc_mediafile_player_box' . ($compactWaveform ? ' fc_compact' : '') . '">

			<div class="fc_mediafile_controls_outer">

				<!--div id="fc_mediafile_current_time_' . $fnn . '" class="media_time">00:00:00</div-->
				<div id="fc_mediafile_controls_' . $fnn . '" class="fc_mediafile_controls">
					<a href="javascript:;" class="btn playBtn">
						<span class="icon-play-circle controls"></span><span class="btnControlsText">' . Text::_('FLEXI_FIELD_MEDIAFILE_PLAY') . '</span>
					</a>
					<a href="javascript:;" class="btn pauseBtn" style="display: none;">
						<span class="icon-pause-circle controls"></span><span class="btnControlsText">' . Text::_('FLEXI_FIELD_MEDIAFILE_PAUSE') . '</span>
					</a>
					<a href="javascript:;" class="btn stopBtn" style="display: none;">
						<span class="icon-stop-circle controls"></span><span class="btnControlsText">' . Text::_('FLEXI_FIELD_MEDIAFILE_STOP') . '</span>
					</a>
					<a href="javascript:;" class="btn loadBtn" style="display: none;">
						<span class="icon-loop controls"></span><span class="btnControlsText">' . Text::_('FLEXI_FIELD_MEDIAFILE_LOAD') . '</span>
					</a>
					' . ($allowdownloads ? $_download_btn_html : '') . '
					' . (!$wf_zoom_slider ? '' : '
					<div class="fc_mediafile_wf_zoom_box">
						- <input id="fc_mediafile_slider_' . $fnn. '" type="range" min="0.5" max="200" value="0.5" class="fc_mediafile_wf_zoom" /> +
					</div>
					') . '
				</div>

			</div>

			<div class="fc_mediafile_audio_spectrum_box_outer" >

				<div id="fc_mediafile_audio_spectrum_box_' . $fnn . '" class="fc_mediafile_audio_spectrum_box"
					data-fc_tagid="' . $field->name . '_' . $n . '"
					data-fc_fname="' .$field_name_js . '"
				>
					' . (!$wf_load_progress ? '' : '
					<div class="fc_mediafile_audio_spectrum_progressbar">
						<div class="barText"></div>
						<div class="bar" style="width: 100%;"></div>
					</div>
					') . '
					<div id="fc_mediafile_audio_spectrum_' . $fnn . '" class="fc_mediafile_audio_spectrum"></div>
				</div>

			</div>

		</div>
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
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}


//document.addEventListener('DOMContentLoaded', function()
$js = ""
."
	fcfield_mediafile_base_url['".$field_name_js."'] = '".$base_url."';
"
. (!$per_value_js ? "" : "
	jQuery(document).ready(function()
	{
		" . $per_value_js . "
	});
");

if ($js) $document->addScriptDeclaration($js);

