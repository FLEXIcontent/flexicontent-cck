<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
use Joomla\String\StringHelper;
$isAdmin = Factory::getApplication()->isClient('administrator');

// Inline SVG icons — no external font dependencies
$svg_flag     = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M14.778.085A.5.5 0 0 1 15 .5V8a.5.5 0 0 1-.314.464L14.5 8l.186.464-.003.001-.006.003-.023.009a12.435 12.435 0 0 1-.397.15c-.264.095-.631.223-1.047.35-.816.252-1.879.523-2.71.523-.847 0-1.548-.28-2.158-.525l-.028-.01C7.68 8.71 7.14 8.5 6.5 8.5c-.7 0-1.638.23-2.437.477A19.626 19.626 0 0 0 3 9.342V15.5a.5.5 0 0 1-1 0V.5a.5.5 0 0 1 1 0v.282c.226-.079.496-.17.79-.26C4.606.272 5.67 0 6.5 0c.84 0 1.524.277 2.121.519l.043.018C9.286.788 9.828 1 10.5 1c.7 0 1.638-.23 2.437-.477a19.587 19.587 0 0 0 1.349-.476l.019-.007.004-.002h.001"/></svg>';
$svg_archive  = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M0 2a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1v7.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 1 12.5V5a1 1 0 0 1-1-1V2zm2 3v7.5A1.5 1.5 0 0 0 3.5 14h9a1.5 1.5 0 0 0 1.5-1.5V5H2zm13-3H1v2h14V2zM5 7.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5z"/></svg>';
$svg_eye      = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>';
$svg_download = '<svg viewBox="0 0 384 512" width="1.3em" height="1.1em" fill="currentColor" aria-hidden="true"><path d="M169.4 470.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 370.8 224 64c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 306.7L54.6 265.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z"/></svg>';
$svg_cart     = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM5 13a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm7 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>';
$svg_mail     = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555zM0 4.697v7.104l5.803-3.558L0 4.697zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757zm3.436-.586L16 11.801V4.697l-5.803 3.546z"/></svg>';
$svg_play     = '<svg viewBox="0 0 16 16" width="1.3em" height="1.3em" fill="currentColor" aria-hidden="true"><path d="m11.596 8.697-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.693-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393z"/></svg>';
$svg_pause    = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M5.5 3.5A1.5 1.5 0 0 1 7 5v6a1.5 1.5 0 0 1-3 0V5a1.5 1.5 0 0 1 1.5-1.5zm5 0A1.5 1.5 0 0 1 12 5v6a1.5 1.5 0 0 1-3 0V5a1.5 1.5 0 0 1 1.5-1.5z"/></svg>';
$svg_stop     = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M5 3.5h6A1.5 1.5 0 0 1 12.5 5v6a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 11V5A1.5 1.5 0 0 1 5 3.5z"/></svg>';
$svg_loop     = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/><path d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/></svg>';
$svg_headphones = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M8 3a5 5 0 0 0-5 5v1h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8a6 6 0 1 1 12 0v5a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1V8a5 5 0 0 0-5-5z"/></svg>';
$svg_music    = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M9 13c0 1.105-1.12 2-2.5 2S4 14.105 4 13s1.12-2 2.5-2 2.5.895 2.5 2zm0-10.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0 0 1H8V10H6.5a.5.5 0 0 0 0 1H8v2a.5.5 0 0 0 1 0V2.5z"/></svg>';
$svg_sliders  = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M11.5 2a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM9.05 3a2.5 2.5 0 0 1 4.9 0H16v1h-2.05a2.5 2.5 0 0 1-4.9 0H0V3h9.05zM4.5 7a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM2.05 8a2.5 2.5 0 0 1 4.9 0H16v1H6.95a2.5 2.5 0 0 1-4.9 0H0V8h2.05zm9.45 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm-2.45 1a2.5 2.5 0 0 1 4.9 0H16v1h-2.05a2.5 2.5 0 0 1-4.9 0H0v-1h9.05z"/></svg>';
$svg_wave     = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M8 15a.5.5 0 0 1-.5-.5v-13a.5.5 0 0 1 1 0v13a.5.5 0 0 1-.5.5zm2-2a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 1 0v9a.5.5 0 0 1-.5.5zm-4 0a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 1 0v9a.5.5 0 0 1-.5.5zm6-2a.5.5 0 0 1-.5-.5v-5a.5.5 0 0 1 1 0v5a.5.5 0 0 1-.5.5zm-8 0a.5.5 0 0 1-.5-.5v-5a.5.5 0 0 1 1 0v5a.5.5 0 0 1-.5.5zm10-2a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 1 0v1a.5.5 0 0 1-.5.5zm-12 0a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 1 0v1a.5.5 0 0 1-.5.5z"/></svg>';
$svg_clock    = '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M8 3.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9zM1 8a7 7 0 1 1 14 0A7 7 0 0 1 1 8z"/><path d="M7.5 5a.5.5 0 0 1 .5.5v2.5l1.75 1.75a.5.5 0 0 1-.707.707l-2-2A.5.5 0 0 1 7 8V5.5a.5.5 0 0 1 .5-.5z"/></svg>';

