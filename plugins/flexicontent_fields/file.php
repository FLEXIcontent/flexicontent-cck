<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.file
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsFile extends JPlugin
{
	static $field_types = array('file');
	var $task_callable = array('share_file_form', 'share_file_email');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsFile( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_file', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// some parameter shortcuts
		$document  = JFactory::getDocument();
		$app				= JFactory::getApplication();
		
		$size       = $field->parameters->get('size', 30 );
		$max_values = (int)$field->parameters->get('max_values', 0 );
		$required   = $field->parameters->get('required', 0 );
		$required   = $required ? ' required' : '';
		
		$fieldname = FLEXI_J16GE ? 'custom['.$field->name.'][]' : $field->name.'[]';
		
		if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
		$js = "
			var value_counter".$field->id."=".count($field->value).";
			var maxValues".$field->id."=".$max_values.";
			
			function qfSelectFile".$field->id."(id, file) {
				if((value_counter".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
			  value_counter".$field->id."++;
			  var valcounter = $('".$field->name."');
				valcounter.value = value_counter".$field->id.";
				
				var name 	= 'a_name'+id;
				var ixid 	= 'a_id'+id;
				var li 		= document.createElement('li');
				var txt		= document.createElement('span');
				var hid		= document.createElement('input');
				var span	= document.createElement('span');
				var img		= document.createElement('img');
				
				var filelist = document.getElementById('sortables_".$field->id."');
				
				$(span).addClass('fcfield-drag');
				
				var button = document.createElement('input');
				button.type = 'button';
				button.name = 'removebutton_'+id;
				button.id = 'removebutton_'+id;
				$(button).addClass('fcfield-button');
				$(button).addEvent('click', function() { deleteField".$field->id."(this) });
				button.value = '".JText::_( 'FLEXI_REMOVE_FILE',true )."';
				
				txt.type = 'text';
				txt.size = '".$size."';
				txt.readonly = 'readonly';  /*txt.disabled = 'disabled';*/  /*txt.dir='rtl';*/
				txt.id	= name;
				txt.innerHTML	= file;
				txt.addClass('fcfield_textval inputbox inline_style_published');
				
				hid.type = 'hidden';
				hid.name = '".$fieldname."';
				hid.value = id;
				hid.id = ixid;
				
				img.src = '".JURI::base()."components/com_flexicontent/assets/images/move2.png';
				img.alt = '".JText::_( 'FLEXI_CLICK_TO_DRAG',true )."';
				
				filelist.appendChild(li);
				li.appendChild(span);
				span.appendChild(img);
				li.appendChild(button);
				li.appendChild(txt);
				li.appendChild(hid);
				
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag',
					containment: 'parent',
					tolerance: 'pointer'
				});
			}
			";
		
		if (!FLEXI_J16GE) $document->addScript( JURI::root(true).'/components/com_flexicontent/assets/js/sortables.js' );
		
		// Add the drag and drop sorting feature
		$js .= "
		jQuery(document).ready(function(){
			jQuery('#sortables_".$field->id."').sortable({
				handle: '.fcfield-drag',
				containment: 'parent',
				tolerance: 'pointer'
			});
		});
		";
		
		$js .= "					
		function deleteField".$field->id."(el)
		{
		  value_counter".$field->id."--;
			
		  var valcounter = $('".$field->name."');
			if ( value_counter".$field->id." > 0 ) valcounter.value = value_counter".$field->id.";
			else valcounter.value = '';
			
			var row = jQuery(el).closest('li');
			jQuery(row).hide('slideUp', function() { $(this).remove(); } );
		}
		";
		
		$css = '
		#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
		#sortables_'.$field->id.' li {
			clear: both;
			display: block;
			list-style: none;
			height: auto;
			position: relative;
		}
		#sortables_'.$field->id.' li span.fcfield_textval { cursor:text; padding:4px!important; font-family:tahoma!important; white-space:pre-wrap!important; word-wrap:break-word!important; }
		#sortables_'.$field->id.' li span.inline_style_published   { color:#444!important; }
		#sortables_'.$field->id.' li span.inline_style_unpublished { background: #ffffff; color:gray; border-width:0px; text-decoration:line-through; }
		';
		
		$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_FILE' ).'" onclick="deleteField'.$field->id.'(this);" />';
		$move2 	= '<span class="fcfield-drag">'.JHTML::image ( JURI::base().'components/com_flexicontent/assets/images/move2.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		JHTML::_('behavior.modal', 'a.modal_'.$field->id);
		
		$files_data = !empty($field->value) ? $this->getFileData( $field->value, $published=false ) : array();
		$field->html = array();
		$i = 0;
		foreach($files_data as $file_id => $file_data) {
			/*$field->html[] = ($file_data->published ?
			'  <input class="fcfield_textval inputbox inline_style_published" size="'.$size.'" type="text" id="a_name'.$i.'" value="'.$file_data->filename.'" readonly="readonly" dir="rtl"/>' :
			'  <input class="fcfield_textval inputbox inline_style_unpublished" size="'.$size.'" style="'.$inline_style_unpublished.'" type="text" id="a_name'.$i.'" value="'.$file_data->filename.' [UNPUBLISHED]" readonly="readonly" dir="rtl"/>'
			)*/
			$field->html[] = $move2 . $remove_button .
				($file_data->published ?
				'  <span class="fcfield_textval inputbox inline_style_published" type="text" id="a_name'.$i.'" readonly="readonly" >'.$file_data->filename.'</span>' :
				'  <span class="fcfield_textval inputbox inline_style_unpublished" style="'.$inline_style_unpublished.'" type="text" id="a_name'.$i.'" [UNPUBLISHED]" readonly="readonly" >'.$file_data->filename.'</span>'
				)
				.'  <input type="hidden" id="a_id'.$i.'" name="'.$fieldname.'" value="'.$file_id.'" />'
			;
			$i++;
			//if ($max_values && $i >= $max_values) break;  // break out of the loop, if maximum file limit was reached
		}
		
		$field->html = '<li>'. implode('</li><li>', $field->html) .'</li>';
		$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
		
		$user = JFactory::getUser();
		$autoselect = $field->parameters->get( 'autoselect', 1 ) ;
		$linkfsel = JURI::base(true).'/index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;index='.$i.'&amp;field='.$field->id.'&amp;itemid='.$item->id.'&amp;autoselect='.$autoselect.'&amp;items=0&amp;filter_uploader='.$user->id.'&amp;'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()).'=1';
		$field->html .= "
		<div class=\"fcclear\"></div>
		<div class=\"fcfield-button-add\">
			<div class=\"blank\">
				<a class=\"modal_".$field->id."\" title=\"".JText::_( 'FLEXI_ADD_FILE' )."\" href=\"".$linkfsel."\" rel=\"{handler: 'iframe', size: {x:(MooTools.version>='1.2.4' ? window.getSize().x : window.getSize().size.x)-100, y: (MooTools.version>='1.2.4' ? window.getSize().y : window.getSize().size.y)-100}}\">".JText::_( 'FLEXI_ADD_FILE' )."</a>
			</div>
		</div>
		";
		
		$field->html .= '<input id="'.$field->name.'" class="'.$required.'" style="display:none;" name="__fcfld_valcnt__['.$field->name.']" value="'.($i ? $i : '').'" />';
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		static $langs = null;
		if ($langs === null) $langs = FLEXIUtilities::getLanguages('code');
		
		static $isMobile = null;
		static $isTablet = null;
		static $useMobile = null;
		if ($useMobile===null) 
		{
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			$force_desktop_layout = $cparams->get('force_desktop_layout', 0 );
			//$start_microtime = microtime(true);
			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			//$time_passed = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//printf('<br/>-- [Detect Mobile: %.3f s] ', $time_passed/1000000);
		}
		
		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;
		if ( empty($values) ) { $field->{$prop} = ''; return; }
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		// some parameter shortcuts
		$useicon = $field->parameters->get( 'useicon', 1 ) ;
		$lowercase_filename = $field->parameters->get( 'lowercase_filename', 1 ) ;
		$link_filename      = $field->parameters->get( 'link_filename', 1 ) ;
		$display_filename	= $field->parameters->get( 'display_filename', 1 ) ;
		$display_lang     = $field->parameters->get( 'display_lang', 1 ) ;
		$display_hits     = $field->parameters->get( 'display_hits', 0 ) ;
		$display_descr		= $field->parameters->get( 'display_descr', 1 ) ;
		
		$add_lang_img = $display_lang == 1 || $display_lang == 3;
		$add_lang_txt = $display_lang == 2 || $display_lang == 3 || $isMobile;
		$add_hits_img = $display_hits == 1 || $display_hits == 3;
		$add_hits_txt = $display_hits == 2 || $display_hits == 3 || $isMobile;
		
		$usebutton    = $field->parameters->get( 'usebutton', 1 ) ;
		$buttonsposition = $field->parameters->get('buttonsposition', 1);
		$use_infoseptxt   = $field->parameters->get( 'use_infoseptxt', 1 ) ;
		$use_actionseptxt = $field->parameters->get( 'use_actionseptxt', 1 ) ;
		$infoseptxt   = $use_infoseptxt   ?  ' '.$field->parameters->get( 'infoseptxt', '' ).' '    :  ' ';
		$actionseptxt = $use_actionseptxt ?  ' '.$field->parameters->get( 'actionseptxt', '' ).' '  :  ' ';
		
		$allowdownloads = $field->parameters->get( 'allowdownloads', 1 ) ;
		$downloadstext  = $allowdownloads==2 ? $field->parameters->get( 'downloadstext', 'FLEXI_DOWNLOAD' ) : 'FLEXI_DOWNLOAD';
		$downloadstext  = JText::_($downloadstext);
		$downloadsinfo  = JText::_('FLEXI_FIELD_FILE_DOWNLOAD_INFO', true);
		
		$allowview = $field->parameters->get( 'allowview', 0 ) ;
		$viewtext  = $allowview==2 ? $field->parameters->get( 'viewtext', 'FLEXI_FIELD_FILE_VIEW' ) : 'FLEXI_FIELD_FILE_VIEW';
		$viewtext  = JText::_($viewtext);
		$viewinfo  = JText::_('FLEXI_FIELD_FILE_VIEW_INFO', true);
		
		$allowshare = $field->parameters->get( 'allowshare', 0 ) ;
		$sharetext  = $allowshare==2 ? $field->parameters->get( 'sharetext', 'FLEXI_FIELD_FILE_EMAIL_TO_FRIEND' ) : 'FLEXI_FIELD_FILE_EMAIL_TO_FRIEND';
		$sharetext  = JText::_($sharetext);
		$shareinfo  = JText::_('FLEXI_FIELD_FILE_EMAIL_TO_FRIEND_INFO', true);
		
		$allowaddtocart = $field->parameters->get( 'use_downloads_manager', 0);
		$addtocarttext  = $allowaddtocart==2 ? $field->parameters->get( 'addtocarttext', 'FLEXI_FIELD_FILE_ADD_TO_DOWNLOADS_CART' ) : 'FLEXI_FIELD_FILE_ADD_TO_DOWNLOADS_CART';
		$addtocarttext  = JText::_($addtocarttext);
		$addtocartinfo  = JText::_('FLEXI_FIELD_FILE_ADD_TO_DOWNLOADS_CART_INFO', true);
		
		$noaccess_display	     = $field->parameters->get( 'noaccess_display', 1 ) ;
		$noaccess_url_unlogged = $field->parameters->get( 'noaccess_url_unlogged', false ) ;
		$noaccess_url_logged   = $field->parameters->get( 'noaccess_url_logged', false ) ;
		$noaccess_msg_unlogged = JText::_($field->parameters->get( 'noaccess_msg_unlogged', '' ));
		$noaccess_msg_logged   = JText::_($field->parameters->get( 'noaccess_msg_logged', '' ));
		$noaccess_addvars      = $field->parameters->get( 'noaccess_addvars', 0);

		// Select appropriate messages depending if user is logged on
		$noaccess_url = JFactory::getUser()->guest ? $noaccess_url_unlogged : $noaccess_url_logged;
		$noaccess_msg = JFactory::getUser()->guest ? $noaccess_msg_unlogged : $noaccess_msg_logged;
		
		// VERIFY downloads manager module is installed and enabled
		static $mod_is_enabled = null;
		if ($allowaddtocart && $mod_is_enabled === null) {
			$db = JFactory::getDBO();
			$query = "SELECT published FROM #__modules WHERE module = 'mod_flexidownloads' AND published = 1";
			$db->setQuery($query);
			$mod_is_enabled = $db->loadResult();
			if (!$mod_is_enabled) {
				$app = JFactory::getApplication();
				$app->enqueueMessage("FILE FIELD: please disable parameter \"Use Downloads Manager Module\", the module is not install or not published", 'message' );
			}
		}
		$allowaddtocart = $allowaddtocart ? $mod_is_enabled : 0;
		
		
		// Downloads manager feature
		if ($allowshare) {
			if (file_exists ( JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php' )) {
				$com_mailto_found = true;
				require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
				
				$status = 'width=700,height=360,menubar=yes,resizable=yes';
			} else {
				$com_mailto_found = false;
			}
		}
		
		if($pretext) { $pretext = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? $posttext : ' ' . $posttext; }

		// Description as tooltip
		if ($display_descr==2) JHTML::_('behavior.tooltip');

		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}
		
		// initialise property
		$field->{$prop} = array();

		// Get user access level (these are multiple for J2.5)
		$user = JFactory::getUser();
		if (FLEXI_J16GE) $aid_arr = $user->getAuthorisedViewLevels();
		else             $aid = (int) $user->get('aid');

		$n = 0;

		// Get All file information at once (Data maybe cached already)
		// TODO (maybe) e.g. contentlists should could call this function ONCE for all file fields,
		// This may be done by adding a new method to fields to prepare multiple fields with a single call
		$files_data = $this->getFileData( $values, $published=true );   //print_r($files_data); exit;
		
		// Optimization, do some stuff outside the loop
		static $hits_icon = null;
		if ($hits_icon===null && ($display_hits==1 || $display_hits==3)) {
			$_attribs = $display_hits==1 ? 'class="icon-hits hasTip" title=":: %s '.JText::_( 'FLEXI_HITS', true ).'"' : 'class="icon-hits"';
			$hits_icon = FLEXI_J16GE ?
				JHTML::image('components/com_flexicontent/assets/images/'.'user.png', JText::_( 'FLEXI_HITS' ), $_attribs) :
				JHTML::_('image.site', 'user.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_HITS' ), $_attribs);
			$hits_icon .= ' ';
		}
		
		$show_filename = $display_filename || $prop=='namelist';
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		foreach($files_data as $file_id => $file_data)
		{
			// *****************************
			// Check user access on the file
			// *****************************
			$authorized = true;
			$is_public  = true;
			if ( !empty($file_data->access) ) {
				if (FLEXI_J16GE) {
					$authorized = in_array($file_data->access,$aid_arr);
					$is_public  = in_array($public_acclevel,$aid_arr);
				} else {
					$authorized = $file_data->access <= $aid;
					$is_public  = $file_data->access <= $public_acclevel;
				}
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
				$icon = JHTML::image($file_data->icon, $file_data->ext, 'class="icon-mime hasTip" title="'.JText::_('FLEXI_FIELD_FILE_TYPE').'::'.$file_data->ext.'"');
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
						$lang_tip = JText::_( 'FLEXI_LANGUAGE', true ).'::'.($file_data->language=='*' ? JText::_("All") : $langs->{$file_data->language}->name);
						$_attribs = '" class="icon-lang hasTip" title="'.$lang_tip.'"';
					} else {
						$_attribs = '" class="icon-lang"';
					}
					$lang .= "\n".'<img src="'.$langs->{$file_data->language}->imgsrc.'" '.$_attribs.' /> ';
				}
				if ( $add_lang_txt ) {
					$lang .= '['. ($file_data->language=='*' ? JText::_("FLEXI_ALL_LANGUAGES") : $langs->{$file_data->language}->name) .']';
				}
				$lang .= '</span>';
			}
			
			
			// c. HITS: either as icon or as inline text or both
			$hits = '';
			if ($display_hits)
			{
				$hits = '<span class="fcfile_hits">';
				if ( $add_hits_img && @ $hits_icon ) {
					$hits .= sprintf($hits_icon, $file_data->hits);
				}
				if ( $add_hits_txt ) {
					$hits .= '('.$file_data->hits.'&nbsp;'.JTEXT::_('FLEXI_HITS').')';
				}
				$hits .= '</span>';
			}
			
			
			// d. FILENAME / TITLE: decide whether to show it (if we do not use button, then displaying of filename is forced)
			$_filetitle = $file_data->altname ? $file_data->altname : $file_data->filename;
			if ($lowercase_filename) $_filetitle = mb_strtolower( $_filetitle, "UTF-8");
			$name_str   = $display_filename==2 ? $file_data->filename : $_filetitle;
			$name_classes = $file_classes.($file_classes ? ' ' : '').'fcfile_title';
			$name_html  = '<span class="'.$name_classes.'">'. $name_str . '</span>';
			
			
			// e. DESCRIPTION: either as tooltip or as inline text
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
					$descr_inline = ' <span class="fcfile_descr_inline fc-mssg fc-caption" style="max-wdith">'. $file_data->description . '</span>';
				}
				if ($descr_icon) $descr_icon = ' <span class="fcfile_descr_tip">'. $descr_icon . '</span>';
			}
			
			
			
			
			// *****************************
			// Create field's displayed html
			// *****************************
			
			// [1]: either create the download link -or- use no authorized link ...
			if ( !$authorized ) {
				$dl_link = $noaccess_url;
				if ($noaccess_msg) {
					$str = '<span class="fcfile_noauth_msg fc-mssg-inline fc-noauth">' .$noaccess_msg. '</span> ';
				}
			} else {
				$dl_link = JRoute::_( 'index.php?option=com_flexicontent&id='. $file_id .'&cid='.$field->item_id.'&fid='.$field->id.'&task=download' );
				$str = '';
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
				
				$file_classes .= ($file_classes ? ' ' : '').'fc_button fcsimple';   // Add an extra css class (button display)
				
				// DOWNLOAD: single file instant download
				if ($allowdownloads) {
					// NO ACCESS: add file info via form field elements, in case the URL target needs to use them
					$file_data_fields = "";
					if ( !$authorized && $noaccess_addvars) {
						$file_data_fields =
							'<input type="hidden" name="fc_field_id" value="'.$field->id.'"/>'."\n".
							'<input type="hidden" name="fc_item_id" value="'.$field->item_id.'"/>'."\n".
							'<input type="hidden" name="fc_file_id" value="'.$file_id.'"/>'."\n";
					}
					
					// The download button in a mini form ...
					$actions_arr[] = ''
						.'<form id="form-download-'.$field->id.'-'.($n+1).'" method="post" action="'.$dl_link.'" style="display:inline-block;" >'
						.$file_data_fields
						.'<input type="submit" name="download-'.$field->id.'[]" class="'.$file_classes.' fcfile_downloadFile" title="'.$downloadsinfo.'" value="'.$downloadstext.'"/>'
						.'</form>'."\n";
				}
				
				if ($authorized && $allowview && !$file_data->url) {
					$actions_arr[] = '
						<a href="'.$dl_link.'?method=view" class="fancybox '.$file_classes.' fcfile_viewFile" data-fancybox-type="iframe" title="'.$viewinfo.'" style="line-height:1.3em;" >
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
					$attribs .= ' contentid="'. $field->item_id .'"';
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
							'&fc_item_id="'.$field->item_id.
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
					$attribs .= ' contentid="'. $field->item_id .'"';
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
			$field->url[]      = $dl_link;
			$field->file_data[] = $file_data;
			$basePath = $file_data->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;
			$file->abspath[] = str_replace(DS, '/', JPath::clean($basePath.DS.$file_data->filename));
			
			$n++;
		}
		
		if (!empty($fancybox_needed)) flexicontent_html::loadFramework('fancybox');
		
		// Apply seperator and open/close tags
		if(count($field->{$prop})) {
			$field->{$prop}  = implode($separatorf, $field->{$prop});
			$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
		}
	}
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Check if field has posted data
		if ( empty($post) ) return;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;   //echo "<pre>"; print_r($post);
		
		// Get configuration
		$is_importcsv      = JRequest::getVar('task') == 'importcsv';
		$import_docs_folder  = JRequest::getVar('import_docs_folder');
		
		// Execute once
		static $initialized = null;
		static $srcpath_original = '';
		if ( $is_importcsv && !$initialized ) {
			$initialized = 1;
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.jpath');
			$srcpath_original  = JPath::clean( JPATH_SITE .DS. $import_docs_folder .DS );
			require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'filemanager.php');
		}
		
		$new=0;
		$newpost = array();
    foreach ($post as $n => $v)
    {
    	if (empty($v)) continue;
			
			// support for basic CSV import / export
			if ( $is_importcsv ) {
				if ( !is_numeric($v) ) {
					$filename = $v;
					$fman = new FlexicontentControllerFilemanager();
					JRequest::setVar( 'return-url', null, 'post' );
					JRequest::setVar( 'file-dir-path', DS. $import_docs_folder, 'post' );
					JRequest::setVar( 'file-filter-re', preg_quote($filename), 'post' );
					JRequest::setVar( 'secure', 1, 'post' );
					JRequest::setVar( 'keep', 1, 'post' );
					$file_ids = $fman->addlocal();
					$v = !empty($file_ids) ? reset($file_ids) : ''; // Get fist element
					//$_filetitle = key($file_ids);  this is the cleaned up filename, currently not needed
				}
			}
			if ( !empty ($v) && is_numeric($v) ) $newpost[$v] = $new++;
    }
    $post = array_flip($newpost);
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		if ($post) {
			$_files_data = $this->getFileData( $post, $published=true, $extra_select =', id AS value_id' );
			$values = array();
			if ($_files_data) foreach($_files_data as $_file_id => $_file_data) $values[$_file_id] = (array)$_file_data;
		} else {
			$field->field_rawvalues = 1;
			$field->field_valuesselect = ' file.id AS value_id, file.altname, file.description, file.filename';
			$field->field_valuesjoin   = ' JOIN #__flexicontent_files AS file ON file.id = fi.value';
			$field->field_groupby      = null;
		}
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array('filename'), $search_properties=array('description'), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		if ($post) {
			$_files_data = $this->getFileData( $post, $published=true, $extra_select =', id AS value_id' );
			$values = array();
			if ($_files_data) foreach($_files_data as $_file_id => $_file_data) $values[$_file_id] = (array)$_file_data;
		} else {
			$field->unserialize = 0;
			$field->field_rawvalues = 1;
			$field->field_valuesselect = ' file.id AS value_id, file.altname, file.description, file.filename';
			$field->field_valuesjoin   = ' JOIN #__flexicontent_files AS file ON file.id = fi.value';
			$field->field_groupby      = null;
		}
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array('filename'), $search_properties=array('description'), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	function getFileData( $value, $published=1, $extra_select='' )
	{
		// Find which file data are already cached, and if no new file ids to query, then return cached only data
		static $cached_data = array();
		$return_data = array();
		$new_ids = array();
		$values = is_array($value) ? $value : array($value);
		foreach ($values as $file_id) {
			$f = (int)$file_id;
			if ( !isset($cached_data[$f]) && $f)
				$new_ids[] = $f;
		}
		
		// Get file data not retrieved already
		if ( count($new_ids) )
		{
			// Only query files that are not already cached
			$db = JFactory::getDBO();
			$query = 'SELECT * '. $extra_select //filename, altname, description, ext, id'
					. ' FROM #__flexicontent_files'
					. ' WHERE id IN ('. implode(',', $new_ids) . ')'
					. ($published ? '  AND published = 1' : '')
					;
			$db->setQuery($query);
			$new_data = $db->loadObjectList('id');

			if ($new_data) foreach($new_data as $file_id => $file_data) {
				$cached_data[$file_id] = $file_data;
			}
		}
		
		// Finally get file data in correct order
		foreach($values as $file_id) {
			$f = (int)$file_id;
			if ( isset($cached_data[$f]) && $f)
				$return_data[$file_id] = $cached_data[$f];
		}

		return !is_array($value) ? @$return_data[(int)$value] : $return_data;
	}


	function addIcon( &$file )
	{
		static $icon_exists = array();
		
		switch ($file->ext)
		{
			// Image
			case 'jpg':
			case 'png':
			case 'gif':
			case 'xcf':
			case 'odg':
			case 'bmp':
			case 'jpeg':
				$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/image.png';
			break;

			// Non-image document
			default:
				if ( !isset($icon_exists[$file->ext]) ) {
					$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$file->ext.'.png';
					$icon_exists[$file->ext] = file_exists($icon);
				}
				if ( $icon_exists[$file->ext] ) {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/'.$file->ext.'.png';
				} else {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
				}
			break;
		}
		return $file;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	/**
	 * Create form for sharing the download link of given file
	 *
	 * @access public
	 * @since 1.0
	 */
	function share_file_form($tpl = null)
	{
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		
		//$tree_var = JRequest::getVar( 'tree_var', "" );
		$file_id    = (int) JRequest::getInt( 'file_id', 0 );
		$content_id = (int) JRequest::getInt( 'content_id', 0 );
		$field_id   = (int) JRequest::getInt( 'field_id', 0 );
		$tpl = JRequest::getCmd( '$tpl', 'default' );
		
		// Check for missing file id
		if (!$file_id) {
			jexit( JText::_('file id is missing') );
		}
		
		// Check file exists
		$query = ' SELECT * FROM #__flexicontent_files WHERE id='. $file_id;
		$db->setQuery( $query );
		$file = $db->loadObject();
		
		if ($db->getErrorNum())  {
			jexit( __FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()) );
		}
		if (!$file) {
			jexit( JText::_('file id no '.$file_id.', was not found') );
		}
		
		$data = new stdClass();
		$data->file_id    = $file_id;
		$data->content_id = $content_id;
		$data->field_id   = $field_id;

		// Load with previous data, if it exists
		$mailto		= JRequest::getString('mailto', '', 'post');
		$sender		= JRequest::getString('sender', '', 'post');
		$from			= JRequest::getString('from', '', 'post');
		$subject	= JRequest::getString('subject', '', 'post');
		$desc     = JRequest::getString('desc', '', 'post');

		if ($user->get('id') > 0) {
			$data->sender	= $user->get('name');
			$data->from		= $user->get('email');
		}
		else
		{
			$data->sender	= $sender;
			$data->from		= $from;
		}

		$data->subject = $subject;
		$data->desc    = $desc;
		$data->mailto  = $mailto;
		
		$document->addStyleSheet(JURI::base() . 'components/com_flexicontent/assets/css/flexicontent.css');
		include('file'.DS.'share_form.php');
		$session->set('com_flexicontent.formtime', time());
	}
	
	
	/**
	 * Send email with download (file) link, to the given email address
	 *
	 * @access public
	 * @since 1.0
	 */
	function share_file_email()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));
		
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();
		$app  = JFactory::getApplication();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		
		$timeout = $session->get('com_flexicontent.formtime', 0);
		if ($timeout == 0 || time() - $timeout < 2) {
			JError::raiseNotice(500, JText:: _ ('FLEXI_FIELD_FILE_EMAIL_NOT_SENT'));
			return $this->share_file_form();
		}
		
		$SiteName	= $app->getCfg('sitename');
		$MailFrom	= $app->getCfg('mailfrom');
		$FromName	= $app->getCfg('fromname');
		
		
		$file_id    = (int) JRequest::getInt( 'file_id', 0 );
		$content_id = (int) JRequest::getInt( 'content_id', 0 );
		$field_id   = (int) JRequest::getInt( 'field_id', 0 );
		$tpl = JRequest::getCmd( '$tpl', 'default' );
		
		// Check for missing file id
		if (!$file_id) {
			jexit( JText::_('file id is missing') );
		}
		
		// Check file exists
		$query = ' SELECT * FROM #__flexicontent_files WHERE id='. $file_id;
		$db->setQuery( $query );
		$file = $db->loadObject();
		
		if ($db->getErrorNum())  {
			jexit( __FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()) );
		}
		if (!$file) {
			jexit( JText::_('file id no '.$file_id.', was not found') );
		}
		


		// Create SELECT OR JOIN / AND clauses for checking Access
		$access_clauses['select'] = '';
		$access_clauses['join']   = '';
		$access_clauses['and']    = '';
		$access_clauses = $this->_createFieldItemAccessClause( $get_select_access = false, $include_file = true );
		
		
		// Get field's configuration
		$q = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = '.(int) $field_id;
		$db->setQuery($q);
		$fld = $db->loadObject();
		$field_params = FLEXI_J16GE ? new JRegistry($fld->attribs) : new JParameter($fld->attribs);
		
		// Get all needed data related to the given file
		$query  = 'SELECT f.id, f.filename, f.altname, f.secure, f.url,'
				.' i.title as item_title, i.introtext as item_introtext, i.fulltext as item_fulltext, u.email as item_owner_email, '
				
				// Item and Current Category slugs (for URL)
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as itemslug,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				
				.' FROM #__flexicontent_fields_item_relations AS rel'
				.' LEFT JOIN #__flexicontent_files AS f ON f.id = rel.value'
				.' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
				.' LEFT JOIN #__content AS i ON i.id = rel.item_id'
				.' LEFT JOIN #__categories AS c ON c.id = i.catid'
				.' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				.' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
				.' LEFT JOIN #__users AS u ON u.id = i.created_by'
				. $access_clauses['join']
				.' WHERE rel.item_id = ' . $content_id
				.' AND rel.field_id = ' . $field_id
				.' AND f.id = ' . $file_id
				.' AND f.published= 1'
				. $access_clauses['and']
				;
		$db->setQuery($query);
		$file = $db->loadObject();
		
		if ($db->getErrorNum())  {
			jexit( __FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()) );
		}
		if ( empty($file) ) {
			// this is normally not reachable because the share link should not have been displayed for the user, but it is reachable if e.g. user session has expired
			jexit( JText::_( 'FLEXI_ALERTNOTAUTH' ). "File data not found OR no access for file #: ". $file_id ." of content #: ". $content_id ." in field #: ".$field_id );
		}
		
		$coupon_vars = '';
		if ( $field_params->get('enable_coupons', 0) ) 
		{
			// Insert new download coupon into the DB, in the case the file is sent to a user with no ACCESS
			$coupon_token = uniqid();  // create coupon token
			$query = ' INSERT #__flexicontent_download_coupons '
				. 'SET user_id = ' . (int)$user->id
				. ', file_id = ' . $file_id
				. ', token = ' . $db->Quote($coupon_token)
				. ', hits = 0'
				. ', hits_limit = '. (int)$field_params->get('coupon_hits_limit', 3)
				. ', expire_on = NOW() + INTERVAL '. (int)$field_params->get('coupon_expiration_days', 15).' DAY'
				;
			$db->setQuery( $query );
			$db->query();
			$coupon_id = $db->insertid();  // get id of newly created coupon
			$coupon_vars = '&conid='.$coupon_id.'&contok='.$coupon_token;
		}
		
		$uri  = JURI::getInstance();
		$base = $uri->toString( array('scheme', 'host', 'port'));
		$vars = '&id='.$file_id.'&cid='.$content_id.'&fid='.$field_id . $coupon_vars;
		$link = $base . JRoute::_( 'index.php?option=com_flexicontent&task=download'.$vars, false );
		
		// Verify that this is a local link
		if (!$link || !JURI::isInternal($link)) {
			//Non-local url...
			JError::raiseNotice(500, JText:: _ ('FLEXI_FIELD_FILE_EMAIL_NOT_SENT'));
			return $this->share_file_form();
		}

		// An array of email headers we do not want to allow as input
		$headers = array (	'Content-Type:',
							'MIME-Version:',
							'Content-Transfer-Encoding:',
							'bcc:',
							'cc:');

		// An array of the input fields to scan for injected headers
		$fields = array(
			'mailto',
			'sender',
			'from',
			'subject',
		);

		/*
		 * Here is the meat and potatoes of the header injection test.  We
		 * iterate over the array of form input and check for header strings.
		 * If we find one, send an unauthorized header and die.
		 */
		foreach ($fields as $field)
		{
			foreach ($headers as $header)
			{
				if (strpos($_POST[$field], $header) !== false)
				{
					JError::raiseError(403, '');
				}
			}
		}

		/*
		 * Free up memory
		 */
		unset ($headers, $fields);

		$email		= JRequest::getString('mailto', '', 'post'); echo "<br>";
		$sender		= JRequest::getString('sender', '', 'post'); echo "<br>";
		$from			= JRequest::getString('from', '', 'post'); echo "<br>";
		$_subject = JText::sprintf('FLEXI_FIELD_FILE_SENT_BY', $sender); echo "<br>";
		$subject  = JRequest::getString('subject', $_subject, 'post'); echo "<br>";
		$desc     = JRequest::getString('desc', '', 'post'); echo "<br>";
		
		// Check for a valid to address
		$error	= false;
		if (! $email  || ! JMailHelper::isEmailAddress($email))
		{
			$error	= JText::sprintf('FLEXI_FIELD_FILE_EMAIL_INVALID', $email);
			JError::raiseWarning(0, $error);
		}

		// Check for a valid from address
		if (! $from || ! JMailHelper::isEmailAddress($from))
		{
			$error	= JText::sprintf('FLEXI_FIELD_FILE_EMAIL_INVALID', $from);
			JError::raiseWarning(0, $error);
		}

		if ($error)
		{
			return $this->share_file_form();
		}

		// Build the message to send
		$body  = JText::sprintf('FLEXI_FIELD_FILE_EMAIL_MSG', $SiteName, $sender, $from, $link);
		$body	.= "\n\n".JText::_('FLEXI_FIELD_FILE_EMAIL_SENDER_NOTES').":\n\n".$desc;
		
		// Clean the email data
		$subject = JMailHelper::cleanSubject($subject);
		$body    = JMailHelper::cleanBody($body);
		$sender  = JMailHelper::cleanAddress($sender);
		
		$html_mode=false; $cc=null; $bcc=null;
		$attachment=null; $replyto=null; $replytoname=null;
		
		// Send the email
		$send_result = FLEXI_J16GE ?
			JFactory::getMailer()->sendMail( $from, $sender, $email, $subject, $body, $html_mode, $cc, $bcc, $attachment, $replyto, $replytoname ) :
			JUtility::sendMail( $from, $sender, $email, $subject, $body, $html_mode, $cc, $bcc, $attachment, $replyto, $replytoname );
		if ( $send_result !== true )
		{
			JError::raiseNotice(500, JText:: _ ('FLEXI_FIELD_FILE_EMAIL_NOT_SENT'));
			return $this->share_file_form();
		}
		
		$document->addStyleSheet(JURI::base() . 'components/com_flexicontent/assets/css/flexicontent.css');
		include('file'.DS.'share_result.php');
	}


	// Private common method to create join + and-where SQL CLAUSEs, for checking access of field - item pair(s), IN FUTURE maybe moved
	function _createFieldItemAccessClause($get_select_access = false, $include_file = false )
	{
		$user  = JFactory::getUser();
		$select_access = $joinacc = $andacc = '';
		
		// Access Flags for: content item and field
		if ( $get_select_access ) {
			$select_access = '';
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				if ($include_file) $select_access .= ', CASE WHEN'.
					'   f.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_file_access';
				$select_access .= ', CASE WHEN'.
					'  fi.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_field_access';
				$select_access .= ', CASE WHEN'.
					'  ty.access IN (0,'.$aid_list.') AND '.
					'   c.access IN (0,'.$aid_list.') AND '.
					'   i.access IN (0,'.$aid_list.')'.
					' THEN 1 ELSE 0 END AS has_content_access';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					if ($include_file) $select_access .= ', CASE WHEN'.
						'   (gf.aro IN ( '.$user->gmid.' ) OR  f.access <= '. $aid . ')  THEN 1 ELSE 0 END AS has_file_access';
					$select_access .= ', CASE WHEN'.
						'  (gfi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. $aid . ')  THEN 1 ELSE 0 END AS has_field_access';
					$select_access .= ', CASE WHEN'.
						'   (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. $aid . ') AND '.
						'   (gc.aro IN ( '.$user->gmid.' ) OR  c.access <= '. $aid . ') AND '.
						'   (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. $aid . ')'.
						' THEN 1 ELSE 0 END AS has_content_access';
				} else {
					if ($include_file) $select_access .= ', CASE WHEN'.
						'   f.access <= '. $aid . '  THEN 1 ELSE 0 END AS has_file_access';
					$select_access .= ', CASE WHEN'.
						' fi.access <= '. $aid . '  THEN 1 ELSE 0 END AS has_field_access';
					$select_access .= ', CASE WHEN'.
						'  ty.access <= '. $aid . ' AND '.
						'   c.access <= '. $aid . ' AND '.
						'   i.access <= '. $aid .
						' THEN 1 ELSE 0 END AS has_content_access';
				}
			}
		}
		
		else {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				if ($include_file)
					$andacc .= ' AND  f.access IN (0,'.$aid_list.')';  // AND file access
				$andacc   .= ' AND fi.access IN (0,'.$aid_list.')';  // AND field access
				$andacc   .= ' AND ty.access IN (0,'.$aid_list.')  AND  c.access IN (0,'.$aid_list.')  AND  i.access IN (0,'.$aid_list.')';  // AND content access
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					if ($include_file) $andacc .=
						' AND  (gf.aro IN ( '.$user->gmid.' ) OR f.access <= '. $aid . ' OR f.access IS NULL)';  // AND file access
					$andacc   .=
						' AND (gfi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. $aid . ')';  // AND field access
					$andacc   .=
						' AND (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. $aid . ')';   // AND content access: type, cat, item
						' AND  (gc.aro IN ( '.$user->gmid.' ) OR  c.access <= '. $aid . ')';
						' AND  (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. $aid . ')';
				} else {
					if ($include_file)
						$andacc .= ' AND (f.access <= '.$aid .' OR f.access IS NULL)';  // AND file access
					$andacc   .= ' AND fi.access <= '.$aid ;                          // AND field access
					$andacc   .= ' AND ty.access <= '.$aid . ' AND  c.access <= '.$aid . ' AND  i.access <= '.$aid ;  // AND content access
				}
			}
		}
		
		if (FLEXI_ACCESS) {
			if ($include_file)
				$joinacc .= ' LEFT JOIN #__flexiaccess_acl AS gf ON f.id = gf.axo AND gf.aco = "read" AND gf.axosection = "file"';        // JOIN file access
			$joinacc   .= ' LEFT JOIN #__flexiaccess_acl AS gfi ON fi.id = gfi.axo AND gfi.aco = "read" AND gfi.axosection = "field"';  // JOIN field access
			$joinacc   .= ' LEFT JOIN #__flexiaccess_acl AS gt ON ty.id = gt.axo AND gt.aco = "read" AND gt.axosection = "type"';       // JOIN content access: type, cat, item
			$joinacc   .= ' LEFT JOIN #__flexiaccess_acl AS gc ON  c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
			$joinacc   .= ' LEFT JOIN #__flexiaccess_acl AS gi ON  i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
		}
		
		$clauses['select'] = $select_access;
		$clauses['join']   = $joinacc;
		$clauses['and']    = $andacc;
		return $clauses;
	}
	
}
