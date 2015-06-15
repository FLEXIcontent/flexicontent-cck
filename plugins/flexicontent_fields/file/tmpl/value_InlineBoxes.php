<?php
$field->url = array();
$field->abspath = array();
$field->file_data = array();
foreach($files_data as $file_id => $file_data)
{
	// Check if it exists and get file size
	$basePath = $file_data->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;
	$abspath = str_replace(DS, '/', JPath::clean($basePath.DS.$file_data->filename));
	if ($display_size) {
		$path_exists = file_exists($abspath);
		$file_data->size = $path_exists ? filesize($abspath) : 0;
	}
	
	// *****************************
	// Check user access on the file
	// *****************************
	$authorized = true;
	$is_public  = true;
	if ( !empty($file_data->access) ) {
		$authorized = in_array($file_data->access,$aid_arr);
		$is_public  = in_array($public_acclevel,$aid_arr);
	}
	
	// If no access and set not to show then continue
	if ( !$authorized && !$noaccess_display ) continue;
	
	// Initialize CSS classes variable
	$file_classes = !$authorized ? 'fcfile_noauth' : '';
	
	
	
	// *****************************
	// Prepare displayed information
	// *****************************
	
	
	// a. ICON: create it according to filetype
	$icon = '';
	if ($useicon) {
		$file_data	= $this->addIcon( $file_data );
		$_tooltip_title   = '';
		$_tooltip_content = JText::_( 'FLEXI_FIELD_FILE_TYPE', true ) .': '. $file_data->ext;
		$icon = FLEXI_J30GE ?
			JHTML::image($file_data->icon, $file_data->ext, 'class="fcicon-mime hasTooltip" title="'.JHtml::tooltipText($_tooltip_title, $_tooltip_content, 1, 0).'"'):
			JHTML::image($file_data->icon, $file_data->ext, 'class="fcicon-mime hasTip" title="'.$_tooltip_title.'::'.$_tooltip_content.'"');
		$icon = '<span class="fcfile_mime">'.$icon.'</span>';
	}
	
	
	// b. LANGUAGE: either as icon or as inline text or both
	$lang = ''; $lang_str = '';
	$file_data->language = $file_data->language=='' ? '*' : $file_data->language;
	if ($display_lang && $file_data->language!='*')  // ... skip 'ALL' language ... maybe allow later
	{
		$lang = '<span class="fcfile_lang">';
		if ( $add_lang_img && @ $langs->{$file_data->language}->imgsrc ) {
			if (!$add_lang_txt) {
				$_tooltip_title   = JText::_( 'FLEXI_LANGUAGE', true );
				$_tooltip_content = $file_data->language=='*' ? JText::_("All") : $langs->{$file_data->language}->name;
				$_attribs = FLEXI_J30GE ?
					'class="hasTooltip fcicon-lang" title="'.JHtml::tooltipText($_tooltip_title, $_tooltip_content, 0, 0).'"' :
					'class="hasTip fcicon-lang" title="'.$_tooltip_title.'::'.$_tooltip_content.'"';
			} else {
				$_attribs = ' class="fcicon-lang"';
			}
			$lang .= "\n".'<img src="'.$langs->{$file_data->language}->imgsrc.'" '.$_attribs.' /> ';
		}
		if ( $add_lang_txt ) {
			$lang .= '['. ($file_data->language=='*' ? JText::_("FLEXI_ALL_LANGUAGES") : $langs->{$file_data->language}->name) .']';
		}
		$lang .= '</span>';
	}
	
	
	// c. SIZE: in KBs / MBs
	$sizeinfo = '';
	if ($display_size)
	{
		$sizeinfo = '<span class="fcfile_size">';
		if ($display_size==1)
			$sizeinfo .= '<span class="badge">'.number_format($file_data->size / 1024, 0).'&nbsp;'.JTEXT::_('FLEXI_KBS').'</span>';
		else if ($display_size==2)
			$sizeinfo .= '<span class="badge">'.number_format($file_data->size / 1048576, 2).'&nbsp;'.JTEXT::_('FLEXI_MBS').'</span>';
		else
			$sizeinfo .= '<span class="badge">'.number_format($file_data->size / 1073741824, 2).'&nbsp;'.JTEXT::_('FLEXI_GBS').'</span>';
		$sizeinfo .= '</span>';
	}
	
	
	// d. HITS: either as icon or as inline text or both
	$hits = '';
	if ($display_hits)
	{
		$hits = '<span class="fcfile_hits">';
		if ( $add_hits_img && @ $hits_icon ) {
			$hits .= sprintf($hits_icon, $file_data->hits);
		}
		if ( $add_hits_txt ) {
			$hits .= '<span class="badge badge-info">'.$file_data->hits.'&nbsp;'.JTEXT::_('FLEXI_HITS').'</span>';
		}
		$hits .= '</span>';
	}
	
	
	// e. FILENAME / TITLE: decide whether to show it (if we do not use button, then displaying of filename is forced)
	$_filetitle = $file_data->altname ? $file_data->altname : $file_data->filename;
	if ($lowercase_filename) $_filetitle = mb_strtolower( $_filetitle, "UTF-8");
	
	$filename_original = $file_data->filename_original ? $file_data->filename_original : $file_data->filename;
	$$filename_original = str_replace( array("'", "\""), array("\\'", ""), $filename_original );
	$filename_original = htmlspecialchars($filename_original, ENT_COMPAT, 'UTF-8');
	
	$name_str   = $display_filename==2 ? $filename_original : $_filetitle;
	$name_classes = $file_classes.($file_classes ? ' ' : '').'badge fcfile_title';
	$name_html  = '<span class="'.$name_classes.'">'. $name_str . '</span>';
	
	
	// f. DESCRIPTION: either as tooltip or as inline text
	$descr_tip = $descr_inline = $descr_icon = '';
	if (!empty($file_data->description)) {
		if ( !$authorized ) {
			if ($noaccess_display != 2 ) {
				$descr_tip    = flexicontent_html::escapeJsText($name_str . '::' . $file_data->description,'s');
				$descr_icon = '<img src="components/com_flexicontent/assets/images/comment.png" class="hasTip" title="'. $descr_tip .'"/>';
				$descr_inline  = '';
			}
		} else if ($display_descr==1 || $prop=='namelist') {   // As tooltip
			$descr_tip    = flexicontent_html::escapeJsText($name_str . '::' . $file_data->description,'s');
			$descr_icon = '<img src="components/com_flexicontent/assets/images/comment.png" class="hasTip" title="'. $descr_tip .'"/>';
			$descr_inline  = '';
		} else if ($display_descr==2) {  // As inline text
			$descr_inline = ' <span class="fcfile_descr_inline alert alert-info fc-iblock">'. nl2br($file_data->description) . '</span>';
		}
		if ($descr_icon) $descr_icon = ' <span class="fcfile_descr_tip">'. $descr_icon . '</span>';
	}
	
	
	
	
	// *****************************
	// Create field's displayed html
	// *****************************
	
	$str = '';
	
	// [1]: either create the download link -or- use no authorized link ...
	if ( !$authorized ) {
		$dl_link = $noaccess_url;
		if ($noaccess_msg) {
			$str = '<span class="fcfile_noauth_msg alert">' .$noaccess_msg. '</span> ';
		}
	} else {
		$dl_link = JRoute::_( 'index.php?option=com_flexicontent&id='. $file_id .'&cid='.$item->id.'&fid='.$field->id.'&task=download' );
	}
	
	// SOME behavior FLAGS
	$not_downloadable = !$dl_link || $prop=='namelist';
	$filename_shown = (!$authorized || $show_filename);
	$filename_shown_as_link = $filename_shown && $link_filename && !$usebutton;
	
	
	// [2]: Add information properties: filename, and icons with optional inline text
	$info_arr = array();
	if ( ($filename_shown && !$filename_shown_as_link) || $not_downloadable ) {   // Filename will be shown if not l
		$info_arr[] = $icon .' '. $name_html;
	}
	if ($lang) $info_arr[] = $lang;
	if ($sizeinfo) $info_arr[] = $sizeinfo;
	if ($hits) $info_arr[] = $hits;
	if ($descr_icon) $info_arr[] = $descr_icon;
	$str .= implode($info_arr, $infoseptxt);
	
	// [3]: Display the buttons:  DOWNLOAD, SHARE, ADD TO CART
	
	$actions_arr = array();
	
	// ***********************
	// CASE 1: no download ... 
	// ***********************
	
	// EITHER (a) Current user NOT authorized to download file AND no access URL is not configured
	// OR     (b) creating a file list with no download links, (the 'prop' display variable is 'namelist')
	if ( $not_downloadable ) {
		// nothing to do here, the file name/title will be shown above
	}
	
	
	// *****************************************************************************************
	// CASE 2: Display download button passing file variables via a mini form
	// (NOTE: the form action can be a no access url if user is not authorized to download file)
	// *****************************************************************************************
	
	else if ($usebutton) {
		
		$file_classes .= ($file_classes ? ' ' : '').(FLEXI_J16GE ? 'btn' : 'fc_button fcsimple');   // Add an extra css class (button display)
		
		// DOWNLOAD: single file instant download
		if ($allowdownloads) {
			// NO ACCESS: add file info via form field elements, in case the URL target needs to use them
			$file_data_fields = "";
			if ( !$authorized && $noaccess_addvars) {
				$file_data_fields =
					'<input type="hidden" name="fc_field_id" value="'.$field->id.'"/>'."\n".
					'<input type="hidden" name="fc_item_id" value="'.$item->id.'"/>'."\n".
					'<input type="hidden" name="fc_file_id" value="'.$file_id.'"/>'."\n";
			}
			
			// The download button in a mini form ...
			$actions_arr[] = ''
				.'<form id="form-download-'.$field->id.'-'.($n+1).'" method="post" action="'.$dl_link.'" style="display:inline-block;" >'
				.$file_data_fields
				.'<input type="submit" name="download-'.$field->id.'[]" class="'.$file_classes.' btn-success fcfile_downloadFile" title="'.$downloadsinfo.'" value="'.$downloadstext.'"/>'
				.'</form>'."\n";
		}
		
		if ($authorized && $allowview && !$file_data->url) {
			$actions_arr[] = '
				<a href="'.$dl_link.'?method=view" class="fancybox '.$file_classes.' btn-info fcfile_viewFile" data-fancybox-type="iframe" title="'.$viewinfo.'" style="line-height:1.3em;" >
					'.$viewtext.'
				</a>';
			$fancybox_needed = 1;
		}
		
		// ADD TO CART: the link will add file to download list (tree) (handled via a downloads manager module)
		if ($authorized && $allowaddtocart && !$file_data->url) {
			// CSS class to anchor downloads list adding function
			$addtocart_classes = $file_classes. ($file_classes ? ' ' : '') .'fcfile_addFile';
			
			$attribs  = ' class="'. $addtocart_classes .'"';
			$attribs .= ' title="'. $addtocartinfo .'"';
			$attribs .= ' filename="'. flexicontent_html::escapeJsText($_filetitle,'s') .'"';
			$attribs .= ' fieldid="'. $field->id .'"';
			$attribs .= ' contentid="'. $item->id .'"';
			$attribs .= ' fileid="'. $file_data->id .'"';
			$actions_arr[] =
				'<input type="button" '. $attribs .' value="'.$addtocarttext.'" />';
		}
		
		
		// SHARE FILE VIA EMAIL: open a popup or inline email form ...
		if ($is_public && $allowshare && !$com_mailto_found) {
			// skip share popup form button if com_mailto is missing
			$actions_arr[] =
				' com_mailto component not found, please disable <b>download link sharing parameter</b> in this file field';
		} else if ($is_public && $allowshare) {
			$send_onclick = 'window.open(\'%s\',\'win2\',\''.$status.'\'); return false;';
			$send_form_url = 'index.php?option=com_flexicontent&tmpl=component'
				.'&task=call_extfunc&exttype=plugins&extfolder=flexicontent_fields&extname=file&extfunc=share_file_form'
				.'&file_id='.$file_id.'&content_id='.$item->id.'&field_id='.$field->id;
			$actions_arr[] =
				'<input type="button" class="'.$file_classes.' fcfile_shareFile" onclick="'
					.sprintf($send_onclick, JRoute::_($send_form_url)).'" title="'.$shareinfo.'" value="'.$sharetext.'" />';
		}
	}
	
	
	// *******************************************************************************************
	// CASE 3: display a download link (with file title or filename) passing variables via the URL 
	// (NOTE: the target link can be a no access url if user is not authorized to download file)
	// *******************************************************************************************
	
	else {
		
		// DOWNLOAD: single file instant download
		if ($allowdownloads) {
			// NO ACCESS: add file info via URL variables, in case the URL target needs to use them
			if ( !$authorized && $noaccess_addvars) {
				$dl_link .=
					'&fc_field_id="'.$field->id.
					'&fc_item_id="'.$item->id.
					'&fc_file_id="'.$file_id;
			}
			
			// The download link, if filename/title not shown, then display a 'download' prompt text
			$actions_arr[] =
				($filename_shown && $link_filename ? $icon.' ' : '')
				.'<a href="' . $dl_link . '" class="'.$file_classes.' fcfile_downloadFile" title="'.$downloadsinfo.'" >'
				.($filename_shown && $link_filename ? $name_str : $downloadstext)
				.'</a>';
		}
		
		if ($authorized && $allowview && !$file_data->url) {
			$actions_arr[] = '
				<a href="'.$dl_link.'?method=view" class="fancybox '.$file_classes.' fcfile_viewFile" data-fancybox-type="iframe" title="'.$viewinfo.'" >
					'.$viewtext.'
				</a>';
			$fancybox_needed = 1;
		}
		
		// ADD TO CART: the link will add file to download list (tree) (handled via a downloads manager module)
		if ($authorized && $allowaddtocart && !$file_data->url) {
			// CSS class to anchor downloads list adding function
			$addtocart_classes = $file_classes. ($file_classes ? ' ' : '') .'fcfile_addFile';
			
			$attribs  = ' class="'. $addtocart_classes .'"';
			$attribs .= ' title="'. $addtocartinfo .'"';
			$attribs .= ' filename="'. flexicontent_html::escapeJsText($_filetitle,'s') .'"';
			$attribs .= ' fieldid="'. $field->id .'"';
			$attribs .= ' contentid="'. $item->id .'"';
			$attribs .= ' fileid="'. $file_data->id .'"';
			$actions_arr[] =
				'<a href="javascript:;" '. $attribs .' >'
				.$addtocarttext
				.'</a>';
		}
		
		// SHARE FILE VIA EMAIL: open a popup or inline email form ...
		if ($is_public && $allowshare && !$com_mailto_found) {
			// skip share popup form button if com_mailto is missing
			$str .= ' com_mailto component not found, please disable <b>download link sharing parameter</b> in this file field';
		} else if ($is_public && $allowshare) {
			$send_onclick = 'window.open(\'%s\',\'win2\',\''.$status.'\'); return false;';
			$send_form_url = 'index.php?option=com_flexicontent&tmpl=component'
				.'&task=call_extfunc&exttype=plugins&extfolder=flexicontent_fields&extname=file&extfunc=share_file_form'
				.'&file_id='.$file_id.'&content_id='.$item->id.'&field_id='.$field->id;
			$actions_arr[] =
				'<a href="javascript:;" class="fcfile_shareFile" onclick="'.sprintf($send_onclick, JRoute::_($send_form_url)).'" title="'.$shareinfo.'">'
				.$sharetext
				.'</a>';
		}
	}
	
	//Display the buttons "DOWNLOAD, SHARE, ADD TO CART" before or after the filename
	if ($buttonsposition) {
		$str .= (count($actions_arr) ?  $infoseptxt : "")
			.'<span class="fcfile_actions">'
			.  implode($actions_arr, $actionseptxt)
			.'</span>';
	} else {
		$str = (count($actions_arr) ?  $infoseptxt : "")
			.'<span class="fcfile_actions">'
			.  implode($actions_arr, $actionseptxt)
			.'</span>'.$str;
	}
	
	// [4]: Add the file description (if displayed inline)
	if ($descr_inline) $str .= $descr_inline;
	
	
	// Values Prefix and Suffix Texts
	$field->{$prop}[]	=  $pretext . $str . $posttext;
	
	// Some extra data for developers: (absolute) file URL and (absolute) file path
	$field->url[] = $dl_link;
	$field->abspath[] = $abspath;
	$field->file_data[] = $file_data;
	
	$n++;
}
