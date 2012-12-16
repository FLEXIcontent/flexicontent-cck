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
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'file') return;

		// some parameter shortcuts
		$document		= & JFactory::getDocument();
		$size				= $field->parameters->get( 'size', 30 ) ;
		
		$app				= & JFactory::getApplication();
		$prefix			= $app->isSite() ? 'administrator/' : '';
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		
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
			
			$(li).addClass('sortabledisabled');
			$(span).addClass('fcfield-drag');
			
			var button = document.createElement('input');
			button.type = 'button';
			button.name = 'removebutton_'+id;
			button.id = 'removebutton_'+id;
			$(button).addClass('fcfield-button');
			$(button).addEvent('click', function() { deleteField".$field->id."(this) });
			button.value = '".JText::_( 'FLEXI_REMOVE_FILE' )."';
			
			txt.type = 'text';
			txt.size = '".$size."';
			txt.disabled = 'disabled';
			txt.id	= name;
			txt.value	= file;
			
			hid.type = 'hidden';
			hid.name = '".$fieldname."';
			hid.value = id;
			hid.id = ixid;
			
			img.src = '".$prefix."components/com_flexicontent/assets/images/move3.png';
			img.alt = '".JText::_( 'FLEXI_CLICK_TO_DRAG' )."';
			
			filelist.appendChild(li);
			li.appendChild(txt);
			li.appendChild(button);
			li.appendChild(hid);
			li.appendChild(span);
			span.appendChild(img);
			
			new Sortables($('sortables_".$field->id."'), {
				'constrain': true,
				'clone': true,
				'handle': '.fcfield-drag'
			});
		}
		
		function deleteField".$field->id."(el) {
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
		$document->addScriptDeclaration($js);

			// Add the drag and drop sorting feature
			$js = "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.fcfield-drag'
					});			
				});
			";
			if (!FLEXI_J16GE) $document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration($js);

			$css = '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear:both;
				list-style: none;
				height: 20px;
				}
			#sortables_'.$field->id.' li input { cursor: text;}
			';
			$document->addStyleDeclaration($css);

			$move 	= JHTML::image ( JURI::root().'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
				
		JHTML::_('behavior.modal', 'a.modal_'.$field->id);
		
		$i = 0;
		$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">';
		if($field->value) {
			foreach($field->value as $file) {
				$field->html .= '<li>';
				$filedata = $this->getFileData( $file );
				$field->html .= '  <input size="'.$size.'" style="background: #ffffff;" type="text" id="a_name'.$i.'" value="'.$filedata->filename.'" disabled="disabled" />';
				$field->html .= '  <input type="hidden" id="a_id'.$i.'" name="'.$fieldname.'" value="'.$file.'" />';
				$field->html .= '  <input class="inputbox fcfield-button" type="button" onclick="deleteField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_REMOVE_FILE' ).'" />';
				$field->html .= '  <span class="fcfield-drag">'.$move.'</span>';
				$field->html .= '</li>';
				$i++;
			}
		}
		
		$files = implode(":", $field->value);
		$user = & JFactory::getUser();
		$autoselect = $field->parameters->get( 'autoselect', 1 ) ;
		$linkfsel = JURI::base().'index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;index='.$i.'&amp;field='.$field->id.'&amp;itemid='.$item->id.'&amp;autoselect='.$autoselect.'&amp;items=0&amp;filter_uploader='.$user->id.'&amp;'.JUtility::getToken().'=1';
		$field->html .= "
		</ul>
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
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'file') return;

		$values = $values ? $values : $field->value ;
		
		$mainframe = & JFactory::getApplication();

		// some parameter shortcuts
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$remove_space	= $field->parameters->get( 'remove_space', 0 ) ;
		$separatorf	= $field->parameters->get( 'separatorf', 3 ) ;
		$opentag		= $field->parameters->get( 'opentag', '' ) ;
		$closetag		= $field->parameters->get( 'closetag', '' ) ;
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
		
		if($pretext) { $pretext = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		// Description as tooltip
		if ($display_filename==2) JHTML::_('behavior.tooltip');

		switch($separatorf)
		{
			case 0:
			$separatorf = ' ';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = ' | ';
			break;

			case 3:
			$separatorf = ', ';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			default:
			$separatorf = ' ';
			break;
		}
		
		// initialise property
		$field->{$prop} = array();

		// Get user access level (these are multiple for J2.5)
		$user = & JFactory::getUser();
		if (FLEXI_J16GE) $aid_arr = $user->getAuthorisedViewLevels();
		else             $aid = (int) $user->get('aid');
		
		$n = 0;
		foreach ($values as $value) {
			$icon = '';
			$filedata = $this->getFileData( $value );
			//print_r($filedata); exit;
			if ( $filedata )
			{
				$authorized = true;
				// Check user access on the file
				if ( !empty($filedata->access) ) {
					if (FLEXI_J16GE) {
						$authorized = in_array($filedata->access,$aid_arr);
					} else {
						$authorized = $aid >= $filedata->access;
					}
				}
				
				// If no access and set not to show then continue
				if ( !$authorized && !$noaccess_display ) continue;
				
				// --. Create icon according to filetype
				if ($useicon) {
					$filedata	= $this->addIcon( $filedata );
					$icon		= JHTML::image($filedata->icon, $filedata->ext, 'class="icon-mime"') .'&nbsp;';
				}
				
				// --. Decide whether to show filename (if we do not use button, then displaying of filename is forced)
				$name_str   = ($display_filename || !$usebutton) ? $filedata->altname : '';
				$name_html  = !empty($name_str) ? '&nbsp;<span class="fcfile_name">'. $name_str . '</span>' : '';
				
				// --. Description as tooltip or inline text ... prepare related variables
				$alt_str = $class_str = $text_html  = '';
				if (!empty($filedata->description)) {
					if ( !$authorized) {
						if ($noaccess_display != 2 ) {
							$alt_str    = $name_str . '::' . $filedata->description;
							$class_str  = ' hasTip';
							$text_html  = '';
						}
					} else if ($display_descr==1) {   // As tooltip
						$alt_str    = $name_str . '::' . $filedata->description;
						$class_str  = ' hasTip';
						$text_html  = '';
					} else if ($display_descr==2) {  // As inline text
						$alt_str    = '';
						$class_str  = '';
						$text_html  = ' <span class="fcfile_descr">'. $filedata->description . '</span>';
					}
				}
				
				// --. Create the download link or use no authorized link ...
				if ( !$authorized ) {
					$dl_link = $noaccess_url;
					$str = $noaccess_msg . ($noaccess_msg ? ': ' : '') . $icon;
					$class_str .= ' fc_file_noauth';   // Add an extra css class
				} else {
					$dl_link = JRoute::_( 'index.php?option=com_flexicontent&id='. $value .'&cid='.$field->item_id.'&fid='.$field->id.'&task=download' );
					$str = '';
				}
				
				// --. Finally create displayed html ... a download button (*) OR a download link
				// (*) with file manager 's description of file as tooltip or as inline text
				if (!$dl_link) {
					// no link ... (case of current user not authorized to download file)
					$str = $icon . '<span class="'.$class_str.'" title="'. $alt_str .'" >' . $name_html . '</span>' ." ". $text_html;
				} else if ($usebutton) {
					$class_str .= ' button';   // Add an extra css class
					$str  = '<form id="form-download-'.$field->id.'-'.($n+1).'" method="post" action="'.$dl_link.'">';
					$str .= $icon.'<input type="submit" name="download-'.$field->id.'[]" class="'.$class_str.'" title="'. $alt_str .'" value="'.JText::_('FLEXI_DOWNLOAD').'"/>'. $name_html ." ". $text_html;
					// Add variables for target URL to use (case of current user not authorized to download file)
					if ( !$authorized && $noaccess_addvars) {
						$str .= '<input type="hidden" name="fc_file_id" value="'.$value.'"/>'."\n";
						$str .= '<input type="hidden" name="fc_field_id" value="'.$field->id.'"/>'."\n";
						$str .= '<input type="hidden" name="fc_item_id" value="'.$field->item_id.'"/>'."\n";
					}
					$str .= '</form>';
				} else {
					$name_html = $filedata->altname;   // no download button, force display of filename
					// Add variables for target URL to use (case of current user not authorized to download file)
					if ( !$authorized && $noaccess_addvars) {
						$dl_link .= '&fc_file_id="'.$value;
						$dl_link .= '&fc_field_id="'.$field->id;
						$dl_link .= '&fc_item_id="'.$field->item_id;
					}
					$str = $icon . '<a href="' . $dl_link . '" class="'.$class_str.'" title="'. $alt_str .'" >' . $name_html . '</a>' ." ". $text_html;
				}
				
				// Values Prefix and Suffox Texts
				$field->{$prop}[]	=  $pretext . $str . $posttext;
				$n++;
			}
			
		}
		
		// Values Separator
		$field->{$prop} = implode($separatorf, $field->{$prop});
		
		// Field opening / closing texts
		if ($field->{$prop})
			$field->{$prop} = $opentag . $field->{$prop} . $closetag;
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'file') return;
		if(!is_array($post) && !strlen($post)) return;

		$mainframe =& JFactory::getApplication();
		
		$newpost = array();
		
		for ($n=0, $c=count($post); $n<$c; $n++)
		{
			if ($post[$n] != '') $newpost[] = $post[$n];
		}
		
		$post = array_unique($newpost);
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	function getFileData( $value )
	{
		$db =& JFactory::getDBO();
		$session = & JFactory::getSession();
		if (FLEXI_J16GE) {
			jimport('joomla.database.table');
			$sessiontable = JTable::getInstance('session');
		} else {
			jimport('joomla.database.table.session');
			$sessiontable = new JTableSession( $db );
		}
		$sessiontable->load($session->getId());
		
		$and = (!$sessiontable->client_id) ? ' AND published = 1' : '';
		$query = 'SELECT * ' //filename, altname, description, ext, id'
				. ' FROM #__flexicontent_files'
				. ' WHERE id = '. (int) $value
				. $and
				;
		$db->setQuery($query);
		$filedata = $db->loadObject();
		
		return $filedata;
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