// Important create a -1 "value", before any other normal values, so that it is at 1st position of the array
$field->{$prop}[-1] = '';

$field->url = array();
$field->abspath = array();
$field->file_data = array();
$field->hits_total = 0;

$compactWaveform  = true;
$create_preview   = (int) $field->parameters->get('mm_create_preview', 1);
$wf_zoom_slider   = (int) $field->parameters->get('wf_zoom_slider', 1);
$wf_load_progress = (int) $field->parameters->get('wf_load_progress', 1);
$wf_add_waveform  = (int) $field->parameters->get('wf_add_waveform' . ($view === 'item' ? '' : '_cat'), 1);

$compact_display    = (int) $field->parameters->get('compact_display', 0);
$compact_display    = static::$isItemsManager ? 2 : $compact_display;

$infoseptxt   = $compact_display ? ' ' : $infoseptxt;
$actionseptxt = $compact_display ? ' ' : $actionseptxt;

$per_value_js = "";
$n = 0;
$i = 0;

foreach($values as $file_id)
{
	// Skip empty value but add empty placeholder if inside fieldgroup
	if (empty($file_id) || !isset($files_data[$file_id]))
	{
		if ($is_ingroup)
		{
			$field->{$prop}[$n++] = '';
		}
		continue;
	}
	$file_data = $files_data[$file_id];

	// Use a random integer so that the player can be displayed multiple times inside same page
	$FN_n      = $field_name_js.'_'.$n . '_' . mt_rand();


	// ***
	// *** Check if it exists and get file size
	// ***

	$basePath = $file_data->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;
	if (!$file_data->url)
	{
		$abspath = str_replace(DS, '/', Path::clean($basePath.DS.$file_data->filename));
	}
	else
	{
		$abspath = $file_data->url == 2
			? Path::clean(JPATH_ROOT.DS.$file_data->filename)
			: $file_data->filename;
	}

	$_size = '-';

	if ($display_size)
	{
		if ($file_data->url == 1)
		{
			$_size = (int)$file_data->size ? (int)$file_data->size : '-';
		}
		elseif (file_exists($abspath))
		{
			$_size = filesize($abspath);
		}

		// Override DB size with the calculated file size
		$file_data->size = (int) $_size;
	}

	// Force new window for URLs that have zero file's size
	$non_file_url = $file_data->url == 1 && !$file_data->size;


	// ***
	// *** Check user access on the file
	// ***

	$authorized = true;
	$is_public  = true;
	if ( !empty($file_data->access) )
	{
		$authorized = in_array($file_data->access,$aid_arr);
		$is_public  = in_array($public_acclevel,$aid_arr);
	}

	// If no access and set not to show 'no-access' message then skip the value, if not in field group
	if ( !$authorized && !$noaccess_display )
	{
		// Some extra data for developers: (absolute) file URL and (absolute) file path
		if ($is_ingroup)
		{
			$field->{$prop}[$n++]	=  '';
		}
		continue;
	}

	// Initialize CSS classes variable
	$file_classes = !$authorized ? 'fcfile_noauth' : '';
	$analytics_classes = ' piwik_download ';


	// ***
	// *** Prepare displayed information
	// ***

	// a. ICON: create it according to filetype
	$icon = '';
	if ($useicon)
	{
		$file_data = $this->addIcon( $file_data );
		$_tooltip_title   = '';
		$_tooltip_content = \Joomla\CMS\Language\Text::_( 'FLEXI_FIELD_FILE_TYPE', true ) .': '. $file_data->ext;
		$icon = '
		<span class="fcfile_mime">
			' . \Joomla\CMS\HTML\HTMLHelper::image($file_data->icon, $file_data->ext, 'class="fcicon-mime '.$tooltip_class.'" title="'.\Joomla\CMS\HTML\HTMLHelper::tooltipText($_tooltip_title, $_tooltip_content, 1, 0).'"') . '
		</span>';
	}

	// b. LANGUAGE: either as icon or as inline text or both
	$lang = '';
	$file_data->language = $file_data->language == '' ? '*' : $file_data->language;

	// Also show 'ALL' language
	if ($display_lang)
	{
		$lang = '<span class="fcfile_lang fc-iblock">';

		$lang .= $display_lang == 1 || $display_lang == 3 ? $svg_flag . ' ' : '';
		$lang .= $display_lang == 2 || $display_lang == 3 ? '<span class="fcfile_lang_label label">' . \Joomla\CMS\Language\Text::_('FLEXI_LANGUAGE'). '</span> ' : '';
		$lang .=
		'<span class="fcfile_lang_value value">'
			. ($file_data->language === '*' ? \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_ALL_LANGS') : $langs->{$file_data->language}->name) .
		'</span>';

		$lang .= '</span>';
	}


	/**
	 * c. SIZE: in KBs / MBs
	 */
	$sizeinfo = '';

	if ($display_size)
	{
		$sizeinfo .= '
		<span class="fcfile_size fc-iblock">';

		$sizeinfo .= $display_size == 1 || $display_size == 3 ? $svg_archive . ' ' : '';
		$sizeinfo .= $display_size == 2 || $display_size == 3 ? '<span class="fcfile_size_label label">' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_SIZE') . '</span> ' : '';

		if (!is_numeric($_size))
		{
			$sizeinfo .= '<span class="fcfile_size_value value">' . $_size . '</span>';
		}
		elseif ($_size < 1048576)
		{
			$sizeinfo .= '<span class="fcfile_size_value value">' . number_format($_size / 1024, 0) . '&nbsp;'. \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_KBS').'</span>';
		}
		elseif ($_size < 1073741824)
		{
			$sizeinfo .= '<span class="fcfile_size_value value">' . number_format($_size / 1048576, 2) . '&nbsp;'. \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_MBS').'</span>';
		}
		else
		{
			$sizeinfo .= '<span class="fcfile_size_value value">' . number_format($_size / 1073741824, 2) . '&nbsp;'. \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_GBS').'</span>';
		}

		$sizeinfo .= '</span>';
	}


	/**
	 * d. HITS: either as icon or as inline text or both
	 */
	$hits = '';

	if ($display_hits)
	{
		$hits = '
		<span class="fcfile_hits fc-iblock">';

		$hits .= $display_hits == 1 || $display_hits == 3 ? $svg_eye . ' ' : '';
		$hits .= $display_hits == 2 || $display_hits == 3 ? '<span class="fcfile_hits_label label">' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_HITS'). '</span> ' : '';
		$hits .= '<span class="fcfile_hits_value value">'.$file_data->hits.'</span>';

		$hits .= '</span>';
	}

	$field->hits_total += $file_data->hits;


	// e. FILENAME / TITLE: decide whether to show it (if we do not use button, then displaying of filename is forced)
	$filetitle = $file_data->altname ? $file_data->altname : $file_data->filename;
	$filetitle_escaped = htmlspecialchars($filetitle, ENT_COMPAT, 'UTF-8');

	if ($lowercase_filename) $filetitle = StringHelper::strtolower( $filetitle );
	$filename_original = $file_data->filename_original ? $file_data->filename_original : $file_data->filename;

	$name_str = $display_filename==2 ? $filename_original : $filetitle;
	$name_escaped = htmlspecialchars($name_str, ENT_COMPAT, 'UTF-8');

	$name_classes = $file_classes . ' fcfile_title';
	$name_html = '<span class="' . $name_classes . '">'. $name_str . '</span>';


	// f. DESCRIPTION: either as tooltip or as inline text
	$descr_tip = $descr_inline = $descr_icon = '';
	if (!empty($file_data->description))
	{
		// Not authorized
		if (!$authorized)
		{
			if ($noaccess_display != 2)
			{
				$descr_tip  = \Joomla\CMS\HTML\HTMLHelper::tooltipText($name_str, $file_data->description, 0, 1);
				$descr_icon = '<img src="components/com_flexicontent/assets/images/comments.png" class="hasTooltip" alt="'.$name_escaped.'" title="'. $descr_tip .'"/>';
				$descr_inline  = '';
			}
		}

		// As tooltip
		elseif ($display_descr==1 || $prop=='namelist')
		{
			$descr_tip  = \Joomla\CMS\HTML\HTMLHelper::tooltipText($name_str, $file_data->description, 0, 1);
			$descr_icon = '<img src="components/com_flexicontent/assets/images/comments.png" class="hasTooltip" alt="'.$name_escaped.'" title="'. $descr_tip .'"/>';
			$descr_inline  = '';
		}

		// As inline text
		elseif ($display_descr==2)
		{
			$descr_inline = ' <div class="fcfile_descr_inline alert alert-info">'. nl2br($file_data->description) . '</div>';
		}

		if ($descr_icon)
		{
			$descr_icon = '
			<span class="fcfile_descr_tip">
				<span class="fcfile_descr_tip_label label">
					' . \Joomla\CMS\Language\Text::_('FLEXI_DESCRIPTION'). '
				</span>
				'. $descr_icon . '
			</span>
		';
		}
	}


	// ***
	// *** Create field's displayed html
	// ***

	$html = '';
	$noauth = '';


	/**
	 * Either create the download link -or- use no authorized link ...
	 */
	if (!$authorized)
	{
		$dl_link = $noaccess_url;
		if ($noaccess_msg)
		{
			$noauth = '<div class="fcfile_noauth_msg alert alert-warning">' .$noaccess_msg. '</div> ';
		}
	}
	else
	{
		$dl_link = 'index.php?option=com_flexicontent&id='. $file_id .'&cid='.$item->id.'&fid='.$field->id.'&task=download';
		$dl_link = $isAdmin ? flexicontent_html::getSefUrl($dl_link) : \Joomla\CMS\Router\Route::_( $dl_link );
	}

	// SOME behavior FLAGS
	$not_downloadable = !$dl_link || $prop=='namelist';
	$filename_shown = (!$authorized || $show_filename);
	$filename_shown_as_link = $filename_shown && $link_filename && !$usebutton;



/**
 * ****** SKIP THIS PART IF display_properties_only
 */
$is_csv_export = $prop === 'csv_export';

if ($is_csv_export) {
	$html .= $abspath;
}
else if ($prop !== 'display_properties_only') :

	// [0]: filename (if visible)
	if ((($filename_shown && !$filename_shown_as_link) || $not_downloadable) && $display_filename != -1)
	{
		$html .= '<div class="fcfile_name' . ($compact_display ? ' fcfile_compact' : '') . '">' . $icon . ' ' . $name_html . '</div>';
	}


	// [1]: Not authorized message
	$html .= $noauth;


	// [2]: Add information properties: filename, and icons with optional inline text
	$info_arr = array();

	if ($lang)       $info_arr[] = $lang;
	if ($sizeinfo)   $info_arr[] = $sizeinfo;
	if ($hits)       $info_arr[] = $hits;
	if ($descr_icon) $info_arr[] = $descr_icon;

	$html .= (!$compact_display ? '<div class="fcclear"></div>' : '') . implode($infoseptxt, $info_arr);


	// [3]: Add the file description (if displayed inline)
	if ($descr_inline && $compact_display != 2)
	{
		$html .= '<div class="fcclear"></div>' . $descr_inline . '<div class="fcclear"></div>' ;
	}


	// [4]: Display the buttons:  DOWNLOAD, SHARE, ADD TO CART

	$actions_arr = array();


	// ***
	// *** CASE 1: no download ...
	// ***

	// EITHER (a) Current user NOT authorized to download file AND no access URL is not configured
	// OR     (b) creating a file list with no download links, (the 'prop' display variable is 'namelist')
	if ( $not_downloadable )
	{
		// nothing to do here, the file name/title will be shown above
	}


	// ***
	// *** CASE 2: Display download button passing file variables via a mini form
	// *** (NOTE: the form action can be a no access url if user is not authorized to download file)
	// ***

	else if ($usebutton)
	{
		$file_classes .= ' btn';  // ' fc_button fcsimple';   // Add an extra css class (button display)
		$file_classes .= (static::$isItemsManager ? ' btn-small' : '');

		// DOWNLOAD: single file instant download
		if ($allowdownloads)
		{
			// NO ACCESS: add file info via URL variables, in case the URL target needs to use them
			if (!$authorized && $noaccess_addvars)
			{
				$vars = array(
					'fc_field_id="' . $field->id,
					'fc_item_id="' . $item->id,
					'fc_file_id="' . $file_id,
				);
				$dl_link .= strpos($dl_link, '?') !== false ? '&amp;' : '?';
				$dl_link .= implode('&amp;', $vars);
			}

			// The Download Button
			$_download_btn_html = '
				<button type="button" onclick="window.open(\''.$dl_link.'\', ' . ($non_file_url ? "''": "'_self'") . ')"
					class="' . $file_classes . ' btn-success fcfile_downloadFile ' . $analytics_classes . '" title="'.htmlspecialchars($downloadsinfo, ENT_COMPAT, 'UTF-8').'"
				>
					' . ($compact_display != 2 ? $downloadstext : '') . '
					' . ($compact_display == 2 ? ' ' . $svg_download : '') . '
				</button>';
			// Do not add it here ... we will add it inline with player
			//$actions_arr[] = $_download_btn_html;
		}

		if ($authorized && $allowview && !$file_data->url)
		{
			$view_link = $dl_link . (strpos($dl_link, '?') !== false ? '&amp;' : '?') . 'method=view';
			$view_file_classes = $file_classes . ' btn-info fcfile_viewFile';
			$actions_arr[] = '
				<button type="button" data-href="' . $view_link . '" class="' . $view_file_classes .'" title="' . $viewinfo . '" '
					. ($viewinside>=2 ? ' onclick="var url = jQuery(this).attr(\'data-href\'); window.open(url, ' . ($viewinside==3 ? "'_self'" : "") . ');" ' : '')
					. ($viewinside==1 ? ' onclick="var url = jQuery(this).attr(\'data-href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title:\''. $filetitle_escaped .'\'}); return false;" ' : '')
					. ($viewinside==0 ? ' onclick="var url = jQuery(this).attr(\'data-href\'); jQuery.fancybox.open([{ src: url , type: \'iframe\'}]); "' : '')
					. '
				>
					' . ($compact_display != 2 ? $viewtext : '') . '
					' . ($compact_display == 2 ? ' ' . $svg_eye : '') . '
				</button>';
			$fancybox_needed = $viewinside == 0;
		}

		// ADD TO CART: the link will add file to download list (tree) (handled via a downloads manager module)
		if ($authorized && $allowaddtocart && !$file_data->url)
		{
			// CSS class to anchor downloads list adding function
			$addtocart_classes = $file_classes . ' fcfile_addFile';

			$attribs = ' class="'. $addtocart_classes . '"'
				. ' title="'. $addtocartinfo .'"'
				. ' data-filename="'. $filetitle_escaped .'"'
				. ' data-fieldid="'. $field->id .'"'
				. ' data-contentid="'. $item->id .'"'
				. ' data-fileid="'. $file_data->id .'"';
			$actions_arr[] =
				'<button ' . $attribs . '>
					' . ($compact_display != 2 ? htmlspecialchars($addtocarttext, ENT_COMPAT, 'UTF-8') : '') . '
					' . ($compact_display == 2 ? ' ' . $svg_cart : '') . '
				</button>';
		}


		// SHARE FILE VIA EMAIL: open a popup or inline email form ...
		if ($is_public && $allowshare && !$com_mailto_found)
		{
			// skip share popup form button if com_mailto is missing
			$actions_arr[] =
				' com_mailto component not found, please disable <b>download link sharing parameter</b> in this file field';
		}
		else if ($is_public && $allowshare)
		{
			$send_form_url = 'index.php?option=com_flexicontent&tmpl=component'
				.'&task=call_extfunc&exttype=plugins&extfolder=flexicontent_fields&extname=file&extfunc=share_file_form'
				.'&file_id='.$file_id.'&content_id='.$item->id.'&field_id='.$field->id;
			$actions_arr[] =
				'<button class="' . $file_classes . ' fcfile_shareFile" title="'.$shareinfo.'" data-href="'.$send_form_url.'"
					onclick="var url = jQuery(this).attr(\'data-href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 800, 800, 0, {title:\''.htmlspecialchars($sharetext, ENT_COMPAT, 'UTF-8').'\'}); return false;" '.
				'>
					' . ($compact_display != 2 ? htmlspecialchars($sharetext, ENT_COMPAT, 'UTF-8') : '') . '
					' . ($compact_display == 2 ? ' ' . $svg_mail : '') . '
				</button>';
		}
	}


	// ***
	// *** CASE 3: display a download link (with file title or filename) passing variables via the URL
	// *** (NOTE: the target link can be a no access url if user is not authorized to download file)
	// ***

	else
	{
		// DOWNLOAD: single file instant download
		if ($allowdownloads)
		{
			// NO ACCESS: add file info via URL variables, in case the URL target needs to use them
			if ( !$authorized && $noaccess_addvars)
			{
				$vars = array(
					'fc_field_id="' . $field->id,
					'fc_item_id="' . $item->id,
					'fc_file_id="' . $file_id,
				);
				$dl_link .= strpos($dl_link, '?') !== false ? '&amp;' : '?';
				$dl_link .= implode('&amp;', $vars);
			}

			// The download link, if filename/title not shown, then display a 'download' prompt text
			$actions_arr[] =
				($filename_shown && $link_filename ? $icon.' ' : '')
				.'<a href="' . $dl_link . '" class="' . $file_classes . ' fcfile_downloadFile ' . $analytics_classes . '" title="' . htmlspecialchars($downloadsinfo, ENT_COMPAT, 'UTF-8') . '" ' . ($non_file_url ? 'target="_blank"' : '') . '>'
				.($filename_shown && $link_filename ? $name_str : $downloadstext)
				.'</a>';
		}

		if ($authorized && $allowview && !$file_data->url)
		{
			$actions_arr[] = '
				<a href="' . $dl_link . (strpos($dl_link, '?') !== false ? '&amp;' : '?') . 'method=view" ' . ($viewinside==2 ? 'target="_blank"' : '')
					. ' class="' . ($viewinside==0 ? 'fancybox ' : '') . $file_classes . ' fcfile_viewFile" '.($viewinside==0 ? 'data-type="iframe" ' : '')
					. ($viewinside==1 ? ' onclick="var url = jQuery(this).attr(\'href\');  fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title:\''. $filetitle_escaped .'\'}); return false;" ' : '')
					. ' title="' . $viewinfo . '" >
					' . $viewtext . '
				</a>';
			$fancybox_needed = $viewinside == 0;
		}

		// ADD TO CART: the link will add file to download list (tree) (handled via a downloads manager module)
		if ($authorized && $allowaddtocart && !$file_data->url)
		{
			// CSS class to anchor downloads list adding function
			$addtocart_classes = $file_classes . ' fcfile_addFile';

			$attribs  = ' class="'. $addtocart_classes .'"'
				. ' title="'. $addtocartinfo .'"'
				. ' filename="'. $filetitle_escaped .'"'
				. ' fieldid="'. $field->id .'"'
				. ' contentid="'. $item->id .'"'
				. ' fileid="'. $file_data->id .'"';
			$actions_arr[] = '
				<a href="javascript:;" '. $attribs .' >
					' . $addtocarttext . '
				</a>';
		}

		// SHARE FILE VIA EMAIL: open a popup or inline email form ...
		if ($is_public && $allowshare && !$com_mailto_found)
		{
			// skip share popup form button if com_mailto is missing
			$html .= ' com_mailto component not found, please disable <b>download link sharing parameter</b> in this file field';
		}
		else if ($is_public && $allowshare)
		{
			$send_form_url = 'index.php?option=com_flexicontent&tmpl=component'
				.'&task=call_extfunc&exttype=plugins&extfolder=flexicontent_fields&extname=file&extfunc=share_file_form'
				.'&file_id='.$file_id.'&content_id='.$item->id.'&field_id='.$field->id;
			$actions_arr[] =
				'<a href="'.$send_form_url.'" class="fcfile_shareFile" title="'.$shareinfo.'" '.
				'  onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 800, 800, 0, {title:\''.$sharetext.'\'}); return false;">'
				.$sharetext
				.'</a>';
		}
	}

	//Display the buttons "DOWNLOAD, SHARE, ADD TO CART" before or after the filename
	$html = (static::$isItemsManager || (!$html && !$actions_arr) ? '' : '<fieldset><legend></legend>') . '
		' .
		($buttonsposition ? $html : '') .
		($actions_arr ? '
			<div class="fcfile_actions ' . ($compact_display ? ' fcfile_compact' : '') . '">
				' . implode($actionseptxt, $actions_arr) . '
			</div>' : '') .
		(!$buttonsposition ? $html : '') .
		(static::$isItemsManager ? '' : '</fieldset>');

	if ($wf_add_waveform)
	{
		if ($create_preview)
		{
			$ext         = strtolower(flexicontent_upload::getExt($file_data->filename));
			$previewname = preg_replace('/\.' . $ext . '$/i', '', basename($file_data->filename)) . '.mp3';
			$peaksname   = preg_replace('/\.' . $ext . '$/i', '', basename($file_data->filename)) . '.json';
			$previewpath = 'audio_preview/' . $previewname;
			$peakspath   = 'audio_preview/' . $peaksname;
		}
		else
		{
			$previewpath = $file_data->filename;
			$peakspath   = null;
		}

		$fnn = $item->id . '_' . $FN_n;

		$html .= '<div class="fcclear"></div>'
		. '
		<div class="fc_mediafile_player_box' . ($compactWaveform ? ' fc_compact' : '') . '">

			<div class="fc_mediafile_controls_outer">

				<!--div id="fc_mediafile_current_time_' . $fnn . '" class="media_time">00:00:00</div-->
				<div id="fc_mediafile_controls_' . $fnn . '" class="fc_mediafile_controls">
					<a href="javascript:;" class="btn playBtn">
						' . $svg_play . '<span class="btnControlsText">' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIAFILE_PLAY') . '</span>
					</a>
					<a href="javascript:;" class="btn pauseBtn" style="display: none;">
						' . $svg_pause . '<span class="btnControlsText">' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIAFILE_PAUSE') . '</span>
					</a>
					<a href="javascript:;" class="btn stopBtn" style="display: none;">
						' . $svg_stop . '<span class="btnControlsText">' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIAFILE_STOP') . '</span>
					</a>
					<a href="javascript:;" class="btn loadBtn" style="display: none;">
						' . $svg_loop . '<span class="btnControlsText">' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIAFILE_LOAD') . '</span>
					</a>
					' . ($allowdownloads && isset($_download_btn_html) ? $_download_btn_html : '') . '
					' . (!$wf_zoom_slider ? '' : '
					<div class="fc_mediafile_wf_zoom_box">
						- <input id="fc_mediafile_slider_' . $fnn. '" type="range" min="0.5" max="200" value="0.5" class="fc_mediafile_wf_zoom" /> +
					</div>
					') . '
				</div>

			</div>

			<div class="fc_mediafile_audio_spectrum_box_outer" >

				<div id="fc_mediafile_audio_spectrum_box_' . $fnn . '" class="fc_mediafile_audio_spectrum_box"
					data-fc_tagid="'  . $fnn . '"
					data-fc_fname="' .$field_name_js . '"
				>
					<div id="fcview_' . $fnn . '_file-data-txt"
						data-filename="' . htmlspecialchars($previewpath, ENT_COMPAT, 'UTF-8') . '"
						data-wfpreview="' . htmlspecialchars($previewpath, ENT_COMPAT, 'UTF-8') . '"
						data-wfpeaks="' . htmlspecialchars($peakspath ?? '', ENT_COMPAT, 'UTF-8') . '"
						class="fc-wf-filedata"
					></div>
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
	}


