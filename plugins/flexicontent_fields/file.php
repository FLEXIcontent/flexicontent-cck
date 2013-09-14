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
		$size      = $field->parameters->get( 'size', 30 ) ;
		$multiple  = 1; //$field->parameters->get( 'allow_multiple', 1 ) ;  // cannot be disable file adding function would need updating

		$app				= JFactory::getApplication();
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';

		$fieldname = FLEXI_J16GE ? 'custom['.$field->name.'][]' : $field->name.'[]';
		
		$js = "
			var value_counter=".count($field->value).";
			
			function qfSelectFile".$field->id."(id, file) {
			  value_counter++;
				
			  var valcounter = $('".$field->name."');
				valcounter.value = value_counter;
				
				var name 	= 'a_name'+id;
				var ixid 	= 'a_id'+id;
				var li 		= document.createElement('li');
				var txt		= document.createElement('input');
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
				txt.disabled = 'disabled';
				txt.id	= name;
				txt.value	= file;
				txt.addClass('fcfield_textval inputbox inline_style_published');
				
				hid.type = 'hidden';
				hid.name = '".$fieldname."';
				hid.value = id;
				hid.id = ixid;
				
				img.src = '".JURI::root()."components/com_flexicontent/assets/images/move2.png';
				img.alt = '".JText::_( 'FLEXI_CLICK_TO_DRAG',true )."';
				
				filelist.appendChild(li);
				li.appendChild(txt);
				li.appendChild(span);
				span.appendChild(img);
				li.appendChild(button);
				li.appendChild(hid);
				
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.fcfield-drag'
				});
			}
			";
		
		if ($multiple) // handle multiple records
		{
			if (!FLEXI_J16GE) $document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			
			// Add the drag and drop sorting feature
			$js .= "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.fcfield-drag'
					});
				});
			";
			
			$js .= "					
			function deleteField".$field->id."(el)
			{
			  value_counter--;
				
			  var valcounter = $('".$field->name."');
				if ( value_counter > 0 ) valcounter.value = value_counter;
				else valcounter.value = '';
				
				var field	= $(el);
				var row		= field.getParent();
				if (MooTools.version>='1.2.4') {
					var fx = new Fx.Morph(row, {duration: 300, transition: Fx.Transitions.linear});
				} else {
					var fx = row.effects({duration: 300, transition: Fx.Transitions.linear});
				}
				
				fx.start({
					'height': 0,
					'opacity': 0
				}).chain(function(){
					(MooTools.version>='1.2.4')  ?  row.destroy()  :  row.remove();
				});
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
			#sortables_'.$field->id.' li input { cursor: text;}
			#sortables_'.$field->id.' li input.inline_style_published   { font-family:tahoma!important; font-style:italic!important; color:#444!important; font-style:tahona; }
			#sortables_'.$field->id.' li input.inline_style_unpublished { background: #ffffff; color:gray; border-width:0px; text-decoration:line-through; }
			';
			
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_FILE' ).'" onclick="deleteField'.$field->id.'(this);" />';
			$move2 	= '<span class="fcfield-drag">'.JHTML::image ( JURI::root().'components/com_flexicontent/assets/images/move2.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$js = '';
			$css = '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		JHTML::_('behavior.modal', 'a.modal_'.$field->id);
		
		$files_data = !empty($field->value) ? $this->getFileData( $field->value, $published=false ) : array();
		$field->html = array();
		$i = 0;
		foreach($files_data as $file_id => $file_data) {
			$field->html[] = ($file_data->published ?
			'  <input class="fcfield_textval inputbox inline_style_published" size="'.$size.'" type="text" id="a_name'.$i.'" value="'.$file_data->filename.'" disabled="disabled" />' :
			'  <input class="fcfield_textval inputbox inline_style_unpublished" size="'.$size.'" style="'.$inline_style_unpublished.'" type="text" id="a_name'.$i.'" value="'.$file_data->filename.' [UNPUBLISHED]" disabled="disabled" />'
			)
			.'  <input type="hidden" id="a_id'.$i.'" name="'.$fieldname.'" value="'.$file_id.'" />'
			.$move2
			.$remove_button
			;
			$i++;
		}
		
		if ($multiple) { // handle multiple records (FORCED ON , partially implemented, variable is always true)
			$field->html = '<li>'. implode('</li><li>', $field->html) .'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
		} else {  // handle single values
			$field->html = '<div>'.$field->html[0].'</div>';
		}
		
		$user = JFactory::getUser();
		$autoselect = $field->parameters->get( 'autoselect', 1 ) ;
		$linkfsel = JURI::base().'index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;index='.$i.'&amp;field='.$field->id.'&amp;itemid='.$item->id.'&amp;autoselect='.$autoselect.'&amp;items=0&amp;filter_uploader='.$user->id.'&amp;'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()).'=1';
		$field->html .= "
		<div class=\"fcfield-button-add\">
			<div class=\"blank\">
				<a class=\"modal_".$field->id."\" title=\"".JText::_( 'FLEXI_ADD_FILE' )."\" href=\"".$linkfsel."\" rel=\"{handler: 'iframe', size: {x:(MooTools.version>='1.2.4' ? window.getSize().x : window.getSize().size.x)-100, y: (MooTools.version>='1.2.4' ? window.getSize().y : window.getSize().size.y)-100}}\">".JText::_( 'FLEXI_ADD_FILE' )."</a>
			</div>
		</div>
		";
		
		$field->html .= '<input id="'.$field->name.'" class="'.$required.'" style="display:none;" name="__fcfld_valcnt__['.$field->name.']" value="'.($i ? $i : '').'">';
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
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
		$useicon		= $field->parameters->get( 'useicon', 1 ) ;
		$usebutton	= $field->parameters->get( 'usebutton', 0 ) ;
		$display_filename	= $field->parameters->get( 'display_filename', 0 ) ;
		$display_descr		= $field->parameters->get( 'display_descr', 0 ) ;

		$noaccess_display	     = $field->parameters->get( 'noaccess_display', 1 ) ;
		$noaccess_url_unlogged = $field->parameters->get( 'noaccess_url_unlogged', false ) ;
		$noaccess_url_logged   = $field->parameters->get( 'noaccess_url_logged', false ) ;
		$noaccess_msg_unlogged = JText::_($field->parameters->get( 'noaccess_msg_unlogged', '' ));
		$noaccess_msg_logged   = JText::_($field->parameters->get( 'noaccess_msg_logged', '' ));
		$noaccess_addvars      = $field->parameters->get( 'noaccess_addvars', 0);

		// Select appropriate messages depending if user is logged on
		$noaccess_url = JFactory::getUser()->guest ? $noaccess_url_unlogged : $noaccess_url_logged;
		$noaccess_msg = JFactory::getUser()->guest ? $noaccess_msg_unlogged : $noaccess_msg_logged;
		
		// Downloads manager feature
		$use_downloads_manager = $field->parameters->get( 'use_downloads_manager', 0);
		static $mod_is_enabled = null;
		if ($use_downloads_manager && $mod_is_enabled === null) {
			$db = JFactory::getDBO();
			$query = "SELECT published FROM #__modules WHERE module = 'mod_flexidownloads' AND published = 1";
			$db->setQuery($query);
			$mod_is_enabled = $db->loadResult();
			if (!$mod_is_enabled) {
				$app = JFactory::getApplication();
				$app->enqueueMessage("FILE FIELD: please disable parameter \"Use Downloads Manager Module\", the module is not install or not published", 'message' );
			}
		}
		$use_downloads_manager = $use_downloads_manager ? $mod_is_enabled : 0;
		
		if($pretext) { $pretext = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? $posttext : ' ' . $posttext; }

		// Description as tooltip
		if ($display_filename==2) JHTML::_('behavior.tooltip');

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
		$user = & JFactory::getUser();
		if (FLEXI_J16GE) $aid_arr = $user->getAuthorisedViewLevels();
		else             $aid = (int) $user->get('aid');

		$n = 0;

		// Get All file information at once (Data maybe cached already)
		// TODO (maybe) e.g. contentlists should could call this function ONCE for all file fields,
		// This may be done by adding a new method to fields to prepare multiple fields with a single call
		$files_data = $this->getFileData( $values, $published=true );   //print_r($files_data); exit;
		
		foreach($files_data as $file_id => $file_data) {
			$icon = '';
			$authorized = true;
			// Check user access on the file
			if ( !empty($file_data->access) ) {
				if (FLEXI_J16GE) {
					$authorized = in_array($file_data->access,$aid_arr);
				} else {
					$authorized = $aid >= $file_data->access;
				}
			}

			// If no access and set not to show then continue
			if ( !$authorized && !$noaccess_display ) continue;

			// --. Create icon according to filetype
			if ($useicon) {
				$file_data	= $this->addIcon( $file_data );
				$icon		= JHTML::image($file_data->icon, $file_data->ext, 'class="icon-mime"') .'&nbsp;';
			}

			// --. Decide whether to show filename (if we do not use button, then displaying of filename is forced)
			$_filename  = $file_data->altname ? $file_data->altname : $file_data->filename;
			//$_filename  = mb_strtolower( $_filename, "UTF-8");
			$name_str   = ($display_filename || !$usebutton || $prop=='namelist') ? $_filename : '';
			$name_html  = !empty($name_str) ? '&nbsp;<span class="fcfile_name">'. $name_str . '</span>' : '';

			// --. Description as tooltip or inline text ... prepare related variables
			$alt_str = $class_str = $text_html  = '';
			if (!empty($file_data->description)) {
				if ( !$authorized ) {
					if ($noaccess_display != 2 ) {
						$alt_str    = flexicontent_html::escapeJsText($name_str . '::' . $file_data->description,'s');
						$class_str  = ' hasTip';
						$text_html  = '';
					}
				} else if ($display_descr==1 || $prop=='namelist') {   // As tooltip
					$alt_str    = flexicontent_html::escapeJsText($name_str . '::' . $file_data->description,'s');
					$class_str  = ' hasTip';
					$text_html  = '';
				} else if ($display_descr==2) {  // As inline text
					$alt_str    = '';
					$class_str  = '';
					$text_html  = ' <span class="fcfile_descr">'. $file_data->description . '</span>';
				}
			}

			// --. Create the download link or use no authorized link ...
			if ( !$authorized ) {
				$dl_link = $noaccess_url;
				$str = $noaccess_msg . ($noaccess_msg ? ': ' : '') . $icon;
				$class_str .= ' fc_file_noauth';   // Add an extra css class
			} else {
				$dl_link = JRoute::_( 'index.php?option=com_flexicontent&id='. $file_id .'&cid='.$field->item_id.'&fid='.$field->id.'&task=download' );
				$str = '';
			}
			
			
			// *****************************
			// Create field's displayed html
			// *****************************
			
			// ****************************
			// CASE 1: no download link ... 
			// ****************************
			
			// EITHER (a) Current user NOT authorized to download file AND no access URL is not configured
			// OR     (b) creating a file list with no download links, (the 'prop' display variable is 'namelist')
			if (!$dl_link || $prop=='namelist') {
				$str = $icon . '<span class="'.$class_str.'" title="'. $alt_str .'" >' . $name_html . '</span>' ." ". $text_html;
			}
			
			
			// *****************************************************************************************
			// CASE 2: Display download button passing file variables via a mini form
			// (NOTE: the form action can be a no access url if user is not authorized to download file)
			// *****************************************************************************************
			
			else if ($usebutton) {
				$class_str .= ' button';   // Add an extra css class (button display)
				
				// MULTI-DOWNLOAD MODE: the button will add file to download list (tree) (handled via a downloads manager module)
				if ($authorized && $use_downloads_manager && !$file->url) {
					$class_str .= ($class_str ? ' ' : '') .'fcfile_addFile';   // CSS class to anchor downloads list adding function
					
					$attribs  = ' class="'. $class_str .'"';
					$attribs .= ' title="'. $alt_str .'"';
					$attribs .= ' filename="'. flexicontent_html::escapeJsText($_filename,'s') .'"';
					$attribs .= ' fieldid="'. $field->id .'"';
					$attribs .= ' contentid="'. $field->item_id .'"';
					$attribs .= ' fileid="'. $file_data->id .'"';
					$str = '<input type="button" '. $attribs .' value="'.JText::_('FLEXI_DOWNLOAD').'" />'.$name_html ." ". $text_html;
				}
				
				// SINGLE (INSTANT) DOWNLOAD MODE
				else {
					
					// NO ACCESS: add file info via form field elements, in case the URL target needs to use them
					$file_data_fields = "";
					if ( !$authorized && $noaccess_addvars) {
						$file_data_fields =
							'<input type="hidden" name="fc_field_id" value="'.$field->id.'"/>'."\n".
							'<input type="hidden" name="fc_item_id" value="'.$field->item_id.'"/>'."\n".
							'<input type="hidden" name="fc_file_id" value="'.$file_id.'"/>'."\n";
					}
					
					// The download button in a mini form ...
					$str  = '<form id="form-download-'.$field->id.'-'.($n+1).'" method="post" action="'.$dl_link.'">';
					$str .= $file_data_fields;
					$str .= $icon.'<input type="submit" name="download-'.$field->id.'[]" class="'.$class_str.'" title="'. $alt_str .'" value="'.JText::_('FLEXI_DOWNLOAD').'"/>'. $name_html ." ". $text_html;
					$str .= '</form>'."\n";
				}
			}
			
			
			// *******************************************************************************************
			// CASE 3: display a download link (with file title or filename) passing variables via the URL 
			// (NOTE: the target link can be a no access url if user is not authorized to download file)
			// *******************************************************************************************
			
			else {
				
				// MULTI-DOWNLOAD MODE: the link will add file to download list (tree) (handled via a downloads manager module)
				if ($authorized && $use_downloads_manager && !$file_data->url) {
					$class_str .= ($class_str ? ' ' : '') .'fcfile_addFile';   // CSS class to anchor downloads list adding function
					
					$attribs  = ' class="'. $class_str .'"';
					$attribs .= ' title="'. $alt_str .'"';
					$attribs .= ' filename="'. flexicontent_html::escapeJsText($_filename,'s') .'"';
					$attribs .= ' fieldid="'. $field->id .'"';
					$attribs .= ' contentid="'. $field->item_id .'"';
					$attribs .= ' fileid="'. $file_data->id .'"';
					$str = '<a href="javascript:;" '. $attribs .' >'.$name_str.'</a> '. $text_html;
				}
				
				// SINGLE (INSTANT) DOWNLOAD MODE
				else {
					
					// NO ACCESS: add file info via URL variables, in case the URL target needs to use them
					if ( !$authorized && $noaccess_addvars) {
						$dl_link .=
							'&fc_field_id="'.$field->id.
							'&fc_item_id="'.$field->item_id.
							'&fc_file_id="'.$file_id;
					}
					
					// The download link
					$str = $icon . '<a href="' . $dl_link . '" class="'.$class_str.'" title="'. $alt_str .'" >' . $name_html . '</a> '. $text_html;
				}
			}
			
			// Values Prefix and Suffix Texts
			$field->{$prop}[]	=  $pretext . $str . $posttext;
			$field->url[]	=  $dl_link;
			$n++;
		}
		
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
					//$_filename = key($file_ids);  this is the cleaned up filename, currently not needed
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
				$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$file->ext.'.png';
				if (file_exists($icon)) {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/'.$file->ext.'.png';
				} else {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
				}
			break;
		}
		return $file;
	}
	
}