endif;   // END OF   $prop !== 'display_properties_only'



	/**
	 * Basic display of audio / video media data
	 */
	$show_props      = (int) $field->parameters->get('mm_show_props' . ($view === 'item' ? '' : '_cat'), 1);
	$show_props_list = $field->parameters->get(
		'mm_show_props_list' . ($view === 'item' ? '' : '_cat'),
		array('media_format','bit_rate', 'bits_per_sample', 'sample_rate', 'duration', 'channels')
	);
	$show_props_list = FLEXIUtilities::paramToArray($show_props_list, "/[\s]*,[\s]*/", false, true);

	if (isset($file_data->media_type) && ( ($prop === 'display_properties_only' && $view === 'item') || $show_props ))
	{
		/*
		$mediadata = array(
			//'state', 'media_type', 'codec_type', 'codec_name', 'codec_long_name', 'resolution', 'fps',
			'media_format', 'bit_rate', 'bits_per_sample', 'sample_rate', 'duration',
			'channels', 'channel_layout', 'checked_out', 'checked_out_time');
		*/
		$mediadata = $show_props_list ?:
			array('media_format', 'bit_rate', 'bits_per_sample', 'sample_rate', 'duration', 'channels');

		$media_format = $file_data->media_format;

		$html .= '<table class="table audiotable">';
		foreach($mediadata as $md_name)
		{
			$PROP_NAME = $md_name;
			$PROP_VALUE = $file_data->$md_name;

			if ($md_name == 'channels')
			{
				$PROP_NAME = $svg_headphones . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_CHANNELS');

				// Only change value if it is 2 or 1
				if ($PROP_VALUE == 2 || $PROP_VALUE == 1)
				{
					$PROP_VALUE = $PROP_VALUE == 2 ? 'Stereo' : 'Mono';
				}

			}

			if ($md_name == 'media_format')
			{
				$PROP_NAME = $svg_music . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_MEDIA_TYPE');
			}

			if ($md_name == 'bit_rate')
			{
				$PROP_NAME = $svg_sliders . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_BIT_RATE');
				$PROP_VALUE = ($PROP_VALUE / 1000).' Kbps';
			}

			if ($md_name == 'bits_per_sample')
			{
				$PROP_NAME = $svg_sliders . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_BIT_DEPTH');
				$PROP_VALUE = $PROP_VALUE.' Bit';
			}

			if ($md_name == 'sample_rate')
			{
				$PROP_NAME = $svg_wave . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_SAMPLE_RATE');
				$PROP_VALUE = $PROP_VALUE.' Hz';
			}

			if ($md_name == 'duration')
			{
				$PROP_NAME = $svg_clock . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_DURATION');
				$PROP_VALUE = gmdate("H:i:s", $PROP_VALUE);
			}

			if ($md_name == 'bit_rate' && ($media_format == 'wav' || $media_format == 'aiff'))  continue;
			if ($md_name == 'bits_per_sample' && $media_format == 'mp3')  continue;

			$html .=  '
				<tr>
					<td class="key"> ' . $PROP_NAME . '</td>
					<td>' . $PROP_VALUE . '</td>
				</tr>';
		}

		$html .= '</table>';
	}


	// Values Prefix and Suffix Texts
	$field->{$prop}[$n]	=  !$is_csv_export
		? $pretext . $html . $posttext
		: $html;

	// Some extra data for developers: (absolute) file URL and (absolute) file path
	$field->url[$use_ingroup ? $n : $i] = $dl_link;
	$field->direct_url[$use_ingroup ? $n : $i] = $file_data->url == 2 ? Uri::root(true) . '/' . $file_data->filename : ($file_data->url == 1 ? $file_data->filename : $dl_link);
	$field->abspath[$use_ingroup ? $n : $i] = $abspath;
	$field->file_data[$use_ingroup ? $n : $i] = $file_data;

	/*if ($filename_original && $prop !== 'display_properties_only')
	{
		$per_value_js .= "
			fcview_mediafile.initValue('" . $fnn . "', '".$field_name_js."');
		";
	}*/

	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';

	$n++;
	$i++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}

Factory::getDocument()->addScriptDeclaration("
	fcview_mediafile_base_url['".$field_name_js."'] = '".$base_url."';

	" . (!$per_value_js ? "" : "
	//document.addEventListener('DOMContentLoaded', function()
	jQuery(document).ready(function()
	{
		" . $per_value_js . "
	});
"));


// ***
// *** Create total INFO
// ***

$file_totals = '';

// Total number of files
if ($display_total_count && $prop !== 'display_properties_only')
{
	$file_totals .= '
		<div class="fcfile_total_count">
			' . ($compact_display ? '' : '<span class="fcfile_total_count_label">'. $total_count_label .' </span>') . '
			<span class="fcfile_total_count_value badge">' . ($compact_display ? ' ' . $total_count_label. ': ' : '') . count($values) . '</span>
		</div>
	';
}

// Total download hits (of all files)
if ($display_total_hits && $compact_display != 2 && $field->hits_total && $prop !== 'display_properties_only')
{
	$file_totals .='
		<div class="fcfile_total_hits">
			<span class="fcfile_total_hits_label">'. $total_hits_label .' </span>
			<span class="fcfile_total_hits_value badge">'. $field->hits_total .'</span>
		</div>
	';
}

// Add -1 position (display at top of field or at top of field, or at top/bottom of field group)
if ($file_totals && $prop !== 'display_properties_only')
{
	$field->{$prop}[-1] = '
		<div class="' . ($compact_display == 2 ? '' : 'alert alert-success fcfile_total') . '">
			' . $file_totals . '
		</div>
		';
}
